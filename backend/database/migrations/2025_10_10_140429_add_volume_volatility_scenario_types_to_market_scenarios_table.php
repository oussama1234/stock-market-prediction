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
        // Modify the enum to include new scenario types
        DB::statement("ALTER TABLE market_scenarios MODIFY COLUMN scenario_type ENUM(
            'bullish', 
            'bearish', 
            'neutral', 
            'momentum_reversal', 
            'volatility_breakout',
            'accumulation_phase',
            'distribution_phase',
            'volatility_expansion'
        ) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum values
        DB::statement("ALTER TABLE market_scenarios MODIFY COLUMN scenario_type ENUM(
            'bullish', 
            'bearish', 
            'neutral', 
            'momentum_reversal', 
            'volatility_breakout'
        ) NOT NULL");
    }
};
