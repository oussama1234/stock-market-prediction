<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class MarketScenario extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_id', 'user_id', 'timeframe', 'scenario_type', 'scenario_name', 'description',
        'expected_change_percent', 'expected_change_min', 'expected_change_max',
        'target_price', 'current_price', 'open_price', 'actual_close_price', 'actual_change_percent',
        'confidence_level', 'probability',
        'trigger_indicators', 'related_news', 'suggested_action', 'action_reasoning',
        'votes_count', 'bookmarks_count', 'valid_until', 'is_active', 'is_winner', 'model_version',
        'ai_confidence', 'ai_reasoning', 'ai_final_score',
    ];

    protected $casts = [
        'expected_change_percent' => 'decimal:4',
        'expected_change_min' => 'decimal:4',
        'expected_change_max' => 'decimal:4',
        'target_price' => 'decimal:2',
        'current_price' => 'decimal:2',
        'open_price' => 'decimal:2',
        'actual_close_price' => 'decimal:2',
        'actual_change_percent' => 'decimal:4',
        'confidence_level' => 'integer',
        'probability' => 'decimal:2',
        'trigger_indicators' => 'array',
        'related_news' => 'array',
        'votes_count' => 'integer',
        'bookmarks_count' => 'integer',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
        'is_winner' => 'boolean',
        'ai_confidence' => 'integer',
        'ai_final_score' => 'decimal:4',
    ];

    // Relationships
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes with caching for performance
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('valid_until', '>', now());
    }

    public function scopeForStock(Builder $query, int $stockId): Builder
    {
        return $query->where('stock_id', $stockId);
    }

    public function scopeForTimeframe(Builder $query, string $timeframe): Builder
    {
        return $query->where('timeframe', $timeframe);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('scenario_type', $type);
    }

    public function scopeOrderByConfidence(Builder $query): Builder
    {
        return $query->orderBy('confidence_level', 'desc');
    }

    public function scopeOrderByVotes(Builder $query): Builder
    {
        return $query->orderBy('votes_count', 'desc');
    }

    // Helper methods
    public function isBullish(): bool
    {
        return $this->scenario_type === 'bullish';
    }

    public function isBearish(): bool
    {
        return $this->scenario_type === 'bearish';
    }

    public function isExpired(): bool
    {
        return $this->valid_until->isPast();
    }

    public function isWinner(): bool
    {
        return $this->is_winner === true;
    }

    public function getConfidenceLabel(): string
    {
        if ($this->confidence_level >= 80) return 'Very High';
        if ($this->confidence_level >= 65) return 'High';
        if ($this->confidence_level >= 50) return 'Medium';
        if ($this->confidence_level >= 35) return 'Low';
        return 'Very Low';
    }

    public function getColorClass(): string
    {
        return match($this->scenario_type) {
            'bullish' => 'green',
            'bearish' => 'red',
            'neutral' => 'gray',
            'momentum_reversal' => 'purple',
            'volatility_breakout' => 'orange',
            'accumulation_phase' => 'green',
            'distribution_phase' => 'red',
            'volatility_expansion' => 'yellow',
            'ai_high_confidence' => 'blue',
            default => 'blue',
        };
    }
    
    public function isAIPrediction(): bool
    {
        return $this->scenario_type === 'ai_high_confidence';
    }

    // Static method to get cached scenarios for a stock
    public static function getCachedScenariosForStock(int $stockId, string $timeframe = 'today'): \Illuminate\Support\Collection
    {
        $cacheKey = "scenarios:stock:{$stockId}:timeframe:{$timeframe}";
        
        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($stockId, $timeframe) {
            return self::active()
                ->forStock($stockId)
                ->forTimeframe($timeframe)
                ->orderByConfidence()
                ->with('stock:id,symbol,name')
                ->get();
        });
    }

    // Clear cache when scenarios are updated
    protected static function booted()
    {
        static::saved(function ($scenario) {
            self::clearCache($scenario->stock_id);
        });

        static::deleted(function ($scenario) {
            self::clearCache($scenario->stock_id);
        });
    }

    protected static function clearCache(int $stockId): void
    {
        Cache::forget("scenarios:stock:{$stockId}:timeframe:today");
        Cache::forget("scenarios:stock:{$stockId}:timeframe:tomorrow");
    }
}
