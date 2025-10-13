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
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained()->onDelete('cascade');
            $table->string('prediction', 10); // 'up', 'down', 'neutral'
            $table->decimal('confidence_score', 5, 4); // 0 to 1
            $table->decimal('predicted_change_percent', 10, 4)->nullable();
            $table->string('timeframe', 20)->default('1day'); // 1day, 1week, 1month
            $table->decimal('current_price', 10, 2);
            $table->decimal('target_price', 10, 2)->nullable();
            $table->text('reasoning')->nullable();
            $table->json('indicators')->nullable(); // Technical indicators used
            $table->decimal('sentiment_score', 5, 4)->nullable();
            $table->integer('news_count')->default(0);
            $table->string('model_version', 20)->default('v1.0');
            $table->timestamp('prediction_date');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('stock_id');
            $table->index('prediction_date');
            $table->index(['stock_id', 'is_active']);
            $table->index(['prediction_date', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
