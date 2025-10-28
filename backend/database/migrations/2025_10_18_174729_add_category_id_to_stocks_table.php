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
            $table->foreignId('category_id')->nullable()->after('sector')
                  ->constrained('stock_categories')
                  ->onDelete('set null');
            
            $table->boolean('is_popular')->default(false)->after('category_id')
                  ->index()->comment('Popular stocks for homepage/search');
            
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['category_id']);
            $table->dropColumn(['category_id', 'is_popular']);
        });
    }
};
