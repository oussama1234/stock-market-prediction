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
            // Add predicted_change_percent if not exists
            if (!Schema::hasColumn('predictions', 'predicted_change_percent')) {
                $table->decimal('predicted_change_percent', 10, 2)->nullable()->after('predicted_price');
            }
            
            // Add reasoning if not exists
            if (!Schema::hasColumn('predictions', 'reasoning')) {
                $table->text('reasoning')->nullable()->after('news_count');
            }
            
            // Add indicators JSON column if not exists
            if (!Schema::hasColumn('predictions', 'indicators')) {
                $table->json('indicators')->nullable()->after('reasoning');
            }
            
            // Add model_version if not exists
            if (!Schema::hasColumn('predictions', 'model_version')) {
                $table->string('model_version', 50)->default('v1.0')->after('indicators');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn([
                'predicted_change_percent',
                'reasoning',
                'indicators',
                'model_version'
            ]);
        });
    }
};
