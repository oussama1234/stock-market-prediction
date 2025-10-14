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
            $table->string('category', 50)->nullable()->after('sector')->comment('Stock category: Technology, Finance, Healthcare, Consumer, Energy, etc.');
            $table->decimal('volatility_multiplier', 3, 2)->default(1.00)->after('category')->comment('Volatility multiplier for predictions (1.0 = normal, 1.5 = high volatility)');
        });
        
        // Categorize existing stocks
        DB::table('stocks')->where('symbol', 'AVGO')->update(['category' => 'Technology', 'volatility_multiplier' => 1.8]);
        DB::table('stocks')->where('symbol', 'NVDA')->update(['category' => 'Technology', 'volatility_multiplier' => 2.0]);
        DB::table('stocks')->where('symbol', 'MSFT')->update(['category' => 'Technology', 'volatility_multiplier' => 1.5]);
        DB::table('stocks')->where('symbol', 'AAPL')->update(['category' => 'Technology', 'volatility_multiplier' => 1.5]);
        DB::table('stocks')->where('symbol', 'GOOGL')->update(['category' => 'Technology', 'volatility_multiplier' => 1.6]);
        DB::table('stocks')->where('symbol', 'META')->update(['category' => 'Technology', 'volatility_multiplier' => 1.7]);
        DB::table('stocks')->where('symbol', 'TSLA')->update(['category' => 'Technology', 'volatility_multiplier' => 2.5]);
        DB::table('stocks')->where('symbol', 'AMZN')->update(['category' => 'Technology', 'volatility_multiplier' => 1.6]);
        DB::table('stocks')->where('symbol', 'TSM')->update(['category' => 'Technology', 'volatility_multiplier' => 1.8]);
        DB::table('stocks')->where('symbol', 'ADBE')->update(['category' => 'Technology', 'volatility_multiplier' => 1.5]);
        DB::table('stocks')->where('symbol', 'CRM')->update(['category' => 'Technology', 'volatility_multiplier' => 1.6]);
        DB::table('stocks')->where('symbol', 'NFLX')->update(['category' => 'Technology', 'volatility_multiplier' => 1.8]);
        
        DB::table('stocks')->where('symbol', 'JPM')->update(['category' => 'Finance', 'volatility_multiplier' => 1.3]);
        DB::table('stocks')->where('symbol', 'V')->update(['category' => 'Finance', 'volatility_multiplier' => 1.2]);
        DB::table('stocks')->where('symbol', 'MA')->update(['category' => 'Finance', 'volatility_multiplier' => 1.2]);
        DB::table('stocks')->where('symbol', 'BRK.A')->update(['category' => 'Finance', 'volatility_multiplier' => 1.1]);
        
        DB::table('stocks')->where('symbol', 'JNJ')->update(['category' => 'Healthcare', 'volatility_multiplier' => 1.0]);
        DB::table('stocks')->where('symbol', 'UNH')->update(['category' => 'Healthcare', 'volatility_multiplier' => 1.2]);
        
        DB::table('stocks')->where('symbol', 'WMT')->update(['category' => 'Consumer', 'volatility_multiplier' => 1.0]);
        DB::table('stocks')->where('symbol', 'HD')->update(['category' => 'Consumer', 'volatility_multiplier' => 1.1]);
        DB::table('stocks')->where('symbol', 'DIS')->update(['category' => 'Consumer', 'volatility_multiplier' => 1.3]);
        DB::table('stocks')->where('symbol', 'PG')->update(['category' => 'Consumer', 'volatility_multiplier' => 0.9]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn(['category', 'volatility_multiplier']);
        });
    }
};
