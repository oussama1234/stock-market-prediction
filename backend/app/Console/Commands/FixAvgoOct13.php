<?php

namespace App\Console\Commands;

use App\Models\Stock;
use App\Models\StockPrice;
use Illuminate\Console\Command;

class FixAvgoOct13 extends Command
{
    protected $signature = 'stocks:fix-avgo-oct13';
    protected $description = 'Fix AVGO Oct 13 record to have correct close price';

    public function handle()
    {
        $stock = Stock::where('symbol', 'AVGO')->first();
        
        if (!$stock) {
            $this->error('AVGO not found!');
            return Command::FAILURE;
        }
        
        // Fix Oct 13 record - it should have close = 324.63 (not 356.70)
        $oct13 = StockPrice::where('stock_id', $stock->id)
            ->where('interval', '1day')
            ->whereDate('price_date', '2025-10-13')
            ->first();
        
        if ($oct13) {
            $old = $oct13->close;
            $oct13->close = 324.63;
            $oct13->save();
            $this->info("✅ Fixed Oct 13: close {$old} → 324.63");
        }
        
        // Clear cache
        \Illuminate\Support\Facades\Cache::forget('quote:AVGO');
        \Illuminate\Support\Facades\Cache::forget('prediction_today_AVGO');
        $this->info("✅ Cache cleared");
        
        return Command::SUCCESS;
    }
}
