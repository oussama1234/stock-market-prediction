# Quick Model V6 Configuration Summary

## Overview
All backend services and API endpoints have been configured to use **quick_model_v6.py** for stock predictions. This model includes advanced multi-factor analysis with improved sentiment analysis, technical indicators, and component scoring.

## Updated Files

### 1. **Configuration Files**
- **`config/prediction.php`** (Line 76)
  - Updated model script path from `python/models/quick_model_v4.py` to `python/models/quick_model_v6.py`
  - Direct configuration reference for all services

### 2. **Core Services**
- **`app/Services/PredictionService.php`** (Lines 250-267, 324)
  - Updated docstring from v2/v4 to v6
  - Changed `$pythonPath` from `quick_model_v4.py` to `quick_model_v6.py`
  - Updated model version default to `quick_model_v6`
  - Enhanced documentation about multi-factor prediction capabilities

### 3. **API Controllers**
- **`app/Http/Controllers/Api/PredictionController.php`** (Lines 41-43, 115)
  - Updated CRITICAL comment to reference v6
  - Changed default model version to `quick_model_v6`
  - Enhanced documentation about sentiment analysis and component scores

- **`app/Http/Controllers/PredictionController.php`** (Lines 60, 120)
  - Updated comments to reference v6 multi-factor analysis
  - All prediction endpoints now use v6 through PredictionService

### 4. **Jobs**
- **`app/Jobs/DetectReboundAndRegenerateJob.php`** (Line 118)
  - Updated comment to reference v6 when regenerating predictions
  - Uses v6 through PredictionService::getPredictionForHorizon()

- **`app/Jobs/AutoGeneratePredictionsJob.php`**
  - Uses EnhancedPredictionService (PHP-based, not dependent on model version)

- **`app/Jobs/GeneratePredictionsJob.php`**
  - Uses PredictionService which now defaults to v6

### 5. **Python Model**
- **`python/models/quick_model_v6.py`** (Lines 25-26, 868-920)
  - Added `import argparse` for compatibility
  - Updated `main()` function to support both argparse and direct JSON modes
  - Compatible with PHP backend calling convention: `python quick_model_v6.py predict --features '{json}'`
  - Also supports legacy mode: `python quick_model_v6.py '{json}'`

## Features of Quick Model V6

✅ **Multi-Factor Analysis**
- Technical indicators (RSI, MACD, Bollinger Bands, Golden Cross, Death Cross)
- Sentiment analysis (bullish/bearish keywords)
- Global market influence (Asian + European markets)
- Volume analysis
- Fundamental indicators
- Intraday momentum (NEW)

✅ **Output Data**
- Individual component scores (technical, sentiment, global_markets, volume, fundamentals, intraday)
- Component contributions to final prediction
- Top reasons for prediction
- Confidence calibration with alignment detection
- Strong alignment indicator when technical and sentiment indicators agree

✅ **Confidence Boosting**
- Alignment detection between technical and sentiment indicators
- Sigmoid-based probability calibration
- Minimum 55% confidence enforcement
- Maximum 98% confidence for strong signals

## API Endpoints Using V6

All prediction endpoints now use v6:

### REST API (`api/v*`)
- `GET /api/predictions/{symbol}` - Get current prediction
- `POST /api/predictions/{symbol}/generate` - Generate new prediction
- `GET /api/predictions/{symbol}/history` - Get prediction history
- `GET /api/predict/{ticker}` - Get prediction by ticker
- `POST /api/predict/{ticker}/regenerate-today` - Regenerate today prediction

### Legacy API
- `GET /api/predict/show/{ticker}` - Legacy prediction endpoint
- `POST /api/predict/{ticker}/regenerate-today` - Legacy regenerate endpoint

## Testing

To test v6 directly:

```bash
# With argparse (preferred):
python backend/python/models/quick_model_v6.py predict --features '{
  "current_price": 150.0,
  "rsi_14": 55,
  "macd_signal": 0.5,
  "volume_ratio": 1.2,
  "news_count": 3,
  "news_sentiment_score": 0.6
}'

# Legacy mode:
python backend/python/models/quick_model_v6.py '{
  "current_price": 150.0,
  "rsi_14": 55,
  ...
}'
```

## Verification Checklist

- ✅ PredictionService uses v6
- ✅ Config file points to v6
- ✅ API controllers reference v6
- ✅ All jobs use v6 through PredictionService
- ✅ Python model supports CLI interface
- ✅ Frontend receives component scores and contributions
- ✅ Alignment detection included in predictions

## Rollback

If needed to revert to v4, update:
1. `config/prediction.php` line 76
2. `app/Services/PredictionService.php` line 267
3. API controller defaults

Note: Component scores won't be available with v4.
