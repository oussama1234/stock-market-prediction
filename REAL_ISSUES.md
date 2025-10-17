# Real Issues: Why Sentiment & Volume Scores Are Wrong

## Issue 1: Sentiment Should Use Strong Keyword Boost

**Current Problem (Line 540):**
```php
'news_sentiment_score' => $sentiment,  // This is AVERAGED from all articles!
'has_surge_keywords' => $has_surge_keywords,  // This is just a boolean!
```

**What's Happening:**
1. Backend detects bullish/bearish keywords (lines 500-532) ✓
2. Sets `has_surge_keywords = true` if any bullish keyword found ✓
3. BUT then sends only the average sentiment to Python (which is diluted!)
4. Python receives average sentiment (-0.005) instead of keyword boost

**Real Fix Needed:**
Pass keyword COUNT and keyword BOOST to the model instead of just average sentiment:

```php
// Count bullish and bearish keywords
$bullish_keyword_count = 0;
$bearish_keyword_count = 0;

foreach ($recentNews as $article) {
    $text = strtolower($article->title . ' ' . $article->description);
    
    // Count bullish keywords
    foreach (array_keys($bullishKeywords) as $keyword) {
        if (str_contains($text, strtolower($keyword))) {
            $bullish_keyword_count++;
        }
    }
    
    // Count bearish keywords  
    foreach (array_keys($bearishKeywords) as $keyword) {
        if (str_contains($text, strtolower($keyword))) {
            $bearish_keyword_count++;
        }
    }
}

// Pass to model
'news_sentiment_score' => $sentiment,
'bullish_keyword_count' => $bullish_keyword_count,
'bearish_keyword_count' => $bearish_keyword_count,
```

Then in Python model:
```python
bullish_count = features.get('bullish_keyword_count', 0)
bearish_count = features.get('bearish_keyword_count', 0)
if bullish_count > bearish_count:
    score += 0.5  # Strong bullish signal!
```

---

## Issue 2: Volume Ratio Should Be Real Volume Not Average

**Current Problem (Line 647-650):**
```php
$volumeSMA = array_sum(array_slice($volumes, -20)) / 20;
$data['volume_sma_ratio'] = $volumeSMA > 0 ? $volume / $volumeSMA : 1.0;
$data['volume_ratio'] = $data['volume_sma_ratio'];
```

**The Bug:**
- `$volume` = today's volume (from `$latestPrice->volume`)
- `$volumeSMA` = average of last 20 days
- Ratio = `today's / average` ✓ THIS IS CORRECT

**BUT the real issue is:**
For AVGO, if today's volume is HUGE, the ratio could be 2.0+ but the code is still calculating it correctly!

**The REAL problem is in the Python model:**
In `quick_model_v6.py` line 486-514, the volume score is being NEGATIVELY impacted if price doesn't move much:

```python
elif volume_ratio > 1.2:
    score += np.tanh(price_change) * 0.5  # <-- WRONG!
    # This means if volume is high but price change is 0, 
    # score becomes tanh(0) * 0.5 = 0
    # Or if price went DOWN, score could be negative!
```

**Real Fix in Python:**

```python
def _calculate_volume_score_v2(self, features: Dict) -> float:
    volume_ratio = features.get('volume_ratio', 1.0)
    
    # Volume alone is a signal of conviction/participation
    # High volume = bullish regardless of direction
    
    if volume_ratio > 3.0:
        return 0.9  # Huge volume
    elif volume_ratio > 2.0:
        return 0.8  # Very high volume
    elif volume_ratio > 1.5:
        return 0.6  # High volume
    elif volume_ratio > 1.2:
        return 0.4  # Elevated volume  <-- THIS SHOULD BE POSITIVE!
    elif volume_ratio > 1.0:
        return 0.15
    else:
        return -0.1  # Low volume
```

---

## Summary of Fixes Needed

| Component | Issue | Fix |
|-----------|-------|-----|
| **Sentiment** | Average dilutes bullish keywords | Pass keyword COUNTS to model, boost sentiment when bullish_keywords > bearish_keywords |
| **Volume** | Negatively penalized for small price moves | Make volume score INDEPENDENT of price direction, always positive for high volume |

These are backend/model logic issues, not data quality issues!
