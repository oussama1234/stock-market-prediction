import sys
import os
from pprint import pprint

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
MODELS_DIR = os.path.join(os.path.dirname(CURRENT_DIR), 'models')
sys.path.append(MODELS_DIR)

from quick_model_v6 import QuickModelV6  # type: ignore

m = QuickModelV6()

# Test with various fear/greed and sentiment values
test_cases = [
    {
        'name': 'Neutral Fear & Neutral Sentiment',
        'features': {
            'close': 100.0,
            'fear_greed_index': 50,  # Neutral
            'news_sentiment_score': 0.0,  # Neutral (-1 to +1 range)
            'price_change_1d': 0.2,
            'rsi_14': 50,
        }
    },
    {
        'name': 'Extreme Fear with Positive News',
        'features': {
            'close': 100.0,
            'fear_greed_index': 20,  # Extreme Fear - should be bullish signal
            'news_sentiment_score': 0.5,  # Positive news
            'price_change_1d': 0.2,
            'rsi_14': 50,
        }
    },
    {
        'name': 'Extreme Greed with Negative News',
        'features': {
            'close': 100.0,
            'fear_greed_index': 85,  # Extreme Greed - should be bearish signal
            'news_sentiment_score': -0.4,  # Negative news
            'price_change_1d': -0.3,
            'rsi_14': 70,
        }
    },
    {
        'name': 'Fear (30) with Strong Positive Sentiment',
        'features': {
            'close': 100.0,
            'fear_greed_index': 30,  # Fear - bullish contrarian
            'news_sentiment_score': 0.8,  # Very positive news
            'price_change_1d': 0.5,
            'rsi_14': 45,
        }
    },
]

print("=" * 80)
print("Testing Fear & Greed Index and Market Sentiment Scoring")
print("=" * 80)

for test in test_cases:
    print(f"\n{test['name']}")
    print("-" * 80)
    
    result = m.predict(test['features'])
    
    # Extract relevant scores
    fear_score = result['scores']['fear_index']
    sentiment_score = result['scores']['sentiment']
    composite_score = result['scores']['composite']
    
    # Get factor details
    fear_details = result['factors']['fear_index']
    sentiment_details = result['factors']['sentiment']
    
    print(f"Input:")
    print(f"  Fear & Greed Index: {test['features']['fear_greed_index']}")
    print(f"  News Sentiment: {test['features']['news_sentiment_score']}")
    
    print(f"\nScores:")
    print(f"  Fear Index Score: {fear_score:+.4f} (weight: {result['weights']['fear_index']:.0%})")
    print(f"  Sentiment Score: {sentiment_score:+.4f} (weight: {result['weights']['sentiment']:.0%})")
    print(f"  Composite Score: {composite_score:+.4f}")
    
    print(f"\nContributions:")
    print(f"  Fear Index Impact: {result['contributions']['fear_index']:+.4f}")
    print(f"  Sentiment Impact: {result['contributions']['sentiment']:+.4f}")
    
    print(f"\nPrediction:")
    print(f"  Label: {result['label']}")
    print(f"  Probability: {result['probability']:.1%}")
    print(f"  Expected Move: {result['expected_pct_move']:+.2f}%")
    
    print(f"\nFear Details:")
    print(f"  Index Value: {fear_details['fear_greed_index']}")
    print(f"  Level: {fear_details['fear_level']}")
    print(f"  Score: {fear_details['score']:+.4f}")
    
    print(f"\nSentiment Details:")
    print(f"  News Sentiment: {sentiment_details['news_sentiment_score']:+.2f}")
    print(f"  Fear & Greed: {sentiment_details['fear_greed_index']}")
    print(f"  Score: {sentiment_details['score']:+.4f}")

print("\n" + "=" * 80)
print("Test completed - verify that fear_index and sentiment are non-zero when inputs are provided")
print("=" * 80)
