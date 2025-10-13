<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Watchlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'stock_id', 'notes', 'display_order',
        'is_favorite', 'target_price', 'stop_loss', 'added_at',
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
        'target_price' => 'decimal:2',
        'stop_loss' => 'decimal:2',
        'added_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function stock(): BelongsTo { return $this->belongsTo(Stock::class); }

    // Scopes
    public function scopeFavorites($query) { return $query->where('is_favorite', true); }
    public function scopeOrdered($query) { return $query->orderBy('display_order'); }
}
