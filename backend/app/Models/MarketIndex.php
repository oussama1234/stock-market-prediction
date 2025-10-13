<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketIndex extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'market_indices';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'symbol',
        'name',
        'index_name',
        'current_price',
        'change',
        'change_percent',
        'volume',
        'avg_volume',
        'day_high',
        'day_low',
        'open_price',
        'previous_close',
        'week_52_high',
        'week_52_low',
        'trend',
        'momentum_score',
        'momentum_strength',
        'sma_20',
        'sma_50',
        'sma_200',
        'last_updated',
        'market_close_time',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'current_price' => 'decimal:2',
        'change' => 'decimal:2',
        'change_percent' => 'decimal:4',
        'volume' => 'integer',
        'avg_volume' => 'integer',
        'day_high' => 'decimal:2',
        'day_low' => 'decimal:2',
        'open_price' => 'decimal:2',
        'previous_close' => 'decimal:2',
        'week_52_high' => 'decimal:2',
        'week_52_low' => 'decimal:2',
        'momentum_score' => 'decimal:4',
        'sma_20' => 'decimal:2',
        'sma_50' => 'decimal:2',
        'sma_200' => 'decimal:2',
        'last_updated' => 'datetime',
        'market_close_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get trend as a formatted string
     */
    public function getTrendLabelAttribute(): string
    {
        return match($this->trend) {
            'bullish' => '📈 Bullish',
            'bearish' => '📉 Bearish',
            default => '➡️ Neutral',
        };
    }

    /**
     * Check if index is bullish
     */
    public function isBullish(): bool
    {
        return $this->trend === 'bullish';
    }

    /**
     * Check if index is bearish
     */
    public function isBearish(): bool
    {
        return $this->trend === 'bearish';
    }

    /**
     * Get momentum description
     */
    public function getMomentumDescriptionAttribute(): string
    {
        if (!$this->momentum_score) {
            return 'Unknown';
        }

        $score = (float) $this->momentum_score;
        $strength = $this->momentum_strength ?? 'moderate';
        
        if ($score > 50) {
            return ucfirst($strength) . ' Bullish Momentum';
        } elseif ($score < -50) {
            return ucfirst($strength) . ' Bearish Momentum';
        } else {
            return 'Neutral Momentum';
        }
    }

    /**
     * Scope to get specific index by name
     */
    public function scopeByIndexName($query, string $indexName)
    {
        return $query->where('index_name', $indexName);
    }

    /**
     * Scope to get bullish indices
     */
    public function scopeBullish($query)
    {
        return $query->where('trend', 'bullish');
    }

    /**
     * Scope to get bearish indices
     */
    public function scopeBearish($query)
    {
        return $query->where('trend', 'bearish');
    }

    /**
     * Get all indices as associative array
     */
    public static function getAllIndicesArray(): array
    {
        $indices = self::all();
        
        $result = [];
        foreach ($indices as $index) {
            $result[$index->index_name] = [
                'symbol' => $index->symbol,
                'name' => $index->name,
                'current_price' => $index->current_price,
                'change' => $index->change,
                'change_percent' => $index->change_percent,
                'trend' => $index->trend,
                'momentum_score' => $index->momentum_score,
                'momentum_strength' => $index->momentum_strength,
                'volume' => $index->volume,
                'day_high' => $index->day_high,
                'day_low' => $index->day_low,
                'last_updated' => $index->last_updated?->toDateTimeString(),
            ];
        }
        
        return $result;
    }
}
