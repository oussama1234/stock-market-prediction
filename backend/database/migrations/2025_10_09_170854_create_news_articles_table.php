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
        Schema::create('news_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('content')->nullable();
            $table->string('url')->unique();
            $table->string('image_url')->nullable();
            $table->string('source', 100);
            $table->string('author')->nullable();
            $table->timestamp('published_at');
            $table->decimal('sentiment_score', 5, 4)->nullable(); // -1 to 1
            $table->string('sentiment_label', 20)->nullable(); // positive, negative, neutral
            $table->json('entities')->nullable(); // Extracted entities (tickers, companies)
            $table->string('category', 50)->nullable();
            $table->string('language', 5)->default('en');
            $table->timestamps();
            
            $table->index('stock_id');
            $table->index('published_at');
            $table->index('sentiment_score');
            $table->index(['stock_id', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_articles');
    }
};
