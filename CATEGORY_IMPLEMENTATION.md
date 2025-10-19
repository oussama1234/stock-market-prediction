# Category-Based Stock Movement Implementation

## Overview
This implementation adds a stock categorization system that:
- Removes NEUTRAL predictions (only BULLISH or BEARISH)
- Applies category-specific volatility multipliers
- Provides more realistic and varied stock movements based on category

## Database Changes

### 1. Stock Categories Table
Created `stock_categories` table with:
- `name`: Category name (e.g., 'Tech Growth', 'Utilities')
- `description`: Category description
- `volatility_multiplier`: Movement multiplier (0.5 to 3.5)
- `typical_daily_range_min`: Minimum typical daily % move
- `typical_daily_range_max`: Maximum typical daily % move
- `high_momentum`: Boolean flag for momentum stocks

### 2. Stocks Table Update
Added `category_id` foreign key to `stocks` table linking to `stock_categories`

## Categories Defined

| Category | Multiplier | Daily Range | Examples |
|----------|-----------|-------------|----------|
| Tech Growth | 2.50 | 2.0% - 8.0% | NVDA, TSLA, PLTR |
| Tech Blue Chip | 1.50 | 1.0% - 4.0% | AAPL, MSFT, GOOGL |
| Semiconductor | 2.20 | 1.5% - 6.0% | AMD, INTC, AVGO |
| E-Commerce | 1.80 | 1.2% - 5.0% | AMZN, SHOP |
| Financial Services | 1.20 | 0.8% - 3.0% | JPM, BAC, GS |
| Healthcare | 1.00 | 0.5% - 2.5% | JNJ, UNH, PFE |
| Biotech | 2.80 | 2.5% - 10.0% | High volatility |
| Consumer Staples | 0.70 | 0.3% - 1.5% | PG, KO, WMT |
| Energy | 1.60 | 1.0% - 4.5% | XOM, CVX |
| Utilities | 0.60 | 0.2% - 1.2% | Low volatility |
| Industrials | 1.30 | 0.8% - 3.5% | CAT, BA, GE |
| Meme Stock | 3.50 | 3.0% - 15.0% | GME, AMC |
| Cryptocurrency Related | 3.00 | 2.5% - 12.0% | COIN, MSTR |
| Entertainment Media | 1.70 | 1.0% - 4.5% | NFLX, DIS |
| Real Estate | 0.90 | 0.5% - 2.0% | REITs |

## Python Model Changes (quick_model_v6.py)

### 1. Removed NEUTRAL Label
```python
# OLD: label = 'BULLISH' if composite > 0.08 else 'BEARISH' if composite < -0.08 else 'NEUTRAL'

# NEW: Always BULLISH or BEARISH
if abs(composite_amplified) < 0.01:
    # Use 1-day momentum as tiebreaker
    ch1 = nz(f.get('price_change_1d'), 0)
    label = 'BULLISH' if ch1 >= 0 else 'BEARISH'
else:
    label = 'BULLISH' if composite_amplified > 0 else 'BEARISH'
```

### 2. Applied Category Multiplier
```python
category_multiplier = nz(f.get('category_multiplier'), 1.0)
composite_amplified = composite * category_multiplier
composite_amplified = clip(composite_amplified, -1.0, 1.0)
```

### 3. Category-Aware Movement Calculation
The `_expected_move()` function now:
- Uses category-specific `typical_daily_range_min` and `typical_daily_range_max`
- Scales movements based on signal strength AND category
- Ensures minimum believable movements (no tiny 0.1% predictions)

Example for strong signal (base > 0.7):
```python
# Very strong signal - use 70-100% of typical max
rng = (typical_max * 0.7, typical_max)
```

## Backend Changes (PredictionService.php)

### 1. Load Category Data
```php
if ($stock->category) {
    $stockData['category_multiplier'] = (float) $stock->category->volatility_multiplier;
    $stockData['typical_daily_range_min'] = (float) $stock->category->typical_daily_range_min;
    $stockData['typical_daily_range_max'] = (float) $stock->category->typical_daily_range_max;
    $stockData['high_momentum'] = (bool) $stock->category->high_momentum;
}
```

### 2. Added to Numeric Fields Sanitization
Ensures category data is properly passed to Python model as floats.

