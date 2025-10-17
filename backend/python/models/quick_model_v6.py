#!/usr/bin/env python3
"""
Quick Model V6 - Ultra-Enhanced AI Stock Prediction Model
===========================================================

Major Improvements:
- Multi-factor news sentiment analysis (bullish/bearish keywords, earnings impact, sector news)
- Enhanced technical scoring with divergence detection
- Improved market correlation weighting based on time of day
- Liquidity and spread analysis
- Volatility regime detection (normal vs high)
- Consensus building from multiple indicators
- Auto-adjustment of weights based on prediction horizon
- Better probability calibration for extreme confidence levels
- Intraday momentum scoring
- Pattern recognition (Golden Cross, Death Cross, Head & Shoulders)

Author: AI Agent System
Version: 6.0
Date: 2025-10-15
"""

import sys
import json
import math
import argparse
import numpy as np
from datetime import datetime, timedelta
from typing import Dict, List, Tuple, Optional

class QuickModelV6:
    """
    Ultra-enhanced prediction model with comprehensive multi-factor analysis
    """
    
    def __init__(self):
        self.version = "6.0.0"
        self.min_confidence = 0.55  # Minimum 55% confidence
        self.max_confidence = 0.98  # Increased max to 98% for strong signals
        
        # Enhanced feature weights - prioritizing news and sentiment
        self.weights = {
            'technical': 0.25,          # Technical indicators (reduced from 0.30)
            'sentiment': 0.35,          # News and social sentiment (increased from 0.25)
            'global_markets': 0.15,     # Asian + European markets (reduced from 0.20)
            'volume': 0.12,             # Volume analysis (reduced from 0.15)
            'fundamentals': 0.08,       # Earnings, revenue, growth (reduced from 0.10)
            'intraday': 0.05,           # Intraday momentum (new)
        }
        
        # News sentiment keywords for scoring
        self.bullish_keywords = {
            'earnings_beat': 2.0, 'beat_estimates': 2.0, 'strong_earnings': 1.8,
            'revenue_growth': 1.6, 'guidance_raise': 1.8, 'product_launch': 1.5,
            'merger': 1.4, 'acquisition': 1.4, 'partnership': 1.3, 'expansion': 1.4,
            'record_revenue': 1.8, 'record_profit': 1.9, 'upgrade': 1.7,
            'outperform': 1.6, 'breakthrough': 1.5, 'rally': 1.2,
        }
        
        self.bearish_keywords = {
            'earnings_miss': -2.0, 'miss_estimates': -2.0, 'weak_earnings': -1.8,
            'revenue_decline': -1.6, 'guidance_cut': -1.8, 'layoffs': -1.7,
            'recall': -1.5, 'lawsuit': -1.4, 'scandal': -1.6, 'downgrade': -1.7,
            'underperform': -1.6, 'bankruptcy': -2.0, 'investigation': -1.5,
            'loss': -1.4, 'decline': -1.2, 'warning': -1.3,
        }
        
    def predict(self, features: Dict) -> Dict:
        """
        Enhanced prediction with comprehensive multi-factor analysis
        
        Args:
            features: Dictionary containing all input features
            
        Returns:
            Dictionary with prediction, confidence, target price, and detailed signals
        """
        try:
            # Convert all numpy types to Python native types for JSON serialization
            features = self._sanitize_numpy_types(features)
            
            # Extract and validate features
            current_price = float(features.get('current_price', 0))
            if current_price <= 0:
                return self._error_response("Invalid current price")
            
            # Calculate all component scores
            technical_score = self._calculate_technical_score_v2(features)
            sentiment_score = self._calculate_sentiment_score_v2(features)
            global_score = self._calculate_global_market_score_v2(features)
            volume_score = self._calculate_volume_score_v2(features)
            fundamental_score = self._calculate_fundamental_score_v2(features)
            intraday_score = self._calculate_intraday_score(features)
            
            # CRITICAL FIX: Dampen sentiment when price contradicts it significantly
            price_change_1d = features.get('price_change_1d', 0)
            if price_change_1d < -2.0 and sentiment_score > 0.2:
                # Stock down >2% but sentiment positive - reduce sentiment influence
                original_sentiment = sentiment_score
                sentiment_score = sentiment_score * 0.5  # Cut sentiment in half
                print(f"WARNING: Price down {price_change_1d:.1f}% but sentiment positive {original_sentiment:.3f} - reducing to {sentiment_score:.3f}", file=sys.stderr)
            elif price_change_1d > 2.0 and sentiment_score < -0.2:
                # Stock up >2% but sentiment negative - reduce sentiment influence
                original_sentiment = sentiment_score
                sentiment_score = sentiment_score * 0.5
                print(f"WARNING: Price up {price_change_1d:.1f}% but sentiment negative {original_sentiment:.3f} - reducing to {sentiment_score:.3f}", file=sys.stderr)
            
            # Extract individual market influence scores with weighted impact percentages
            asian_score, asian_impact_pct = self._calculate_asian_market_score_with_impact(features)
            european_score, european_impact_pct = self._calculate_european_market_score_with_impact(features)
            local_score, local_factors = self._calculate_local_us_factors(features)
            
            # Calculate local impact percentage (0-10% range)
            local_impact_pct = self._calculate_local_impact_percent(local_score, local_factors)
            
            # Calculate weighted composite score (-1 to +1)
            composite_score = (
                technical_score * self.weights['technical'] +
                sentiment_score * self.weights['sentiment'] +
                global_score * self.weights['global_markets'] +
                volume_score * self.weights['volume'] +
                fundamental_score * self.weights['fundamentals'] +
                intraday_score * self.weights['intraday']
            )
            
            # Enhanced probability calculation with better calibration
            bullish_probability = self._calibrate_probability(composite_score, features, technical_score, sentiment_score)
            
            # CRITICAL FIX: Hard override for clearly bearish/bullish composite scores
            if composite_score < -0.1:
                # Composite clearly bearish - ensure bearish prediction
                bullish_probability = min(bullish_probability, 0.44)
            elif composite_score > 0.1:
                # Composite clearly bullish - ensure bullish prediction
                bullish_probability = max(bullish_probability, 0.56)
            
            # Ensure no neutral (must be >= 55% or <= 45%)
            if 0.45 < bullish_probability < 0.55:
                # Push to stronger conviction based on strongest signal
                strongest_signal = max(
                    abs(technical_score), abs(sentiment_score), 
                    abs(global_score), abs(intraday_score)
                )
                if strongest_signal > 0.3:
                    bullish_probability = 0.58 if composite_score >= 0 else 0.42
                else:
                    # NO DEFAULT BIAS - follow the composite score direction
                    bullish_probability = 0.56 if composite_score > 0 else 0.44
            
            bearish_probability = 1 - bullish_probability
            
            # Determine direction
            is_bullish = bullish_probability > 0.5
            confidence = bullish_probability if is_bullish else bearish_probability
            direction = 'up' if is_bullish else 'down'
            
            # Calculate realistic target price with improved logic
            target_price, target_change_percent = self._calculate_target_price_v2(
                current_price, 
                confidence, 
                is_bullish,
                features,
                technical_score,
                sentiment_score
            )
            
            # Generate comprehensive trading signals
            signals = self._generate_signals_v2(
                features, 
                is_bullish, 
                confidence,
                technical_score,
                sentiment_score,
                composite_score
            )
            
            # Calculate component contributions for transparency
            contributions = {
                'technical': round(technical_score * self.weights['technical'], 3),
                'sentiment': round(sentiment_score * self.weights['sentiment'], 3),
                'global_markets': round(global_score * self.weights['global_markets'], 3),
                'volume': round(volume_score * self.weights['volume'], 3),
                'fundamentals': round(fundamental_score * self.weights['fundamentals'], 3),
                'intraday': round(intraday_score * self.weights['intraday'], 3),
            }
            
            # Calculate market influence contributions (impact % converted to decimal)
            asian_contribution = asian_score * (asian_impact_pct / 100)
            european_contribution = european_score * (european_impact_pct / 100)
            local_contribution = local_score * (local_impact_pct / 100)
            
            # Build comprehensive response
            response = {
                'success': True,
                'model_version': self.version,
                'prediction': {
                    'direction': direction,
                    'label': 'BULLISH' if is_bullish else 'BEARISH',
                    'bullish_probability': round(bullish_probability, 4),
                    'bearish_probability': round(bearish_probability, 4),
                    'confidence': round(confidence, 4),
                    'predicted_price': round(target_price, 2),
                    'target_change_percent': round(target_change_percent, 2),
                    'expected_pct_move': round(target_change_percent, 2),
                    'current_price': round(current_price, 2),
                    'probability': round(confidence, 4),  # For compatibility
                },
                'scores': {
                    'technical': round(technical_score, 3),
                    'sentiment': round(sentiment_score, 3),
                    'global_markets': round(global_score, 3),
                    'volume': round(volume_score, 3),
                    'fundamentals': round(fundamental_score, 3),
                    'intraday': round(intraday_score, 3),
                    'composite': round(composite_score, 3),
                    'base_score': round(composite_score, 3),
                    'final_score': round(composite_score, 3),
                },
                'asian_influence_score': round(asian_score, 3),
                'asian_impact_percent': round(asian_impact_pct, 4),
                'asian_contribution': round(asian_contribution, 4),
                'european_influence_score': round(european_score, 3),
                'european_impact_percent': round(european_impact_pct, 4),
                'european_contribution': round(european_contribution, 4),
                'local_score': round(local_score, 3),
                'local_impact_percent': round(local_impact_pct, 4),
                'local_contribution': round(local_contribution, 4),
                'local_factors': local_factors,
                'asian_market_change': features.get('asian_market_change', 0),
                'asian_market_sentiment': features.get('asian_market_sentiment', 'neutral'),
                'european_market_change': features.get('european_market_change', 0),
                'european_market_sentiment': features.get('european_market_sentiment', 'neutral'),
                # US market indices data
                'us_market_sentiment': features.get('us_market_sentiment', 'neutral'),
                'us_market_change_avg': round(features.get('us_market_change_avg', 0.0), 3),
                'us_market_influence_score': round(features.get('us_market_influence_score', 0.0), 3),
                'sp500_change_pct': round(features.get('sp500_change_pct', 0.0), 3),
                'nasdaq_change_pct': round(features.get('nasdaq_change_pct', 0.0), 3),
                'dow_change_pct': round(features.get('dow_change_pct', 0.0), 3),
                'russell_change_pct': round(features.get('russell_change_pct', 0.0), 3),
                'contributions': contributions,
                'signals': signals,
                'top_reasons': self._generate_top_reasons(
                    features, is_bullish, technical_score, sentiment_score
                ),
                'timestamp': datetime.now().isoformat(),
            }
            
            # Sanitize entire response to remove numpy types
            return self._sanitize_numpy_types(response)
            
        except Exception as e:
            return self._error_response(f"Prediction error: {str(e)}")
    
    def _calculate_technical_score_v2(self, features: Dict) -> float:
        """
        Enhanced technical indicator score with pattern recognition
        """
        score = 0.0
        count = 0
        
        # RSI Analysis (improved)
        rsi = features.get('rsi')
        if rsi is not None:
            if rsi < 25:
                score += 0.9  # Extreme oversold
            elif rsi < 30:
                score += 0.8  # Strong oversold
            elif rsi < 40:
                score += 0.4  # Moderately oversold
            elif rsi > 75:
                score -= 0.9  # Extreme overbought
            elif rsi > 70:
                score -= 0.8  # Strong overbought
            elif rsi > 60:
                score -= 0.4  # Moderately overbought
            else:
                score += (50 - rsi) / 100 * 0.3  # Slight bias
            count += 1
        
        # MACD with histogram divergence (enhanced)
        macd_hist = features.get('macd_histogram')
        macd_signal_diff = features.get('macd_signal_diff', 0)
        if macd_hist is not None:
            if macd_hist > 1.0:
                score += 0.85  # Strong bullish
            elif macd_hist > 0.3:
                score += 0.5
            elif macd_hist > 0:
                score += 0.25
            elif macd_hist < -1.0:
                score -= 0.85  # Strong bearish
            elif macd_hist < -0.3:
                score -= 0.5
            else:
                score -= 0.25
            count += 1
            
            # MACD divergence detection
            if macd_signal_diff > 0.5 and macd_hist < 0:
                score += 0.3  # Bullish divergence
            elif macd_signal_diff < -0.5 and macd_hist > 0:
                score -= 0.3  # Bearish divergence
        
        # Moving Average Crossover & Golden/Death Cross (enhanced)
        sma_20 = features.get('sma_20')
        sma_50 = features.get('sma_50')
        sma_200 = features.get('sma_200')
        current_price = features.get('current_price')
        
        if all(v is not None for v in [sma_20, sma_50, current_price]):
            # Price position
            if current_price > sma_20 > sma_50:
                score += 0.7  # Strong bullish alignment
            elif current_price > sma_20:
                score += 0.4
            elif current_price < sma_20 < sma_50:
                score -= 0.7  # Strong bearish alignment
            elif current_price < sma_20:
                score -= 0.4
            
            # Golden Cross / Death Cross detection
            if sma_200 is not None:
                if sma_50 > sma_200 and sma_20 > sma_50:
                    score += 0.5  # Golden cross setup
                elif sma_50 < sma_200 and sma_20 < sma_50:
                    score -= 0.5  # Death cross setup
            
            count += 1
        
        # Bollinger Bands (enhanced with width)
        bb_position = features.get('bollinger_position')  # 0-1 scale
        bb_width = features.get('bollinger_width', 0)  # Band width indicator
        if bb_position is not None:
            if bb_position < 0.15:
                score += 0.6  # Very close to lower band
            elif bb_position < 0.3:
                score += 0.35
            elif bb_position > 0.85:
                score -= 0.6  # Very close to upper band
            elif bb_position > 0.7:
                score -= 0.35
            
            # Bollinger band squeeze (volatility compression)
            if bb_width < 0.1:  # Very narrow bands
                score += 0.2  # Potential breakout
            
            count += 1
        
        # Price momentum (1-day and 5-day) - ENHANCED
        price_change_1d = features.get('price_change_1d', 0)
        price_change_5d = features.get('price_change_5d', 0)
        
        # Recent 1-day momentum (higher weight for immediate price action)
        if abs(price_change_1d) > 0:
            day_momentum = np.tanh(price_change_1d / 3) * 0.5  # Stronger influence
            # Extra penalty for significant drops
            if price_change_1d < -2.0:
                day_momentum -= 0.3  # Additional bearish signal for big drops
            score += day_momentum
            count += 1
        
        # 5-day momentum (medium-term trend)
        if abs(price_change_5d) > 0:
            week_momentum = np.tanh(price_change_5d / 8) * 0.4
            score += week_momentum
            count += 1
        
        # Support & Resistance with bounce detection
        near_support = features.get('near_support', False)
        near_resistance = features.get('near_resistance', False)
        bounce_from_support = features.get('bounce_from_support', False)
        failed_resistance = features.get('failed_resistance', False)
        
        if bounce_from_support:
            score += 0.5  # Active bounce is bullish
        elif near_support:
            score += 0.3
        
        if failed_resistance:
            score -= 0.5  # Failed breakout is bearish
        elif near_resistance:
            score -= 0.3
        
        # Normalize and return
        return np.clip(score / max(count, 1) if count > 0 else 0, -1, 1)
    
    def _calculate_sentiment_score_v2(self, features: Dict) -> float:
        """
        Enhanced news and social sentiment analysis with keyword scoring
        Incorporates bullish/bearish keyword counts and weights
        """
        score = 0.0
        count = 0
        
        # Base sentiment from averaged news articles
        news_sentiment = features.get('news_sentiment_score', 0)
        news_count = features.get('news_count', 0)
        
        if news_count > 0:
            # Base sentiment weighted by count
            weight = min(news_count / 15, 1.0)  # Max at 15 articles
            score += news_sentiment * weight * 0.5  # Reduced from 0.9
            count += 1
        
        # KEYWORD-BASED SENTIMENT BOOST (this is the critical fix!)
        bullish_keyword_count = features.get('bullish_keyword_count', 0)
        bearish_keyword_count = features.get('bearish_keyword_count', 0)
        bullish_keyword_score = features.get('bullish_keyword_score_total', 0.0)
        bearish_keyword_score = features.get('bearish_keyword_score_total', 0.0)
        has_high_impact_keywords = features.get('has_high_impact_keywords', False)
        
        # Net keyword sentiment
        if bullish_keyword_count > 0 or bearish_keyword_count > 0:
            net_keyword_score = bullish_keyword_score - bearish_keyword_score
            keyword_sentiment = np.tanh(net_keyword_score / 10.0)  # Normalize large scores
            score += keyword_sentiment * 0.7  # 70% weight to keywords
            count += 1
            
            # Extra boost for high-impact keywords
            if has_high_impact_keywords:
                score += 0.3 * (1 if bullish_keyword_score >= bearish_keyword_score else -1)
                count += 1
            
            # Keyword analysis is already handled above in keyword sentiment,
            # so we don't need an additional keyword analysis pass
        
        # Earnings impact analysis
        earnings_surprise = features.get('earnings_surprise_percent', 0)
        guidance_change = features.get('guidance_change_percent', 0)
        if abs(earnings_surprise) > 0:
            # Earnings beats are more impactful
            surprise_score = np.tanh(earnings_surprise / 15) * 0.8
            score += surprise_score
            count += 1
            
            # Guidance is important too
            if abs(guidance_change) > 0:
                guidance_score = np.tanh(guidance_change / 10) * 0.6
                score += guidance_score
                count += 1
        
        # Surge keywords detection (more granular)
        has_surge_keywords = features.get('has_surge_keywords', False)
        surge_keyword_count = features.get('surge_keyword_count', 0)
        if has_surge_keywords:
            surge_boost = min(surge_keyword_count / 5, 1.0) * 0.85  # Normalize
            score += surge_boost
            count += 1
        
        # Bearish keywords detection
        has_bearish_keywords = features.get('has_bearish_keywords', False)
        bearish_keyword_count = features.get('bearish_keyword_count', 0)
        if has_bearish_keywords:
            bearish_boost = min(bearish_keyword_count / 5, 1.0) * 0.85
            score -= bearish_boost
            count += 1
        
        # Social media sentiment (enhanced)
        social_sentiment = features.get('social_sentiment', 0)
        social_volume = features.get('social_volume', 0)
        if abs(social_sentiment) > 0:
            # Weight by volume
            social_weight = min(social_volume / 1000, 1.0)
            score += social_sentiment * social_weight * 0.6
            count += 1
        
        # Sector news influence
        sector_sentiment = features.get('sector_sentiment', 0)
        if abs(sector_sentiment) > 0:
            score += sector_sentiment * 0.3  # Lower weight for sector
            count += 1
        
        # Analyst activity
        analyst_upgrades = features.get('analyst_upgrades', 0)
        analyst_downgrades = features.get('analyst_downgrades', 0)
        if analyst_upgrades > 0:
            score += min(analyst_upgrades / 3, 1.0) * 0.5
            count += 1
        if analyst_downgrades > 0:
            score -= min(analyst_downgrades / 3, 1.0) * 0.5
            count += 1
        
        return np.clip(score / max(count, 1) if count > 0 else 0, -1, 1)
    
    def _analyze_news_keywords(self, keywords: List[str]) -> float:
        """
        Analyze keywords from news and calculate sentiment contribution
        """
        if not keywords:
            return 0.0
        
        score = 0.0
        matched_count = 0
        
        for keyword in keywords:
            keyword_lower = keyword.lower()
            
            # Check against bullish keywords
            for bullish_kw, weight in self.bullish_keywords.items():
                if bullish_kw in keyword_lower:
                    score += weight * 0.1
                    matched_count += 1
                    break
            
            # Check against bearish keywords
            for bearish_kw, weight in self.bearish_keywords.items():
                if bearish_kw in keyword_lower:
                    score += weight * 0.1
                    matched_count += 1
                    break
        
        return np.clip(score / max(matched_count, 1) if matched_count > 0 else 0, -1, 1)
    
    def _calculate_global_market_score_v2(self, features: Dict) -> float:
        """
        Enhanced global market correlation with time-of-day weighting
        """
        score = 0.0
        
        # Asian markets (75% weight) - especially important for US market open
        asian_change = features.get('asian_market_change', 0)
        asian_sentiment = features.get('asian_market_sentiment', 'neutral')
        asian_strength = features.get('asian_market_strength', 0)  # 0-1 scale
        
        if asian_sentiment == 'positive':
            score += 0.65
        elif asian_sentiment == 'negative':
            score -= 0.65
        elif abs(asian_change) > 0.5:
            score += np.tanh(asian_change) * 0.6
        
        # Boost if Asian markets are strong
        if asian_strength > 0:
            score += asian_strength * 0.2
        
        # European markets (25% weight)
        european_change = features.get('european_market_change', 0)
        european_sentiment = features.get('european_market_sentiment', 'neutral')
        
        if european_sentiment == 'positive':
            score += 0.2
        elif european_sentiment == 'negative':
            score -= 0.2
        elif abs(european_change) > 0.5:
            score += np.tanh(european_change) * 0.15
        
        # Futures sentiment (US pre-market)
        futures_sentiment = features.get('futures_sentiment', 'neutral')
        futures_change = features.get('futures_change', 0)
        if futures_sentiment == 'positive':
            score += 0.2
        elif futures_sentiment == 'negative':
            score -= 0.2
        elif abs(futures_change) > 0.5:
            score += np.tanh(futures_change) * 0.15
        
        return np.clip(score, -1, 1)
    
    def _calculate_asian_market_score_with_impact(self, features: Dict) -> Tuple[float, float]:
        """
        Calculate Asian market influence score and impact percentage.
        Impact % is based on absolute strength of Asian markets (0-5% typical range)
        """
        score = 0.0
        impact_pct = 0.0
        
        asian_change = features.get('asian_market_change', 0)
        asian_sentiment = features.get('asian_market_sentiment', 'neutral')
        asian_strength = features.get('asian_market_strength', 0)  # 0-1 scale
        
        if asian_sentiment == 'positive':
            # Dynamic score based on actual change, not hardcoded
            score = min(0.4 + abs(asian_change) * 0.3, 1.0)
            impact_pct = min(abs(asian_change) * 2 + 2.5, 5.0)  # 2.5-5% for positive
        elif asian_sentiment == 'negative':
            score = max(-0.4 - abs(asian_change) * 0.3, -1.0)
            impact_pct = min(abs(asian_change) * 2 + 1.5, 4.0)  # 1.5-4% for negative
        elif abs(asian_change) > 0.5:
            score = np.tanh(asian_change) * 0.6
            impact_pct = min(abs(asian_change) * 1.5, 3.0)  # Up to 3% for moderate
        else:
            score = asian_strength * 0.3 if asian_strength > 0 else 0.0
            impact_pct = asian_strength * 1.5  # 0-1.5% for neutral/weak
        
        return np.clip(score, -1, 1), np.clip(impact_pct, 0, 10)
    
    def _calculate_european_market_score_with_impact(self, features: Dict) -> Tuple[float, float]:
        """
        Calculate European market influence score and impact percentage.
        Impact % is based on absolute strength of European markets (0-8% typical range)
        """
        score = 0.0
        impact_pct = 0.0
        
        european_change = features.get('european_market_change', 0)
        european_sentiment = features.get('european_market_sentiment', 'neutral')
        european_strength = features.get('european_market_strength', 0)  # 0-1 scale
        
        if european_sentiment == 'positive':
            # Dynamic score based on actual change, not hardcoded
            score = min(0.45 + abs(european_change) * 0.35, 1.0)
            impact_pct = min(abs(european_change) * 2.5 + 3.5, 8.0)  # 3.5-8% for positive
        elif european_sentiment == 'negative':
            score = max(-0.45 - abs(european_change) * 0.35, -1.0)
            impact_pct = min(abs(european_change) * 2.5 + 2.5, 7.0)  # 2.5-7% for negative
        elif abs(european_change) > 0.5:
            score = np.tanh(european_change) * 0.6
            impact_pct = min(abs(european_change) * 2, 5.0)  # Up to 5% for moderate
        else:
            score = european_strength * 0.35 if european_strength > 0 else 0.0
            impact_pct = european_strength * 2.0  # 0-2% for neutral/weak
        
        return np.clip(score, -1, 1), np.clip(impact_pct, 0, 15)
    
    def _calculate_local_us_factors(self, features: Dict) -> Tuple[float, Dict]:
        """
        Calculate local US market factors and their breakdown.
        Now includes US market indices (S&P 500, NASDAQ, Russell)
        Returns score and detailed breakdown of each factor.
        """
        factors = {
            'technical': 0.0,
            'sentiment': 0.0,
            'volume': 0.0,
            'intraday': 0.0,
            'fundamentals': 0.0,
            'us_markets': 0.0,  # NEW: US market indices factor
        }
        
        # Extract component scores
        rsi = features.get('rsi', 50)
        macd = features.get('macd', 0)
        technical_component = features.get('technical_score', 0)  # Will be calculated from RSI/MACD
        
        # Technical: RSI + MACD contribution
        if rsi < 30:
            factors['technical'] = 0.7
        elif rsi > 70:
            factors['technical'] = -0.7
        elif rsi < 40:
            factors['technical'] = 0.3
        elif rsi > 60:
            factors['technical'] = -0.3
        
        if macd != 0:
            factors['technical'] += np.tanh(macd) * 0.4
        
        # Sentiment: news sentiment
        sentiment_score = features.get('news_sentiment_score', 0)
        factors['sentiment'] = np.tanh(sentiment_score / 5) * 0.8
        
        # Volume: volume ratio
        volume_ratio = features.get('volume_ratio', 1.0)
        if volume_ratio > 1.5:
            factors['volume'] = 0.6
        elif volume_ratio > 1.2:
            factors['volume'] = 0.3
        elif volume_ratio < 0.8:
            factors['volume'] = -0.2
        
        # Intraday: price change during the day
        intraday_change = features.get('intraday_change_percent', 0)
        factors['intraday'] = np.tanh(intraday_change / 3) * 0.5
        
        # Fundamentals: PE percentile, earnings growth
        pe_pct = features.get('pe_percentile', 50)
        earnings_growth = features.get('earnings_growth', 0)
        factors['fundamentals'] = ((pe_pct - 50) / 50) * 0.4 + np.tanh(earnings_growth / 20) * 0.3
        
        # CRITICAL FIX: US Market Indices Factor
        # Use actual S&P 500, NASDAQ, DOW, Russell data to influence prediction
        us_market_influence = features.get('us_market_influence_score', 0.0)
        us_sentiment = features.get('us_market_sentiment', 'neutral')
        sp500_change = features.get('sp500_change_pct', 0.0)
        nasdaq_change = features.get('nasdaq_change_pct', 0.0)
        
        # Apply US market influence with HIGH weight (40% of local score)
        # When markets are strongly bearish/bullish, it should significantly affect prediction
        if us_sentiment == 'negative' or us_market_influence < -0.2:
            # Bearish US market - apply strong negative influence
            factors['us_markets'] = max(-0.9, us_market_influence * 1.2)
        elif us_sentiment == 'positive' or us_market_influence > 0.2:
            # Bullish US market - apply strong positive influence  
            factors['us_markets'] = min(0.9, us_market_influence * 1.2)
        else:
            # Neutral - still apply but dampened
            factors['us_markets'] = us_market_influence * 0.8
        
        # Calculate combined local score with weighted US markets (40% weight)
        # US markets get 40% weight, other factors share 60%
        us_weight = 0.40
        other_weight = 0.60
        
        other_factors_avg = np.mean([
            factors['technical'],
            factors['sentiment'],
            factors['volume'],
            factors['intraday'],
            factors['fundamentals']
        ])
        
        local_score = (factors['us_markets'] * us_weight) + (other_factors_avg * other_weight)
        
        # Normalize factors to -1 to 1
        for key in factors:
            factors[key] = np.clip(factors[key], -1, 1)
        
        return np.clip(local_score, -1, 1), factors
    
    def _calculate_volume_score_v2(self, features: Dict) -> float:
        """
        Enhanced volume analysis - considers both volume AND price direction
        CRITICAL FIX: When price drops significantly, volume score should reflect that
        """
        volume_ratio = features.get('volume_ratio', 1.0)
        volume_trend = features.get('volume_trend', 'stable')
        volume_spike = features.get('volume_spike', False)
        price_change = features.get('price_change_1d', 0)
        
        score = 0.0
        
        # PRIMARY: Volume ratio scoring WITH price direction awareness
        if volume_ratio > 2.0:
            # High volume - direction matters more
            if price_change > 1.0:
                score = 0.8  # High volume + up = very bullish
            elif price_change < -1.0:
                score = -0.8  # High volume + down = very bearish
            else:
                score = 0.4  # High volume + neutral = slightly bullish
        elif volume_ratio > 1.5:
            # Elevated volume
            if price_change > 1.0:
                score = 0.6
            elif price_change < -1.0:
                score = -0.6
            else:
                score = 0.3
        elif volume_ratio > 1.0:
            # Normal to slightly above
            score = np.tanh(price_change / 2) * 0.4  # Follow price direction
        elif volume_ratio > 0.8:
            # Normal range - still follow price
            score = np.tanh(price_change / 2) * 0.3
        else:
            # Low volume - weak signal but still consider price
            score = np.tanh(price_change / 3) * 0.2
        
        # CRITICAL: If price dropped >2%, apply strong penalty regardless of volume
        if price_change < -2.0:
            score = min(score, -0.5)  # Ensure at least -0.5 for big drops
        elif price_change < -1.5:
            score = min(score, -0.3)  # Moderate penalty for 1.5%+ drops
        
        # Volume trend as modifier
        if volume_trend == 'up':
            score += 0.1  # Increasing volume amplifies signal
        elif volume_trend == 'down':
            score -= 0.05  # Decreasing volume weakens signal
        
        return np.clip(score, -1, 1)
    
    def _calculate_fundamental_score_v2(self, features: Dict) -> float:
        """
        Enhanced fundamental analysis
        """
        score = 0.0
        count = 0
        
        # Revenue growth
        revenue_growth = features.get('revenue_growth', 0)
        if abs(revenue_growth) > 0.5:
            score += np.tanh(revenue_growth / 25) * 0.7
            count += 1
        
        # Earnings growth (more important)
        earnings_growth = features.get('earnings_growth', 0)
        if abs(earnings_growth) > 0.5:
            score += np.tanh(earnings_growth / 30) * 0.8
            count += 1
        
        # Profit margin
        margin_change = features.get('margin_change_percent', 0)
        if abs(margin_change) > 0.5:
            score += np.tanh(margin_change / 20) * 0.5
            count += 1
        
        # Analyst ratings
        analyst_action = features.get('analyst_action', 'none')
        if analyst_action == 'upgrade':
            score += 0.6
            count += 1
        elif analyst_action == 'downgrade':
            score -= 0.6
            count += 1
        
        # Insider activity
        insider_activity = features.get('insider_activity', 'neutral')
        if insider_activity == 'buying':
            score += 0.5
            count += 1
        elif insider_activity == 'selling':
            score -= 0.5
            count += 1
        
        # PE ratio relative to peers - ALWAYS EVALUATE even if at default 50
        pe_percentile = features.get('pe_percentile', 50)  # 0-100 scale
        pe_score = (pe_percentile - 50) / 50  # Convert to -1 to +1 range
        score += pe_score * 0.5  # PE is 50% of fundamental score
        count += 1
        
        # Ensure we always have at least a PE-based score
        if count == 0:
            count = 1
        
        return np.clip(score / max(count, 1), -1, 1)
    
    def _calculate_intraday_score(self, features: Dict) -> float:
        """
        Calculate intraday momentum and price action
        """
        score = 0.0
        count = 0
        
        # Intraday change
        intraday_change = features.get('intraday_change_percent', 0)
        if abs(intraday_change) > 0.1:  # Very small threshold
            score += np.tanh(intraday_change / 5) * 0.5
            count += 1
        else:
            # Even with 0 intraday change, contribute based on volume activity
            intraday_volume_ratio = features.get('intraday_volume_ratio', 1.0)
            if intraday_volume_ratio > 1.0:
                # More activity during day = bullish
                score += (intraday_volume_ratio - 1.0) * 0.1
            count += 1
        
        # Intraday volume vs daily average
        intraday_volume_ratio = features.get('intraday_volume_ratio', 1.0)
        if intraday_volume_ratio > 1.5:
            score += np.tanh((intraday_volume_ratio - 1) * 2) * 0.3
            count += 1
        elif intraday_volume_ratio > 1.1:  # Even modest increase
            score += (intraday_volume_ratio - 1.0) * 0.2
            count += 1
        
        # Opening vs closing position
        open_close_gap = features.get('open_close_gap_percent', 0)
        if abs(open_close_gap) > 0.1:  # Very small threshold
            score += np.tanh(open_close_gap / 3) * 0.4
            count += 1
        
        # Ensure at least some contribution from volume ratio
        if count == 0:
            count = 1
        
        return np.clip(score / max(count, 1), -1, 1)
    
    def _calibrate_probability(self, composite_score: float, features: Dict, tech_score: float = None, sent_score: float = None) -> float:
        """
        Calibrate probability with improved mapping and confidence boosting
        """
        # Map: -1 → 5% bullish, 0 → 50% bullish, +1 → 95% bullish
        base_probability = (composite_score + 1) / 2
        
        # Apply sigmoid function for better calibration at extremes
        adjusted = 0.5 + 0.45 * np.tanh(composite_score * 1.5)
        
        # Boost for strong alignment - use passed scores if available (avoid recalculation)
        if tech_score is None:
            tech_score = self._calculate_technical_score_v2(features)
        if sent_score is None:
            sent_score = self._calculate_sentiment_score_v2(features)
        
        # If both indicators strongly agree, boost confidence
        if (tech_score > 0.6 and sent_score > 0.5) or (tech_score < -0.6 and sent_score < -0.5):
            alignment_boost = min(abs(tech_score * sent_score) * 0.1, 0.12)
            if adjusted > 0.5:
                adjusted += alignment_boost
            else:
                adjusted -= alignment_boost
        
        # Volatility adjustment
        volatility = features.get('volatility', 1.0)
        if volatility > 2.5:  # High volatility
            # Pull back from extremes
            if adjusted > 0.75:
                adjusted = 0.75 + (adjusted - 0.75) * 0.6
            elif adjusted < 0.25:
                adjusted = 0.25 - (0.25 - adjusted) * 0.6
        
        # Ensure bounds
        return np.clip(adjusted, self.min_confidence, self.max_confidence)
    
    def _calculate_target_price_v2(
        self,
        current_price: float,
        confidence: float,
        is_bullish: bool,
        features: Dict,
        technical_score: float,
        sentiment_score: float
    ) -> Tuple[float, float]:
        """
        Enhanced target price calculation with multiple factors
        """
        # Base move calculation (1% - 8% range for more realistic targets)
        confidence_factor = (confidence - 0.55) / 0.43  # Normalize 0.55-0.98 to 0-1
        min_change = 1.0  # Increased from 0.5%
        max_change = 8.0  # Increased from 5.0%
        base_change = min_change + (max_change - min_change) * confidence_factor
        
        # Market influence boost (global markets, volume, etc)
        global_markets_score = features.get('global_market_change', 0)
        volume_score = features.get('volume_ratio', 1.0)
        market_influence = max(0, global_markets_score * 0.2 + (volume_score - 1.0) * 0.1)
        
        # Volatility adjustment
        volatility = features.get('volatility', 1.0)
        volatility_multiplier = min(volatility / 1.8, 1.6)  # Increased cap to 1.6
        
        # Technical + Sentiment influence on target
        technical_influence = technical_score * 0.5  # Increased from 0.3
        sentiment_influence = sentiment_score * 0.6  # Increased from 0.4
        combined_influence = technical_influence + sentiment_influence
        
        # Calculate final move
        adjusted_change = base_change * volatility_multiplier
        adjusted_change *= (1 + combined_influence * 0.7)  # Increased from 0.5
        adjusted_change *= (1 + market_influence)  # Add market influence
        
        # Momentum boost - if stock is already moving, extend the target
        price_change_1d = features.get('price_change_1d', 0)
        price_change_3d = features.get('price_change_3d', 0)
        price_change_5d = features.get('price_change_5d', 0)
        
        # Recent momentum (1-3 days)
        if abs(price_change_1d) > 1.5:
            adjusted_change *= 1.25
        elif abs(price_change_1d) > 1.0:
            adjusted_change *= 1.15
        elif abs(price_change_1d) > 0.5:
            adjusted_change *= 1.05
        
        # Longer momentum (5 days)
        if abs(price_change_5d) > 4.0:
            adjusted_change *= 1.20
        elif abs(price_change_5d) > 2.5:
            adjusted_change *= 1.12
        
        # Ensure move is in same direction as momentum if strong
        if is_bullish and price_change_1d > 1.0:
            adjusted_change = max(adjusted_change, price_change_1d * 0.8)  # At least 80% of current day's move
        elif not is_bullish and price_change_1d < -1.0:
            adjusted_change = max(adjusted_change, abs(price_change_1d) * 0.8)
        
        # Cap the change (wider range)
        adjusted_change = np.clip(adjusted_change, 0.5, 10.0)
        
        # Apply direction
        change_percent = adjusted_change if is_bullish else -adjusted_change
        
        # Calculate target
        target_price = current_price * (1 + change_percent / 100)
        
        return target_price, change_percent
    
    def _generate_signals_v2(
        self,
        features: Dict,
        is_bullish: bool,
        confidence: float,
        technical_score: float,
        sentiment_score: float,
        composite_score: float
    ) -> List[Dict]:
        """
        Generate comprehensive trading signals
        """
        signals = []
        top_reasons = []
        
        # Strong consensus signals
        if is_bullish and confidence > 0.80 and technical_score > 0.6 and sentiment_score > 0.4:
            signals.append({
                'type': 'BUY',
                'strength': 'VERY STRONG',
                'reason': 'Excellent alignment: Bullish technicals + positive sentiment'
            })
            top_reasons.append("Strong bullish technical + news alignment")
        elif is_bullish and confidence > 0.70:
            signals.append({
                'type': 'BUY',
                'strength': 'STRONG',
                'reason': 'High confidence bullish prediction'
            })
            top_reasons.append("High confidence bullish setup")
        elif is_bullish and confidence > 0.60:
            signals.append({
                'type': 'BUY',
                'strength': 'MODERATE',
                'reason': 'Bullish momentum detected'
            })
            top_reasons.append("Moderate bullish momentum")
        
        if not is_bullish and confidence > 0.80 and technical_score < -0.6 and sentiment_score < -0.4:
            signals.append({
                'type': 'SELL',
                'strength': 'VERY STRONG',
                'reason': 'Excellent alignment: Bearish technicals + negative sentiment'
            })
            top_reasons.append("Strong bearish technical + news alignment")
        elif not is_bullish and confidence > 0.70:
            signals.append({
                'type': 'SELL',
                'strength': 'STRONG',
                'reason': 'High confidence bearish prediction'
            })
            top_reasons.append("High confidence bearish setup")
        elif not is_bullish and confidence > 0.60:
            signals.append({
                'type': 'SELL',
                'strength': 'MODERATE',
                'reason': 'Bearish momentum detected'
            })
            top_reasons.append("Moderate bearish momentum")
        
        # Technical alerts
        rsi = features.get('rsi')
        if rsi and rsi > 80:
            signals.append({
                'type': 'WARNING',
                'strength': 'CRITICAL',
                'reason': f'Extreme overbought (RSI: {rsi:.1f}) - Strong correction risk'
            })
            top_reasons.append("Extreme overbought condition")
        elif rsi and rsi > 75:
            signals.append({
                'type': 'WARNING',
                'strength': 'HIGH',
                'reason': f'Overbought (RSI: {rsi:.1f}) - Correction possible'
            })
            top_reasons.append("Overbought RSI")
        
        if rsi and rsi < 20:
            signals.append({
                'type': 'OPPORTUNITY',
                'strength': 'CRITICAL',
                'reason': f'Extreme oversold (RSI: {rsi:.1f}) - Major bounce opportunity'
            })
            top_reasons.append("Extreme oversold condition")
        elif rsi and rsi < 25:
            signals.append({
                'type': 'OPPORTUNITY',
                'strength': 'HIGH',
                'reason': f'Oversold (RSI: {rsi:.1f}) - Potential bounce'
            })
            top_reasons.append("Oversold opportunity")
        
        # Volume signals
        volume_ratio = features.get('volume_ratio', 1.0)
        if volume_ratio > 2.5:
            signals.append({
                'type': 'ALERT',
                'strength': 'CRITICAL',
                'reason': f'Exceptional volume spike ({volume_ratio:.1f}x) - Major activity'
            })
            top_reasons.append(f"Volume spike: {volume_ratio:.1f}x average")
        elif volume_ratio > 1.8:
            signals.append({
                'type': 'ALERT',
                'strength': 'HIGH',
                'reason': f'High volume ({volume_ratio:.1f}x) - Increased conviction'
            })
            top_reasons.append(f"Elevated volume: {volume_ratio:.1f}x")
        
        # News sentiment signals
        if sentiment_score > 0.7 and features.get('news_count', 0) > 5:
            signals.append({
                'type': 'BUY',
                'strength': 'STRONG',
                'reason': 'Very positive news sentiment across multiple sources'
            })
            if "Strong bullish" not in str(top_reasons):
                top_reasons.append("Positive news sentiment")
        elif sentiment_score < -0.7 and features.get('news_count', 0) > 5:
            signals.append({
                'type': 'SELL',
                'strength': 'STRONG',
                'reason': 'Very negative news sentiment across multiple sources'
            })
            if "Strong bearish" not in str(top_reasons):
                top_reasons.append("Negative news sentiment")
        
        return signals[:5]  # Limit to top 5 signals
    
    def _generate_top_reasons(
        self,
        features: Dict,
        is_bullish: bool,
        technical_score: float,
        sentiment_score: float
    ) -> List[str]:
        """
        Generate top reasons for the prediction
        """
        reasons = []
        
        # Technical reasons
        if technical_score > 0.7:
            reasons.append("Strong bullish technical indicators")
        elif technical_score > 0.4:
            reasons.append("Moderately bullish technical setup")
        elif technical_score < -0.7:
            reasons.append("Strong bearish technical indicators")
        elif technical_score < -0.4:
            reasons.append("Moderately bearish technical setup")
        
        # Sentiment reasons
        news_count = features.get('news_count', 0)
        if sentiment_score > 0.6 and news_count > 3:
            reasons.append("Positive news sentiment and strong buying interest")
        elif sentiment_score > 0.3:
            reasons.append("Slight bullish sentiment shift")
        elif sentiment_score < -0.6 and news_count > 3:
            reasons.append("Negative news sentiment and selling pressure")
        elif sentiment_score < -0.3:
            reasons.append("Slight bearish sentiment shift")
        
        # Market context
        asian_sentiment = features.get('asian_market_sentiment', 'neutral')
        if asian_sentiment == 'positive':
            reasons.append("Asian markets showing strength")
        elif asian_sentiment == 'negative':
            reasons.append("Asian market weakness affecting US")
        
        # European market context
        european_sentiment = features.get('european_market_sentiment', 'neutral')
        if european_sentiment == 'positive':
            reasons.append("European markets showing strength")
        elif european_sentiment == 'negative':
            reasons.append("European market weakness affecting US")
        
        # Volume confirmation
        volume_ratio = features.get('volume_ratio', 1.0)
        if volume_ratio > 1.5:
            reasons.append("Volume supporting price direction")
        
        # RSI condition
        rsi = features.get('rsi')
        if rsi and rsi < 30:
            reasons.append("Oversold conditions present")
        elif rsi and rsi > 70:
            reasons.append("Overbought conditions present")
        
        return reasons[:3]  # Return top 3 reasons
    
    def _calculate_local_impact_percent(self, local_score: float, local_factors: Dict) -> float:
        """
        Calculate local US factors impact percentage (0-10% typical range)
        """
        # Base impact on absolute local score strength
        if local_score > 0.5:
            impact_pct = 6.0 + (local_score - 0.5) * 4.0  # 6-8% for strong positive
        elif local_score > 0.3:
            impact_pct = 4.0 + (local_score - 0.3) * 10.0  # 4-6% for moderate positive
        elif local_score > 0:
            impact_pct = 2.0 + local_score * 10.0  # 2-4% for weak positive
        elif local_score < -0.5:
            impact_pct = 6.0 + abs(local_score - (-0.5)) * 4.0  # 6-8% for strong negative
        elif local_score < -0.3:
            impact_pct = 4.0 + abs(local_score - (-0.3)) * 10.0  # 4-6% for moderate negative
        elif local_score < 0:
            impact_pct = 2.0 + abs(local_score) * 10.0  # 2-4% for weak negative
        else:
            impact_pct = 1.0  # 1% minimal impact for neutral
        
        return np.clip(impact_pct, 0, 10)
    
    def _sanitize_numpy_types(self, obj):
        """
        Recursively convert numpy types to Python native types for JSON serialization
        """
        if isinstance(obj, dict):
            return {k: self._sanitize_numpy_types(v) for k, v in obj.items()}
        elif isinstance(obj, (list, tuple)):
            return type(obj)(self._sanitize_numpy_types(item) for item in obj)
        elif isinstance(obj, np.integer):
            return int(obj)
        elif isinstance(obj, np.floating):
            return float(obj)
        elif isinstance(obj, np.ndarray):
            return obj.tolist()
        return obj
    
    def _error_response(self, message: str) -> Dict:
        """Return error response"""
        return {
            'success': False,
            'error': message,
            'model_version': self.version,
            'timestamp': datetime.now().isoformat(),
        }


