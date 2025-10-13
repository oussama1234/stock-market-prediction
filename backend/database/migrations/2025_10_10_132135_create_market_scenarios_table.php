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
        Schema::create('market_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            
            // Scenario metadata
            $table->string('timeframe', 20)->default('today'); // 'today', 'tomorrow'
            $table->string('scenario_type', 50); // 'bullish', 'bearish', 'neutral', 'momentum_reversal', 'volatility_breakout'
            $table->string('scenario_name', 100);
            $table->text('description');
            
            // Price predictions
            $table->decimal('expected_change_percent', 8, 4);
            $table->decimal('expected_change_min', 8, 4);
            $table->decimal('expected_change_max', 8, 4);
            $table->decimal('target_price', 12, 2)->nullable();
            $table->decimal('current_price', 12, 2);
            
            // Confidence & probability
            $table->integer('confidence_level')->default(50); // 0-100
            $table->decimal('probability', 5, 2)->nullable(); // 0-100
            
            // Key indicators triggering this scenario (JSON)
            $table->json('trigger_indicators');
            
            // Related news headlines (JSON array)
            $table->json('related_news')->nullable();
            
            // Suggested action
            $table->string('suggested_action', 20); // 'buy', 'sell', 'hold', 'wait'
            $table->text('action_reasoning')->nullable();
            
            // User interaction
            $table->integer('votes_count')->default(0);
            $table->integer('bookmarks_count')->default(0);
            
            // Meta
            $table->dateTime('valid_until');
            $table->boolean('is_active')->default(true);
            $table->string('model_version', 50)->default('v1.0');
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['stock_id', 'timeframe', 'is_active']);
            $table->index(['scenario_type', 'created_at']);
            $table->index('valid_until');
        });
        
        // User interactions with scenarios
        Schema::create('scenario_user_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained('market_scenarios')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('interaction_type', ['vote', 'bookmark']); // Extensible for future interactions
            $table->timestamps();
            
            // Prevent duplicate votes/bookmarks
            $table->unique(['scenario_id', 'user_id', 'interaction_type'], 'scenario_user_unique');
            $table->index(['user_id', 'interaction_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scenario_user_interactions');
        Schema::dropIfExists('market_scenarios');
    }
};
