<?php

namespace App\Console\Commands;

use App\Jobs\UpdateStockPricesJob;
use App\Models\Stock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * UpdateAllStockPrices Command
 * 
 * Console command to manually trigger stock price updates for all stocks.
 * Can also be scheduled to run automatically.
 * 
 * Usage:
 * php artisan stocks:update-prices
 */
class UpdateAllStockPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stocks:update-prices
                          {--async : Dispatch as a queued job instead of running synchronously}
                          {--limit= : Limit the number of stocks to update (for testing)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update prices and market data for all stocks in the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting stock prices update...');
        
        // Get count of stocks
        $stockCount = Stock::count();
        
        if ($stockCount === 0) {
            $this->warn('⚠️  No stocks found in database. Nothing to update.');
            return Command::SUCCESS;
        }
        
        $this->info("📊 Found {$stockCount} stocks to update");
        
        // Check if we should dispatch as async job
        if ($this->option('async')) {
            $this->info('📤 Dispatching update job to queue...');
            UpdateStockPricesJob::dispatch();
            $this->info('✅ Job dispatched successfully! Check logs for progress.');
            return Command::SUCCESS;
        }
        
        // Run synchronously
        $this->info('⏳ Updating stocks synchronously...');
        $this->newLine();
        
        try {
            // Create progress bar
            $bar = $this->output->createProgressBar($stockCount);
            $bar->setFormat('verbose');
            $bar->start();
            
            $limit = $this->option('limit');
            $stocks = $limit ? Stock::limit((int)$limit)->get() : Stock::all();
            
            $successCount = 0;
            $failureCount = 0;
            $errors = [];
            
            foreach ($stocks as $stock) {
                try {
                    // Get quote and update
                    $quote = app(\App\Services\StockService::class)->getQuote($stock->symbol);
                    
                    if ($quote && isset($quote['current_price'])) {
                        app(\App\Services\StockService::class)->storePriceData($stock, $quote);
                        $successCount++;
                    } else {
                        $failureCount++;
                        $errors[] = $stock->symbol;
                    }
                    
                    $bar->advance();
                    
                    // Small delay to avoid API rate limits
                    usleep(100000); // 100ms
                    
                } catch (\Exception $e) {
                    $failureCount++;
                    $errors[] = "{$stock->symbol}: {$e->getMessage()}";
                    $bar->advance();
                    continue;
                }
            }
            
            $bar->finish();
            $this->newLine(2);
            
            // Display summary
            $this->info('✅ Update completed!');
            $this->newLine();
            
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Stocks', $stockCount],
                    ['Successfully Updated', $successCount],
                    ['Failed Updates', $failureCount],
                    ['Success Rate', round(($successCount / $stockCount) * 100, 2) . '%'],
                ]
            );
            
            if (!empty($errors)) {
                $this->newLine();
                $this->warn('⚠️  Failed stocks:');
                foreach (array_slice($errors, 0, 10) as $error) {
                    $this->line("  • {$error}");
                }
                if (count($errors) > 10) {
                    $this->line('  ... and ' . (count($errors) - 10) . ' more');
                }
            }
            
            Log::info('Manual stock price update completed', [
                'total' => $stockCount,
                'success' => $successCount,
                'failed' => $failureCount,
            ]);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Update failed: ' . $e->getMessage());
            Log::error('Manual stock update failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
