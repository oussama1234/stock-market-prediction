<?php

namespace App\Console\Commands;

use App\Models\Stock;
use App\Services\StockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchStockPrices extends Command
{
    protected $signature = 'stocks:fetch-prices';
    protected $description = 'Fetch current prices for all stocks and store in database';

    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        parent::__construct();
        $this->stockService = $stockService;
    }

    public function handle()
    {
        $this->info('Fetching prices for all stocks...');
        
        $stocks = Stock::all();
        $successCount = 0;
        $failCount = 0;
        
        $bar = $this->output->createProgressBar($stocks->count());
        $bar->start();
        
        foreach ($stocks as $stock) {
            try {
                // Get quote
                $quote = $this->stockService->getQuote($stock->symbol);
                
                if ($quote && isset($quote['current_price'])) {
                    // Store price data
                    $this->stockService->storePriceData($stock, $quote);
                    $successCount++;
                    $this->line("\n✓ {$stock->symbol}: \${$quote['current_price']}");
                } else {
                    $failCount++;
                    $this->line("\n✗ {$stock->symbol}: No quote data");
                }
                
                $bar->advance();
                
                // Small delay to avoid rate limiting
                usleep(100000); // 0.1 second
                
            } catch (\Exception $e) {
                $failCount++;
                $this->error("\n✗ {$stock->symbol}: " . $e->getMessage());
                $bar->advance();
            }
        }
        
        $bar->finish();
        
        $this->newLine(2);
        $this->info("Completed!");
        $this->info("Success: {$successCount}");
        $this->error("Failed: {$failCount}");
        
        return 0;
    }
}
