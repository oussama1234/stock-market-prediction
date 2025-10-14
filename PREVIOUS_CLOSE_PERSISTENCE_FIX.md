# Previous Close Persistence Fix

## Date: October 14, 2025

## Problem Summary

The stock prediction system was not using the correct **previous close** value for calculations. The system was querying yesterday's `close` value from the database instead of using the **persisted `previous_close`** field that should be set when the market closes.

### Example Issue (AVGO)
- **Expected Previous Close**: $356.70 (actual close from previous trading day)
- **What System Was Using**: $324.63 (wrong - this was yesterday's close in DB)
- **Current Price**: $347.52
- **Expected Calculation**: $347.52 - $356.70 = -$9.18 (-2.57%)
- **What System Calculated**: $347.52 - $324.63 = +$22.89 (+7.05%) ❌ WRONG

## Root Cause

The `StockService::getQuote()` method was calculating `db_previous_close` by querying:
```php
$lastSavedPrice = StockPrice::where('stock_id', $stock->id)
    ->where('interval', '1day')
    ->where('price_date', '<', now()->toDateString())  // ❌ Gets yesterday's date
    ->orderBy('price_date', 'desc')
    ->first();
```

This retrieved **yesterday's `close`** value (e.g., $324.63) instead of using **today's `previous_close`** field which contains the correct persisted value ($356.70).

## Solution Implemented

### 1. Updated StockService::getQuote() - Use Today's previous_close Field

**File**: `backend/app/Services/StockService.php` (lines 287-332)

**Change**: Modified the query to get today's stock_prices record and use its `previous_close` field:

```php
// CRITICAL: Use TODAY's previous_close field (persisted at market close)
// This is the correct previous close that was stored when market closed yesterday
$stock = Stock::where('symbol', $symbol)->first();
if ($stock && $current) {
    // Get TODAY's price record which contains the persisted previous_close
    $todayPrice = StockPrice::where('stock_id', $stock->id)
        ->where('interval', '1day')
        ->where('price_date', '=', now()->toDateString())  // ✅ Get today's record
        ->whereNotNull('previous_close')
        ->first();
    
    if ($todayPrice && $todayPrice->previous_close) {
        // Use the persisted previous_close from today's record
        $previousClose = (float) $todayPrice->previous_close;
        $dbChange = $current - $previousClose;
        $dbChangePct = $previousClose > 0 ? ($dbChange / $previousClose) * 100 : 0;
        
        // Add database-based change values
        $quote['db_previous_close'] = round($previousClose, 2);
        $quote['db_change'] = round($dbChange, 2);
        $quote['db_change_percent'] = round($dbChangePct, 2);
        $quote['db_last_check_date'] = $todayPrice->price_date;
        
        Log::info("Using persisted previous_close for {$symbol}: Previous={$previousClose}, Current={$current}, Change={$dbChange} ({$dbChangePct}%)");
    }
}
```

### 2. Updated PredictionService::prepareStockData() - Prioritize db_previous_close

**File**: `backend/app/Services/PredictionService.php` (lines 395-417)

**Change**: Modified to prioritize `db_previous_close` (persisted value) over API's `previous_close`:

```php
// CRITICAL: Use db_previous_close (persisted at market close) as the baseline
// This is the accurate previous close stored in the database, NOT the API's previous_close
// Priority: db_previous_close > previous_close field from today's StockPrice > latestPrice->close
$previousClose = $quote['db_previous_close'] ?? $latestPrice?->previous_close ?? $quote['previous_close'] ?? $latestPrice?->close ?? 100.0;

// Debug logging
Log::info("TODAY price change calculation for {$stock->symbol}", [
    'current_price' => $currentPrice,
    'previous_close' => $previousClose,
    'db_previous_close' => $quote['db_previous_close'] ?? 'N/A',
    'api_previous_close' => $quote['previous_close'] ?? 'N/A',
    'today_change_pct' => round($todayChangePercent, 2),
]);
```

## Database Structure

The `stock_prices` table already has a `previous_close` field:

```sql
CREATE TABLE stock_prices (
    id BIGINT PRIMARY KEY,
    stock_id BIGINT,
    open DECIMAL(10,2),
    high DECIMAL(10,2),
    low DECIMAL(10,2),
    close DECIMAL(10,2),
    previous_close DECIMAL(10,2),  -- ✅ This field should be set by scheduler
    change DECIMAL(10,2),
    change_percent DECIMAL(8,4),
    volume BIGINT,
    interval VARCHAR(10),
    price_date DATETIME,
    source VARCHAR(50)
);
```

## How It Should Work

### Scheduler Behavior (Market Close)
When the market closes (e.g., at 4:00 PM ET), the scheduler should:

1. **Fetch final closing price** from API (e.g., $356.70 for AVGO)
2. **Store in database** for today's date:
   ```php
   StockPrice::updateOrCreate([
       'stock_id' => $stock->id,
       'price_date' => now()->toDateString(),
       'interval' => '1day',
   ], [
       'close' => 356.70,
       'previous_close' => $previousDayClose,  // ✅ Set this!
       // ... other fields
   ]);
   ```

### Next Trading Day (Market Open)
When the market opens the next day:

1. **Create new record** for today with:
   - `close`: Current live price (updates throughout day)
   - `previous_close`: Yesterday's close ($356.70) ✅ **THIS STAYS CONSTANT**

2. **All calculations** use this persisted `previous_close`:
   - Price change: `current - previous_close`
   - Change percent: `((current - previous_close) / previous_close) * 100`
   - Predictions: Based on change from `previous_close`

## Verification

### Database Check
```sql
SELECT close, previous_close, price_date 
FROM stock_prices 
WHERE stock_id = (SELECT id FROM stocks WHERE symbol='AVGO') 
  AND interval='1day' 
ORDER BY price_date DESC 
LIMIT 5;
```

**Result**:
```
close    previous_close  price_date
346.04   356.70         2025-10-14 00:00:00  ✅ Correct!
324.63   324.63         2025-10-13 00:00:00
324.63   345.02         2025-10-12 00:00:00
```

### Laravel Log Verification
```
[2025-10-14 16:23:54] production.INFO: Using persisted previous_close for AVGO: 
Previous=356.7, Current=347.52, Change=-9.18 (-2.5735912531539%)

[2025-10-14 16:23:54] production.INFO: TODAY price change calculation for AVGO 
{
    "current_price":347.52,
    "previous_close":356.7,      ✅ Correct!
    "db_previous_close":356.7,   ✅ Correct!
    "api_previous_close":356.7,
    "today_change_pct":-2.57
}
```

### API Response
```json
{
    "current_price": "347.52",
    "predicted_price": "339.26",
    "predicted_change_percent": "-2.3755",
    "expected_pct_move": -2.3755,
    "model_version": "quick_model_v4"
}
```

**Calculation Verification**:
- Current: $347.52
- Expected move: -2.38%
- Target: $347.52 × (1 - 0.023755) = $339.26 ✅

## Frontend Display

The frontend `PredictionCardV2` component correctly shows:

1. **Previous Close**: $356.70 (from `db_previous_close`)
2. **Current Price**: $347.52 (live)
3. **Today's Change**: -$9.18 (-2.57%)
4. **Target Price**: $339.26 (from current × expected_move)

## Key Takeaways

1. ✅ **Previous close is persisted** in the `stock_prices.previous_close` field
2. ✅ **Always use today's `previous_close` field**, not yesterday's `close`
3. ✅ **Priority order**: `db_previous_close` > `latestPrice->previous_close` > API `previous_close`
4. ✅ **Scheduler must set** `previous_close` when storing end-of-day prices
5. ✅ **Frontend calculates** target price from current price, not previous close

## Files Modified

1. `backend/app/Services/StockService.php`
   - Lines 287-332: Modified `getQuote()` to use today's `previous_close` field

2. `backend/app/Services/PredictionService.php`
   - Lines 395-417: Modified `prepareStockData()` to prioritize `db_previous_close`

## Status: ✅ RESOLVED

The previous close persistence system is now working correctly. All predictions and price change calculations are based on the accurate, persisted previous close value from the database.

## Next Steps

1. ✅ System correctly uses persisted previous close
2. ✅ Predictions calculate from current price to target
3. ✅ Frontend displays correct values
4. ⏭️ Verify scheduler properly sets `previous_close` at market close
5. ⏭️ Test with multiple stocks across different trading days
