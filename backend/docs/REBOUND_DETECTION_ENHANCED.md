# Enhanced Rebound Detection System

## Overview
This document describes the enhanced rebound detection system that incorporates both percentage-based and absolute dollar-based price movements to better detect bounce-back opportunities, especially for high-value stocks.

## Problem Statement
The original system relied primarily on percentage-based price changes. For high-priced stocks like NVIDIA (NVDA trading at ~$140), a $9 drop represents significant dollar volatility but might only be ~6% change, which could be missed or underweighted by pure percentage-based detection.

## Solution: Hybrid Detection Approach

### 1. Absolute Drop Severity Calculation
The system now calculates an "absolute drop severity score" based on:
- **Current stock price tier** (>$100, >$50, >$20)
- **1-day absolute dollar drop** (e.g., $9 drop for NVDA)
- **3-day absolute dollar drop** (accumulated multi-day drops)

#### Severity Scoring Logic:
```
For stocks > $100:
  - Every $5 drop in 1 day adds +5 confidence points (max +25)
  - Every $10 drop in 3 days adds +10 confidence points (max +30)

For stocks > $50:
  - Every $3 drop adds +5 confidence points (max +20)

For stocks > $20:
  - Every $1 drop adds +3 confidence points (max +15)
```

### 2. Enhanced Rebound Patterns

#### Pattern 1: V-Shaped Recovery
- **Triggers**: 7-day decline > 3%, 3-day gain > 1%, 1-day positive
- **Base confidence**: 80%
- **Enhancement**: Added absolute drop severity boost
- **Max confidence**: 95% (with positive sentiment)

#### Pattern 2: Confirmed Multi-Day Recovery
- **Triggers**: 3-day gain > 2%, 1-day gain > 0.5%
- **Base confidence**: 75%
- **Enhancement**: +50% of absolute drop severity score
- **Type**: Strong rebound

#### Pattern 3: Strong Daily Bounce
- **Triggers**: 1-day gain > 2.5%
- **Base confidence**: 70%
- **Enhancement**: Full absolute drop severity boost
- **Additional**: +10% if supported by positive news (sentiment > 0.2)

#### Pattern 4: Recovery with Bullish Sentiment
- **Triggers**: 7-day decline > 2%, 1-day gain > 0.3%, sentiment > 0.3
- **Confidence**: 60 + (sentiment × 30) + (7-day decline × 2) + severity
- **Max confidence**: 90%
- **Type**: Moderate rebound

#### Pattern 5: Strong Sentiment After Decline
- **Triggers**: Sentiment > 0.4, 7-day decline > 3%, no strong price pattern yet
- **Base confidence**: 50 + (sentiment × 30)
- **Additional**: +15% if any positive price movement
- **Type**: Weak rebound (requires price confirmation)

#### Pattern 6: News Momentum
- **Triggers**: ≥3 recent news articles, sentiment > 0.4
- **Effect**: +5% for strong rebounds, or 60% baseline for weak rebounds
- **Purpose**: Capture narrative-driven momentum

#### Pattern 7: Intraday Reversal
- **Triggers**: 1-day gain > 1.5%, 3-day still negative
- **Base confidence**: 70%
- **Enhancement**: +70% of absolute drop severity score
- **Use case**: Monday rebounds after Friday drops

#### Pattern 8: Large Dollar Drop Recovery (NEW)
- **Triggers**: 
  - Absolute 1-day drop > $5
  - 1-day price change > 0.5% (showing recovery)
  - Sentiment ≥ 0 (not negative)
- **Confidence**: 65 + (absolute drop × 2)
- **Max confidence**: 90% (95% with positive sentiment > 0.3)
- **Type**: Strong rebound
- **Purpose**: **Specifically catches NVDA-style large dollar drops**

#### Pattern 9: Multi-Day Dollar Drop Recovery (NEW)
- **Triggers**: 
  - Absolute 3-day drop > $10
  - 1-day price change > 0 (any recovery)
- **Confidence**: 60 + (absolute drop × 1.5)
- **Max confidence**: 88%
- **Type**: Moderate rebound
- **Purpose**: Catches accumulated multi-day dollar declines

## Example Scenarios

### Scenario 1: NVDA $9 Drop
**Context**: NVDA trading at $140, drops to $131 (-$9, -6.4%)
**Detection**:
- Absolute drop severity: 25 points (for $9 > $5 threshold)
- If next day shows +0.5% gain with neutral/positive sentiment:
  - Pattern 8 triggers: `large_dollar_drop_recovery`
  - Base confidence: 65 + (9 × 2) = 83%
  - If sentiment > 0.3: confidence → 93%
  - Rebound type: **Strong**

