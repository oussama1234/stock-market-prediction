# Market Indices Auto-Update System

## 🎯 Problem Solved

Market indices (S&P 500, NASDAQ, DOW) were showing **incorrect direction** (bullish when actually bearish) because:
1. **Stale data** - Indices weren't updating automatically
2. **Wrong baseline** - Using API `change_percent` instead of database-calculated `db_change_percent`

## ✅ Solution Implemented

### 1. **Fixed Change Calculation** (MarketIndexService.php)
- **Priority order**: `db_change_percent` > `change_percent`
- Database-calculated values use the correct `previous_close` baseline
- API values are often incorrect after market close

```php
// CRITICAL: Use db_change_percent (calculated from persisted previous_close)
$changePercent = $quote['db_change_percent'] ?? $quote['change_percent'] ?? null;
```

### 2. **Automatic Scheduled Updates** (routes/console.php)

#### During Market Hours (9:30 AM - 4:00 PM ET)
- **Every 15 minutes** - Real-time homepage data
```bash
*/15 9-16 * * * (Mon-Fri)
```

#### Critical Times
- **8:00 AM ET** - Pre-market update
- **4:05 PM ET** - After market close (captures final values)

### 3. **API Auto-Update Fallback** (MarketController.php)

When homepage calls `/api/market/indices`, it automatically checks if data is stale:

#### Staleness Thresholds
- **During market hours (9:30 AM - 4:00 PM ET)**: Update if > 5 minutes old
- **After hours**: Update if > 30 minutes old

#### Auto-Update Logic
```php
protected function autoUpdateStaleIndices(): void
{
    // Check oldest update time
    // If stale, automatically update all indices
    // If no data exists, force update
}
```

## 📊 Update Frequency

| Time Period | Update Frequency | Method |
|------------|-----------------|--------|
| **Pre-market** (8:00 AM) | Once | Scheduled |
| **Market Hours** (9:30 AM - 4:00 PM) | Every 15 minutes | Scheduled |
| **Market Close** (4:05 PM) | Once | Scheduled |
| **After Hours** | On-demand if >30 min old | API Auto-update |
| **API Calls** | On-demand if >5 min old (market hours) | API Auto-update |

## 🔧 Manual Commands

### Update Market Indices Now
```bash
php artisan market:update-indices
```

### Initialize Market Indices (First Time)
```bash
php artisan market:init-indices
```

### View Scheduled Tasks
```bash
php artisan schedule:list | grep market
```

## 🚀 Docker Commands

### Update Indices in Docker
```bash
docker exec market-prediction-php-fpm php artisan market:update-indices
```

### Check Scheduler Status
```bash
docker exec market-prediction-scheduler php artisan schedule:list
```

### View Logs
```bash
docker logs market-prediction-scheduler -f
```

## 📝 How It Works

### Step 1: Scheduled Update (Preferred)
Every 15 minutes during market hours, the scheduler runs:
```bash
php artisan market:update-indices
```

### Step 2: API Auto-Update (Fallback)
If homepage loads and data is stale:
1. API checks `last_updated` timestamp
2. Compares against threshold (5 or 30 minutes)
3. Auto-updates if stale
4. Returns fresh data

### Step 3: Data Source Priority
```
db_change_percent (from StockPrice.previous_close)
    ↓ (if not available)
change_percent (from API)
    ↓ (if not available)
null (fallback)
```

## ✨ Benefits

1. **Always Fresh Data** - Homepage shows current market status
2. **Correct Direction** - Uses accurate previous_close baseline
3. **No Manual Intervention** - Fully automated
4. **Resilient** - Multiple update mechanisms (scheduled + on-demand)
5. **Market-Aware** - Different thresholds for market hours vs after hours

## 🔍 Monitoring

### Check Last Update Time
```bash
curl http://localhost:8000/api/market/indices | jq '.data.sp500.last_updated'
```

### Check Change Values
```bash
curl http://localhost:8000/api/market/indices | jq '.data[] | {symbol, change_percent, last_updated}'
```

## 🐛 Troubleshooting

### Issue: Indices still showing old data
**Solution**: Check if scheduler is running
```bash
docker ps | grep scheduler
docker logs market-prediction-scheduler --tail 50
```

### Issue: Scheduler not running tasks
**Solution**: Restart scheduler container
```bash
docker restart market-prediction-scheduler
```

### Issue: Wrong direction still showing
**Solution**: 
1. Clear cache: `docker exec market-prediction-php-fpm php artisan cache:clear`
2. Force update: `docker exec market-prediction-php-fpm php artisan market:update-indices`
3. Check logs for errors

## 📚 Related Files

- `app/Services/MarketIndexService.php` - Core update logic
- `app/Http/Controllers/Api/MarketController.php` - API with auto-update
- `app/Console/Commands/UpdateMarketIndices.php` - Artisan command
- `routes/console.php` - Scheduled tasks
- `app/Models/MarketIndex.php` - Database model

## 🎉 Result

**Before**: Showing +1.53% (bullish) when market was down
**After**: Showing -0.12% (bearish) - CORRECT! ✅

No more manual commands needed - system updates automatically!
