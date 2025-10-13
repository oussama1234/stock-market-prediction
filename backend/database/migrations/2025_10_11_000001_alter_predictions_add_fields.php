<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            if (!Schema::hasColumn('predictions', 'label')) {
                $table->string('label')->nullable()->after('direction');
            }
            if (!Schema::hasColumn('predictions', 'probability')) {
                $table->decimal('probability', 5, 4)->nullable()->after('confidence_score');
            }
            if (!Schema::hasColumn('predictions', 'scenario')) {
                $table->string('scenario')->nullable()->after('target_date');
            }
            if (!Schema::hasColumn('predictions', 'indicators_snapshot')) {
                $table->json('indicators_snapshot')->nullable()->after('indicators');
            }
            if (!Schema::hasColumn('predictions', 'trigger_keywords')) {
                $table->json('trigger_keywords')->nullable()->after('indicators_snapshot');
            }
            if (!Schema::hasColumn('predictions', 'horizon')) {
                $table->string('horizon', 16)->default('today')->after('timeframe');
            }
        });

        // Add composite index for stock_id, prediction_date, horizon
        Schema::table('predictions', function (Blueprint $table) {
            $indexes = collect($table->getTable())->toArray();
            // Laravel doesn't expose indexes easily; try/catch to avoid duplicate errors
            try {
                $table->index(['stock_id', 'prediction_date', 'horizon'], 'pred_stock_date_horizon');
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            if (Schema::hasColumn('predictions', 'label')) $table->dropColumn('label');
            if (Schema::hasColumn('predictions', 'probability')) $table->dropColumn('probability');
            if (Schema::hasColumn('predictions', 'scenario')) $table->dropColumn('scenario');
            if (Schema::hasColumn('predictions', 'indicators_snapshot')) $table->dropColumn('indicators_snapshot');
            if (Schema::hasColumn('predictions', 'trigger_keywords')) $table->dropColumn('trigger_keywords');
            if (Schema::hasColumn('predictions', 'horizon')) $table->dropColumn('horizon');
            try { $table->dropIndex('pred_stock_date_horizon'); } catch (\Throwable $e) { }
        });
    }
};
