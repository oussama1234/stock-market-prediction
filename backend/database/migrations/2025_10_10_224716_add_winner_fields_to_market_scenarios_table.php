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
        Schema::table('market_scenarios', function (Blueprint $table) {
            $table->boolean('is_winner')->default(false)->after('is_active');
            $table->decimal('open_price', 10, 2)->nullable()->after('current_price');
            $table->decimal('actual_close_price', 10, 2)->nullable()->after('open_price');
            $table->decimal('actual_change_percent', 10, 4)->nullable()->after('actual_close_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('market_scenarios', function (Blueprint $table) {
            $table->dropColumn(['is_winner', 'open_price', 'actual_close_price', 'actual_change_percent']);
        });
    }
};
