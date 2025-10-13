<?php

namespace App\Console\Commands;

use App\Services\StockService;
use Illuminate\Console\Command;

class SeedPopularStocks extends Command
{
    protected $signature = 'stocks:seed-popular';
    protected $description = 'Seed database with popular stocks';

    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        parent::__construct();
        $this->stockService = $stockService;
    }

    public function handle()
    {
        $this->info('Seeding popular stocks...');
        
        $popularSymbols = [
            'AAPL', 'MSFT', 'GOOGL', 'AMZN', 'NVDA',
            'TSLA', 'META', 'BRK.B', 'UNH', 'JNJ',
            'V', 'WMT', 'JPM', 'PG', 'MA',
            'HD', 'DIS', 'NFLX', 'ADBE', 'CRM'
        ];
        
        $bar = $this->output->createProgressBar(count($popularSymbols));
        $bar->start();
        
        $successCount = 0;
        $failCount = 0;
        
        foreach ($popularSymbols as $symbol) {
            try {
                $this->line("\n\nProcessing {$symbol}...");
                
                // Create stock
                $stock = $this->stockService->getOrCreateStock($symbol);
                
                if ($stock) {
                    // Fetch and store price
                    $quote = $this->stockService->getQuote($symbol);
                    
                    if ($quote) {
                        $this->stockService->storePriceData($stock, $quote);
                        $this->info("✓ {$symbol}: Created with price \${$quote['current_price']}");
                        $successCount++;
                    } else {
                        $this->warn("✓ {$symbol}: Created but no price data");
                        $successCount++;
                    }
                } else {
                    $this->error("✗ {$symbol}: Failed to create");
                    $failCount++;
                }
                
                $bar->advance();
                
                // Delay to avoid rate limiting
                sleep(1);
                
            } catch (\Exception $e) {
                $this->error("✗ {$symbol}: " . $e->getMessage());
                $failCount++;
                $bar->advance();
            }
        }
        
        $bar->finish();
        
        $this->newLine(2);
        $this->info("Completed!");
        $this->info("Success: {$successCount}");
        $this->error("Failed: {$failCount}");
        
        // Run predictions
        $this->newLine();
        $this->info("Generating predictions...");
        $this->call('predictions:generate-all');
        
        return 0;
    }
}
