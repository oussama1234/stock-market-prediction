# Quick Reference Guide - Job Dispatch System

## 🚀 Quick Commands

### Test TSM Dispatch
```bash
# Basic test (queued)
docker exec market-prediction-php-fpm php artisan test:tsm-dispatch

# Synchronous test with force regeneration
docker exec market-prediction-php-fpm php artisan test:tsm-dispatch --sync --force

# With price update
docker exec market-prediction-php-fpm php artisan test:tsm-dispatch --sync --force --update-price

# Test all stocks
docker exec market-prediction-php-fpm php artisan test:tsm-dispatch --all
```

### Fetch News for TSM
```bash
docker exec market-prediction-php-fpm php fetch_tsm_news.php
```

### Manual Rebound Analysis
```bash
# Single stock (synchronous)
docker exec market-prediction-php-fpm php artisan analyze:news-rebounds --stock=TSM --sync

# All stocks (queued)
docker exec market-prediction-php-fpm php artisan analyze:news-rebounds --all

# Reprocess sentiment scores
docker exec market-prediction-php-fpm php artisan analyze:news-rebounds --stock=TSM --sync --reprocess
```

### Fetch Historical Data
```bash
docker exec market-prediction-php-fpm php artisan stocks:fetch-historical TSM
```

### Monitor Jobs
```bash
# Watch scheduler
docker logs market-prediction-scheduler -f

# Watch queue worker
docker logs market-prediction-queue-worker -f

# Watch Laravel logs
docker exec market-prediction-php-fpm tail -f storage/logs/laravel.log

# Filter for TSM
docker exec market-prediction-php-fpm tail -f storage/logs/laravel.log | grep TSM

# Filter for rebound
docker exec market-prediction-php-fpm tail -f storage/logs/laravel.log | grep -i rebound
```

---

## 📋 System Architecture

### Scheduled Jobs (Auto-run)
```
07:00 EST - Morning rebound detection (overnight news)
08:00 EST - Morning predictions
09:00-16:00 EST - Hourly price updates + rebound detection
13:00 EST - Mid-day rebound detection
16:30 EST - Final price update after close
16:45 EST - Comprehensive rebound analysis (with reprocessing)
17:00 EST - Evening predictions
Every 4 hrs - Auto-generate predictions
```

### Job Flow
```
1. Price Update (hourly)
   └─> Latest market data fetched

2. Rebound Detection
   ├─> Analyze News Sentiment (each stock)
   └─> Detect Rebound Patterns (each stock)
       └─> Regenerate Prediction (if rebound detected)

3. Prediction Generation
   └─> Enhanced prediction with all factors
```

---

## 🎯 Rebound Pattern Priorities

### PRIORITY 1: Price-Based (Strongest)
- V-shaped recovery
- Confirmed multi-day recovery  
- Strong daily bounce

### PRIORITY 2: Price + Sentiment
- Recovery with bullish sentiment
- Intraday reversal

### PRIORITY 3: Sentiment-Driven (Weakest)
- Sentiment after decline
- News momentum

**Rule**: Actual price movement always trumps news sentiment!

---

## 🔍 Debugging

### Check Why No Rebound Detected
```bash
docker exec market-prediction-php-fpm php artisan test:tsm-dispatch --sync --force
```
Look for log output showing metrics:
- price_1d, price_3d, price_7d
- sentiment score
- news count
- patterns detected

### Check Recent Price Data
```bash
docker exec market-prediction-php-fpm php artisan tinker
```
```php
$stock = \App\Models\Stock::where('symbol', 'TSM')->first();
$prices = \App\Models\StockPrice::where('stock_id', $stock->id)
    ->where('interval', '1day')
    ->orderBy('price_date', 'desc')
    ->limit(10)
    ->get(['price_date', 'close', 'volume']);
$prices->each(fn($p) => print("{$p->price_date}: \${$p->close}\n"));
```

### Check News Articles
```bash
docker exec market-prediction-php-fpm php artisan tinker
```
```php
$stock = \App\Models\Stock::where('symbol', 'TSM')->first();
$news = $stock->newsArticles()
    ->where('published_at', '>=', now()->subHours(48))
    ->orderBy('published_at', 'desc')
    ->get(['title', 'sentiment_score', 'published_at']);
$news->each(fn($n) => print("{$n->published_at}: {$n->title} (score: {$n->sentiment_score})\n"));
```

---

## ⚠️ Troubleshooting

### "Insufficient price data"
**Problem**: Need at least 3 days of historical data  
**Solution**: 
```bash
docker exec market-prediction-php-fpm php artisan stocks:fetch-historical TSM
```

### No news articles
**Problem**: News not fetched yet  
**Solution**: 
```bash
docker exec market-prediction-php-fpm php fetch_tsm_news.php
```

### Jobs not running
**Problem**: Queue worker or scheduler not active  
**Solution**: 
```bash
# Check containers
docker ps | grep market

# Restart if needed
docker restart market-prediction-queue-worker
docker restart market-prediction-scheduler
```

### Database connection error (from host)
**Problem**: Running PHP commands from Windows host  
**Solution**: Always use `docker exec` to run commands inside container

---

## 📊 Success Indicators

### System Working Correctly:
- ✅ Price updates every hour during market hours
- ✅ Rebound detection runs 4x daily
- ✅ News articles fetched and stored
- ✅ Sentiment scores calculated
- ✅ Predictions regenerated when rebounds detected
- ✅ Logs show detailed metrics

### Check System Health:
```bash
# Are containers running?
docker ps | grep market

# Are jobs scheduled?
docker exec market-prediction-php-fpm php artisan schedule:list

# Any recent predictions?
docker exec market-prediction-php-fpm php artisan tinker --execute="echo \App\Models\Prediction::where('created_at', '>=', now()->subHours(24))->count() . ' predictions in last 24h';"
```

---

## 🎉 Quick Win Tests

### 1. Test TSM Dispatch (2 minutes)
```bash
docker exec market-prediction-php-fpm php artisan test:tsm-dispatch --sync --force
```

### 2. Test All Stocks (5 minutes)
```bash
docker exec market-prediction-php-fpm php artisan test:tsm-dispatch --all
docker exec market-prediction-queue-worker php artisan queue:work --once --queue=default,predictions
```

### 3. Full System Test (10 minutes)
```bash
# 1. Fetch news
docker exec market-prediction-php-fpm php fetch_tsm_news.php

# 2. Fetch historical prices (if needed)
docker exec market-prediction-php-fpm php artisan stocks:fetch-historical TSM

# 3. Run rebound analysis
docker exec market-prediction-php-fpm php artisan analyze:news-rebounds --stock=TSM --sync

# 4. Check results
docker exec market-prediction-php-fpm php artisan test:tsm-dispatch --sync
```

---

## 📚 Documentation Files

- **JOB_DISPATCH_IMPROVEMENTS.md** - Complete system overview and improvements
- **QUICK_REFERENCE.md** - This file, quick commands and tips
- **SESSION_SUMMARY.md** - Previous session work summary
- **FINAL_STATUS.md** - Overall project status

---

## 💡 Pro Tips

1. **Always use `--sync` flag** when testing to see immediate results
2. **Use `--force` flag** to regenerate even without rebound detection
3. **Check logs** after every test to understand what happened
4. **Historical data is key** - rebound detection needs at least 3 days
5. **Monitor queue workers** to ensure jobs are processing
6. **Price updates are crucial** - stale data = poor predictions
7. **News keywords matter** - "rally", "surge" trigger overrides
8. **Test in off-hours** to avoid impacting live trading decisions

---

Generated: 2025-10-13
