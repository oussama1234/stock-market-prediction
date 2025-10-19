# Category-Based Stock Prediction Setup Guide

## Overview
This guide helps you complete the category-based stock prediction implementation for `quick_model_v6`. The system now supports:
- **15 stock categories** (Tech Growth, Utilities, Meme Stocks, etc.)
- **NO NEUTRAL predictions** - always BULLISH or BEARISH
- **Category-specific volatility multipliers** (0.6x to 3.5x)
- **Realistic daily price ranges** per category
- **Enhanced prediction ranges** with predicted_low and predicted_high

---

## What's Already Done ✅

### 1. Database Migrations
- ✅ `stock_categories` table created with:
  - `volatility_multiplier` (0.6 - 3.5)
  - `typical_daily_range_min` and `max`
  - `high_momentum` flag
- ✅ `category_id` column added to `stocks` table
- ✅ `predicted_low` and `predicted_high` columns in `predictions` table

### 2. Models & Seeders
- ✅ `StockCategory` model with relationships
- ✅ `StockCategoriesSeeder` with 15 predefined categories
- ✅ `AssignStockCategories` command for auto-assignment

### 3. Python Model (quick_model_v6.py)
- ✅ Removed NEUTRAL labels (always BULLISH or BEARISH)
- ✅ Applied `category_multiplier` to composite scores
- ✅ Category-aware `_expected_move()` function
- ✅ Uses `typical_daily_range_min/max` for predictions

### 4. Backend Integration
- ✅ `PredictionService::prepareStockData()` loads category data
- ✅ Category info passed to Python model
- ✅ `calculateCategoryAwarePriceRange()` method added
- ✅ API responses include category details in stock info

---

## What Needs to Be Done 🔨

### Step 1: Run Database Migrations
```bash
# From D:\Stock-market-predection\backend
php artisan migrate
```

**Expected Output:**
```
2025_10_18_174656_create_stock_categories_table ................. DONE
2025_10_18_174729_add_category_id_to_stocks_table ............... DONE
```

---

### Step 2: Seed Stock Categories
```bash
php artisan db:seed --class=StockCategoriesSeeder
```

**Expected Output:**
```
Stock categories seeded successfully!
```

**This creates 15 categories:**

| Category | Multiplier | Daily Range | Examples |
|----------|-----------|-------------|----------|
| Tech Growth | 2.50 | 2.0% - 8.0% | NVDA, TSLA, PLTR |
| Tech Blue Chip | 1.50 | 1.0% - 4.0% | AAPL, MSFT, GOOGL |
| Semiconductor | 2.20 | 1.5% - 6.0% | AMD, INTC, AVGO |
| Meme Stock | 3.50 | 3.0% - 15.0% | GME, AMC |
| Crypto Related | 3.00 | 2.5% - 12.0% | COIN, MSTR |
| Consumer Staples | 0.70 | 0.3% - 1.5% | PG, KO, WMT |
| Utilities | 0.60 | 0.2% - 1.2% | Low volatility |

---

### Step 3: Assign Categories to Stocks
```bash
php artisan stocks:assign-categories
```

**Expected Output:**
```
Assigning categories to stocks...
████████████████████████████████  100%

✓ Assigned categories to 150 stocks
```

**The command automatically assigns categories based on:**
1. **Symbol mapping** (e.g., NVDA → Tech Growth)
2. **Industry keywords** (e.g., "semiconductor" → Semiconductor)
3. **Sector fallback** (e.g., "technology" → Tech Blue Chip)

---

### Step 4: Verify Category Assignment
```bash
php artisan tinker
```

Then run:
```php
// Check a specific stock
$stock = \App\Models\Stock::with('category')->where('symbol', 'NVDA')->first();
echo $stock->symbol . " → " . $stock->category?->name . " (" . $stock->category?->volatility_multiplier . "x)\n";

// Count stocks per category
\DB::table('stocks')
    ->join('stock_categories', 'stocks.category_id', '=', 'stock_categories.id')
    ->select('stock_categories.name', \DB::raw('count(*) as total'))
    ->groupBy('stock_categories.name')
    ->orderBy('total', 'desc')
    ->get();
```

---

## Testing Predictions 🧪

### Test High Volatility Stock (NVDA)
```bash
curl http://localhost:8000/api/stocks/NVDA/predict?model=v6
```

**Expected:**
- Label: `BULLISH` or `BEARISH` (never NEUTRAL)
- `expected_pct_move`: ±2% to ±8% (Tech Growth range)
- Category info included in response

### Test Low Volatility Stock (PG)
```bash
curl http://localhost:8000/api/stocks/PG/predict?model=v6
```

**Expected:**
- Label: `BULLISH` or `BEARISH`
- `expected_pct_move`: ±0.3% to ±1.5% (Consumer Staples range)

