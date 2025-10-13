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
        Schema::table('predictions', function (Blueprint $table) {
            // Rename prediction to direction
            $table->renameColumn('prediction', 'direction');
            
            // Change confidence_score to integer (0-100)
            $table->integer('confidence_score')->change();
            
            // Add missing columns
            $table->decimal('predicted_price', 10, 2)->nullable()->after('current_price');
            $table->decimal('price_trend', 10, 4)->nullable()->after('sentiment_score');
            $table->decimal('actual_price', 10, 2)->nullable()->after('predicted_price');
            $table->decimal('accuracy', 5, 2)->nullable()->after('actual_price');
            $table->timestamp('target_date')->nullable()->after('prediction_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->renameColumn('direction', 'prediction');
            $table->decimal('confidence_score', 5, 4)->change();
            $table->dropColumn(['predicted_price', 'price_trend', 'actual_price', 'accuracy', 'target_date']);
        });
    }
};
