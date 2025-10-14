# Previous Close Price Persistence Fix

## Problem Description

The system was calculating incorrect price changes because the `previous_close` field was not being set correctly. This caused the following issues:

1. **Incorrect "1-day price change" calculations**: When stock prices were updated during or after market hours, today's closing price was being written to today's database record
2. **Zero or incorrect price changes**: The next day, when the system looked for the previous close price, it would find today's close price instead of yesterday's, resulting in incorrect calculations
3. **Example**: AVGO stock showed a 0% price change despite having nearly a +10% gain on October 13, 2024

### Root Cause

The `UpdateStockPricesJob` was running during market hours and after market close (hourly from 9 AM - 4 PM, and once at 4:30 PM). When it updated prices, it would:

1. Create/update today's record with `price_date = today`
2. Set `close = today's closing price`
3. Set `previous_close = from API data` (which might be stale or incorrect)

This meant that when calculating price changes the next day, the system would query for "yesterday's record" but find a record with today's close price already written, leading to incorrect calculations.

## Solution

### 1. New Command: `stocks:persist-previous-closes`

Created a new Artisan command that runs at **2 AM ET** every weekday (before market open) to:

1. Query the most recent closing price for each stock from the database (the actual previous day's close)
2. Create or update today's price record with the correct `previous_close` value
3. Set a placeholder value for `close` (using `previous_close` as placeholder) since the field is NOT NULL
4. The actual close price will be updated during market hours by the existing `UpdateStockPricesJob`

**File**: `app/Console/Commands/PersistPreviousCloses.php`

**Usage**:
```bash
# Manual execution
php artisan stocks:persist-previous-closes

# With Docker
docker-compose exec php-fpm php artisan stocks:persist-previous-closes

# For a specific date
php artisan stocks:persist-previous-closes --date=2025-10-14
```

### 2. Scheduler Configuration

Added the new command to the Laravel scheduler in `routes/console.php`:

```php
// IMPORTANT: Persist previous day's closing prices at 2 AM ET
// This runs BEFORE market open to ensure accurate previous_close values
// for the new trading day, preventing incorrect price change calculations
Schedule::command('stocks:persist-previous-closes')
    ->weekdays()
    ->dailyAt('02:00')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->description('Persist previous day closing prices as previous_close for accurate calculations');
```

### 3. Data Flow

**Previous (Incorrect) Flow**:
```
Day 1: 4:30 PM - UpdateStockPricesJob runs
  → Creates record: { date: 2025-10-13, close: 324.63, previous_close: ??? }

Day 2: 9:00 AM - System calculates price change
  → Queries for previous close: SELECT close FROM stock_prices WHERE date < '2025-10-14'
  → Gets: 324.63 (Day 1's close)
  → Current price: 356.70
  → But previous_close in today's record might be wrong!
```

**New (Correct) Flow**:
```
Day 1: 4:30 PM - UpdateStockPricesJob runs
  → Creates/updates record: { date: 2025-10-13, close: 324.63, previous_close: ... }

Day 2: 2:00 AM - PersistPreviousCloses runs
  → Queries previous day's actual close: 324.63
  → Creates today's record: { date: 2025-10-14, close: 324.63 (placeholder), previous_close: 324.63 }

Day 2: 9:00 AM+ - UpdateStockPricesJob runs during market hours
  → Updates today's record: { date: 2025-10-14, close: 356.70, previous_close: 324.63 }
  → previous_close is preserved from 2 AM persistence!
  → Price change = 356.70 - 324.63 = +32.07 (+9.88%) ✅
```

## Testing

### Manual Test
```bash
# Check current state
docker-compose exec php-fpm php artisan stocks:check-avgo

# Run the persist command
docker-compose exec php-fpm php artisan stocks:persist-previous-closes

# Verify the results
docker-compose exec php-fpm php artisan stocks:check-avgo
```

### Expected Results
After running the command, each stock should have:
- A record for today with `previous_close` set to yesterday's actual closing price
- The `close` field initially set to the same value as `previous_close` (placeholder)
- During market hours, the `close` field will be updated with the current day's actual closing price

### Scheduler Verification
```bash
# View scheduled tasks
docker-compose exec scheduler php artisan schedule:list

# Expected output should include:
# 0  2   * * 1-5  php artisan stocks:persist-previous-closes
```

## Benefits

1. **Accurate price change calculations**: The `previous_close` field now always contains the correct previous day's closing price
2. **Reliable data**: No more zero or incorrect price changes due to timing issues
3. **Automated**: Runs automatically at 2 AM ET every weekday before market open
4. **Safe**: Uses a placeholder value for the NOT NULL `close` field, which gets updated during market hours

## Monitoring

To verify the fix is working:

1. **Check logs** for the 2 AM scheduled task:
   ```bash
   docker-compose logs scheduler | grep persist-previous-closes
   ```

2. **Manually inspect price data**:
   ```bash
   docker-compose exec php-fpm php artisan stocks:check-avgo
   ```

3. **Verify scheduler is running**:
   ```bash
   docker-compose exec scheduler php artisan schedule:list
   ```

## Related Files

- `app/Console/Commands/PersistPreviousCloses.php` - New command implementation
- `routes/console.php` - Scheduler configuration
- `app/Services/StockService.php` - Service that stores price data
- `app/Jobs/UpdateStockPricesJob.php` - Job that updates prices during market hours

## Date: October 14, 2025

Fix implemented and tested successfully. The system now persists correct `previous_close` values at 2 AM ET every weekday, ensuring accurate price change calculations throughout the trading day.
