#!/usr/bin/env python3
"""
Quick Model V2 - Today-Only Stock Prediction with Asian Market Signals
=======================================================================

Integrates Asian market data (Nikkei, Hang Seng, Shanghai, Nifty) to improve
today-only predictions for US stocks.

Features:
- Binary classification: BULLISH or BEARISH (no neutral)
- Asian influence scoring (-1 to +1)
- Expected % move calculation
- Correction warning detection
- Ensemble approach combining base model + Asian signals

Author: Stock Prediction System
Version: 2.0
"""

import sys
import json
import os
import argparse
import pickle
import numpy as np
from datetime import datetime
from pathlib import Path

# Suppress warnings
import warnings
warnings.filterwarnings('ignore')

try:
    import lightgbm as lgb
    from sklearn.preprocessing import StandardScaler
    from sklearn.model_selection import train_test_split
    HAS_ML_LIBS = True
except ImportError:
    HAS_ML_LIBS = False
    print("Warning: ML libraries not installed. Install with: pip install lightgbm scikit-learn", file=sys.stderr)


class QuickModelV2:
    """
    Quick Model V2 - Enhanced prediction model with Asian market signals
    """
    
    def __init__(self, model_path=None):
        """
        Initialize the model
        
        Args:
            model_path: Path to saved model file (optional)
        """
        self.base_model = None
        self.scaler = StandardScaler()
        self.model_path = model_path or self._get_default_model_path()
        
        # Configuration from environment or defaults
        self.asian_influence_max = float(os.getenv('ASIAN_INFLUENCE_MAX', '0.5'))
        self.asian_influence_scale = float(os.getenv('ASIAN_INFLUENCE_SCALE', '2.0'))
        
        # Feature names for validation
        self.base_features = [
            'close', 'volume', 'price_change_1d', 'price_change_3d', 'price_change_7d',
            'ema_12', 'ema_26', 'macd', 'macd_signal', 'macd_hist',
            'rsi_14', 'rsi_7', 'atr_14', 'obv',
            'bb_upper', 'bb_middle', 'bb_lower', 'bb_width', 'bb_pct',
            'distance_to_support', 'distance_to_resistance',
            'volume_sma_ratio', 'volume_spike',
            'news_sentiment_score', 'fear_greed_index'
        ]
        
        self.asian_features = [
            'nikkei_change_pct', 'hang_seng_change_pct',
            'shanghai_change_pct', 'nifty_change_pct',
            'asian_avg_change', 'asian_influence_score'
        ]
        
        self.all_features = self.base_features + self.asian_features
        
        # Load model if exists
        if os.path.exists(self.model_path):
            self.load_model()
    
    def _get_default_model_path(self):
        """Get default model save path"""
        base_dir = Path(__file__).parent.parent
        models_dir = base_dir / 'saved_models'
        models_dir.mkdir(exist_ok=True)
        return str(models_dir / 'quick_model_v2.pkl')
    
    def train(self, X_train, y_train, X_val=None, y_val=None):
        """
        Train the base model
        
        Args:
            X_train: Training features (dict or numpy array)
            y_train: Training labels (1 for bullish, 0 for bearish)
            X_val: Validation features (optional)
            y_val: Validation labels (optional)
        """
        if not HAS_ML_LIBS:
            raise ImportError("ML libraries required for training")
        
        # Prepare data
        if isinstance(X_train, dict):
            X_train = self._dict_to_array(X_train)
        
        # Fit scaler
        self.scaler.fit(X_train)
        X_train_scaled = self.scaler.transform(X_train)
        
        # Create and train model
        self.base_model = lgb.LGBMClassifier(
            objective='binary',
            n_estimators=200,
            max_depth=6,
            learning_rate=0.05,
            num_leaves=31,
            min_child_samples=20,
            subsample=0.8,
            colsample_bytree=0.8,
            random_state=42,
            verbose=-1
        )
        
        # Training
        eval_set = None
        if X_val is not None and y_val is not None:
            if isinstance(X_val, dict):
                X_val = self._dict_to_array(X_val)
            X_val_scaled = self.scaler.transform(X_val)
            eval_set = [(X_val_scaled, y_val)]
        
        self.base_model.fit(
            X_train_scaled, y_train,
            eval_set=eval_set,
            eval_metric='auc',
            callbacks=[lgb.early_stopping(stopping_rounds=20, verbose=False)]
        )
        
        # Save model
        self.save_model()
        
        return self
    
    def predict(self, features):
        """
        Make prediction with Asian market influence
        
        Args:
            features: Dict of feature values
            
        Returns:
            Dict with prediction results
        """
        # Validate features
        features = self._validate_features(features)
        
        # Extract Asian features
        asian_influence_score = features.get('asian_influence_score', 0)
        asian_avg_change = features.get('asian_avg_change', 0)
        
        # Base model prediction
        if self.base_model is None:
            # Fallback: simple rule-based prediction
            base_score = self._fallback_prediction(features)
        else:
            # ML model prediction
            X = self._dict_to_array(features)
            X_scaled = self.scaler.transform(X.reshape(1, -1))
            base_prob = self.base_model.predict_proba(X_scaled)[0, 1]
            base_score = (base_prob - 0.5) * 2  # Convert to -1..+1
        
        # CRITICAL: Check for rebound patterns and boost signal
        rebound_boost = self._detect_rebound_pattern(features)
        if rebound_boost > 0.3:
            # Strong rebound detected - apply boost to base score
            base_score = base_score + (rebound_boost * 0.4)
            base_score = np.clip(base_score, -1, 1)
        
        # Calculate Asian impact
        asian_impact_pct = min(
            abs(asian_influence_score) * self.asian_influence_max,
            self.asian_influence_max
        )
        
        # Ensemble scoring
        final_score = (
            (1 - asian_impact_pct) * base_score +
            asian_impact_pct * np.sign(asian_influence_score) * abs(asian_influence_score)
        )
        
        # Convert to probability
        probability = self._sigmoid(final_score * 5)
        
        # Determine label
        label = 'BULLISH' if final_score > 0 else 'BEARISH'
        
        # Calculate expected move
        expected_pct_move = self._calculate_expected_move(
            final_score,
            features.get('atr_14', 0),
            features.get('bb_width', 0)
        )
        
        # Detect correction warning
        correction_warning = self._detect_correction_warning(features)
        
        # Generate top reasons
        top_reasons = self._generate_reasons(
            features, final_score, asian_influence_score, base_score
        )
        
        return {
            'label': label,
            'probability': float(probability),
            'expected_pct_move': float(expected_pct_move),
            'asian_influence_score': float(asian_influence_score),
            'asian_impact_percent': float(asian_impact_pct),
            'base_score': float(base_score),
            'final_score': float(final_score),
            'correction_warning': correction_warning,
            'top_reasons': top_reasons,
            'model_version': 'quick_model_v2',
            'timestamp': datetime.now().isoformat()
        }
    
    def _validate_features(self, features):
        """Validate and fill missing features"""
        validated = {}
        
        # Fill base features
        for feature in self.base_features:
            validated[feature] = features.get(feature, 0.0)
        
        # Fill Asian features
        for feature in self.asian_features:
            validated[feature] = features.get(feature, 0.0)
        
        return validated
    
    def _dict_to_array(self, features):
        """Convert feature dict to numpy array"""
        return np.array([features.get(f, 0.0) for f in self.all_features])
    
    def _sigmoid(self, x):
        """Sigmoid activation"""
        return 1 / (1 + np.exp(-np.clip(x, -500, 500)))
    
    def _fallback_prediction(self, features):
        """Simple rule-based fallback when model not available"""
        score = 0
        
        # CRITICAL: Detect rebound pattern (most important)
        rebound_score = self._detect_rebound_pattern(features)
        if rebound_score != 0:
            # Rebound patterns get highest priority
            score += rebound_score * 0.6
        
        # News sentiment (increased weight for rebounds)
        news_sentiment = features.get('news_sentiment_score', 0)
        if abs(news_sentiment) > 0:
            # If news is positive and we have rebound, amplify it
            if rebound_score > 0 and news_sentiment > 0:
                score += np.clip(news_sentiment * 0.5, 0, 0.5)  # Increased from 0.2
            else:
                score += np.clip(news_sentiment * 0.3, -0.3, 0.3)  # Increased from 0.2
        
        # RSI contribution (adjusted for rebounds)
        rsi = features.get('rsi_14', 50)
        if rsi > 70:
            # Don't penalize as much if there's strong positive news/rebound
            if news_sentiment > 0.3 or rebound_score > 0.3:
                score -= 0.1  # Reduced from 0.3
            else:
                score -= 0.25
        elif rsi < 30:
            score += 0.3
        
        # MACD contribution (reduced weight)
        macd_hist = features.get('macd_hist', 0)
        score += np.clip(macd_hist / 10, -0.2, 0.2)  # Reduced from 0.3
        
        # Price momentum (short-term prioritized)
        price_change_1d = features.get('price_change_1d', 0)
        price_change_3d = features.get('price_change_3d', 0)
        
        # Recent momentum is more important
        score += np.clip(price_change_1d / 50, -0.2, 0.2)
        score += np.clip(price_change_3d / 150, -0.15, 0.15)
        
        return np.clip(score, -1, 1)
    
    def _detect_rebound_pattern(self, features):
        """
        Detect rebound patterns - when a stock has declined but is now recovering
        with positive news sentiment
        
        Returns: score from -1 to +1 indicating rebound strength
        """
        score = 0
        
        price_change_1d = features.get('price_change_1d', 0)
        price_change_3d = features.get('price_change_3d', 0)
        price_change_7d = features.get('price_change_7d', 0)
        news_sentiment = features.get('news_sentiment_score', 0)
        rsi = features.get('rsi_14', 50)
        
        # Pattern 1: Strong positive news after recent decline (PRIMARY REBOUND SIGNAL)
        if news_sentiment > 0.3 and price_change_7d < 0:
            # Stock was down but has positive news - this alone is a strong rebound signal
            strength = min(abs(price_change_7d) / 15, 0.6)  # More decline = stronger rebound potential
            score += 0.5 + strength  # Increased base score
            
            # Additional boost if already showing recovery
            if price_change_1d > 0 or price_change_3d > 0:
                score += 0.2  # Extra boost for confirmed recovery
        
        # Pattern 2: V-shape recovery (was down 7d but up recent days)
        if price_change_7d < -3 and price_change_3d > 1 and price_change_1d > 0:
            # Clear V-shape
            recovery_strength = (price_change_1d + price_change_3d) / 2
            score += np.clip(recovery_strength / 10, 0, 0.4)
        
        # Pattern 3: Oversold RSI with positive momentum and news
        if rsi < 40 and news_sentiment > 0.2:
            if price_change_1d > 0.5:
                # Oversold bouncing with positive news
                score += 0.3
        
        # Pattern 4: Significant positive 1-day change with positive news (key rebound signal)
        if price_change_1d > 2 and news_sentiment > 0.2:
            # Strong bounce with positive news - likely rebound
            score += 0.5
        
        # Pattern 5: Recovery from oversold with volume
        volume_ratio = features.get('volume_sma_ratio', 1.0)
        if rsi < 35 and volume_ratio > 1.3 and price_change_1d > 0:
            # Oversold, high volume, positive day - strong rebound signal
            score += 0.35
        
        return np.clip(score, -1, 1)
    
    def _calculate_expected_move(self, final_score, atr, bb_width):
        """
        Calculate expected percentage move
        
        Args:
            final_score: Ensemble score (-1 to +1)
            atr: Average True Range
            bb_width: Bollinger Band width
        """
        # Base move from score magnitude
        base_magnitude = abs(final_score)
        
        # Calibration curve
        calibration = {
            0.1: 0.5,
            0.3: 1.2,
            0.5: 2.0,
            0.7: 3.0,
            1.0: 4.0
        }
        
        keys = list(calibration.keys())
        values = list(calibration.values())
        base_move = np.interp(base_magnitude, keys, values)
        
        # Volatility adjustment
        volatility_factor = 1 + (atr / 10.0) if atr > 0 else 1.0
        expected_magnitude = base_move * volatility_factor
        
        # Apply direction
        expected_move = expected_magnitude * np.sign(final_score)
        
        return round(expected_move, 2)
    
    def _detect_correction_warning(self, features):
        """
        Detect potential correction scenarios
        
        Returns:
            Dict with warning details or {'warning': False}
        """
        warnings = []
        
        # Thresholds from environment
        pct_threshold = float(os.getenv('CORRECTION_PCT_7D', '10'))
        rsi_threshold = float(os.getenv('CORRECTION_RSI_THRESHOLD', '80'))
        bb_zscore_threshold = float(os.getenv('CORRECTION_BB_ZSCORE', '2.0'))
        
        # Check 1: Price surge + overbought RSI
        price_change_7d = features.get('price_change_7d', 0)
        rsi = features.get('rsi_14', 50)
        
        if price_change_7d > pct_threshold and rsi > rsi_threshold:
            severity = 'HIGH' if rsi > 85 else 'MEDIUM'
            confidence = min(0.9, 0.5 + (rsi - 70) / 50)
            warnings.append({
                'reason': f'Price up {price_change_7d:.1f}% in 7 days + RSI {rsi:.0f}',
                'severity': severity,
                'confidence': confidence
            })
        
        # Check 2: Bollinger z-score
        bb_pct = features.get('bb_pct', 0)
        if bb_pct > 1.0:
            bb_width = features.get('bb_width', 1)
            bb_middle = features.get('bb_middle', 1)
            close = features.get('close', 1)
            
            if bb_width > 0:
                z_score = (close - bb_middle) / (bb_width / 2)
                if z_score > bb_zscore_threshold:
                    severity = 'HIGH' if z_score > 2.5 else 'MEDIUM'
                    confidence = min(0.85, z_score / 3.0)
                    warnings.append({
                        'reason': f'Bollinger z-score {z_score:.1f} (overbought)',
                        'severity': severity,
                        'confidence': confidence
                    })
        
        # Check 3: Volume spike with price spike
        volume_ratio = features.get('volume_sma_ratio', 1.0)
        if volume_ratio > 2.0 and price_change_7d > pct_threshold:
            warnings.append({
                'reason': f'Unusual volume ({volume_ratio:.1f}x avg) with price spike',
                'severity': 'MEDIUM',
                'confidence': 0.65
            })
        
        if not warnings:
            return {'warning': False}
        
        # Aggregate severity
        severities = [w['severity'] for w in warnings]
        max_severity = 'HIGH' if 'HIGH' in severities else 'MEDIUM' if 'MEDIUM' in severities else 'LOW'
        
        return {
            'warning': True,
            'severity': max_severity,
            'reasons': [w['reason'] for w in warnings],
            'confidence': max([w['confidence'] for w in warnings]),
            'details': {
                'price_change_7d': price_change_7d,
                'rsi': rsi,
                'volume_ratio': volume_ratio
            }
        }
    
    def _generate_reasons(self, features, final_score, asian_score, base_score):
        """Generate human-readable reasons for prediction"""
        reasons = []
        
        # PRIORITY: Detect and report rebound patterns first
        price_change_1d = features.get('price_change_1d', 0)
        price_change_3d = features.get('price_change_3d', 0)
        price_change_7d = features.get('price_change_7d', 0)
        news_sentiment = features.get('news_sentiment_score', 0)
        rsi = features.get('rsi_14', 50)
        
        # Check for rebound patterns
        is_rebounding = False
        
        # Pattern 1: Strong positive news after decline (MOST IMPORTANT - even without price recovery yet)
        if news_sentiment > 0.3 and price_change_7d < 0:
            if price_change_1d > 0 or price_change_3d > 0:
                reasons.append(f"🚀 Strong rebound confirmed: Positive news ({news_sentiment:+.2f}) + recovery from {price_change_7d:.1f}% decline")
            else:
                reasons.append(f"🚀 Major rebound setup: Bullish news ({news_sentiment:+.2f}) after {price_change_7d:.1f}% decline - recovery likely")
            is_rebounding = True
        elif price_change_7d < -3 and price_change_3d > 1 and price_change_1d > 0:
            reasons.append(f"📈 V-shaped recovery pattern: Down {price_change_7d:.1f}% (7d) but up {price_change_3d:.1f}% (3d)")
            is_rebounding = True
        elif price_change_1d > 2 and news_sentiment > 0.2:
            reasons.append(f"⚡ Significant bounce: Up {price_change_1d:.1f}% today with positive news")
            is_rebounding = True
        
        # News sentiment (prioritize if significant)
        if abs(news_sentiment) > 0.3 and not is_rebounding:
            direction = 'bullish' if news_sentiment > 0 else 'bearish'
            strength = 'strongly' if abs(news_sentiment) > 0.5 else 'moderately'
            reasons.append(f"News sentiment {strength} {direction} ({news_sentiment:+.2f})")
        
        # Asian market influence
        if abs(asian_score) > 0.3:
            direction = 'positive' if asian_score > 0 else 'negative'
            strength = 'strong' if abs(asian_score) > 0.6 else 'moderate'
            reasons.append(
                f"Asian markets show {strength} {direction} influence ({asian_score:+.2f})"
            )
        
        # RSI (context-aware based on rebound)
        if rsi > 70:
            if is_rebounding:
                reasons.append(f"RSI elevated at {rsi:.0f}, but supported by strong positive catalysts")
            else:
                reasons.append(f"RSI overbought at {rsi:.0f} (caution advised)")
        elif rsi < 30:
            reasons.append(f"RSI oversold at {rsi:.0f} (strong bounce potential)")
        elif rsi < 40:
            reasons.append(f"RSI at {rsi:.0f} (recovery zone, watch for bounce)")
        
        # Price trend
        if abs(price_change_7d) > 5 and not is_rebounding:
            direction = 'upward' if price_change_7d > 0 else 'downward'
            reasons.append(f"Strong {direction} trend: {price_change_7d:+.1f}% over 7 days")
        
        # MACD
        macd_hist = features.get('macd_hist', 0)
        if abs(macd_hist) > 0.5:
            direction = 'bullish' if macd_hist > 0 else 'bearish'
            reasons.append(f"MACD showing {direction} momentum")
        
        # Volume
        volume_spike = features.get('volume_spike', False)
        volume_ratio = features.get('volume_sma_ratio', 1.0)
        if volume_spike or volume_ratio > 1.5:
            if is_rebounding:
                reasons.append(f"Strong volume surge ({volume_ratio:.1f}x) confirms rebound")
            else:
                reasons.append(f"Elevated volume activity ({volume_ratio:.1f}x average)")
        
        # Fear & Greed
        fear_greed = features.get('fear_greed_index', 50)
        if fear_greed > 75:
            reasons.append(f"Market greed at {fear_greed} (caution on entries)")
        elif fear_greed < 25:
            reasons.append(f"Market fear at {fear_greed} (contrarian opportunity)")
        
        # Limit to top 6 reasons (increased from 5 to accommodate rebound info)
        return reasons[:6] if reasons else ["Technical analysis suggests trend continuation"]
    
    def save_model(self):
        """Save model to disk"""
        if self.base_model is None:
            return
        
        model_data = {
            'base_model': self.base_model,
            'scaler': self.scaler,
            'feature_names': self.all_features,
            'version': '2.0',
            'timestamp': datetime.now().isoformat()
        }
        
        with open(self.model_path, 'wb') as f:
            pickle.dump(model_data, f)
        
        print(f"Model saved to {self.model_path}")
    
    def load_model(self):
        """Load model from disk"""
        try:
            with open(self.model_path, 'rb') as f:
                model_data = pickle.load(f)
            
            self.base_model = model_data['base_model']
            self.scaler = model_data['scaler']
            
            print(f"Model loaded from {self.model_path}")
            return True
        except Exception as e:
            print(f"Failed to load model: {e}", file=sys.stderr)
            return False


