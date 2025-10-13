"""
Test Suite for quick_model_v2
==============================

Tests Asian market influence, correction warnings, and prediction logic
"""

import pytest
import sys
import os
from pathlib import Path

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent.parent))

from models.quick_model_v2 import QuickModelV2


class TestAsianInfluence:
    """Tests for Asian market influence calculation"""
    
    def test_strong_bearish_asian_markets_override_bullish_base(self):
        """
        When Asian markets strongly bearish (avg -2%),
        final label should be BEARISH even if base model slightly bullish
        """
        model = QuickModelV2()
        
        features = {
            'close': 150.0,
            'rsi_14': 55,  # Neutral RSI
            'macd_hist': 0.5,  # Slightly bullish
            'asian_influence_score': -0.8,  # Strong bearish from Asia
            'asian_avg_change': -2.5,
            'atr_14': 2.0,
            'bb_width': 5.0,
        }
        
        prediction = model.predict(features)
        
        assert prediction['label'] == 'BEARISH', "Should be bearish due to strong Asian influence"
        assert prediction['asian_impact_percent'] > 0.3, "Asian impact should be significant"
        assert prediction['final_score'] < 0, "Final score should be negative"
    
    def test_strong_bullish_asian_markets_override_bearish_base(self):
        """
        When Asian markets strongly bullish (avg +2%),
        final label should be BULLISH even if base model slightly bearish
        """
        model = QuickModelV2()
        
        features = {
            'close': 150.0,
            'rsi_14': 45,  # Neutral RSI
            'macd_hist': -0.3,  # Slightly bearish
            'asian_influence_score': 0.85,  # Strong bullish from Asia
            'asian_avg_change': 2.8,
            'atr_14': 2.0,
            'bb_width': 5.0,
        }
        
        prediction = model.predict(features)
        
        assert prediction['label'] == 'BULLISH', "Should be bullish due to strong Asian influence"
        assert prediction['asian_impact_percent'] > 0.3, "Asian impact should be significant"
        assert prediction['final_score'] > 0, "Final score should be positive"
    
    def test_neutral_asian_markets_low_impact(self):
        """
        When Asian markets neutral, impact should be minimal
        """
        model = QuickModelV2()
        
        features = {
            'close': 150.0,
            'rsi_14': 60,
            'asian_influence_score': 0.05,  # Nearly neutral
            'asian_avg_change': 0.1,
            'atr_14': 2.0,
            'bb_width': 5.0,
        }
        
        prediction = model.predict(features)
        
        assert prediction['asian_impact_percent'] < 0.1, "Impact should be minimal for neutral Asian markets"
    
    def test_asian_impact_caps_at_50_percent(self):
        """
        Asian impact percentage should never exceed 50%
        """
        model = QuickModelV2()
        
        features = {
            'close': 150.0,
            'asian_influence_score': 1.0,  # Maximum score
            'asian_avg_change': 5.0,
            'atr_14': 2.0,
            'bb_width': 5.0,
        }
        
        prediction = model.predict(features)
        
        assert prediction['asian_impact_percent'] <= 0.5, "Asian impact should never exceed 50%"


class TestCorrectionWarnings:
    """Tests for correction warning detection"""
    
    def test_high_rsi_with_price_surge_triggers_warning(self):
        """
        When price up > 10% + RSI > 80, correction warning should trigger
        """
        model = QuickModelV2()
        
        features = {
            'close': 165.0,
            'price_change_7d': 12.5,  # Up 12.5% in 7 days
            'rsi_14': 87,  # Overbought
            'bb_pct': 1.1,
            'bb_middle': 150,
            'bb_width': 10,
            'volume_sma_ratio': 1.2,
            'atr_14': 2.0,
        }
        
        prediction = model.predict(features)
        warning = prediction['correction_warning']
        
        assert warning['warning'] == True, "Warning should be triggered"
        assert warning['severity'] == 'HIGH', "Severity should be HIGH for RSI > 85"
        assert 'RSI' in warning['reasons'][0], "Reason should mention RSI"
    
    def test_bollinger_zscore_extreme_triggers_warning(self):
        """
        When Bollinger z-score > 2.5, warning should trigger
        """
        model = QuickModelV2()
        
        features = {
            'close': 170.0,
            'price_change_7d': 8.0,
            'rsi_14': 75,
            'bb_pct': 1.3,  # Above upper band
            'bb_middle': 150,
            'bb_width': 10,  # Close is (170-150) / (10/2) = 4.0 z-score
            'volume_sma_ratio': 1.0,
            'atr_14': 2.0,
        }
        
        prediction = model.predict(features)
        warning = prediction['correction_warning']
        
        assert warning['warning'] == True, "Warning should be triggered for high z-score"
        assert 'Bollinger' in warning['reasons'][0] or 'overbought' in warning['reasons'][0].lower()
    
    def test_volume_spike_with_price_spike_triggers_warning(self):
        """
        When volume > 2x average AND price up > 10%, warning should trigger
        """
        model = QuickModelV2()
        
        features = {
            'close': 160.0,
            'price_change_7d': 11.0,
            'rsi_14': 70,
            'bb_pct': 0.9,
            'bb_middle': 150,
            'bb_width': 10,
            'volume_sma_ratio': 2.5,  # 2.5x average volume
            'atr_14': 2.0,
        }
        
        prediction = model.predict(features)
        warning = prediction['correction_warning']
        
        assert warning['warning'] == True, "Warning should be triggered"
        assert 'volume' in warning['reasons'][0].lower() or 'Volume' in warning['reasons'][0]
    
    def test_no_warning_for_normal_conditions(self):
        """
        No warning when conditions are normal
        """
        model = QuickModelV2()
        
        features = {
            'close': 152.0,
            'price_change_7d': 3.0,  # Small gain
            'rsi_14': 55,  # Neutral
            'bb_pct': 0.5,
            'bb_middle': 150,
            'bb_width': 10,
            'volume_sma_ratio': 1.0,
            'atr_14': 2.0,
        }
        
        prediction = model.predict(features)
        warning = prediction['correction_warning']
        
        assert warning['warning'] == False, "No warning should be triggered for normal conditions"


