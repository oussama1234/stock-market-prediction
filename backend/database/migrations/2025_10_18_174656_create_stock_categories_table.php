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
        Schema::create('stock_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique(); // Category name (e.g., 'Tech Growth', 'Stable Value')
            $table->text('description')->nullable();
            $table->decimal('volatility_multiplier', 5, 2)->default(1.00); // Movement multiplier (0.5 to 3.0)
            $table->decimal('typical_daily_range_min', 5, 2)->default(0.5); // Typical min daily % move
            $table->decimal('typical_daily_range_max', 5, 2)->default(2.0); // Typical max daily % move
            $table->boolean('high_momentum')->default(false); // Flag for momentum stocks
            $table->timestamps();
            
            $table->index('name');
            $table->index('volatility_multiplier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_categories');
    }
};
