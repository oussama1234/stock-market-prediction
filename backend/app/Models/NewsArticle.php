<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class NewsArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_id', 'title', 'description', 'content', 'url', 'image_url',
        'source', 'author', 'published_at', 'sentiment_score', 'sentiment_label',
        'entities', 'category', 'language',
        'is_important', 'importance_date', 'expected_surge_percent', 'surge_keywords',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'sentiment_score' => 'decimal:4',
        'entities' => 'array',
        'is_important' => 'boolean',
        'importance_date' => 'date',
        'expected_surge_percent' => 'decimal:2',
        'surge_keywords' => 'array',
    ];

    // Relationships
    public function stock(): BelongsTo { return $this->belongsTo(Stock::class); }

    // Scopes
    public function scopeRecent(Builder $query): Builder {
        return $query->orderBy('published_at', 'desc');
    }
    
    public function scopePositive(Builder $query): Builder {
        return $query->where('sentiment_score', '>', 0);
    }
    
    public function scopeNegative(Builder $query): Builder {
        return $query->where('sentiment_score', '<', 0);
    }
    
    public function scopeBySource(Builder $query, string $source): Builder {
        return $query->where('source', $source);
    }
    
    public function scopeImportant(Builder $query): Builder {
        return $query->where('is_important', true);
    }
    
    public function scopeForDate(Builder $query, $date): Builder {
        return $query->whereDate('importance_date', $date);
    }
    
    public function scopeImportantToday(Builder $query): Builder {
        return $query->where('is_important', true)
                    ->whereDate('importance_date', now()->toDateString());
    }

    // Helper methods
    public function getSentimentLabel(): string {
        if ($this->sentiment_score > 0.2) return 'positive';
        if ($this->sentiment_score < -0.2) return 'negative';
        return 'neutral';
    }
    
    public function isPositive(): bool { return $this->sentiment_score > 0; }
    public function isNegative(): bool { return $this->sentiment_score < 0; }
    
    public function isImportantForToday(): bool {
        return $this->is_important && 
               $this->importance_date && 
               $this->importance_date->isToday();
    }
    
    public function hasSurgeExpectation(): bool {
        return $this->expected_surge_percent !== null && $this->expected_surge_percent >= 6.0;
    }
}
