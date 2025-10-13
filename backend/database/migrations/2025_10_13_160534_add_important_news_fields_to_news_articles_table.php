<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add fields to track important news that should trigger stock surges
     */
    public function up(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            // Flag for important news (mega-cap tech stock news, major announcements)
            $table->boolean('is_important')->default(false)->after('sentiment_label');
            
            // Date when the news is important (today or tomorrow based on market close)
            $table->date('importance_date')->nullable()->after('is_important');
            
            // Expected surge percentage (e.g., 6% for major OpenAI chip deals)
            $table->decimal('expected_surge_percent', 5, 2)->nullable()->after('importance_date');
            
            // Matched keywords that triggered the importance flag
            $table->json('surge_keywords')->nullable()->after('expected_surge_percent');
            
            // Indexes for querying
            $table->index('is_important');
            $table->index('importance_date');
            $table->index(['stock_id', 'importance_date']);
            $table->index(['is_important', 'importance_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropIndex(['stock_id', 'importance_date']);
            $table->dropIndex(['is_important', 'importance_date']);
            $table->dropIndex(['is_important']);
            $table->dropIndex(['importance_date']);
            
            $table->dropColumn([
                'is_important',
                'importance_date',
                'expected_surge_percent',
                'surge_keywords',
            ]);
        });
    }
};
