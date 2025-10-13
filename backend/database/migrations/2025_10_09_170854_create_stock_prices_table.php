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
        Schema::create('stock_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained()->onDelete('cascade');
            $table->decimal('open', 10, 2)->nullable();
            $table->decimal('high', 10, 2)->nullable();
            $table->decimal('low', 10, 2)->nullable();
            $table->decimal('close', 10, 2);
            $table->decimal('previous_close', 10, 2)->nullable();
            $table->decimal('change', 10, 2)->nullable();
            $table->decimal('change_percent', 10, 4)->nullable();
            $table->bigInteger('volume')->nullable();
            $table->string('interval', 10)->default('1day'); // 1min, 5min, 1hour, 1day
            $table->timestamp('price_date');
            $table->string('source', 50)->nullable(); // API source
            $table->timestamps();
            
            $table->index('stock_id');
            $table->index('price_date');
            $table->index(['stock_id', 'price_date', 'interval']);
            $table->unique(['stock_id', 'price_date', 'interval']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_prices');
    }
};
