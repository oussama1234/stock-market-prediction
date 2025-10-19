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
        Schema::table('stocks', function (Blueprint $table) {
            // Drop old category and volatility_multiplier columns
            // We now use the stock_categories relationship instead
            $table->dropColumn(['category', 'volatility_multiplier']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->string('category', 100)->nullable()->after('category_id');
            $table->decimal('volatility_multiplier', 5, 2)->nullable()->after('category');
        });
    }
};
