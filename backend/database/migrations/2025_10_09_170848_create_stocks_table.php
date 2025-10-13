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
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 10)->unique(); // Stock ticker symbol
            $table->string('name')->nullable(); // Company name
            $table->string('exchange', 10)->nullable(); // Exchange (NASDAQ, NYSE, etc.)
            $table->string('currency', 5)->default('USD');
            $table->string('country', 3)->nullable();
            $table->string('type', 20)->default('equity'); // equity, etf, etc.
            $table->string('industry')->nullable();
            $table->string('sector')->nullable();
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('website')->nullable();
            $table->bigInteger('market_cap')->nullable();
            $table->bigInteger('shares_outstanding')->nullable();
            $table->json('metadata')->nullable(); // Additional data from APIs
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index('symbol');
            $table->index(['exchange', 'symbol']);
            $table->index('last_fetched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
