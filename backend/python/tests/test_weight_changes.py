#!/usr/bin/env python3
"""
Test weight changes: before and after
- Before: Tech 25%, Fund 20%, Sent 20%, Regional 15%, Liq 10%, Fear 10%
- After:  Tech 15%, Fund 10%, Sent 20%, Regional 25%, Liq 15%, Fear 15%
"""
import sys
import os

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
MODELS_DIR = os.path.join(os.path.dirname(CURRENT_DIR), 'models')
sys.path.append(MODELS_DIR)

from quick_model_v6 import QuickModelV6  # type: ignore

def test_stock(symbol, cat_mult, rng, desc=""):
    """Test with bearish market conditions"""
    f = {
        'symbol': symbol,
        'category_multiplier': cat_mult,
        'typical_daily_range_min': rng[0],
        'typical_daily_range_max': rng[1],
        # Bearish technicals
        'rsi_14': 65,
        'macd_hist': -0.5,
        'price_change_1d': -0.8,
        'price_change_3d': -1.5,
        'price_change_7d': -2.0,
        'bb_pct': 0.7,
        # Bullish fundamentals (should not override bearish)
        'pe_ratio': 18,
        'eps_growth': 15,
        'revenue_growth': 12,
        'roe': 18,
        'profit_margin': 20,
        # Bearish sentiment
        'news_sentiment_score': -0.4,
        'fear_greed_index': 35,
        # Bearish regional/global
        'sp500_change': -0.6,
        'nasdaq_change': -0.8,
        'european_influence_score': -0.5,
        'european_avg_change': -0.5,
        'asian_influence_score': -0.4,
        'asian_avg_change': -0.4,
        # Bearish liquidity
        'volume_sma_ratio': 0.9,
        'inst_flow_score': -0.3,
    }
    
    m = QuickModelV6()
    res = m.predict(f)
    
    print(f"\n{symbol} ({desc})")
    print(f"  Label: {res['label']}")
    print(f"  Expected Move: {res['expected_pct_move']:+.2f}%")
    print(f"  Probability: {res['probability']*100:.1f}%")
    print(f"  Contributions:")
    for factor, contrib in res['contributions'].items():
        if factor != 'composite':
            weight = res['weights'].get(factor, 0)
            print(f"    {factor:12s}: {contrib:+.4f} (weight {weight*100:.0f}%)")
    print(f"  Composite: {res['contributions']['composite']:+.4f}")
    
    return res

if __name__ == '__main__':
    print("="*70)
    print("BEFORE: Tech 25%, Fund 20%, Sent 20%, Regional 15%, Liq 10%, Fear 10%")
    print("Testing with BEARISH market conditions (technicals, sentiment, global)")
    print("="*70)
    
    stocks = [
        ('AVGO', 2.2, (1.5, 6.0), 'Semiconductor'),
        ('NVDA', 2.5, (2.0, 8.0), 'Tech Growth'),
        ('MSFT', 1.5, (1.0, 4.0), 'Tech Blue Chip'),
        ('TSLA', 2.5, (2.0, 8.0), 'Tech Growth'),
        ('AMD', 2.2, (1.5, 6.0), 'Semiconductor'),
    ]
    
    for sym, mult, rng, desc in stocks:
        test_stock(sym, mult, rng, desc)
    
    print("\n" + "="*70)
    print("Note: Fundamentals are strong (+) but should NOT override bearish signals")
    print("Expected: All stocks should be BEARISH with negative moves")
    print("="*70)
