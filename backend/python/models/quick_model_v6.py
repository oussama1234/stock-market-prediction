#!/usr/bin/env python3
"""
Quick Model V6 - Integrated Multi-Factor Global Equity Model
============================================================

Purpose:
- Unified, interpretable, multi-factor scoring across Technicals, Fundamentals,
  Sentiment, Regional Weighting, and Liquidity.
- Produces market condition tags and short/mid-term outlooks.
- Zero external deps required to run; ML optional if installed.

Inputs (features dict; all optional, sensible defaults used):
- Technicals: close, volume, price_change_{1d,3d,7d}, rsi_14, rsi_7, macd, macd_hist,
  ema_12, ema_26, atr_14, bb_width, bb_pct, volume_sma_ratio, volume_spike,
  distance_to_support, distance_to_resistance
- Fundamentals: pe_ratio, pb_ratio, ps_ratio, eps_growth, revenue_growth, roe,
  profit_margin, debt_to_equity, dividend_yield
- Sentiment: news_sentiment_score [-1..1], fear_greed_index [0..100],
  keyword_ai, keyword_layoffs, keyword_expansion, keyword_earnings_beat
- Regional/Global: european_influence_score, asian_influence_score,
  sp500_change, nasdaq_change, treasury_yield_10y, usd_jpy_change, gold_change, oil_change,
  fed_sentiment_score [-1..1]
- Liquidity: relative_volume, inst_flow_score

Outputs:
- label: Bullish/Bearish/Neutral
- probability: 0..1
- expected_pct_move: signed % for near-term expectation
- factors: per-dimension scores and sub-scores
- tags: market condition tags
- outlook: 1D/1W/1M/3M projections with confidence
- top_reasons: interpretable drivers

Author: Stock Prediction System
Version: 6.0
"""

import sys
import json
import os
import argparse
import math
from datetime import datetime
from pathlib import Path

# Optional ML support
HAS_SK = False
try:
    from sklearn.preprocessing import StandardScaler  # type: ignore
    HAS_SK = True
except Exception:
    HAS_SK = False

import warnings
warnings.filterwarnings('ignore')


TECH_WEIGHT = 0.25
FUND_WEIGHT = 0.20
SENT_WEIGHT = 0.20
REGN_WEIGHT = 0.15
LIQ_WEIGHT  = 0.10
FEAR_WEIGHT = 0.10


def clip(v, lo, hi):
    return float(max(lo, min(hi, v)))


def nz(v, default=0.0):
    try:
        if v is None:
            return default
        return float(v)
    except Exception:
        return default


