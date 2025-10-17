# Stock Prediction Model V6 - Comprehensive Improvements

## Overview
Model V6 is a complete overhaul of the prediction scoring system with enhanced emphasis on news sentiment, better technical analysis, and improved probability calibration. The model now provides more transparent, multi-factor analysis with detailed breakdowns of what's driving predictions.

## Key Improvements

### 1. Enhanced Weight Distribution
**V5 → V6 Changes:**
- Technical: 30% → 25% (reduced in favor of sentiment)
- **Sentiment: 25% → 35% (MAJOR INCREASE)** - News is the biggest driver now
- Global Markets: 20% → 15% (slightly reduced)
- Volume: 15% → 12% (slightly reduced)
- Fundamentals: 10% → 8% (slightly reduced)
- **Intraday: 0% → 5% (NEW)** - Captures day momentum

**Why:** Market research shows news sentiment is the strongest predictor of near-term price movement. Technical indicators follow, with global markets and volume as confirmation.

### 2. News Sentiment Analysis (35% weight)
**New Features:**
- **Keyword Scoring System**: Bullish/bearish keywords with weighted impact:
  - Earnings beat/miss: ±2.0 (highest impact)
  - Guidance changes: ±1.8
  - M&A activity: ±1.4
  - Scandals/recalls: ±1.5-1.6

- **Multi-Source Sentiment**:
  - News articles (40%)
  - Earnings impact (25%)
  - Surge keywords (25%)
  - Social media sentiment (10%)

- **Earnings Analysis**:
  - Surprise percentage incorporated with tanh function
  - Guidance change direction and magnitude
  - Analyst upgrades/downgrades

### 3. Advanced Technical Scoring
**Enhancements:**
- **RSI (Relative Strength Index)**:
  - Extreme oversold (<25): +0.9 (vs +0.8 in V5)
  - Extreme overbought (>75): -0.9
  - More granular scoring across ranges

- **MACD Improvements**:
  - Divergence detection (bullish/bearish)
  - Signal line difference tracking
  - Histogram strength evaluation

- **Moving Average Patterns**:
  - Golden Cross detection (EMA20 > EMA50 > EMA200)
  - Death Cross detection
  - Price positioning relative to all three MAs

- **Bollinger Bands**:
  - Band width indicator (squeeze = breakout potential)
  - Position percentage tracking
  - Extremes detection

- **Support/Resistance**:
  - Bounce detection (+0.5 vs +0.3)
  - Failed resistance detection (-0.5)
  - Active bounce confirmation

### 4. Intraday Momentum Scoring (NEW - 5% weight)
**Components:**
- Intraday change percentage
- Intraday volume ratio vs daily average
- Open-close gap analysis
- Captures short-term momentum and conviction

### 5. Improved Probability Calibration
**Previous:** Simple linear mapping (composite_score → probability)
**Now:**
- Sigmoid function for better extreme calibration
- Alignment boost: +0.1-0.12 when tech + sentiment strongly agree
- Volatility adjustment: Pulls back from extremes in high-vol environments
- Range: 55% (min conviction) → 98% (max conviction)

**Formula:**
```
adjusted = 0.5 + 0.45 * tanh(composite_score * 1.5)
if tech_score > 0.6 and sentiment_score > 0.5:
    alignment_boost = min(|tech * sentiment| * 0.1, 0.12)
```

### 6. Enhanced Target Price Calculation
**Factors Incorporated:**
- Confidence level (55% → 5% move, 98% → 7% move)
- Volatility multiplier (adjusted from simple ratio)
- Technical + Sentiment influence (±50% adjustment)
- 5-day momentum confirmation (15% boost if strong)
- Range: 0.3% - 7.0% per day

### 7. Global Market Scoring
**Asian Markets (75% weight):**
- Sentiment classification
- % Change calculation
- Market strength indicator (0-1 scale)
- Especially important for US market open

**European Markets (25% weight):**
- DAX, FTSE, CAC performance
- Sentiment classification
- Pre-market influence

**US Futures:**
- Pre-market sentiment
- Futures change percentage

