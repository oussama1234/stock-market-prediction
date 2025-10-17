# Score Analysis: Why Sentiment & Volume Are Low/Negative

## Problem 1: Sentiment Score = -0.005 (Despite Strong Articles)

### Root Cause
The sentiment score is extremely low (-0.005) because it's based on the **average sentiment from the database**, not individual strong article keywords.

**Current Flow:**
```php
$rawSentiment = $stock->getAverageSentiment() ?? 0.0;  // Gets average from ALL news articles
$sentiment = $rawSentiment / 10.0;  // Normalize to -1 to +1

// Result: If 50 articles average 0.5/10, final sentiment = 0.05
// But if just 1 negative article exists, it drags average down!
```

### Why This Happens

1. **Averaging Effect**: One negative article can cancel out many positive ones
2. **No Keyword Boost**: The system isn't detecting strong keywords like "earnings beat", "record revenue"
3. **Missing Recent News Weight**: Old negative articles still drag down the average

### Solution

**Boost sentiment calculation with keyword detection:**

```python
# In quick_model_v6.py _calculate_sentiment_score_v2():

# Current: Only uses raw average sentiment
# FIX: Also check for bullish/bearish keywords

bullish_keywords = {
    'earnings_beat': 2.0, 'beat_estimates': 2.0, 'strong_earnings': 1.8,
    'record_revenue': 1.8, 'guidance_raise': 1.8, 'product_launch': 1.5,
    'upgrade': 1.7, 'breakthrough': 1.5
}

bearish_keywords = {
    'earnings_miss': -2.0, 'weak_earnings': -1.8, 'revenue_decline': -1.6,
    'guidance_cut': -1.8, 'downgrade': -1.7, 'bankruptcy': -2.0
}

# Weight recent news MORE heavily than old news
```

---

## Problem 2: Volume Score = -0.3 (Negative Despite High Volume)

### Root Cause
Volume score is **NEGATIVE** because the calculation combines volume ratio with **price change**:

**Current Code (quick_model_v6.py line 486-512):**
```python
volume_ratio = features.get('volume_ratio', 1.0)  # Ratio of current vol to avg
price_change = features.get('price_change_1d', 0)  # 1-day price change

if volume_ratio > 2.0:
    if price_change > 1.5:
        score += 0.85  # Only BULLISH if high volume + big price UP
    elif price_change < -1.5:
        score -= 0.85  # BEARISH if high volume + big price DOWN
    elif price_change < 0:
        score -= 0.5   # <-- THIS IS THE PROBLEM!
```

### Why Volume = -0.3

For AVGO:
- Volume ratio = 1.2 (20% above average) ✓ Good
- Price change = +0.5% (small increase)
- BUT the calculation goes: `score += np.tanh(0.5) * 0.2 = +0.0996`

**However**, if price_change was negative on the same day:
- The negative volume confirmation triggered: `score -= 0.5`
- Result: Volume score becomes negative!

### Solution

**Separate volume analysis from price direction:**

```python
def _calculate_volume_score_v2(self, features: Dict) -> float:
    """
    Enhanced volume analysis - separate from price action
    """
    score = 0.0
    volume_ratio = features.get('volume_ratio', 1.0)
    
    # HIGH VOLUME IS ALWAYS BULLISH (shows market interest)
    # Only question is: what direction?
    
    if volume_ratio > 2.0:
        score += 0.7  # Very high volume = strong conviction
    elif volume_ratio > 1.5:
        score += 0.5  # High volume = good participation
    elif volume_ratio > 1.2:
        score += 0.3  # Elevated volume = some interest
    elif volume_ratio > 1.0:
        score += 0.1  # Slightly above average
    else:
        score -= 0.1  # Below average volume = weak signal
    
    # Optional: Confirm direction with price change
    # But don't penalize high volume for small price moves
    price_change = features.get('price_change_1d', 0)
    if volume_ratio > 1.5:
        if price_change > 1.0:
            score += 0.2  # Boost for strong directional move
        # Don't penalize if price didn't move much
    
    return np.clip(score, -1, 1)
```

---

## Recommended Fixes

### Fix 1: Enhance Sentiment Detection
**File:** `backend/python/models/quick_model_v6.py` line 319-399

```python
def _calculate_sentiment_score_v2(self, features: Dict) -> float:
    score = 0.0
    count = 0
    
    # 1. Base sentiment from database
    news_sentiment = features.get('news_sentiment_score', 0)
    news_count = features.get('news_count', 0)
    if news_count > 0:
        # Weight by recency (recent news more important)
        weight = min(news_count / 15, 1.0)
        score += news_sentiment * weight * 0.9
        count += 1
    
    # 2. KEYWORD DETECTION (This is missing!)
    # Check if recent articles contain strong positive/negative keywords
    news_keywords = features.get('news_keywords', [])
    if news_keywords:
        # Look for: "beat", "revenue", "product", "partnership"
        keyword_score = self._analyze_news_keywords(news_keywords)
        if keyword_score != 0:
            score += keyword_score * 0.6  # 60% weight to keywords
            count += 1
    
    # 3. Boost if multiple strong signals align
    has_surge_keywords = features.get('has_surge_keywords', False)
    if has_surge_keywords and news_count > 3:
        score += 0.4  # Significant boost for multiple bullish articles
        count += 1
    
    return np.clip(score / max(count, 1), -1, 1)
```

### Fix 2: Separate Volume from Price Action
**File:** `backend/python/models/quick_model_v6.py` line 475-524

Replace the entire `_calculate_volume_score_v2` function with:

```python
def _calculate_volume_score_v2(self, features: Dict) -> float:
    """
    Volume analysis - measure of market conviction/participation
    High volume = strong signal regardless of price direction
    """
    score = 0.0
    volume_ratio = features.get('volume_ratio', 1.0)
    
    # Volume scoring (independent of price)
    if volume_ratio > 3.0:
        score = 0.9   # Exceptional volume
    elif volume_ratio > 2.0:
        score = 0.8   # Very high volume
    elif volume_ratio > 1.5:
        score = 0.6   # High volume
    elif volume_ratio > 1.2:
        score = 0.4   # Elevated volume
    elif volume_ratio > 1.0:
        score = 0.15  # Slightly above average
    else:
        score = -0.1  # Below average = weak participation
    
    # Optional: Confirm with price direction (don't penalize!)
    price_change = features.get('price_change_1d', 0)
    if volume_ratio > 1.5 and price_change > 1.0:
        score += 0.1  # Small boost for direction confirmation
    elif volume_ratio > 1.5 and price_change < -1.0:
        score -= 0.1  # Small penalty for direction disagreement
    
    return np.clip(score, -1, 1)
```

---

## Testing the Fixes

### For Sentiment:
```bash
# Test with strong news articles
python test_model.py  # Should show sentiment > 0.3 for AVGO with recent earnings articles
```

### For Volume:
```bash
# AVGO has good volume (1.2x average)
# Should show volume score > 0.3 regardless of small price moves
python test_model.py  # Should show volume: 0.4 (not -0.3)
```

---

## Summary

| Issue | Current | Root Cause | Fix |
|-------|---------|-----------|-----|
| **Sentiment -0.005** | Too low | Averaging dilutes strong keywords | Add keyword detection & boost recent news |
| **Volume -0.3** | Negative | Penalizes high vol with small price change | Decouple volume from price direction |

These fixes will:
- ✅ Boost sentiment for stocks with recent strong articles (earnings beats, launches)
- ✅ Show positive volume scores when volume is genuinely elevated
- ✅ Provide more accurate and intuitive scoring
