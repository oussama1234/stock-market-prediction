<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCategory extends Model
{
    protected $fillable = [
        'name',
        'description',
        'volatility_multiplier',
        'typical_daily_range_min',
        'typical_daily_range_max',
        'high_momentum',
    ];
    
    protected $casts = [
        'volatility_multiplier' => 'decimal:2',
        'typical_daily_range_min' => 'decimal:2',
        'typical_daily_range_max' => 'decimal:2',
        'high_momentum' => 'boolean',
    ];
    
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'category_id');
    }
}
