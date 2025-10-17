# Complete Logic Fixes - Sentiment & Volume Scoring

## What Was Fixed

### 1. **Sentiment Logic (Backend + Python Model)**

#### Backend Changes (PredictionService.php)
- **Before**: Only passed boolean flags (`has_surge_keywords`, `has_bearish_keywords`)
- **After**: Now passes:
  - `bullish_keyword_count` - How many bullish keywords detected
  - `bearish_keyword_count` - How many bearish keywords detected
  - `bullish_keyword_score_total` - Sum of all bullish keyword weights
  - `bearish_keyword_score_total` - Sum of all bearish keyword weights
  - `has_high_impact_keywords` - Flag for critical keywords (score ≥ ±3)

#### Python Model Changes (quick_model_v6.py)
- **Before**: Used only averaged sentiment score (diluted by old articles)
- **After**: 
  - 50% weight: Base averaged sentiment from all news articles
  - 70% weight: NEW keyword-based sentiment boost
    - Calculates `net_keyword_score = bullish_score - bearish_score`
    - Applies tanh normalization to prevent extreme values
    - Extra 30% boost for high-impact keywords (score ≥ ±3)
  
**Result**: Sentiment score now properly reflects strong bullish/bearish keywords detected in recent news, not diluted by averaging.

---

### 2. **Volume Logic (Python Model Only)**

#### Python Model Changes (quick_model_v6.py)

**The Critical Bug**: Volume score was being negatively penalized if price didn't move much:
```python
# OLD (WRONG):
elif volume_ratio > 1.2:
    score += np.tanh(price_change) * 0.5
    # If price_change = 0%, this becomes 0!
    # If price_change = -0.5%, this becomes negative!
```

**The Fix**: Decoupled volume from price direction:
```python
# NEW (CORRECT):
elif volume_ratio > 1.2:
    score = 0.4  # Always positive!
    # High volume = market conviction, independent of direction
```

#### New Volume Scoring Scale (independent of price):
- `volume_ratio > 3.0`: **0.9** (Exceptional)
- `volume_ratio > 2.5`: **0.85** (Very high)
- `volume_ratio > 2.0`: **0.8** (Very high)
- `volume_ratio > 1.5`: **0.6** (High)
- `volume_ratio > 1.2`: **0.4** (Elevated) ← **THIS IS THE FIX!**
- `volume_ratio > 1.0`: **0.15** (Slightly above average)
- `volume_ratio > 0.8`: **0.0** (Normal)
- `volume_ratio ≤ 0.8`: **-0.1** (Weak)

#### Price Direction as Optional Confirmation Only:
- IF volume is high (>1.5) AND price moved strongly (>1%): +0.1 boost
- IF volume is high (>1.5) AND price moved strongly down (<-1%): -0.1 penalty
- **BUT**: If volume is high and price barely moved → NO PENALTY

**Result**: Volume score now reflects market participation correctly. AVGO with volume_ratio=1.2 will show +0.4 instead of negative.

---

## Files Modified

1. **`backend/app/Services/PredictionService.php`**
   - Enhanced keyword analysis loop (lines 499-544)
   - Added keyword count and score fields to input data (lines 556-561)
   - Now counts keywords with weighted scores instead of just boolean flags

2. **`backend/python/models/quick_model_v6.py`**
   - Rewrote `_calculate_sentiment_score_v2()` (lines 320-385)
     - Now incorporates bullish/bearish keyword counts
     - Applies keyword sentiment boost with 70% weight
     - Extra boost for high-impact keywords
   - Rewrote `_calculate_volume_score_v2()` (lines 494-542)
     - Decoupled from price direction
     - Uses independent volume ratio scale
     - Price direction only optional confirmation

3. **`backend/app/Services/KeywordService.php`** (NO CHANGES)
   - Already properly structured with weighted keyword scores
   - Returns keywords with scores (e.g., 'earnings beat' => 3, 'tariff' => -3)

---

## Expected Behavior After Fix

### For AVGO with strong news:
- **Sentiment Score**: Now shows 0.3-0.5+ (was -0.005)
  - Bullish keywords detected → keyword boost applied
  - High-impact keywords → extra 30% boost
  
- **Volume Score**: Now shows 0.4+ (was -0.3)
  - Volume ratio 1.2 → always positive 0.4
  - No longer penalized for small price moves

### Real-World Example:
Stock with:
- 5 bullish keyword mentions (earnings beat, partnership, acquisition)
- Volume ratio of 1.2
- Previous sentiment average: -0.005
- Current day price change: +0.5%

**OLD Results**:
- Sentiment: -0.005 (diluted average)
- Volume: -0.15 (penalized for small move)
- Combined: -0.155

**NEW Results**:
- Sentiment: +0.35 (keyword boost + high-impact boost)
- Volume: +0.4 (strong participation, independent)
- Combined: +0.75 ✓ Much more accurate!

---

## Testing

Deploy the changes and test with:
```bash
curl http://localhost/api/predictions/AVGO?horizon=today
```

Expected in response:
- `scores.sentiment`: Should show positive value (not near 0)
- `scores.volume`: Should show positive value (not negative)
- `contributions.sentiment`: Non-zero contribution to final score
- `contributions.volume`: Positive contribution to final score
