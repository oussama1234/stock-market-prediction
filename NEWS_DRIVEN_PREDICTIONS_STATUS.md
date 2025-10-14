# News-Driven Predictions System - Implementation Status

## Date: October 14, 2025

## ✅ COMPLETED

### 1. News Storage with Stock Association
- ✅ `news_articles` table has all required fields (stock_id, published_at, sentiment_score)
- ✅ Updated `FetchNewsArticlesJob` to save articles to database
- ✅ Articles are stored with stock_id linking them to specific stocks
- ✅ Sentiment analysis job dispatched automatically after fetching

### 2. Keyword-Based Sentiment Analysis
- ✅ `AnalyzeNewsSentimentJob` has comprehensive keyword detection
- ✅ Bullish keywords: "AI chip deal", "stock soars", "surge", "rally", "rebound" (weights: +6 to +9)
- ✅ Bearish keywords: "crash", "plunge", "tariff", "downgrade" (weights: -6 to -9)
- ✅ Sentiment scores stored as decimal (-10 to +10 range)
- ✅ Rebound pattern detection integrated

### 3. API Endpoints for News with Sentiment
- ✅ `GET /api/stocks/{symbol}/news` - Recent news from database
- ✅ `GET /api/stocks/{symbol}/news/today` - Today's news with aggregate sentiment
- ✅ `POST /api/stocks/{symbol}/news/fetch` - Fetch and store fresh news

### 4. Verified Working
**Test Results for AVGO:**
- Fetched: 20 articles
- Stored: 30 articles (including historical)
- Today's articles: 8
- Average sentiment: +0.1375 (slightly bullish)
- Bullish: 5, Bearish: 1, Neutral: 2

## 🚧 IN PROGRESS / TODO

### 5. Integrate News Sentiment into Prediction Model
**Current Status:** News sentiment is calculated but not yet factored into prediction scoring

**What Needs to be Done:**
1. Modify `PredictionService::prepareStockData()` to include today's news sentiment
2. Add field: `today_news_sentiment` to the data passed to Python model
3. Update Python `quick_model_v4.py` to use `today_news_sentiment` as additive factor
4. Weight it appropriately (suggested: 10-15% of final score)

**Implementation Plan:**
```php
// In PredictionService::prepareStockData()
$todayNews = $stock->newsArticles()
    ->where('published_at', '>=', now()->startOfDay())
    ->whereNotNull('sentiment_score')
    ->get();

$todayNewsSentiment = $todayNews->avg('sentiment_score') ?? 0;
$todayNewsCount = $todayNews->count();

$data['today_news_sentiment'] = $todayNewsSentiment;
$data['today_news_count'] = $todayNewsCount;
```

```python
# In Python quick_model_v4.py predict() method
today_news_sentiment = features.get('today_news_sentiment', 0)
today_news_count = features.get('today_news_count', 0)

# Add news sentiment as additive factor (10% weight)
if today_news_count > 0:
    news_contribution = (today_news_sentiment / 10) * 0.10  # Normalize and weight
    final_score += news_contribution
```

### 6. Add Technical Analytics to Prediction
**Current Status:** Technical indicators calculated but not explicitly shown in breakdown

**What Needs to be Done:**
1. Extract technical indicator contributions from `indicators_snapshot`
2. Add detailed breakdown showing:
   - RSI contribution (+/- score)
   - MACD contribution
   - Bollinger Bands position
   - Volume analysis
   - Support/Resistance levels
3. Include in prediction response with individual weights

### 7. Enhance PredictionCardV2 Display
**Current Status:** Basic prediction display without detailed breakdown

**What Needs to be Added:**
1. **News Sentiment Section:**
   - Display today's news articles (top 5-10)
   - Show individual sentiment scores
   - Aggregate sentiment score with visual indicator
   - Contribution to final prediction (+X% or -X%)

2. **Technical Analytics Section:**
   - RSI indicator with visual gauge
   - MACD histogram
   - Bollinger Bands position
   - Volume vs Average
   - Each with contribution score

