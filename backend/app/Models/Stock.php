<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol', 'name', 'exchange', 'currency', 'country', 'type',
        'industry', 'sector', 'category', 'volatility_multiplier',
        'description', 'logo_url', 'website',
        'market_cap', 'shares_outstanding', 'metadata', 'last_fetched_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_fetched_at' => 'datetime',
        'market_cap' => 'integer',
        'shares_outstanding' => 'integer',
        'volatility_multiplier' => 'decimal:2',
    ];

    // Relationships
    public function watchlists(): HasMany { return $this->hasMany(Watchlist::class); }
    public function prices(): HasMany { return $this->hasMany(StockPrice::class); }
    public function newsArticles(): HasMany { return $this->hasMany(NewsArticle::class); }
    public function predictions(): HasMany { return $this->hasMany(Prediction::class); }
    
    public function latestPrice() {
        return $this->hasOne(StockPrice::class)->latestOfMany('price_date');
    }
    
    public function activePrediction() {
        return $this->hasOne(Prediction::class)->where('is_active', true)->latest('prediction_date');
    }

    // Scopes
    public function scopeSearch(Builder $query, string $search): Builder {
        return $query->where('symbol', 'LIKE', "%{$search}%")->orWhere('name', 'LIKE', "%{$search}%");
    }
    
    public function scopePopular(Builder $query): Builder { return $query->orderBy('market_cap', 'desc'); }
    public function scopeNeedsFetch(Builder $query, int $minutes = 15): Builder {
        return $query->whereNull('last_fetched_at')->orWhere('last_fetched_at', '<', now()->subMinutes($minutes));
    }

    // Helper methods
    public function getCurrentPrice(): ?float { return $this->latestPrice?->close; }
    public function markAsFetched(): void { $this->update(['last_fetched_at' => now()]); }
    public function getAverageSentiment(): ?float {
        return $this->newsArticles()->whereNotNull('sentiment_score')->avg('sentiment_score');
    }
}
