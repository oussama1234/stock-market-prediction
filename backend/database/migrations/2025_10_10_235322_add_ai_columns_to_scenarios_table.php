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
            $table->integer('ai_confidence')->nullable()->after('confidence_level')->comment('AI prediction confidence (0-100)');
            $table->text('ai_reasoning')->nullable()->after('ai_confidence')->comment('AI-generated reasoning for the prediction');
            $table->decimal('ai_final_score', 8, 4)->nullable()->after('ai_reasoning')->comment('AI final weighted score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('market_scenarios', function (Blueprint $table) {
            $table->dropColumn(['ai_confidence', 'ai_reasoning', 'ai_final_score']);
        });
    }
};