3. **Factor Breakdown Card:**
   ```
   Prediction Breakdown:
   ├─ Asian Markets: -0.63 (20% weight) → -0.126
   ├─ European Markets: -0.10 (30% weight) → -0.030
   ├─ Local Sentiment: +0.01 (50% weight) → +0.005
   ├─ Today's News: +0.14 (10% additive) → +0.014
   ├─ Technical: RSI=56.7 → Neutral
   └─ Final Score: -0.15 → BEARISH -2.37%
   ```

### 8. Schedule Regular News Fetching
**Current Status:** Jobs exist but not scheduled

**What Needs to be Done:**
Add to `routes/console.php`:
```php
// Fetch news for popular stocks every 2 hours during market hours
Schedule::command('news:fetch-popular-stocks')
    ->weekdays()
    ->cron('0 */2 * * *')
    ->between('9:00', '18:00')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->description('Fetch news for popular stocks');

// Fetch news after market close
Schedule::command('news:fetch-all-stocks')
    ->weekdays()
    ->dailyAt('16:30')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->description('Fetch news for all stocks after market close');
```

## 📊 Database Schema

### news_articles table
```sql
- id: bigint (PK)
- stock_id: bigint (FK to stocks) ✅
- title: varchar(255)
- description: text
- url: varchar(255) UNIQUE
- image_url: varchar(255)
- source: varchar(100)
- published_at: timestamp ✅
- sentiment_score: decimal(5,4) ✅
- sentiment_label: varchar(20)
- is_important: boolean
- importance_date: date
- expected_surge_percent: decimal(5,2)
- surge_keywords: json
- created_at: timestamp
- updated_at: timestamp
```

## 🔄 Data Flow

### News Fetching & Storage Flow:
```
1. FetchNewsArticlesJob dispatched (manual or scheduled)
   ↓
2. NewsService.getStockNews(symbol) - Fetches from APIs
   ↓
3. NewsService.bulkStoreForStock(stock, articles) - Saves to DB
   ↓
4. AnalyzeNewsSentimentJob dispatched (auto, 10s delay)
   ↓
5. Keywords analyzed, sentiment_score calculated
   ↓
6. Articles updated with sentiment scores
   ↓
7. Prediction API queries today's news sentiment
   ↓
8. Python model factors in news sentiment (TODO)
   ↓
9. Frontend displays detailed breakdown (TODO)
```

## 🎯 Next Steps (Priority Order)

1. **HIGH**: Integrate today's news sentiment into Python prediction model
2. **HIGH**: Test end-to-end flow with multiple stocks
3. **MEDIUM**: Enhance PredictionCardV2 with news + technical breakdown
4. **MEDIUM**: Add scheduled news fetching for all popular stocks
5. **LOW**: Add news article caching and cleanup (delete old articles >30 days)

## 📝 Testing Commands

```powershell
# Fetch news for AVGO
Invoke-WebRequest -Uri "http://localhost:8000/api/stocks/AVGO/news/fetch" -Method POST

# Get today's news with sentiment
Invoke-WebRequest -Uri "http://localhost:8000/api/stocks/AVGO/news/today"

# Get recent news (7 days)
Invoke-WebRequest -Uri "http://localhost:8000/api/stocks/AVGO/news?days=7&limit=20"

# Generate prediction (should include news sentiment once integrated)
Invoke-WebRequest -Uri "http://localhost:8000/api/predictions/AVGO"

# Check database
docker exec market-prediction-mysql mysql -u root -proot_password market_prediction -e "SELECT title, sentiment_score, published_at FROM news_articles WHERE stock_id = (SELECT id FROM stocks WHERE symbol='AVGO') ORDER BY published_at DESC LIMIT 10;"
```

## 🎉 Summary

**Completed:** News storage system with sentiment analysis is fully functional and tested
**Remaining:** Integration into prediction scoring + frontend display enhancements
**Estimated Time:** 2-3 hours for prediction integration + 3-4 hours for frontend
