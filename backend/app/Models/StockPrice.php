<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class StockPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_id', 'open', 'high', 'low', 'close', 'previous_close',
        'change', 'change_percent', 'volume', 'interval', 'price_date', 'source',
    ];

    protected $casts = [
        'open' => 'decimal:2',
        'high' => 'decimal:2',
        'low' => 'decimal:2',
        'close' => 'decimal:2',
        'previous_close' => 'decimal:2',
        'change' => 'decimal:2',
        'change_percent' => 'decimal:4',
        'volume' => 'integer',
        'price_date' => 'datetime',
    ];

    // Relationships
    public function stock(): BelongsTo { return $this->belongsTo(Stock::class); }

    // Scopes
    public function scopeLatest(Builder $query): Builder {
        return $query->orderBy('price_date', 'desc');
    }
    
    public function scopeForInterval(Builder $query, string $interval): Builder {
        return $query->where('interval', $interval);
    }
    
    public function scopeInDateRange(Builder $query, $from, $to): Builder {
        return $query->whereBetween('price_date', [$from, $to]);
    }

    // Helper methods
    public function getPerformance(): array {
        return [
            'change' => $this->change,
            'change_percent' => $this->change_percent,
            'is_up' => $this->change >= 0,
        ];
    }
}
