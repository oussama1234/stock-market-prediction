<?php

namespace App\Console\Commands;

use App\Models\Stock;
use App\Models\StockPrice;
use Illuminate\Console\Command;

class FixPreviousCloses extends Command
{
    protected $signature = 'stocks:fix-previous-closes';
    protected $description = 'Fix previous_close values for all stocks using actual yesterday close';

    public function handle()
    {
        $this->info('Fixing previous_close values for all stocks...');
        
        $stocks = Stock::all();
        $updated = 0;
        $skipped = 0;

        foreach ($stocks as $stock) {
            // Get the last TWO price records
            $prices = StockPrice::where('stock_id', $stock->id)
                ->where('interval', '1day')
                ->orderBy('price_date', 'desc')
                ->limit(2)
                ->get();
            
            if ($prices->count() >= 2) {
                $today = $prices[0];
                $yesterday = $prices[1];
                
                // Update today's record to use yesterday's close as previous_close
                if ($today && $yesterday && $yesterday->close) {
                    $oldPrevClose = $today->previous_close;
                    $today->previous_close = $yesterday->close;
                    $today->save();
                    
                    $updated++;
                    $this->info("✓ {$stock->symbol}: prev_close {$oldPrevClose} → {$yesterday->close}");
                }
            } else {
                $skipped++;
                $this->warn("✗ {$stock->symbol}: Not enough historical data");
            }
        }

        $this->newLine();
        $this->info("✅ Updated: {$updated} stocks");
        $this->info("⚠️  Skipped: {$skipped} stocks (insufficient data)");
        
        // Clear cache to reflect changes
        $this->call('cache:clear');
        
        return Command::SUCCESS;
    }
}
