# Target Price Calculation Fix - From Previous Close

## Date: October 14, 2025

## Problem Summary

The target price was being calculated from the **current price** instead of from the **previous close**. This made the predictions confusing and mathematically incorrect.

### Example (AVGO)
**Before Fix:**
- Prev Close: $356.70
- Current: $347.52
- Expected Move: -2.37%
- Target: $347.52 × (1 - 0.0237) = **$339.27** ❌ WRONG

**After Fix:**
- Prev Close: $356.70
- Current: $347.52
- Expected Move: -2.37%
- Target: $356.70 × (1 - 0.0237) = **$348.23** ✅ CORRECT

## Why It Matters

Predictions should be interpreted as: **"From yesterday's close, we expect the stock to move X% to reach target price Y"**

NOT: "From current price, we expect X% move"

This is standard in financial predictions because:
1. Previous close is a fixed reference point (doesn't change during the day)
2. Makes predictions comparable throughout the trading day
3. Aligns with how professional traders think about price targets

## Solution Implemented

### 1. Backend - PredictionController

**File**: `backend/app/Http/Controllers/Api/PredictionController.php` (lines 88-93)

**Change**: Calculate `predicted_price` from `previous_close`:

```php
// CRITICAL: Calculate predicted_price from PREVIOUS CLOSE, not current price
// The prediction is: "From previous close, we expect X% move to reach target price"
$previousClose = $pythonData['db_previous_close'] ?? $pythonData['api_previous_close'] ?? $pythonData['current_price'] ?? 0;
$expectedMove = $pythonData['expected_pct_move'] ?? 0;
$predictedPrice = $previousClose * (1 + $expectedMove / 100);
```

### 2. Backend - Prediction Model Accessor

**File**: `backend/app/Models/Prediction.php` (lines 98-116)

**Change**: Added `previous_close` accessor to expose it in API responses:

```php
/**
 * Accessor for previous_close from indicators_snapshot
 * This is the persisted previous close used for prediction calculation
 */
public function getPreviousCloseAttribute(): ?float
{
    if (is_array($this->indicators_snapshot) && isset($this->indicators_snapshot['previous_close'])) {
        return (float) $this->indicators_snapshot['previous_close'];
    }
    if (is_array($this->indicators_snapshot) && isset($this->indicators_snapshot['db_previous_close'])) {
        return (float) $this->indicators_snapshot['db_previous_close'];
    }
    return null;
}

// Added to appends
protected $appends = ['expected_pct_move', 'previous_close'];
```

### 3. Frontend - PredictionCardV2 Component

**File**: `frontend/src/components/PredictionCardV2.jsx` (lines 230-236)

**Change**: Calculate target price from `previous_close`:

```javascript
// CRITICAL: Calculate target price from PREVIOUS CLOSE, not current price
// The prediction is: Previous Close -> Target Price (expected move from previous close)
// This way the prediction is consistent: "From yesterday's close of $X, we expect it to reach $Y (Z% move)"
const targetPrice = validPrevClose > 0 ? validPrevClose * (1 + validExpectedMove / 100) : 0;

// Calculate the actual expected change: from Previous Close to Target
const expectedDollarChange = targetPrice - validPrevClose;
```

## Verification

### API Response
```json
{
    "current_price": "347.52",
    "previous_close": 356.7,
    "predicted_price": "348.23",
    "predicted_change_percent": "-2.3734",
    "expected_pct_move": -2.3734
}
```

### Calculation Verification
```
Previous Close: $356.70
Expected Move:  -2.3734%
Calculation:    $356.70 × (1 - 0.023734) = $356.70 × 0.976266 = $348.23 ✅
```

### Frontend Display
```
┌─────────────┬──────────┬──────────┐
│ Prev Close  │ Current  │  Target  │
│  $356.70    │ $347.52  │ $348.23  │
│             │  -2.57%  │  -2.37%  │
└─────────────┴──────────┴──────────┘
```

## Interpretation

Using AVGO as an example:

1. **Yesterday's Close**: $356.70 (fixed reference point)
2. **Current Price**: $347.52 (down -2.57% from prev close)
3. **Model Prediction**: -2.37% from prev close → Target $348.23

**What this means:**
- The stock is currently DOWN -2.57% today
- The model predicts it should end at -2.37% for the day
- This implies the model is slightly bullish from current levels (+0.20%)
- But still bearish from yesterday's close overall (-2.37%)

## Key Benefits

1. ✅ **Consistent Reference Point**: Previous close doesn't change during trading day
2. ✅ **Standard Industry Practice**: Aligns with how Wall Street reports targets
3. ✅ **Clear Communication**: "We expect -2.37% from yesterday's close of $356.70"
4. ✅ **Comparable Predictions**: All predictions use same baseline
5. ✅ **Mathematical Accuracy**: Percentages match the actual dollar values

## Files Modified

1. `backend/app/Http/Controllers/Api/PredictionController.php`
   - Lines 88-93: Calculate predicted_price from previous_close
   - Lines 116-117: Store previous_close in indicators_snapshot

2. `backend/app/Models/Prediction.php`
   - Lines 98-116: Added previous_close accessor
   - Updated $appends array

3. `frontend/src/components/PredictionCardV2.jsx`
   - Lines 230-236: Calculate target from previous_close
   - Line 144: Use previous_close accessor

## Status: ✅ RESOLVED

Target price calculations are now based on previous close, making predictions mathematically accurate and aligned with industry standards.

## Formula Summary

```
Target Price = Previous Close × (1 + Expected Move / 100)
```

**Example:**
```
$348.23 = $356.70 × (1 + (-2.3734) / 100)
$348.23 = $356.70 × 0.976266
✅ Correct!
```
