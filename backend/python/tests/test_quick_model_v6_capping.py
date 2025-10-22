import unittest
import sys
import os

# Ensure module path includes models
CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
MODELS_DIR = os.path.join(os.path.dirname(CURRENT_DIR), 'models')
sys.path.append(MODELS_DIR)

from quick_model_v6 import QuickModelV6  # type: ignore


class TestQuickModelV6Capping(unittest.TestCase):
    def setUp(self):
        self.m = QuickModelV6()

    def predict_move(self, f):
        r = self.m.predict(f)
        return r['expected_pct_move'], r['label']

    def test_normal_day_caps_small_moves(self):
        # AVGO-like stable day, no important news
        f = {
            'symbol': 'AVGO',
            'category_multiplier': 2.2,
            'typical_daily_range_min': 1.5,
            'typical_daily_range_max': 6.0,
            'sp500_change': 0.3,
            'nasdaq_change': 0.3,
            'european_avg_change': 0.3,
            'asian_avg_change': 0.2,
            'news_sentiment_score': 0.0,
            'price_change_1d': 0.1,
        }
        move, _ = self.predict_move(f)
        self.assertLessEqual(move, 1.0, 'Normal day bullish cap should be <= +1.0%')
        self.assertGreaterEqual(move, -0.5, 'Normal day bearish cap should be >= -0.5%')

    def test_positive_news_allows_bigger_jump(self):
        # NVDA-like day with AI news
        f = {
            'symbol': 'NVDA',
            'category_multiplier': 2.5,
            'typical_daily_range_min': 2.0,
            'typical_daily_range_max': 8.0,
            'sp500_change': 0.4,
            'nasdaq_change': 0.6,
            'european_avg_change': 0.4,
            'asian_avg_change': 0.2,
            'news_sentiment_score': 0.8,
            'keyword_ai': 1,
            'price_change_1d': 0.5,
            'rsi_14': 45,
            'macd_hist': 1.0,
        }
        move, label = self.predict_move(f)
        self.assertEqual(label, 'BULLISH')
        self.assertGreaterEqual(move, 1.0)
        self.assertLessEqual(move, 3.0)

    def test_negative_tariff_news_pushes_down(self):
        # MSFT-like day with tariff/negative keyword
        f = {
            'symbol': 'MSFT',
            'category_multiplier': 1.5,
            'typical_daily_range_min': 1.0,
            'typical_daily_range_max': 4.0,
            'sp500_change': -0.2,
            'nasdaq_change': -0.3,
            'european_avg_change': -0.4,
            'asian_avg_change': -0.2,
            'news_sentiment_score': -0.6,
            'keyword_tariff': 1,
            'price_change_1d': -0.4,
            'rsi_14': 65,
            'macd_hist': -1.0,
        }
        move, label = self.predict_move(f)
        self.assertEqual(label, 'BEARISH')
        self.assertLessEqual(move, -0.5)
        self.assertGreaterEqual(move, -3.0)

    def test_severe_bearish_global_allows_more_downside(self):
        # TSLA-like day with severe global down
        f = {
            'symbol': 'TSLA',
            'category_multiplier': 2.5,
            'typical_daily_range_min': 2.0,
            'typical_daily_range_max': 8.0,
            'sp500_change': -1.2,
            'nasdaq_change': -1.3,
            'european_avg_change': -1.5,
            'asian_avg_change': -1.2,
            'news_sentiment_score': -0.2,
            'price_change_1d': -0.6,
            'rsi_14': 70,
            'macd_hist': -1.5,
        }
        move, label = self.predict_move(f)
        self.assertEqual(label, 'BEARISH')
        self.assertLessEqual(move, -1.0)
        self.assertGreaterEqual(move, -3.0)


if __name__ == '__main__':
    unittest.main()
