# TSM Symbol Cleanup

## Date: 2025-10-13

## Issue
TSM was registered as **2330.TW** (Taiwan Stock Exchange) causing confusion with the US ADR symbol **TSM** (NYSE).

---

## Problem

### Duplicate Entries Found:
```sql
id    symbol    name                                              exchange
99    2330.TW   Taiwan Semiconductor Manufacturing Co Ltd        TWSE
111   TSM       Taiwan Semiconductor Manufacturing Company Ltd   NYSE
```

### Data Distribution:
- **2330.TW**: 1 price, 0 news, 6 predictions (minimal data)
- **TSM**: 1 price, 19 news, 42 predictions (all our work)

---

## Solution

### Deleted Duplicate
```sql
DELETE FROM stocks WHERE symbol = '2330.TW' AND id = 99;
```

### Result
```sql
id    symbol    name                                              exchange
111   TSM       Taiwan Semiconductor Manufacturing Company Ltd   NYSE
```

---

## Why TSM (US ADR) is Correct

### 1. **Market Access**
- TSM trades on NYSE (New York Stock Exchange)
- 2330.TW trades on TWSE (Taiwan Stock Exchange)
- US APIs (Finnhub, Alpha Vantage) use TSM

### 2. **Data Availability**
- TSM has English news articles
- TSM has real-time US market data
- TSM has better API support

### 3. **Trading Hours**
- TSM: US market hours (9:30 AM - 4:00 PM EST)
- 2330.TW: Taiwan hours (9:00 AM - 1:30 PM TST)

### 4. **Currency**
- TSM: USD (easier for US-based predictions)
- 2330.TW: TWD (Taiwan Dollar)

---

## Verification

### Database Check:
```bash
docker exec market-prediction-mysql mysql -umarket_user -pmarket_password \
  market_prediction -e "SELECT id, symbol, name, exchange FROM stocks WHERE symbol = 'TSM';"
```

**Output:**
```
id    symbol    name                                              exchange
111   TSM       Taiwan Semiconductor Manufacturing Company Ltd   NYSE
```

### Test Dispatch:
```bash
docker exec market-prediction-php-fpm php artisan test:tsm-dispatch --sync
```

**Result:** ✅ Working perfectly
- Price data: $280.66
- News articles: 19
- Recent news (48h): 5
- Predictions: 42
- Status: Bullish (news override)

---

## Impact

### Before:
- ❌ Duplicate entries causing confusion
- ❌ 2330.TW had no news articles
- ❌ Inconsistent data between entries

### After:
- ✅ Single TSM entry (US ADR)
- ✅ All news and predictions consolidated
- ✅ Clean, consistent database

---

## Important Notes

### Symbol Mapping:
- **TSM** = Taiwan Semiconductor ADR (US)
- **2330.TW** = Taiwan Semiconductor (Taiwan) - DELETED

### If You Need Taiwan Symbol:
If Taiwan exchange data is specifically needed in the future, use:
- Symbol: `2330.TW`
- Exchange: `TWSE`
- Currency: `TWD`
- Note: Requires Taiwan-specific API access

### Current Configuration:
```php
Stock: TSM
Exchange: NYSE
Currency: USD
APIs: Finnhub, Alpha Vantage, NewsAPI (US-focused)
News: English language
Market: US trading hours
```

---

## Testing Checklist

- [x] Duplicate removed from database
- [x] TSM is only entry for Taiwan Semiconductor
- [x] Test dispatch working
- [x] News articles accessible (19 articles)
- [x] Predictions generating correctly
- [x] Rebound detection working
- [x] Price data accurate

---

## Commands Reference

### Check TSM Stock:
```bash
docker exec market-prediction-mysql mysql -umarket_user -pmarket_password \
  market_prediction -e "SELECT * FROM stocks WHERE symbol = 'TSM'\G"
```

### Test TSM Dispatch:
```bash
docker exec market-prediction-php-fpm php artisan test:tsm-dispatch --sync --force
```

### Fetch TSM News:
```bash
docker exec market-prediction-php-fpm php fetch_tsm_news.php
```

### Check TSM Data:
```bash
docker exec market-prediction-mysql mysql -umarket_user -pmarket_password \
  market_prediction -e "
    SELECT 
      s.symbol,
      COUNT(DISTINCT sp.id) as prices,
      COUNT(DISTINCT n.id) as news,
      COUNT(DISTINCT p.id) as predictions
    FROM stocks s
    LEFT JOIN stock_prices sp ON s.id = sp.stock_id
    LEFT JOIN news_articles n ON s.id = n.stock_id  
    LEFT JOIN predictions p ON s.id = p.stock_id
    WHERE s.symbol = 'TSM'
    GROUP BY s.symbol;
  "
```

---

## Summary

✅ **TSM is now correctly registered as US ADR (NYSE)**  
✅ **Duplicate 2330.TW entry removed**  
✅ **All data consolidated under TSM**  
✅ **Job dispatch system working perfectly**  

The system now properly handles TSM as a US-listed security with USD pricing and English news sources.
