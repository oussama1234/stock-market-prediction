<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'ai_high_confidence' to the scenario_type ENUM
        DB::statement("ALTER TABLE market_scenarios MODIFY COLUMN scenario_type ENUM('bullish','bearish','neutral','momentum_reversal','volatility_breakout','accumulation_phase','distribution_phase','volatility_expansion','ai_high_confidence') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'ai_high_confidence' from the scenario_type ENUM
        DB::statement("ALTER TABLE market_scenarios MODIFY COLUMN scenario_type ENUM('bullish','bearish','neutral','momentum_reversal','volatility_breakout','accumulation_phase','distribution_phase','volatility_expansion') NOT NULL");
    }
};