class TestPredictionLogic:
    """Tests for core prediction logic"""
    
    def test_prediction_is_binary(self):
        """
        Prediction should always be BULLISH or BEARISH, never neutral
        """
        model = QuickModelV2()
        
        features = {
            'close': 150.0,
            'rsi_14': 50,  # Perfectly neutral
            'macd_hist': 0.0,  # Neutral
            'asian_influence_score': 0.0,  # Neutral
            'atr_14': 2.0,
        }
        
        prediction = model.predict(features)
        
        assert prediction['label'] in ['BULLISH', 'BEARISH'], "Label must be binary"
        assert prediction['label'] != 'NEUTRAL', "Should never predict NEUTRAL"
    
    def test_expected_move_has_correct_sign(self):
        """
        Expected % move should match the predicted direction
        """
        model = QuickModelV2()
        
        # Bullish scenario
        features_bullish = {
            'close': 150.0,
            'rsi_14': 35,  # Oversold
            'macd_hist': 1.5,  # Strong bullish
            'asian_influence_score': 0.6,
            'atr_14': 2.0,
            'bb_width': 5.0,
        }
        
        prediction = model.predict(features_bullish)
        
        if prediction['label'] == 'BULLISH':
            assert prediction['expected_pct_move'] > 0, "Bullish prediction should have positive move"
        else:
            assert prediction['expected_pct_move'] < 0, "Bearish prediction should have negative move"
    
    def test_probability_in_valid_range(self):
        """
        Probability should always be between 0 and 1
        """
        model = QuickModelV2()
        
        features = {
            'close': 150.0,
            'rsi_14': 70,
            'asian_influence_score': 0.5,
            'atr_14': 2.0,
        }
        
        prediction = model.predict(features)
        
        assert 0 <= prediction['probability'] <= 1, "Probability must be between 0 and 1"
    
    def test_top_reasons_provided(self):
        """
        Prediction should include top reasons
        """
        model = QuickModelV2()
        
        features = {
            'close': 150.0,
            'rsi_14': 85,  # Strong signal
            'macd_hist': 2.0,  # Strong signal
            'asian_influence_score': 0.7,  # Strong signal
            'price_change_7d': 15.0,  # Strong signal
            'volume_sma_ratio': 2.0,  # Strong signal
            'atr_14': 2.0,
        }
        
        prediction = model.predict(features)
        
        assert 'top_reasons' in prediction, "Should include top_reasons"
        assert len(prediction['top_reasons']) > 0, "Should have at least one reason"
        assert len(prediction['top_reasons']) <= 5, "Should have at most 5 reasons"


class TestFeatureHandling:
    """Tests for feature validation and handling"""
    
    def test_missing_features_filled_with_defaults(self):
        """
        Missing features should be filled with default values
        """
        model = QuickModelV2()
        
        # Minimal features
        features = {
            'close': 150.0,
        }
        
        prediction = model.predict(features)
        
        assert prediction is not None, "Should handle missing features"
        assert 'label' in prediction
        assert 'probability' in prediction
    
    def test_feature_validation(self):
        """
        All expected features should be validated
        """
        model = QuickModelV2()
        
        features = {
            'close': 150.0,
            'rsi_14': 55,
            'asian_influence_score': 0.5,
        }
        
        prediction = model.predict(features)
        
        # Should not raise any errors
        assert prediction is not None


class TestExpectedMove:
    """Tests for expected percentage move calculation"""
    
    def test_higher_score_means_bigger_move(self):
        """
        Higher final score should generally lead to bigger expected move
        """
        model = QuickModelV2()
        
        # Low score scenario
        features_low = {
            'close': 150.0,
            'rsi_14': 52,
            'asian_influence_score': 0.1,
            'atr_14': 1.0,  # Low volatility
            'bb_width': 3.0,
        }
        
        # High score scenario
        features_high = {
            'close': 150.0,
            'rsi_14': 30,  # Oversold
            'macd_hist': 2.0,  # Strong momentum
            'asian_influence_score': 0.8,  # Strong Asian influence
            'atr_14': 1.0,
            'bb_width': 3.0,
        }
        
        pred_low = model.predict(features_low)
        pred_high = model.predict(features_high)
        
        # Compare absolute magnitudes
        assert abs(pred_high['expected_pct_move']) > abs(pred_low['expected_pct_move']), \
            "Higher score should lead to bigger expected move"
    
    def test_volatility_increases_expected_move(self):
        """
        Higher volatility (ATR) should increase expected move magnitude
        """
        model = QuickModelV2()
        
        # Low volatility
        features_low_vol = {
            'close': 150.0,
            'rsi_14': 35,
            'asian_influence_score': 0.5,
            'atr_14': 0.5,  # Low volatility
            'bb_width': 2.0,
        }
        
        # High volatility
        features_high_vol = {
            'close': 150.0,
            'rsi_14': 35,
            'asian_influence_score': 0.5,
            'atr_14': 5.0,  # High volatility
            'bb_width': 2.0,
        }
        
        pred_low_vol = model.predict(features_low_vol)
        pred_high_vol = model.predict(features_high_vol)
        
        # High volatility should lead to bigger moves
        assert abs(pred_high_vol['expected_pct_move']) > abs(pred_low_vol['expected_pct_move']), \
            "Higher volatility should increase expected move"


if __name__ == '__main__':
    pytest.main([__file__, '-v'])
