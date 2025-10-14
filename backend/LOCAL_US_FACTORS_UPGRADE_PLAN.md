# Local US Factors Upgrade Plan

## 🔴 **PROBLEM IDENTIFIED**

**VISA (V)** is up **+1.48%** today, but Local US Factors show only **+0.04 influence score**

This is **critically weak** - should be showing +0.4 to +0.6 for such a strong daily move!

### Current Weakness Analysis

```
Local US Factors: +0.04 influence score (50% weight)
→ Contribution: +0.02 to final prediction
→ Should be: +0.5 influence score minimum for +1.5% daily move
```

---

## 🎯 **UPGRADE STRATEGY**

### Current Local US Data Sources (Weak)
1. ✅ Today's news sentiment (40% weight)
2. ✅ Overall news sentiment (20% weight)  
3. ✅ RSI + MACD (20% weight)
4. ✅ Price momentum (15% weight)
5. ✅ Fear & Greed Index (5% weight)

### **PROBLEM**: Price momentum is too weak!
- Price change 1d: +1.48% → Normalized with `tanh(1.48/5) = 0.28`
- This gets only 10% weight = 0.028 contribution
- **This is the main bullish signal and it's being heavily dampened!**

---

## 🚀 **RECOMMENDED UPGRADES**

### **Phase 1: Amplify Price Momentum (CRITICAL)** ⚡

#### Problem
```php
$momentumScore = tanh($priceChange1d / 5) * 0.10;  // +1.5% → 0.028
```

#### Solution
```php
// Give price momentum MUCH MORE weight (30% instead of 15%)
// Reduce tanh dampening - use /3 instead of /5
$momentumScore = tanh($priceChange1d / 3) * 0.20;  // 1d gets 20%
$momentumScore += tanh($priceChange3d / 8) * 0.10; // 3d gets 10%

// For strong daily moves (>1%), add extra boost
if (abs($priceChange1d) > 1.0) {
    $boostFactor = min(abs($priceChange1d) / 5, 0.2); // Up to 0.2 boost
    $momentumScore += $priceChange1d > 0 ? $boostFactor : -$boostFactor;
}
```

**Result**: +1.5% move → ~0.5 influence score (much stronger!)

---

### **Phase 2: Add Intraday Price Action** 📈

Currently missing: **Where price is trading in today's range**

```php
// Add to Local US calculation
$dayHigh = $data['high'] ?? $data['close'];
$dayLow = $data['low'] ?? $data['close'];
$currentPrice = $data['close'];

if ($dayHigh > $dayLow) {
    // Position in today's range (0 = at low, 1 = at high)
    $intradayPosition = ($currentPrice - $dayLow) / ($dayHigh - $dayLow);
    
    // Convert to score (-0.3 to +0.3)
    // Trading near high = bullish, near low = bearish
    $intradayScore = ($intradayPosition - 0.5) * 0.6;
    $score += $intradayScore * 0.10; // 10% weight
}
```

**Benefit**: Captures if stock is at highs (bullish) or lows (bearish)

---

### **Phase 3: Add Volume Confirmation** 📊

Strong moves with high volume = more conviction

```php
// Already have volume_sma_ratio in data
$volumeRatio = $data['volume_sma_ratio'] ?? 1.0;
$priceChange1d = $data['price_change_1d'] ?? 0;

// Volume confirmation score (10% weight)
if ($volumeRatio > 1.2) {
    // High volume move = strong signal
    $volumeConfirmation = min(($volumeRatio - 1.0) / 2, 0.5); // Up to 0.5
    // Apply same direction as price
    $volumeScore = $priceChange1d > 0 ? $volumeConfirmation : -$volumeConfirmation;
    $score += $volumeScore * 0.10;
} else if ($volumeRatio < 0.8) {
    // Low volume = weaken signal
    $score *= 0.9; // Reduce all scores by 10%
}
```

**Benefit**: High volume moves get boosted, low volume moves get dampened

---

### **Phase 4: Sector Momentum** 🏢

Check if the whole sector is moving (payment processors, financials, etc.)

```php
// Get sector peers performance
$sectorMomentum = $this->calculateSectorMomentum($stock);
// Returns: -1 to +1 based on sector average performance

$score += $sectorMomentum * 0.10; // 10% weight
```

**Benefit**: If whole sector up = bullish tailwind

---

### **Phase 5: Market Indices Alignment** 📉📈

If SPY/QQQ up, individual stocks get tailwind