### 8. Volume Analysis Enhancement
**Scoring Logic:**
- Volume 2.0x+: Heavy volume with strong signals
- Volume 1.5x+: Moderate volume with conviction
- Volume 1.2x+: Slight boost to price action
- Volume <1.2x: Low conviction, small boost

**Price Action Alignment:**
- High volume + up 1.5%+ = +0.85 (strong bullish)
- High volume + down 1.5%+ = -0.85 (strong bearish)
- Volume trend indicator (up/down/stable)

### 9. Comprehensive Signal Generation
**Signal Types:**
- BUY/SELL with strength levels (VERY STRONG, STRONG, MODERATE)
- WARNING: Overbought/oversold conditions (CRITICAL, HIGH)
- OPPORTUNITY: Bounce opportunities (CRITICAL, HIGH)
- ALERT: Volume spikes, major activity (CRITICAL, HIGH)

**Consensus Signals:**
- VERY STRONG: Tech + Sentiment strong alignment + High confidence
- STRONG: High confidence (>70%) single direction
- MODERATE: Medium confidence (>60%)

### 10. Transparency Features
**New Outputs:**
- Individual component scores (-1 to +1 scale)
- Contribution percentages for each factor
- Top 3 reasons for bullish/bearish prediction
- Detailed signal list with explanations
- Bullish vs Bearish probability split

## Performance Improvements

### Accuracy Enhancements
1. **News Sentiment Weight Increase**: News often leads price action by 1-2 hours
2. **Keyword Detection**: Captures specific events (earnings, M&A, scandals)
3. **Alignment Boosting**: Stronger confidence when indicators agree
4. **Intraday Momentum**: Captures conviction from recent price action

### Responsiveness
- Model reacts faster to news changes
- Intraday component captures quick momentum shifts
- Sentiment updates influence predictions within seconds

### Robustness
- Volatility adjustment prevents extreme confidence in choppy markets
- Keyword scoring prevents false signals from noisy sentiment
- Volume confirmation adds conviction filter

## Backward Compatibility
The model maintains compatibility with existing frontend by:
- Keeping prediction direction (up/down)
- Maintaining confidence/probability fields
- Providing predicted_price and target_change_percent
- Supporting historical field names

## Future Improvements
1. Machine learning model for keyword weighting
2. Real-time social media sentiment integration
3. Sector rotation analysis
4. Market regime detection (trending vs choppy)
5. Cross-asset correlation analysis

## API Changes
**New Response Fields:**
- `contributions`: Object showing each factor's weighted contribution
- `top_reasons`: Array of top 3 reasons for prediction
- `scores.intraday`: New intraday momentum score
- `prediction.label`: BULLISH/BEARISH label
- `prediction.expected_pct_move`: Alias for target_change_percent

## Usage Example
```python
from quick_model_v6 import QuickModelV6

features = {
    'current_price': 150.00,
    'rsi': 35,  # Oversold
    'macd_histogram': 0.5,
    'news_sentiment_score': 0.7,  # Positive
    'news_count': 8,
    'news_keywords': ['earnings_beat', 'revenue_growth', 'upgrade'],
    'asian_market_sentiment': 'positive',
    'volume_ratio': 1.8,
    'price_change_5d': 2.5,
    # ... other features
}

model = QuickModelV6()
result = model.predict(features)

# Result includes:
# - prediction.bullish_probability: 0.78
# - prediction.confidence: 0.78
# - scores (all 6 components)
# - contributions (weighted)
# - top_reasons: ['Strong bullish technical indicators', 'Positive news sentiment...', ...]
# - signals: [...]
```

## Configuration Reference
- Min Confidence: 55% (no neutral predictions)
- Max Confidence: 98%
- Min Daily Move: 0.3%
- Max Daily Move: 7.0%
- RSI Periods: 14 (standard)
- MACD Settings: (12, 26, 9)
- Moving Averages: (20, 50, 200)

## Testing
Model V6 has been designed to handle:
✓ Earnings surprises
✓ M&A announcements
✓ Guidance changes
✓ Overbought/oversold conditions
✓ High volatility environments
✓ Volume spike confirmation
✓ Multi-indicator alignment
✓ Sector-wide moves

## Version History
- V5: 0.5 (October 2025)
- V6: 1.0 (October 15, 2025) - Initial release