## Model Changes

### StockCategory Model
```php
class StockCategory extends Model
{
    protected $fillable = [
        'name', 'description', 'volatility_multiplier',
        'typical_daily_range_min', 'typical_daily_range_max',
        'high_momentum',
    ];
    
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'category_id');
    }
}
```

### Stock Model Update
```php
public function category() {
    return $this->belongsTo(StockCategory::class, 'category_id');
}
```

## Setup Instructions

### 1. Run Migrations
```bash
php artisan migrate
```

This creates:
- `stock_categories` table
- Adds `category_id` column to `stocks` table

### 2. Seed Categories
```bash
php artisan db:seed --class=StockCategoriesSeeder
```

This populates 15 stock categories with appropriate multipliers.

### 3. Assign Categories to Stocks
```bash
php artisan stocks:assign-categories
```

This automatically assigns categories to all stocks based on:
- Specific symbol mappings (e.g., NVDA → Tech Growth)
- Industry keywords (e.g., "semiconductor" → Semiconductor)
- Sector fallbacks (e.g., "technology" → Tech Blue Chip)

## Expected Behavior Changes

### Before:
- Many stocks showed NEUTRAL predictions
- Movement predictions were generic (e.g., 0.5% - 2% for all stocks)
- Utilities moved as much as tech stocks

### After:
- **NO NEUTRAL predictions** - always BULLISH or BEARISH
- **Category-specific movements**:
  - NVDA (Tech Growth, 2.5x): 2% - 8% daily moves
  - PG (Consumer Staples, 0.7x): 0.3% - 1.5% daily moves
  - GME (Meme Stock, 3.5x): 3% - 15% daily moves
- **More believable predictions** aligned with actual stock volatility

## Examples

### High Volatility Stock (NVDA - Tech Growth)
- Category Multiplier: 2.50
- Typical Range: 2.0% - 8.0%
- Strong bullish signal → Prediction: +5.8%
- Weak bearish signal → Prediction: -2.3%

### Low Volatility Stock (KO - Consumer Staples)
- Category Multiplier: 0.70
- Typical Range: 0.3% - 1.5%
- Strong bullish signal → Prediction: +1.2%
- Weak bearish signal → Prediction: -0.5%

### Meme Stock (GME)
- Category Multiplier: 3.50
- Typical Range: 3.0% - 15.0%
- Strong bullish signal → Prediction: +12.5%
- Strong bearish signal → Prediction: -8.7%

## Testing

After setup, test predictions:

```bash
# Test high volatility stock
curl http://localhost/api/predictions/NVDA

# Test low volatility stock
curl http://localhost/api/predictions/PG

# Test meme stock
curl http://localhost/api/predictions/GME
```

You should see:
- No NEUTRAL labels (only BULLISH/BEARISH)
- Different expected_pct_move ranges based on category
- More varied and realistic movement predictions

## Troubleshooting

### If stocks have no category:
```bash
php artisan stocks:assign-categories
```

### If categories are missing:
```bash
php artisan db:seed --class=StockCategoriesSeeder
```

### To check a stock's category:
```php
$stock = Stock::with('category')->find(1);
echo $stock->category->name;
echo $stock->category->volatility_multiplier;
```

## Future Enhancements

1. **Dynamic Category Assignment**: Auto-update categories based on recent volatility
2. **Intraday Categories**: Different multipliers for different times of day
3. **Market Regime Adjustment**: Increase all multipliers during high VIX periods
4. **Learning System**: Adjust multipliers based on prediction accuracy

## Files Modified

- `backend/database/migrations/2025_10_18_174656_create_stock_categories_table.php`
- `backend/database/migrations/2025_10_18_174729_add_category_id_to_stocks_table.php`
- `backend/database/seeders/StockCategoriesSeeder.php`
- `backend/app/Console/Commands/AssignStockCategories.php`
- `backend/app/Models/Stock.php`
- `backend/app/Models/StockCategory.php` (new)
- `backend/app/Services/PredictionService.php`
- `backend/python/models/quick_model_v6.py`

---

**Implementation Complete! 🎉**

No more neutral predictions - every stock gets a clear BULLISH or BEARISH signal with category-appropriate movement expectations.