def main():
    """CLI interface"""
    parser = argparse.ArgumentParser(description='Quick Model V2 - Stock Prediction')
    parser.add_argument('action', choices=['predict', 'train'], help='Action to perform')
    parser.add_argument('--symbol', type=str, help='Stock symbol')
    parser.add_argument('--features', type=str, help='Features as JSON string')
    parser.add_argument('--model-path', type=str, help='Path to model file')
    
    args = parser.parse_args()
    
    # Initialize model
    model = QuickModelV2(model_path=args.model_path)
    
    if args.action == 'predict':
        if not args.features:
            print(json.dumps({'error': 'Features required for prediction'}))
            sys.exit(1)
        
        try:
            features = json.loads(args.features)
            result = model.predict(features)
            print(json.dumps(result, indent=2))
        except json.JSONDecodeError as e:
            print(json.dumps({'error': f'Invalid JSON: {e}'}))
            sys.exit(1)
        except Exception as e:
            print(json.dumps({'error': f'Prediction failed: {e}'}))
            sys.exit(1)
    
    elif args.action == 'train':
        print(json.dumps({
            'error': 'Training requires data. Use Python API directly for training.',
            'example': 'model.train(X_train, y_train)'
        }))
        sys.exit(1)


if __name__ == '__main__':
    main()