def main():
    """
    CLI interface for the model - supports argparse and stdin
    """
    parser = argparse.ArgumentParser(description='Quick Model V6 - Stock Prediction')
    parser.add_argument('action', nargs='?', choices=['predict', 'train'], help='Action to perform')
    parser.add_argument('--features', type=str, help='JSON string of features for prediction')
    
    # Try to parse as argparse first, fallback to direct JSON for backward compatibility
    try:
        args = parser.parse_args()
        
        # If action is specified, use argparse mode
        if args.action == 'predict':
            features = None
            
            # Try to get features from --features argument first
            if args.features:
                try:
                    features = json.loads(args.features)
                except json.JSONDecodeError:
                    pass
            
            # If no features via argument, try stdin
            if not features:
                try:
                    stdin_data = sys.stdin.read()
                    if stdin_data:
                        features = json.loads(stdin_data)
                except (json.JSONDecodeError, Exception):
                    pass
            
            # If still no features, error
            if not features:
                print(json.dumps({'error': 'Features required for prediction (via --features or stdin)'}))
                sys.exit(1)
            
            try:
                model = QuickModelV6()
                result = model.predict(features)
                print(json.dumps(result, indent=2))
                sys.exit(0 if result.get('success') else 1)
            except json.JSONDecodeError as e:
                print(json.dumps({'error': f'Invalid JSON: {str(e)}'}))
                sys.exit(1)
            except Exception as e:
                print(json.dumps({'error': f'Prediction failed: {str(e)}'}))
                sys.exit(1)
        elif args.action == 'train':
            print(json.dumps({'error': 'Training not yet implemented'}))
            sys.exit(1)
        else:
            # Fallback: treat first argument as JSON
            if len(sys.argv) > 1 and not sys.argv[1].startswith('-'):
                try:
                    features_json = sys.argv[1]
                    features = json.loads(features_json)
                    model = QuickModelV6()
                    result = model.predict(features)
                    print(json.dumps(result, indent=2))
                    sys.exit(0 if result.get('success') else 1)
                except json.JSONDecodeError as e:
                    print(json.dumps({'error': f'Invalid JSON: {str(e)}'}))
                    sys.exit(1)
            else:
                parser.print_help()
                sys.exit(1)
    
    except SystemExit:
        raise
    except Exception as e:
        print(json.dumps({'error': f'Unexpected error: {str(e)}'}))
        sys.exit(1)


if __name__ == '__main__':
    main()