class QuickModelV6:
    def __init__(self):
        # For parity with prior versions
        self.model_path = str((Path(__file__).parent.parent / 'saved_models' / 'quick_model_v6.pkl'))
        self.scaler = StandardScaler() if HAS_SK else None

    def predict(self, f: dict) -> dict:
        f = f or {}
        # Scores per dimension
        technical = self._score_technicals(f)
        fundamentals = self._score_fundamentals(f)
        sentiment = self._score_sentiment(f)
        regional = self._score_regional(f)
        liquidity = self._score_liquidity(f)
        fear_index = self._score_fear_index(f)

        # Detect strong market regime (when most factors align)
        # If technical + sentiment + regional are ALL negative, it's a strong bearish day
        # Fundamentals shouldn't override this for TODAY's prediction
        tech_bearish = technical['score'] < 0
        sent_bearish = sentiment['score'] < -0.05
        regn_bearish = regional['score'] < -0.05
        liq_bearish = liquidity['score'] < -0.05
        fear_bearish = fear_index['score'] < -0.05
        
        # Count bearish signals
        bearish_count = sum([tech_bearish, sent_bearish, regn_bearish, liq_bearish, fear_bearish])
        
        # Adjust weights for strong bearish regime (3+ bearish signals)
        fund_weight_adj = FUND_WEIGHT
        if bearish_count >= 3:
            # Reduce fundamental weight by 50% in strong bearish regime
            fund_weight_adj = FUND_WEIGHT * 0.5
            # Redistribute to technical, sentiment, regional
            weight_boost = (FUND_WEIGHT - fund_weight_adj) / 3
            tech_weight_adj = TECH_WEIGHT + weight_boost
            sent_weight_adj = SENT_WEIGHT + weight_boost
            regn_weight_adj = REGN_WEIGHT + weight_boost
            liq_weight_adj = LIQ_WEIGHT
            fear_weight_adj = FEAR_WEIGHT
        else:
            tech_weight_adj = TECH_WEIGHT
            sent_weight_adj = SENT_WEIGHT
            regn_weight_adj = REGN_WEIGHT
            liq_weight_adj = LIQ_WEIGHT
            fear_weight_adj = FEAR_WEIGHT

        # Weighted composite -1..+1 with adjusted weights
        composite = (
            tech_weight_adj * technical['score'] +
            fund_weight_adj * fundamentals['score'] +
            sent_weight_adj * sentiment['score'] +
            regn_weight_adj * regional['score'] +
            liq_weight_adj  * liquidity['score'] +
            fear_weight_adj * fear_index['score']
        )
        composite = clip(composite, -1.0, 1.0)

        # Get category multiplier from features (default to 1.0 if not provided)
        category_multiplier = nz(f.get('category_multiplier'), 1.0)
        
        # Apply category multiplier to composite score to amplify movements
        # This makes high-volatility stocks move more and low-volatility stocks move less
        composite_amplified = composite * category_multiplier
        composite_amplified = clip(composite_amplified, -1.0, 1.0)
        
        # Probability via logistic transform and calibration
        probability = 1.0 / (1.0 + math.exp(-composite_amplified * 4.5))

        # Label - NO NEUTRAL! Always BULLISH or BEARISH
        # If composite is very close to zero, use a tiebreaker based on recent momentum
        if abs(composite_amplified) < 0.01:
            # Use 1-day momentum as tiebreaker
            ch1 = nz(f.get('price_change_1d'), 0)
            label = 'BULLISH' if ch1 >= 0 else 'BEARISH'
        else:
            label = 'BULLISH' if composite_amplified > 0 else 'BEARISH'

        # Expected short-term move magnitude scaled by momentum/volatility AND category
        expected_pct_move = self._expected_move(composite_amplified, f, category_multiplier)
        # Ensure expected move sign aligns with final label (tiebreaker-safe)
        if label == 'BULLISH' and expected_pct_move < 0:
            expected_pct_move = abs(expected_pct_move)
        elif label == 'BEARISH' and expected_pct_move > 0:
            expected_pct_move = -abs(expected_pct_move)

        # Market condition tags
        tags = self._market_tags(f, technical, sentiment, regional, liquidity, fear_index, composite)

        # Outlooks
        outlook = self._outlook(composite, f)

        # Reasons
        top_reasons = self._reasons(technical, fundamentals, sentiment, regional, liquidity, fear_index, composite)

        weights = {
            'technical': TECH_WEIGHT,
            'fundamentals': FUND_WEIGHT,
            'sentiment': SENT_WEIGHT,
            'regional': REGN_WEIGHT,
            'liquidity': LIQ_WEIGHT,
            'fear_index': FEAR_WEIGHT,
        }
        contributions = {
            'technical': float(TECH_WEIGHT * technical['score']),
            'fundamentals': float(FUND_WEIGHT * fundamentals['score']),
            'sentiment': float(SENT_WEIGHT * sentiment['score']),
            'regional': float(REGN_WEIGHT * regional['score']),
            'liquidity': float(LIQ_WEIGHT * liquidity['score']),
            'fear_index': float(FEAR_WEIGHT * fear_index['score']),
            'composite': float(composite),
        }

        result = {
            'label': label,
            'probability': float(probability),
            'expected_pct_move': float(expected_pct_move),
            'final_score': float(composite),
            'scores': {
                'technical': technical['score'],
                'fundamentals': fundamentals['score'],
                'sentiment': sentiment['score'],
                'regional': regional['score'],
                'liquidity': liquidity['score'],
                'fear_index': fear_index['score'],
                'composite': float(composite),
            },
            'weights': weights,
            'contributions': contributions,
            'factors': {
                'technical': technical,
                'fundamentals': fundamentals,
                'sentiment': sentiment,
                'regional': regional,
                'liquidity': liquidity,
                'fear_index': fear_index,
            },
            'us_factors': {
                'sp500_change': nz(f.get('sp500_change'), 0),
                'nasdaq_change': nz(f.get('nasdaq_change'), 0),
                'russell_2000_change': nz(f.get('russell_2000_change'), 0),
                'treasury_yield_10y': nz(f.get('treasury_yield_10y'), 0),
                'fed_sentiment_score': nz(f.get('fed_sentiment_score'), 0),
            },
            'global_markets': {
                'european_influence_score': nz(f.get('european_influence_score'), 0),
                'asian_influence_score': nz(f.get('asian_influence_score'), 0),
            },
            'tags': tags,
            'outlook': outlook,
            'top_reasons': top_reasons[:8],
            'model_version': 'quick_model_v6',
            'timestamp': datetime.now().isoformat(),
        }
        return result

    # ---------- Scorers ----------
    def _score_technicals(self, f: dict) -> dict:
        rsi = nz(f.get('rsi_14'), 50)
        macd_hist = nz(f.get('macd_hist'), 0)
        ch1 = nz(f.get('price_change_1d'), 0)
        ch3 = nz(f.get('price_change_3d'), 0)
        ch7 = nz(f.get('price_change_7d'), 0)
        bb_pct = nz(f.get('bb_pct'), 0.5)
        bb_width = nz(f.get('bb_width'), 0)
        atr = nz(f.get('atr_14'), 0)
        vol_ratio = nz(f.get('volume_sma_ratio'), 1.0)
        dist_sup = nz(f.get('distance_to_support'), 0)
        dist_res = nz(f.get('distance_to_resistance'), 0)

        score = 0.0
        # RSI zone
        if rsi < 30:
            score += clip((30 - rsi) / 30.0, 0, 1) * 0.35
        elif rsi > 70:
            score -= clip((rsi - 70) / 30.0, 0, 1) * 0.35
        # Momentum
        score += clip(ch1 / 6.0, -0.25, 0.25)
        score += clip(ch3 / 15.0, -0.2, 0.2)
        score += clip(ch7 / 25.0, -0.15, 0.15)
        # MACD
        score += clip(macd_hist / 8.0, -0.2, 0.2)
        # BB position: near lower band -> bullish mean reversion
        score += clip((0.5 - bb_pct) * 0.6, -0.3, 0.3)
        # Volume expansion supports moves
        if vol_ratio > 1.3:
            score += clip((vol_ratio - 1.3) * 0.2, 0, 0.25)
        # Proximity to support/resistance
        score += clip(dist_sup * 0.05, 0, 0.2)
        score -= clip(dist_res * 0.05, 0, 0.2)

        score = clip(score, -1.0, 1.0)
        detail = {
            'rsi_14': rsi,
            'macd_hist': macd_hist,
            'price_change_1d': ch1,
            'price_change_3d': ch3,
            'price_change_7d': ch7,
            'bb_pct': bb_pct,
            'bb_width': bb_width,
            'atr_14': atr,
            'volume_sma_ratio': vol_ratio,
            'distance_to_support': dist_sup,
            'distance_to_resistance': dist_res,
            'score': score,
        }
        return detail

    def _score_fundamentals(self, f: dict) -> dict:
        pe = nz(f.get('pe_ratio'), 20)
        pb = nz(f.get('pb_ratio'), 3)
        ps = nz(f.get('ps_ratio'), 5)
        eps_g = nz(f.get('eps_growth'), 0)
        rev_g = nz(f.get('revenue_growth'), 0)
        roe = nz(f.get('roe'), 10)
        margin = nz(f.get('profit_margin'), 10)
        dte = nz(f.get('debt_to_equity'), 1)
        div = nz(f.get('dividend_yield'), 0)

        score = 0.0
        # Growth/profitability
        score += clip(eps_g / 30.0, -0.2, 0.4)
        score += clip(rev_g / 40.0, -0.2, 0.35)
        score += clip((roe - 10) / 30.0, -0.2, 0.3)
        score += clip((margin - 10) / 30.0, -0.2, 0.25)
        # Leverage penalization
        score -= clip((dte - 1.0) / 3.0, 0, 0.3)
        # Valuation premiums/discounts (heuristic vs sector-neutral)
        score -= clip((pe - 20) / 60.0, 0, 0.25)
        score -= clip((pb - 3) / 6.0, 0, 0.2)
        score -= clip((ps - 5) / 10.0, 0, 0.15)
        # Dividend modest positive (quality tilt)
        score += clip(div / 8.0, 0, 0.1)

        score = clip(score, -1.0, 1.0)
        return {
            'pe_ratio': pe,
            'pb_ratio': pb,
            'ps_ratio': ps,
            'eps_growth': eps_g,
            'revenue_growth': rev_g,
            'roe': roe,
            'profit_margin': margin,
            'debt_to_equity': dte,
            'dividend_yield': div,
            'score': score,
        }

    def _score_sentiment(self, f: dict) -> dict:
        news = nz(f.get('news_sentiment_score'), 0)  # -1..1
        fg = nz(f.get('fear_greed_index'), 50)
        kw_ai = nz(f.get('keyword_ai'), 0)
        kw_layoffs = nz(f.get('keyword_layoffs'), 0)
        kw_expansion = nz(f.get('keyword_expansion'), 0)
        kw_beat = nz(f.get('keyword_earnings_beat'), 0)

        score = 0.0
        score += clip(news * 0.7, -0.7, 0.7)
        # Fear & Greed centered at 50
        score += clip(((fg - 50) / 50.0) * 0.25, -0.25, 0.25)
        # Keywords
        score += clip(kw_ai * 0.05 + kw_expansion * 0.05 + kw_beat * 0.06, 0, 0.3)
        score -= clip(kw_layoffs * 0.08, 0, 0.4)

        score = clip(score, -1.0, 1.0)
        return {
            'news_sentiment_score': news,
            'fear_greed_index': fg,
            'keyword_ai': kw_ai,
            'keyword_layoffs': kw_layoffs,
            'keyword_expansion': kw_expansion,
            'keyword_earnings_beat': kw_beat,
            'score': score,
        }

    def _score_regional(self, f: dict) -> dict:
        eu = nz(f.get('european_influence_score'), 0)
        asia = nz(f.get('asian_influence_score'), 0)
        spx = nz(f.get('sp500_change'), 0)
        ndx = nz(f.get('nasdaq_change'), 0)
        ty10 = nz(f.get('treasury_yield_10y'), 4)
        fed = nz(f.get('fed_sentiment_score'), 0)
        fx = nz(f.get('usd_jpy_change'), 0)
        gold = nz(f.get('gold_change'), 0)
        oil = nz(f.get('oil_change'), 0)

        score = 0.0
        score += clip(eu * 0.30, -0.30, 0.30)
        score += clip(asia * 0.20, -0.20, 0.20)
        # US indices momentum
        score += clip((spx + ndx) / 30.0, -0.25, 0.25)
        # Higher 10Y yield generally bearish for multiples
        score -= clip((ty10 - 4.0) / 3.0, -0.1, 0.25)
        # FED tone
        score += clip(fed * 0.2, -0.2, 0.2)
        # Risk-on proxies
        score += clip(oil / 30.0, -0.1, 0.1)
        # Risk-off proxy
        score -= clip(gold / 20.0, -0.1, 0.1)
        # FX risk appetite (very light)
        score += clip(fx / 20.0, -0.05, 0.05)

        score = clip(score, -1.0, 1.0)
        return {
            'european_influence_score': eu,
            'asian_influence_score': asia,
            'sp500_change': spx,
            'nasdaq_change': ndx,
            'treasury_yield_10y': ty10,
            'fed_sentiment_score': fed,
            'usd_jpy_change': fx,
            'gold_change': gold,
            'oil_change': oil,
            'score': score,
        }

    def _score_liquidity(self, f: dict) -> dict:
        relv = nz(f.get('relative_volume'), f.get('volume_sma_ratio', 1.0))
        inst = nz(f.get('inst_flow_score'), 0)

        score = 0.0
        if relv > 1.0:
            score += clip((relv - 1.0) * 0.25, 0, 0.35)
        else:
            score -= clip((1.0 - relv) * 0.15, 0, 0.15)
        score += clip(inst * 0.25, -0.25, 0.25)

        score = clip(score, -1.0, 1.0)
        return {
            'relative_volume': relv,
            'inst_flow_score': inst,
            'score': score,
        }

    def _score_fear_index(self, f: dict) -> dict:
        """Score based on CNN Fear & Greed Index
        
        Fear & Greed Index ranges from 0 (Extreme Fear) to 100 (Extreme Greed)
        - 0-25: Extreme Fear (bullish contrarian signal)
        - 25-45: Fear (mild bullish)
        - 45-55: Neutral
        - 55-75: Greed (mild bearish)
        - 75-100: Extreme Greed (bearish contrarian signal)
        """
        fg = nz(f.get('fear_greed_index'), 50)
        
        score = 0.0
        
        # Contrarian scoring: Fear is bullish, Greed is bearish
        if fg <= 25:
            # Extreme Fear: Strong buy signal
            score = clip((25 - fg) / 25.0, 0, 1.0) * 0.8
        elif fg < 45:
            # Fear: Mild buy signal
            score = clip((45 - fg) / 20.0, 0, 0.4)
        elif fg <= 55:
            # Neutral zone
            score = 0
        elif fg < 75:
            # Greed: Mild sell signal
            score = -clip((fg - 55) / 20.0, 0, 0.4)
        else:
            # Extreme Greed: Strong sell signal
            score = -clip((fg - 75) / 25.0, 0, 1.0) * 0.8
        
        score = clip(score, -1.0, 1.0)
        return {
            'fear_greed_index': fg,
            'fear_level': 'Extreme Fear' if fg <= 25 else 'Fear' if fg < 45 else 'Neutral' if fg <= 55 else 'Greed' if fg < 75 else 'Extreme Greed',
            'score': score,
        }

    # ---------- Utilities ----------
    def _expected_move(self, composite: float, f: dict, category_multiplier: float = 1.0) -> float:
        base = abs(composite)
        
        # Get category-specific typical range
        typical_min = nz(f.get('typical_daily_range_min'), 0.5)
        typical_max = nz(f.get('typical_daily_range_max'), 2.0)
        
        # Scale bands by normalized ATR (as a fraction of price) and BB width (already fractional)
        close = nz(f.get('close'), 0)
        atr = nz(f.get('atr_14'), 0)
        bbw = nz(f.get('bb_width'), 0)
        atr_norm = (atr / close) if close > 0 else 0.0  # fraction (e.g., 0.04 = 4%)
        # Give ATR a bit more weight than BB width, cap amplification modestly so it doesn't saturate
        vol_amp = clip(atr_norm * 2.0 + bbw, 0.0, 0.5)
        
        # Use category-aware ranges that reflect actual stock behavior
        # Strong signal (>0.6) should approach typical_max
        # Moderate signal (0.3-0.6) should be mid-range
        # Weak signal (<0.3) should be closer to typical_min
        if base > 0.7:
            # Very strong signal - use 70-100% of typical max
            rng = (typical_max * 0.7, typical_max)
        elif base > 0.5:
            # Strong signal - use 50-70% of range
            rng = (typical_min + (typical_max - typical_min) * 0.5, typical_max * 0.7)
        elif base > 0.3:
            # Moderate signal - use middle range
            rng = (typical_min + (typical_max - typical_min) * 0.3, typical_min + (typical_max - typical_min) * 0.6)
        elif base > 0.15:
            # Weak signal - use lower-mid range
            rng = (typical_min * 0.8, typical_min + (typical_max - typical_min) * 0.4)
        else:
            # Very weak signal - use minimum range
            rng = (typical_min * 0.5, typical_min * 1.2)
        
        # Interpolate within range based on signal strength
        move = rng[0] + (rng[1] - rng[0]) * base
        
        # Apply volatility amplification
        move *= (1.0 + vol_amp)
        
        # Apply category multiplier for final adjustment
        move *= category_multiplier
        
        # Ensure we have believable non-zero movements
        move = max(abs(move), typical_min * 0.3)
        
        return move if composite >= 0 else -move

    def _market_tags(self, f: dict, tech: dict, sent: dict, reg: dict, liq: dict, fear: dict, comp: float):
        tags = []
        rsi = tech.get('rsi_14', 50)
        bb_pct = tech.get('bb_pct', 0.5)
        volr = tech.get('volume_sma_ratio', 1.0)
        ch1 = tech.get('price_change_1d', 0)
        fg = sent.get('fear_greed_index', 50)

        if comp > 0.4:
            tags.append('Bullish Trend')
        elif comp < -0.4:
            tags.append('Bearish Trend')
        else:
            tags.append('Range-bound')

        if rsi > 70:
            tags.append('Overbought')
        elif rsi < 30:
            tags.append('Oversold')

        if bb_pct > 0.95:
            tags.append('Upper Band Breakout')
        elif bb_pct < 0.05:
            tags.append('Lower Band Breakdown')

        if volr >= 1.5:
            tags.append('High Relative Volume')

        if fg >= 70:
            tags.append('Greed Regime')
        elif fg <= 30:
            tags.append('Fear Regime')

        # Macro
        ty10 = nz(f.get('treasury_yield_10y'), 4)
        if ty10 >= 5:
            tags.append('High Rates Pressure')

        return tags

    def _outlook(self, comp: float, f: dict) -> dict:
        # Short-term aligns closer to technical/sentiment; mid-term blends fundamentals more
        # Composite score is -1 to +1, we want realistic % moves
        base = comp
        st_conf = clip(abs(base) * 0.9 + 0.1, 0.2, 0.95)
        mt_conf = clip(abs(base) * 0.75 + 0.1, 0.2, 0.9)

        # More realistic multipliers for stock price movements
        # Composite score is -1 to +1, typical range is -0.5 to +0.5
        # Target ranges: 1D: ±3%, 1W: ±7%, 1M: ±15%, 3M: ±25%
        
        return {
            'short_term': {
                '1d': {
                    'direction': 'up' if base > 0 else 'down' if base < 0 else 'flat', 
                    'expected_return_pct': round(clip(base * 3.0, -3.0, 3.0), 2), 
                    'confidence': round(st_conf, 2)
                },
                '1w': {
                    'direction': 'up' if base > 0 else 'down' if base < 0 else 'flat', 
                    'expected_return_pct': round(clip(base * 7.0, -7.0, 7.0), 2), 
                    'confidence': round(st_conf * 0.95, 2)
                },
            },
            'mid_term': {
                '1m': {
                    'direction': 'up' if base > 0 else 'down' if base < 0 else 'flat', 
                    'expected_return_pct': round(clip(base * 15.0, -15.0, 15.0), 2), 
                    'confidence': round(mt_conf, 2)
                },
                '3m': {
                    'direction': 'up' if base > 0 else 'down' if base < 0 else 'flat', 
                    'expected_return_pct': round(clip(base * 25.0, -25.0, 25.0), 2), 
                    'confidence': round(mt_conf * 0.9, 2)
                },
            }
        }

    def _reasons(self, tech, fund, sent, reg, liq, fear, comp):
        reasons = []
        # Technicals
        if tech.get('rsi_14', 50) < 35:
            reasons.append('RSI oversold supports rebound potential')
        if tech.get('price_change_3d', 0) > 1.5:
            reasons.append('Positive short-term momentum')
        if tech.get('bb_pct', 0.5) < 0.2:
            reasons.append('Price near lower Bollinger band (mean reversion)')
        # Fundamentals
        if fund.get('eps_growth', 0) > 10:
            reasons.append('Solid EPS growth backdrop')
        if fund.get('debt_to_equity', 1.0) < 1.0:
            reasons.append('Healthy leverage profile')
        # Sentiment
        if sent.get('news_sentiment_score', 0) > 0.2:
            reasons.append('Positive news sentiment')
        if sent.get('keyword_earnings_beat', 0) > 0:
            reasons.append('Earnings beat mentions detected')
        if sent.get('keyword_layoffs', 0) > 0:
            reasons.append('Layoff mentions weigh on outlook')
        # Regional
        if reg.get('european_influence_score', 0) > 0.2:
            reasons.append('European markets supportive')
        if reg.get('asian_influence_score', 0) > 0.2:
            reasons.append('Asian markets tailwind')
        # Liquidity
        if liq.get('relative_volume', 1.0) > 1.3:
            reasons.append('Elevated relative volume confirms move')
        # Fear Index
        fg = fear.get('fear_greed_index', 50)
        if fg <= 25:
            reasons.append('Extreme fear presents contrarian buy opportunity')
        elif fg >= 75:
            reasons.append('Extreme greed suggests potential market top')
        # Composite
        if comp > 0.4:
            reasons.append('Composite multi-factor score strongly bullish')
        elif comp < -0.4:
            reasons.append('Composite multi-factor score strongly bearish')
        return reasons


def main():
    parser = argparse.ArgumentParser(description='Quick Model V6 - Multi-Factor Global Equity Model')
    parser.add_argument('action', choices=['predict'], help='Action to perform')
    parser.add_argument('--features', type=str, help='JSON string of input features')
    args = parser.parse_args()

    model = QuickModelV6()

    if args.action == 'predict':
        if not args.features:
            print(json.dumps({'error': 'Features required for prediction'}))
            sys.exit(1)
        try:
            feats = json.loads(args.features)
        except Exception as e:
            print(json.dumps({'error': f'Invalid JSON: {e}'}))
            sys.exit(1)
        try:
            out = model.predict(feats)
            print(json.dumps(out, indent=2))
        except Exception as e:
            print(json.dumps({'error': f'Prediction failed: {e}'}))
            sys.exit(1)


if __name__ == '__main__':
    main()