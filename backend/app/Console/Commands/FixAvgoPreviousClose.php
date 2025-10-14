<?php

namespace App\Console\Commands;

use App\Models\Stock;
use App\Models\StockPrice;
use Illuminate\Console\Command;

class FixAvgoPreviousClose extends Command
{
    protected $signature = 'stocks:fix-avgo';
    protected $description = 'Fix AVGO previous_close to correct value';

    public function handle()
    {
        $stock = Stock::where('symbol', 'AVGO')->first();
        
        if (!$stock) {
            $this->error('AVGO not found!');
            return Command::FAILURE;
        }
        
        $price = StockPrice::where('stock_id', $stock->id)
            ->where('interval', '1day')
            ->orderBy('price_date', 'desc')
            ->first();
        
        if (!$price) {
            $this->error('No price record found for AVGO!');
            return Command::FAILURE;
        }
        
        $oldValue = $price->previous_close;
        $price->previous_close = 324.63;
        $price->save();
        
        $this->info("✅ AVGO previous_close: {$oldValue} → 324.63");
        
        // Clear cache
        \Illuminate\Support\Facades\Cache::forget('quote:AVGO');
        \Illuminate\Support\Facades\Cache::forget('prediction_today_AVGO');
        
        $this->info("✅ Cache cleared for AVGO");
        
        return Command::SUCCESS;
    }
}
