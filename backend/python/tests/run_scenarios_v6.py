import sys
import os
from pprint import pprint

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
MODELS_DIR = os.path.join(os.path.dirname(CURRENT_DIR), 'models')
sys.path.append(MODELS_DIR)

from quick_model_v6 import QuickModelV6  # type: ignore

m = QuickModelV6()

symbols = [
    { 'symbol': 'AVGO', 'cat_mult': 2.2, 'range': (1.5, 6.0) },
    { 'symbol': 'NVDA', 'cat_mult': 2.5, 'range': (2.0, 8.0) },
    { 'symbol': 'MSFT', 'cat_mult': 1.5, 'range': (1.0, 4.0) },
    { 'symbol': 'TSLA', 'cat_mult': 2.5, 'range': (2.0, 8.0) },
]

def base_features(cat_mult, rng):
    return {
        'close': 100.0,
        'atr_14': 2.0,              # 2% of price
        'bb_width': 0.05,           # 5%
        'rsi_14': 50,
        'macd_hist': 0.1,
        'price_change_1d': 0.2,
        'category_multiplier': cat_mult,
        'typical_daily_range_min': rng[0],
        'typical_daily_range_max': rng[1],
    }

scenarios = {
    'important_news_positive': {
        'desc': 'Strong positive news (AI/earnings) with stable globals',
        'extras': {
            'sp500_change': 0.2,
            'nasdaq_change': 0.3,
            'european_avg_change': 0.1,
            'asian_avg_change': 0.1,
            'news_sentiment_score': 0.9,
            'keyword_ai': 1,
            'keyword_earnings_beat': 1,
        }
    },
    'globals_up_1_5': {
        'desc': 'Global markets broadly up ~+1.5%, no strong news',
        'extras': {
            'sp500_change': 1.5,
            'nasdaq_change': 1.7,
            'european_avg_change': 1.5,
            'asian_avg_change': 1.5,
            'news_sentiment_score': 0.0,
        }
    }
}

for scen_key, scen in scenarios.items():
    print(f"\nScenario: {scen_key} - {scen['desc']}")
    print('='*70)
    for s in symbols:
        f = base_features(s['cat_mult'], s['range'])
        f.update(scen['extras'])
        res = m.predict(f)
        print(f"{s['symbol']}: label={res['label']}, expected_pct_move={res['expected_pct_move']:+.2f}% prob={res['probability']*100:.1f}%")
