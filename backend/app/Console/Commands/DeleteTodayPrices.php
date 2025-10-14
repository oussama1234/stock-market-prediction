<?php

namespace App\Console\Commands;

use App\Models\StockPrice;
use Illuminate\Console\Command;

class DeleteTodayPrices extends Command
{
    protected $signature = 'stocks:delete-today-prices';
    protected $description = 'Delete today\'s price records so system uses yesterday\'s data';

    public function handle()
    {
        $today = now()->toDateString();
        
        $deleted = StockPrice::where('interval', '1day')
            ->where('price_date', $today)
            ->delete();
        
        $this->info("✅ Deleted {$deleted} price records for today ({$today})");
        $this->info("💡 System will now use yesterday's prices as previous_close");
        
        // Clear all quote caches
        \Illuminate\Support\Facades\Cache::flush();
        $this->info("✅ Cache flushed");
        
        return Command::SUCCESS;
    }
}
