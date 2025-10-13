<?php

namespace App\Console\Commands;

use App\Models\Stock;
use App\Services\StockService;
use Illuminate\Console\Command;

class FetchHistoricalPrices extends Command
{
    protected $signature = 'fetch:historical-prices {symbol?}';
    
    protected $description = 'Fetch historical price data for stocks';

    public function handle(StockService $stockService)
    {
        $symbol = $this->argument('symbol');
        
        if ($symbol) {
            // Fetch for specific symbol
            $this->info("Fetching historical data for {$symbol}...");
            
            $stock = $stockService->getOrCreateStock($symbol);
            
            if (!$stock) {
                $this->error("Failed to get/create stock: {$symbol}");
                return 1;
            }
            
            $stored = $stockService->fetchHistoricalData($stock, 90);
            
            $this->info("✓ Stored {$stored} days of historical data for {$symbol}");
            
            // Show calculated metrics
            $prices = \App\Models\StockPrice::where('stock_id', $stock->id)
                ->where('interval', '1day')
                ->orderBy('price_date', 'desc')
                ->limit(14)
                ->get();
            
            if ($prices->count() >= 14) {
                $volumes = $prices->pluck('volume')->toArray();
                $avgVol = array_sum($volumes) / count($volumes);
                $lastVol = $prices->first()->volume;
                $volRatio = $avgVol ? ($lastVol / $avgVol) : 1.0;
                
                $this->info("\nMetrics:");
                $this->line("  Volume Ratio: " . round($volRatio, 2));
                $this->line("  Average Volume: " . number_format($avgVol));
                $this->line("  Last Volume: " . number_format($lastVol));
            }
            
            return 0;
        }
        
        // Fetch for all stocks
        $stocks = Stock::all();
        
        $this->info("Fetching historical data for {$stocks->count()} stocks...\n");
        
        $bar = $this->output->createProgressBar($stocks->count());
        $bar->start();
        
        foreach ($stocks as $stock) {
            try {
                $stockService->fetchHistoricalData($stock, 90);
                $bar->advance();
            } catch (\Exception $e) {
                $this->error("\nFailed for {$stock->symbol}: " . $e->getMessage());
            }
        }
        
        $bar->finish();
        $this->info("\n\nDone!");
        
        return 0;
    }
}
