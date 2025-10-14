#!/usr/bin/env python3
"""
Quick Model V4 - Advanced Stock Prediction with European and Asian Markets
===========================================================================

Advanced prediction model with local US factors (50%), European market 
influence (30%), and Asian market influence (20%). Includes comprehensive correction 
pattern detection, rebound analysis, and mean reversion signals.

Features:
- Binary classification: BULLISH or BEARISH with confidence
- Local US factors (50% weight)
- European market influence integration (30% weight)
- Asian market influence integration (20% weight)
- Advanced correction patterns detection (8 patterns)
- Mean reversion analysis
- Overbought/oversold detection
- Rebound pattern recognition (5 patterns)
- Expected % move calculation
- Correction alerts with severity levels
- Multi-signal ensemble approach

Market Weight Distribution:
- Local US: 50% (sentiment, technicals, momentum)
- European Markets: 30% (FTSE, DAX, CAC, STOXX, IBEX)
- Asian Markets: 20% (Nikkei, Hang Seng, Shanghai, Nifty)

Author: Stock Prediction System
Version: 4.0
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


class QuickModelV4:
    """
    Quick Model V4 - Advanced prediction model with Local US (50%), European (30%), and Asian (20%) markets
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
        
        # Configuration - Market influence weights
        self.local_weight = 0.50     # 50% weight
        self.european_weight = 0.30  # 30% weight
        self.asian_weight = 0.20     # 20% weight
        
        # Feature names for validation
        self.base_features = [
            'close', 'high', 'low', 'volume', 'price_change_1d', 'price_change_3d', 'price_change_7d',
            'ema_12', 'ema_26', 'macd', 'macd_signal', 'macd_hist',
            'rsi_14', 'rsi_7', 'atr_14', 'obv',
            'bb_upper', 'bb_middle', 'bb_lower', 'bb_width', 'bb_pct',
            'distance_to_support', 'distance_to_resistance',
            'volume_sma_ratio', 'volume_spike',
            'news_sentiment_score', 'fear_greed_index',
            'today_news_sentiment', 'today_news_count'  # Added for upgraded local US factors
        ]
        
        self.asian_features = [
            'nikkei_change_pct', 'hang_seng_change_pct',
            'shanghai_change_pct', 'nifty_change_pct',
            'asian_avg_change', 'asian_influence_score'
        ]
        
        self.european_features = [
            'ftse_change_pct', 'dax_change_pct', 'cac_change_pct',
            'stoxx_change_pct', 'ibex_change_pct',
            'european_avg_change', 'european_influence_score'
        ]
        
        self.all_features = self.base_features + self.asian_features + self.european_features
        
        # Load model if exists
        if os.path.exists(self.model_path):
            self.load_model()
    
    def _get_default_model_path(self):
        """Get default model save path"""
        base_dir = Path(__file__).parent.parent
        models_dir = base_dir / 'saved_models'
        models_dir.mkdir(exist_ok=True)
        return str(models_dir / 'quick_model_v4.pkl')
    
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
        Make prediction with local US (50%), European (30%), and Asian (20%) influence
        
        Args:
            features: Dict of feature values
            
        Returns:
            Dict with prediction results
        """
        # Validate features
        features = self._validate_features(features)
        
        # Extract market features
        european_influence_score = features.get('european_influence_score', 0)
        european_avg_change = features.get('european_avg_change', 0)
        asian_influence_score = features.get('asian_influence_score', 0)
        asian_avg_change = features.get('asian_avg_change', 0)
        
        # CRITICAL: Use pre-calculated local_us_influence_score from backend
        # This score already combines news sentiment, technicals, momentum, and Fear & Greed
        # It's normalized like Asian/European scores for fair comparison
        local_us_influence_score = features.get('local_us_influence_score', None)
        
        if local_us_influence_score is not None:
            # Use the pre-calculated score (PREFERRED method)
            local_score = local_us_influence_score
        elif self.base_model is None:
            # Fallback: simple rule-based prediction
            local_score = self._fallback_prediction(features)
        else:
            # ML model prediction
            X = self._dict_to_array(features)
            X_scaled = self.scaler.transform(X.reshape(1, -1))
            base_prob = self.base_model.predict_proba(X_scaled)[0, 1]
            local_score = (base_prob - 0.5) * 2  # Convert to -1..+1
        
        # CRITICAL: Check for rebound patterns and boost signal
        rebound_boost = self._detect_rebound_pattern(features)
        if rebound_boost > 0.3:
            # Strong rebound detected - apply stronger boost to local score
            local_score = local_score + (rebound_boost * 0.35)
            local_score = np.clip(local_score, -1, 1)
        
        # Calculate European market contribution (30% weight)
        european_score = np.sign(european_influence_score) * abs(european_influence_score)
        european_contribution = european_score * self.european_weight
        
        # Calculate Asian market contribution (20% weight)
        asian_score = np.sign(asian_influence_score) * abs(asian_influence_score)
        asian_contribution = asian_score * self.asian_weight
        
        # Calculate local contribution (50% weight)
        local_contribution = local_score * self.local_weight
        
        # Ensemble scoring with weighted contributions
        final_score = (
            european_contribution +
            asian_contribution +
            local_contribution
        )
        
        # ADVANCED: Detect correction warning BEFORE finalizing prediction
        correction_warning = self._detect_correction_warning(features)
        
        # CRITICAL: Adjust final score based on correction warnings
        correction_adjusted_score = final_score
        if correction_warning['warning']:
            correction_score = correction_warning['correction_score']
            correction_direction = correction_warning['direction']
            
            # Apply correction adjustment
            if correction_direction == 'DOWN' and final_score > 0:
                # Downward correction expected but model says bullish
                adjustment_factor = correction_score / 100  # 0 to 1
                correction_adjusted_score = final_score * (1 - (adjustment_factor * 0.5))
                
            elif correction_direction == 'UP' and final_score < 0:
                # Upward correction expected but model says bearish
                if correction_score > 60:
                    # Strong oversold - flip to bullish
                    correction_adjusted_score = abs(final_score) * 0.7
                else:
                    # Moderate oversold - reduce bearish
                    adjustment_factor = correction_score / 100
                    correction_adjusted_score = final_score * (1 - (adjustment_factor * 0.4))
        
        # Use correction-adjusted score for final prediction
        final_score_with_correction = correction_adjusted_score
        
        # Convert to probability
        probability = self._sigmoid(final_score_with_correction * 5)
        
        # Determine label
        label = 'BULLISH' if final_score_with_correction > 0 else 'BEARISH'
        
        # CRITICAL: Get volatility multiplier for sector-based predictions
        volatility_multiplier = float(features.get('volatility_multiplier', 1.0))
        category = features.get('category', 'Unknown')
        
        # Calculate expected move with volatility multiplier
        expected_pct_move = self._calculate_expected_move(
            final_score_with_correction,
            features.get('atr_14', 0),
            features.get('bb_width', 0),
            volatility_multiplier
        )
        
        # Generate top reasons (include market influences)
        top_reasons = self._generate_reasons(
            features, final_score_with_correction, 
            european_influence_score, asian_influence_score, 
            local_score, correction_warning
        )
        
        return {
            'label': label,
            'probability': float(probability),
            'expected_pct_move': float(expected_pct_move),
            'european_influence_score': float(european_influence_score),
            'european_impact_percent': float(self.european_weight),
            'european_contribution': float(european_contribution),
            'asian_influence_score': float(asian_influence_score),
            'asian_impact_percent': float(self.asian_weight),
            'asian_contribution': float(asian_contribution),
            'local_score': float(local_score),
            'local_impact_percent': float(self.local_weight),
            'local_contribution': float(local_contribution),
            'base_score': float(local_score),
            'final_score': float(final_score_with_correction),
            'correction_warning': correction_warning,
            'correction_adjusted': bool(correction_adjusted_score != final_score),
            'top_reasons': top_reasons,
            'model_version': 'quick_model_v4',
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
        
        # Fill European features
        for feature in self.european_features:
            validated[feature] = features.get(feature, 0.0)
        
        # CRITICAL: Preserve non-feature metadata like volatility_multiplier and category
        # These are used for sector-aware predictions but are NOT training features
        if 'volatility_multiplier' in features:
            validated['volatility_multiplier'] = features['volatility_multiplier']
        if 'category' in features:
            validated['category'] = features['category']
        if 'symbol' in features:
            validated['symbol'] = features['symbol']
        
        return validated
    
    def _dict_to_array(self, features):
        """Convert feature dict to numpy array"""
        return np.array([features.get(f, 0.0) for f in self.all_features])
    
    def _sigmoid(self, x):
        """Sigmoid activation"""
        return 1 / (1 + np.exp(-np.clip(x, -500, 500)))
    
    def _fallback_prediction(self, features):
        """
        UPGRADED: Rule-based fallback with price action priority
        
        Matches PHP weight distribution:
        - Price momentum: 30%
        - Relative strength: 15%
        - Intraday position: 10%
        - Volume: 10%
        - Today's news: 20%
        - Technicals: 10%
        - Overall news: 5%
        """
        score = 0
        
        # ================================================================
        # 1. PRICE MOMENTUM (30% weight) - MOST IMPORTANT!
        # ================================================================
        price_change_1d = features.get('price_change_1d', 0)
        price_change_3d = features.get('price_change_3d', 0)
        
        # Reduced dampening for stronger signals
        momentum_score = np.tanh(price_change_1d / 3) * 0.20  # 1-day: 20%
        momentum_score += np.tanh(price_change_3d / 8) * 0.10  # 3-day: 10%
        
        # BOOST: Strong moves (>1%) get extra amplification
        if abs(price_change_1d) > 1.0:
            boost_factor = min(abs(price_change_1d) / 5, 0.2)
            momentum_score += boost_factor if price_change_1d > 0 else -boost_factor
        
        score += momentum_score
        
        # ================================================================
        # 2. RELATIVE STRENGTH vs market (15% weight)
        # ================================================================
        # Simplified: Just use absolute momentum since we may not have SPY data
        # In production, would compare to SPY change
        relative_strength = price_change_1d * 0.15  # Approximate
        score += np.clip(relative_strength, -0.15, 0.15)
        
        # ================================================================
        # 3. INTRADAY POSITION (10% weight)
        # ================================================================
        close = features.get('close', 100)
        high = features.get('high', close)
        low = features.get('low', close)
        
        if high > low:
            intraday_position = (close - low) / (high - low)
            intraday_score = (intraday_position - 0.5) * 0.6
            score += intraday_score * 0.10
        
        # ================================================================
        # 4. VOLUME CONFIRMATION (10% weight)
        # ================================================================
        volume_ratio = features.get('volume_sma_ratio', 1.0)
        
        if volume_ratio > 1.2:
            volume_confirmation = min((volume_ratio - 1.0) / 2, 0.5)
            volume_score = volume_confirmation if price_change_1d > 0 else -volume_confirmation
            score += volume_score * 0.10
        elif volume_ratio < 0.8:
            score *= 0.9  # Reduce all signals
        
        # ================================================================
        # 5. TODAY'S NEWS SENTIMENT (20% weight)
        # ================================================================
        today_news_sentiment = features.get('today_news_sentiment', 0)
        today_news_count = features.get('today_news_count', 0)
        
        today_news_score = today_news_sentiment * 0.20
        if today_news_count >= 5:
            today_news_score += today_news_sentiment * 0.05
        
        score += today_news_score
        
        # ================================================================
        # 6. TECHNICAL INDICATORS (10% weight)
        # ================================================================
        rsi = features.get('rsi_14', 50)
        macd_hist = features.get('macd_hist', 0)
        
        # RSI: 5% weight
        rsi_score = 0
        if rsi > 70:
            rsi_score = -0.3 * ((rsi - 70) / 30)
        elif rsi < 30:
            rsi_score = 0.3 * ((30 - rsi) / 30)
        else:
            rsi_score = (50 - rsi) / 200
        
        score += rsi_score * 0.05
        
        # MACD: 5% weight
        score += np.clip(macd_hist / 10, -0.05, 0.05)
        
        # ================================================================
        # 7. OVERALL NEWS SENTIMENT (5% weight)
        # ================================================================
        news_sentiment = features.get('news_sentiment_score', 0)
        score += news_sentiment * 0.05
        
        # ================================================================
        # BONUS: Rebound patterns (can override)
        # ================================================================
        rebound_score = self._detect_rebound_pattern(features)
        if rebound_score > 0.5:
            # Strong rebound detected - boost signal
            score += rebound_score * 0.2
        
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
        
        # Pattern 1: Strong positive news after recent decline
        if news_sentiment > 0.3 and price_change_7d < 0:
            strength = min(abs(price_change_7d) / 15, 0.4)
            score += 0.4 + strength
            
            if price_change_1d > 0 or price_change_3d > 0:
                score += 0.2
        
        # Pattern 2: V-shape recovery
        if price_change_7d < -3 and price_change_3d > 1 and price_change_1d > 0:
            recovery_strength = (price_change_1d + price_change_3d) / 2
            score += np.clip(recovery_strength / 8, 0, 0.5)
        
        # Pattern 3: Oversold RSI with positive momentum
        if rsi < 40 and news_sentiment > 0.2:
            if price_change_1d > 0.5:
                score += 0.3
        
        # Pattern 4: Significant positive bounce with news
        if price_change_1d > 2 and news_sentiment > 0.2:
            score += 0.5
        
        # Pattern 5: Recovery from oversold with volume
        volume_ratio = features.get('volume_sma_ratio', 1.0)
        if rsi < 35 and volume_ratio > 1.3 and price_change_1d > 0:
            score += 0.35
        
        return np.clip(score, -1, 1)
    
    def _detect_correction_warning(self, features):
        """
        Detect potential market correction scenarios
        
        Returns: Dict with correction warning info
        """
        warning = False
        correction_score = 0
        direction = 'NEUTRAL'
        patterns = []
        
        rsi_14 = features.get('rsi_14', 50)
        rsi_7 = features.get('rsi_7', 50)
        price_change_7d = features.get('price_change_7d', 0)
        price_change_3d = features.get('price_change_3d', 0)
        bb_pct = features.get('bb_pct', 0.5)
        volume_spike = features.get('volume_spike', False)
        
        # Pattern 1: Extreme overbought (correction DOWN expected)
        if rsi_14 > 75:
            warning = True
            direction = 'DOWN'
            severity = min((rsi_14 - 70) * 10, 40)
            correction_score += severity
            patterns.append(f'Overbought RSI-14: {rsi_14:.1f}')
        
        # Pattern 2: Extreme oversold (correction UP expected)
        if rsi_14 < 25:
            warning = True
            direction = 'UP'
            severity = min((30 - rsi_14) * 10, 40)
            correction_score += severity
            patterns.append(f'Oversold RSI-14: {rsi_14:.1f}')
        
        # Pattern 3: Parabolic rise
        if price_change_7d > 10 and price_change_3d > 5 and rsi_14 > 70:
            warning = True
            direction = 'DOWN'
            correction_score += 30
            patterns.append(f'Parabolic rise: +{price_change_7d:.1f}% (7d)')
        
        # Pattern 4: Extended decline with oversold
        if price_change_7d < -10 and rsi_14 < 35:
            warning = True
            direction = 'UP'
            correction_score += 25
            patterns.append(f'Extended decline: {price_change_7d:.1f}% (7d)')
        
        # Pattern 5: Upper Bollinger Band breakout
        if bb_pct > 0.95 and rsi_14 > 65:
            warning = True
            direction = 'DOWN'
            correction_score += 20
            patterns.append('Upper BB breakout + overbought')
        
        # Pattern 6: Lower Bollinger Band breakdown
        if bb_pct < 0.05 and rsi_14 < 40:
            warning = True
            direction = 'UP'
            correction_score += 20
            patterns.append('Lower BB breakdown + oversold')
        
        # Pattern 7: Volume exhaustion at highs
        if volume_spike and rsi_14 > 70 and price_change_1d > 3:
            warning = True
            direction = 'DOWN'
            correction_score += 25
            patterns.append('Volume exhaustion at highs')
        
        correction_score = min(correction_score, 100)
        
        return {
            'warning': warning,
            'correction_score': correction_score,
            'direction': direction,
            'patterns': patterns,
            'severity': 'HIGH' if correction_score > 60 else 'MODERATE' if correction_score > 30 else 'LOW'
        }
    
    def _calculate_expected_move(self, final_score, atr, bb_width, volatility_multiplier=1.0):
        """
        Calculate expected percentage move - SECTOR-AWARE with volatility multipliers
        
        Args:
            final_score: Ensemble score (-1 to +1)
            atr: Average True Range
            bb_width: Bollinger Band width
            volatility_multiplier: Stock category multiplier (1.0=normal, 2.0=tech giants)
        """
        base_magnitude = abs(final_score)
        
        # Base ranges for predictions - INCREASED for tech stocks
        # With 1.5-2.5x multipliers, even weak signals should produce 2%+ moves
        if base_magnitude > 0.8:
            # Very strong signal - expect major moves
            expected_range = (5.0, 12.0)
        elif base_magnitude > 0.6:
            # Strong signal - expect significant moves
            expected_range = (3.5, 8.0)
        elif base_magnitude > 0.4:
            # Moderate-strong signal
            expected_range = (2.5, 5.0)
        elif base_magnitude > 0.2:
            # Moderate signal - CRITICAL for most predictions
            # Base 1.5% * 1.8x multiplier = 2.7% ✅
            expected_range = (1.5, 3.5)
        else:
            # Weak signal - even here, tech should be 2%+
            # Base 1.2% * 1.8x = 2.16% ✅
            expected_range = (1.2, 2.0)
        
        # Interpolate base move
        base_move = expected_range[0] + (expected_range[1] - expected_range[0]) * base_magnitude
        
        # CRITICAL: Apply volatility multiplier for stock category
        # Technology stocks (NVDA: 2.0x, AVGO: 1.8x, TSLA: 2.5x) get amplified predictions
        # Finance/Healthcare stocks (1.0-1.2x) get standard predictions
        expected_move = base_move * volatility_multiplier
        
        # ENFORCE minimum 2% for tech mega-caps (multiplier >= 1.5)
        # This ensures tech giants always have realistic volatile predictions
        if volatility_multiplier >= 1.5 and abs(expected_move) < 2.0:
            expected_move = 2.0  # Minimum 2% magnitude
        
        # Apply direction based on final_score sign
        final_move = expected_move if final_score >= 0 else -expected_move
        return final_move
    
    def _generate_reasons(self, features, final_score, european_influence, 
                         asian_influence, local_score, correction_warning):
        """Generate human-readable reasons for the prediction"""
        reasons = []
        
        # Market influences with weights
        if abs(european_influence) > 0.1:
            sentiment = 'bullish' if european_influence > 0 else 'bearish'
            reasons.append(
                f'European markets show {sentiment} momentum ({european_influence:+.2f}) '
                f'with 30% weight in model'
            )
        
        if abs(asian_influence) > 0.1:
            sentiment = 'bullish' if asian_influence > 0 else 'bearish'
            reasons.append(
                f'Asian markets indicate {sentiment} direction ({asian_influence:+.2f}) '
                f'with 20% weight in model'
            )
        
        # Local factors
        news_sentiment = features.get('news_sentiment_score', 0)
        if abs(news_sentiment) > 0.2:
            sentiment = 'positive' if news_sentiment > 0 else 'negative'
            reasons.append(f'News sentiment is {sentiment} ({news_sentiment:+.2f})')
        
        # Technical indicators
        rsi_14 = features.get('rsi_14', 50)
        if rsi_14 > 70:
            reasons.append(f'RSI shows overbought conditions ({rsi_14:.1f})')
        elif rsi_14 < 30:
            reasons.append(f'RSI indicates oversold bounce potential ({rsi_14:.1f})')
        
        # Price momentum
        price_change_1d = features.get('price_change_1d', 0)
        if abs(price_change_1d) > 1:
            direction = 'up' if price_change_1d > 0 else 'down'
            reasons.append(f'Strong 1-day momentum {direction} ({price_change_1d:+.1f}%)')
        
        # Correction warning
        if correction_warning['warning']:
            reasons.append(
                f'Correction warning: {correction_warning["direction"]} '
                f'({correction_warning["severity"]})'
            )
        
        # Final ensemble score
        direction = 'bullish' if final_score > 0 else 'bearish'
        reasons.append(f'Overall ensemble signal is {direction} ({final_score:+.2f})')
        
        return reasons[:6]  # Top 6 reasons
    
    def save_model(self):
        """Save model to disk"""
        if self.base_model is not None:
            with open(self.model_path, 'wb') as f:
                pickle.dump({
                    'model': self.base_model,
                    'scaler': self.scaler,
                    'features': self.all_features,
                    'version': 'v4'
                }, f)
    
    def load_model(self):
        """Load model from disk"""
        try:
            with open(self.model_path, 'rb') as f:
                data = pickle.load(f)
                self.base_model = data['model']
                self.scaler = data['scaler']
        except Exception as e:
            print(f"Warning: Could not load model from {self.model_path}: {e}", file=sys.stderr)
            self.base_model = None


def main():
    """CLI interface for the model"""
    parser = argparse.ArgumentParser(description='Quick Model V4 - Stock Prediction')
    parser.add_argument('action', choices=['predict', 'train'], help='Action to perform')
    parser.add_argument('--features', type=str, help='JSON string of features for prediction')
    parser.add_argument('--model-path', type=str, help='Path to model file')
    
    args = parser.parse_args()
    
    model = QuickModelV4(model_path=args.model_path)
    
    if args.action == 'predict':
        if not args.features:
            print(json.dumps({'error': 'Features required for prediction'}))
            sys.exit(1)
        
        try:
            features = json.loads(args.features)
            result = model.predict(features)
            print(json.dumps(result, indent=2))
        except json.JSONDecodeError as e:
            print(json.dumps({'error': f'Invalid JSON: {str(e)}'}))
            sys.exit(1)
        except Exception as e:
            print(json.dumps({'error': f'Prediction failed: {str(e)}'}))
            sys.exit(1)
    
    elif args.action == 'train':
        print(json.dumps({'error': 'Training not yet implemented in CLI'}))
        sys.exit(1)


if __name__ == '__main__':
    main()
