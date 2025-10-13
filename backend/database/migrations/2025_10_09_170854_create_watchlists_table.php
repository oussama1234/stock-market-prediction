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
        Schema::create('watchlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('stock_id')->constrained()->onDelete('cascade');
            $table->string('notes')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_favorite')->default(false);
            $table->decimal('target_price', 10, 2)->nullable();
            $table->decimal('stop_loss', 10, 2)->nullable();
            $table->timestamp('added_at')->useCurrent();
            $table->timestamps();
            
            $table->unique(['user_id', 'stock_id']);
            $table->index('user_id');
            $table->index('stock_id');
            $table->index(['user_id', 'is_favorite']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('watchlists');
    }
};
