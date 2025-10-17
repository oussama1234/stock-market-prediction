#!/usr/bin/env python3
import json
import sys
sys.path.insert(0, 'D:\\Stock-market-predection\\backend\\python\\models')

from quick_model_v6 import QuickModelV6

# Test features with volume_ratio, pe_percentile, analyst_action, and intraday_volume_ratio
features = {
    "current_price": 351.33,
    "volume_ratio": 1.2,
    "intraday_volume_ratio": 1.2,
    "pe_percentile": 55,
    "analyst_action": "upgrade",
    "insider_activity": "neutral",
    "intraday_change_percent": 0,
    "open_close_gap_percent": 0,
    "revenue_growth": 0,
    "earnings_growth": 0,
    "margin_change_percent": 0,
    "rsi": 50,
    "price_change_1d": 0.5,
    "asian_market_sentiment": "positive"
}

model = QuickModelV6()
result = model.predict(features)

print("=" * 50)
print("PYTHON MODEL TEST - FIXED SCORES")
print("=" * 50)
print(f"\nPrediction: {result['prediction']['label']}")
print(f"Confidence: {result['prediction']['probability']*100:.1f}%\n")
print("COMPONENT SCORES:")
print(f"  Technical:      {result['scores']['technical']}")
print(f"  Sentiment:      {result['scores']['sentiment']}")
print(f"  Global Markets: {result['scores']['global_markets']}")
print(f"  Volume:         {result['scores']['volume']}")
print(f"  Fundamentals:   {result['scores']['fundamentals']}")
print(f"  Intraday:       {result['scores']['intraday']}")
print("\nCOMPONENT CONTRIBUTIONS:")
print(f"  Technical:      {result['contributions']['technical']}")
print(f"  Sentiment:      {result['contributions']['sentiment']}")
print(f"  Global Markets: {result['contributions']['global_markets']}")
print(f"  Volume:         {result['contributions']['volume']}")
print(f"  Fundamentals:   {result['contributions']['fundamentals']}")
print(f"  Intraday:       {result['contributions']['intraday']}")
