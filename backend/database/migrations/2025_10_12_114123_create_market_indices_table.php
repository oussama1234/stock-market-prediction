<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('market_indices', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 10)->unique(); // SPY, QQQ, DIA
            $table->string('name', 100); // S&P 500, NASDAQ-100, Dow Jones
            $table->string('index_name', 50); // sp500, nasdaq, dow
            
            // Current Price Data
            $table->decimal('current_price', 12, 2)->nullable();
            $table->decimal('change', 12, 2)->nullable();
            $table->decimal('change_percent', 8, 4)->nullable();
            
            // Trading Volume
            $table->bigInteger('volume')->nullable();
            $table->bigInteger('avg_volume')->nullable();
            
            // Daily Range
            $table->decimal('day_high', 12, 2)->nullable();
            $table->decimal('day_low', 12, 2)->nullable();
            $table->decimal('open_price', 12, 2)->nullable();
            $table->decimal('previous_close', 12, 2)->nullable();
            
            // 52 Week Range
            $table->decimal('week_52_high', 12, 2)->nullable();
            $table->decimal('week_52_low', 12, 2)->nullable();
            
            // Momentum & Trend Analysis
            $table->enum('trend', ['bullish', 'bearish', 'neutral'])->default('neutral');
            $table->decimal('momentum_score', 8, 4)->nullable(); // -100 to +100
            $table->string('momentum_strength', 20)->nullable(); // weak, moderate, strong
            
            // Moving Averages (for trend determination)
            $table->decimal('sma_20', 12, 2)->nullable(); // 20-day simple moving average
            $table->decimal('sma_50', 12, 2)->nullable(); // 50-day simple moving average
            $table->decimal('sma_200', 12, 2)->nullable(); // 200-day simple moving average
            
            // Market Data Timestamps
            $table->timestamp('last_updated')->nullable();
            $table->timestamp('market_close_time')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('symbol');
            $table->index('index_name');
            $table->index(['trend', 'momentum_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_indices');
    }
};