### Test Meme Stock (GME)
```bash
curl http://localhost:8000/api/stocks/GME/predict?model=v6
```

**Expected:**
- Label: `BULLISH` or `BEARISH`
- `expected_pct_move`: ±3% to ±15% (Meme Stock range)

---

## API Response Format

```json
{
  "success": true,
  "data": {
    "label": "BULLISH",
    "probability": 0.68,
    "expected_pct_move": 4.2,
    "final_score": 0.42,
    "model_version": "quick_model_v6",
    "current_price": 850.25,
    "predicted_price": 885.97,
    "predicted_low": 872.10,
    "predicted_high": 899.85
  },
  "stock": {
    "symbol": "NVDA",
    "name": "NVIDIA Corporation",
    "category": {
      "name": "Tech Growth",
      "description": "High-growth technology companies",
      "volatility_multiplier": 2.5,
      "typical_daily_range": {
        "min": 2.0,
        "max": 8.0
      },
      "high_momentum": true
    }
  }
}
```

---

## Troubleshooting 🔧

### Problem: "No category assigned for {symbol}"
**Solution:**
```bash
# Re-run category assignment
php artisan stocks:assign-categories

# Or manually assign a stock
php artisan tinker
$stock = \App\Models\Stock::where('symbol', 'TSLA')->first();
$category = \App\Models\StockCategory::where('name', 'Tech Growth')->first();
$stock->update(['category_id' => $category->id]);
```

### Problem: Model still predicting NEUTRAL
**Solution:**
- Ensure you're using `model=v6` in API call
- Check Python model is `quick_model_v6.py` not v4
- Verify category_multiplier is being passed to Python

### Problem: Predictions too similar across stocks
**Solution:**
- Verify categories are assigned correctly
- Check `category_multiplier` in logs
- Ensure Python model is applying multiplier:
  ```python
  composite_amplified = composite * category_multiplier
  ```

---

## Implementation Details

### Category Multiplier Application

**In Python (`quick_model_v6.py`):**
```python
# Line 137-143
category_multiplier = nz(f.get('category_multiplier'), 1.0)
composite_amplified = composite * category_multiplier
composite_amplified = clip(composite_amplified, -1.0, 1.0)
```

**In PHP (`PredictionService.php`):**
```php
// Line 283-287
if ($stock->category) {
    $stockData['category_multiplier'] = (float) $stock->category->volatility_multiplier;
    $stockData['typical_daily_range_min'] = (float) $stock->category->typical_daily_range_min;
    $stockData['typical_daily_range_max'] = (float) $stock->category->typical_daily_range_max;
}
```

### Expected Move Calculation

The model uses category-specific ranges:
- **Strong signal** (base > 0.7): 70-100% of `typical_max`
- **Moderate signal** (0.3-0.7): Mid-range interpolation
- **Weak signal** (< 0.3): Closer to `typical_min`

Example for NVDA (Tech Growth):
- Strong bullish: +5.6% to +8.0%
- Moderate bullish: +3.0% to +5.5%
- Weak bullish: +2.0% to +3.0%

---

## Verification Checklist

- [ ] Migrations run successfully
- [ ] 15 categories seeded in `stock_categories` table
- [ ] Categories assigned to all stocks
- [ ] Test NVDA prediction (high volatility)
- [ ] Test PG prediction (low volatility)
- [ ] Test GME prediction (meme stock)
- [ ] Verify no NEUTRAL predictions returned
- [ ] Confirm category info in API responses
- [ ] Check logs for category multiplier application

---

## Next Steps (Optional Enhancements)

1. **Dynamic Category Adjustment**: Auto-update multipliers based on recent volatility
2. **Intraday Categories**: Different multipliers for different times of day
3. **Market Regime Adjustment**: Increase all multipliers during high VIX periods
4. **Learning System**: Adjust multipliers based on prediction accuracy

---

## Files Modified

**Migrations:**
- `2025_10_18_174656_create_stock_categories_table.php`
- `2025_10_18_174729_add_category_id_to_stocks_table.php`
- `2025_10_10_015211_add_predicted_ranges_to_predictions_table.php`

**Models:**
- `app/Models/StockCategory.php` (new)
- `app/Models/Stock.php` (added category relationship)

**Services:**
- `app/Services/PredictionService.php` (category support + range calculator)

**Controllers:**
- `app/Http/Controllers/Api/PredictionController.php` (include category in responses)

**Python:**
- `backend/python/models/quick_model_v6.py` (category multiplier + no neutral)

**Seeders:**
- `database/seeders/StockCategoriesSeeder.php`

**Commands:**
- `app/Console/Commands/AssignStockCategories.php`

---

**Status: Ready for deployment! 🚀**

Run the 3 setup commands and test predictions to complete the implementation.