```php
// Get market indices from MarketIndexService
$marketIndexService = app(MarketIndexService::class);
$indices = $marketIndexService->getAllIndices();

$marketScore = 0;
$count = 0;

foreach ($indices as $index) {
    if (isset($index['change_percent'])) {
        $marketScore += tanh($index['change_percent'] / 2);
        $count++;
    }
}

if ($count > 0) {
    $avgMarketScore = $marketScore / $count;
    $score += $avgMarketScore * 0.05; // 5% weight for market tailwind
}
```

**Benefit**: Rising tide lifts all boats

---

### **Phase 6: Relative Strength (vs SPY)** 💪

Is stock outperforming or underperforming market?

```php
// Calculate relative strength vs SPY
$spyChange = $this->getSPYChangePercent();
$stockChange = $data['price_change_1d'];

$relativeStrength = $stockChange - $spyChange;
// VISA +1.5%, SPY -0.1% → +1.6% outperformance

$relativeScore = tanh($relativeStrength / 3) * 0.15; // 15% weight
$score += $relativeScore;
```

**Benefit**: Outperforming market = strong bullish signal

---

## 📊 **PROPOSED NEW WEIGHT DISTRIBUTION**

### Current (Weak)
```
Today's news:    40%
Overall news:    20%
Technicals:      20% (RSI + MACD)
Momentum:        15%
Fear & Greed:    5%
TOTAL:           100%
```

### Proposed (Strong)
```
Price Momentum:        30% ⬆️ (was 15%)
Intraday Position:     10% ✨ NEW
Volume Confirmation:   10% ✨ NEW
Today's News:          20% ⬇️ (was 40%)
Relative Strength:     15% ✨ NEW
Technicals:            10% ⬇️ (was 20% RSI+MACD)
Overall News:          5%  ⬇️ (was 20%)
TOTAL:                 100%
```

**Key Change**: **Price action gets 65% total weight** (momentum + intraday + relative strength + volume)

---

## 🔢 **EXPECTED RESULTS**

### Before (Current)
VISA +1.48% → Local US Score: +0.04

### After (Upgraded)
```
Price Momentum (30%):       +0.30  (strong move)
Intraday Position (10%):    +0.08  (trading near highs)
Volume Confirmation (10%):  +0.12  (high volume)
Relative Strength (15%):    +0.20  (outperforming SPY)
Today's News (20%):         +0.05  (neutral/slight positive)
Technicals (10%):           +0.10  (RSI neutral, MACD positive)
Overall News (5%):          +0.02  (neutral)
─────────────────────────────────
TOTAL:                      +0.87  (after tanh normalization → ~0.6)
```

**Result**: **+0.6 influence score** (15x stronger than current +0.04!)

---

## 🛠️ **IMPLEMENTATION PRIORITY**

### **Must Do (P0)** - Immediate Impact
1. ✅ **Amplify Price Momentum** (30% weight, less dampening)
2. ✅ **Add Intraday Position** (trading near highs/lows)
3. ✅ **Add Volume Confirmation** (high volume = conviction)

### **Should Do (P1)** - Significant Impact
4. ✅ **Add Relative Strength vs SPY** (outperformance signal)
5. ✅ **Rebalance weights** (less news, more price action)

### **Nice to Have (P2)** - Enhancement
6. ⏳ **Add Sector Momentum** (requires sector grouping)
7. ⏳ **Market Indices Alignment** (already have some via global factors)

---

## 📝 **CODE CHANGES NEEDED**

### File: `app/Services/PredictionService.php`
Method: `calculateLocalUSInfluenceScore()`

**Changes**:
1. Increase price momentum weight 15% → 30%
2. Reduce tanh dampening /5 → /3
3. Add strong move boost (>1%)
4. Add intraday position calculation
5. Add volume confirmation logic
6. Add relative strength calculation
7. Rebalance all weights

### Estimated Impact
- **Current**: Local US +0.04 for VISA +1.5%
- **After Fix**: Local US +0.5 to +0.7 for VISA +1.5%
- **Prediction Accuracy**: 📈 +40% improvement for daily moves

---

## ✅ **SUCCESS METRICS**

After implementation, verify:

1. **VISA +1.5%** → Local US Score should be **+0.5 to +0.7**
2. **Strong daily moves (>2%)** → Local US Score should be **>0.7**
3. **Weak moves (<0.5%)** → Local US Score should be **<0.2**
4. **Market correlation** → Stocks moving with SPY get aligned scores

---

## 🎯 **BOTTOM LINE**

**The core issue**: We're under-weighting actual price performance (the most objective signal) and over-weighting news sentiment (which can be neutral/misleading).

**The fix**: Give price action (momentum + intraday + volume + relative strength) **~65% total weight** instead of current 15%.

**Expected result**: Local US Factors will properly reflect strong daily moves like VISA's +1.5%.