### Scenario 2: High-Value Multi-Day Decline
**Context**: Stock at $150, drops $15 over 3 days, shows +1% on day 4
**Detection**:
- Absolute drop severity: 30 points (3-day drop > $10)
- Pattern 9 triggers: `multi_day_dollar_drop_recovery`
- Confidence: 60 + (15 × 1.5) = 82.5%
- Additional patterns may stack (e.g., V-shaped if 7-day also negative)

### Scenario 3: Moderate-Value Stock
**Context**: Stock at $60, drops $4 in 1 day, recovers +2% next day
**Detection**:
- Absolute drop severity: ~6.7 points
- Pattern 3 triggers: `strong_daily_bounce`
- Confidence: 70 + 6.7 = 76.7%
- Pattern 8 may not trigger (< $5 drop threshold)

## Integration Points

### Data Flow
1. **DetectReboundAndRegenerateJob.php** calculates:
   - Percentage-based price changes (1d, 3d, 7d)
   - Absolute dollar drops (1d, 3d)
   - Current price
   - News sentiment

2. **detectReboundPatterns()** evaluates:
   - Absolute drop severity based on price tier
   - All 9 rebound patterns
   - Confidence boosting from severity score
   - Final confidence clamping (0-100%)

3. **Output includes**:
   - Is rebound detected (bool)
   - Pattern names (comma-separated)
   - Confidence level (0-100)
   - Rebound type (strong/moderate/weak)
   - Full metrics including absolute drops

### Job Dispatch
- **ProcessAllStocksReboundDetectionJob**: Dispatches rebound detection for all active stocks
- **DetectReboundAndRegenerateJob**: Processes individual stock (queued)
- **Frequency**: Can be scheduled (e.g., hourly, daily, or on-demand)

## Testing

### Manual Test via Tinker
```php
php artisan tinker

use App\Models\Stock;
use App\Jobs\DetectReboundAndRegenerateJob;

$stock = Stock::where('symbol', 'NVDA')->first();
DetectReboundAndRegenerateJob::dispatch($stock);

// Check logs
tail -f storage/logs/laravel.log
```

### Batch Process All Stocks
```php
use App\Jobs\ProcessAllStocksReboundDetectionJob;
ProcessAllStocksReboundDetectionJob::dispatch();
```

### Monitor Rebound Events
```php
use Illuminate\Support\Facades\Cache;

$symbol = 'NVDA';
$date = now()->format('Y-m-d');
$events = Cache::get("rebound_events_{$symbol}_{$date}", []);
dd($events);
```

## Performance Considerations

### Confidence Capping
- All confidence scores are clamped to 0-100%
- Multiple patterns can stack, but cap prevents unrealistic >100% confidence
- Absolute severity boost is additive but controlled per pattern

### Queue Processing
- Jobs are queued for async processing
- Retry logic: 3 attempts with exponential backoff
- Timeout: 60 seconds per job
- Prediction cache is cleared on successful rebound detection

### Cache Strategy
- Rebound events cached for 7 days per stock per date
- Prediction cache invalidated on rebound: `prediction_{stock_id}_today`
- Stock details cache cleared: `stock_details_{symbol}`

## Logging

### Rebound Detected
```
[INFO] Rebound detected for NVDA
Pattern: large_dollar_drop_recovery, v_shaped_recovery
Confidence: 93%
Type: strong
Metrics: {
  "price_1d": 0.5,
  "price_3d": -2.1,
  "price_7d": -5.3,
  "sentiment": 0.45,
  "news_count": 5,
  "absolute_drop_1d": 9.0,
  "absolute_drop_3d": 12.5,
  "current_price": 131.0,
  "absolute_drop_severity_score": 25.0
}
```

### No Rebound
```
[INFO] No rebound detected for AAPL
Metrics: {...}
Reason: Conditions not met for any rebound pattern
```

## Future Enhancements

1. **Machine Learning Integration**: Train model to predict rebound probability using historical absolute drops
2. **Sector-Specific Thresholds**: Different $ drop thresholds for tech vs. banking vs. retail
3. **Volatility Adjustment**: Scale severity based on stock's historical volatility (β)
4. **Volume Confirmation**: Require high volume on recovery day for higher confidence
5. **Options Activity**: Incorporate unusual options volume as rebound signal
6. **Pre-market/After-hours**: Consider extended hours price action
7. **Analyst Upgrades**: Boost confidence if analyst upgrades coincide with recovery

## Conclusion
This enhanced system provides **dual-mode detection** that captures both:
- **Traditional percentage-based patterns** (effective for most stocks)
- **Absolute dollar-based patterns** (critical for high-value stocks)

The approach ensures large absolute price swings in expensive stocks like NVDA are properly weighted and detected as strong rebound candidates, leading to more accurate prediction regeneration and better trading signals.
