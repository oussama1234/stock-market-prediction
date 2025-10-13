<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Prediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_id', 'direction', 'confidence_score', 'predicted_change_percent',
        'timeframe', 'current_price', 'predicted_price', 'predicted_low', 'predicted_high',
        'actual_price', 'accuracy', 'target_price', 'target_date', 'reasoning', 'indicators',
        'sentiment_score', 'price_trend', 'news_count', 'model_version', 'prediction_date',
        'expires_at', 'is_active',
        // New extended fields
        'label', 'probability', 'scenario', 'indicators_snapshot', 'trigger_keywords', 'horizon',
    ];

    protected $casts = [
        'confidence_score' => 'integer',
        'predicted_change_percent' => 'decimal:4',
        'current_price' => 'decimal:2',
        'predicted_price' => 'decimal:2',
        'predicted_low' => 'decimal:2',
        'predicted_high' => 'decimal:2',
        'actual_price' => 'decimal:2',
        'accuracy' => 'decimal:2',
        'target_price' => 'decimal:2',
        'price_trend' => 'decimal:4',
        'sentiment_score' => 'decimal:4',
        'indicators' => 'array',
        'news_count' => 'integer',
        'prediction_date' => 'datetime',
        'target_date' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        // New extended casts
        'probability' => 'decimal:4',
        'indicators_snapshot' => 'array',
        'trigger_keywords' => 'array',
    ];

    // Relationships
    public function stock(): BelongsTo { return $this->belongsTo(Stock::class); }

    // Scopes
    public function scopeActive(Builder $query): Builder {
        return $query->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }
    
    public function scopeRecent(Builder $query): Builder {
        return $query->orderBy('prediction_date', 'desc');
    }
    
    public function scopeBullish(Builder $query): Builder {
        return $query->where('direction', 'up');
    }
    
    public function scopeBearish(Builder $query): Builder {
        return $query->where('direction', 'down');
    }
    
    public function scopeHighConfidence(Builder $query, float $threshold = 0.7): Builder {
        return $query->where('confidence_score', '>=', $threshold);
    }

    // Helper methods
    public function isBullish(): bool { return $this->direction === 'up'; }
    public function isBearish(): bool { return $this->direction === 'down'; }
    public function isExpired(): bool {
        return $this->expires_at && $this->expires_at->isPast();
    }
    
    public function getConfidenceLevel(): string {
        if ($this->confidence_score >= 0.8) return 'high';
        if ($this->confidence_score >= 0.5) return 'medium';
        return 'low';
    }
}
