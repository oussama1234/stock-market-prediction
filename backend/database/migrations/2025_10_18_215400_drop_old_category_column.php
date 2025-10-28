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
        // Drop columns only if they exist to avoid SQL errors on fresh databases
        if (Schema::hasColumn('stocks', 'category')) {
            Schema::table('stocks', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }

        if (Schema::hasColumn('stocks', 'volatility_multiplier')) {
            Schema::table('stocks', function (Blueprint $table) {
                $table->dropColumn('volatility_multiplier');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            if (!Schema::hasColumn('stocks', 'category')) {
                $table->string('category', 100)->nullable();
            }
            if (!Schema::hasColumn('stocks', 'volatility_multiplier')) {
                $table->decimal('volatility_multiplier', 5, 2)->nullable();
            }
        });
    }
};
