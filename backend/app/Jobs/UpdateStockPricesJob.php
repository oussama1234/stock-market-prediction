<?php

namespace App\Jobs;

use App\Models\Stock;
use App\Services\StockService;
use App\Services\MarketIndexService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * UpdateStockPricesJob
 * 
 * Fetches and updates stock prices for all stocks in the database.
 * This job should be dispatched regularly to keep stock data fresh.
 */
class UpdateStockPricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 120;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     * 
     * Fetches all stocks from database and updates their prices
     * using the existing StockService. Also updates market indices.
     */
    public function handle(StockService $stockService, MarketIndexService $marketIndexService): void
    {
        try {
            Log::info('Starting stock prices and market indices update job');
            
            // First, update market indices
            Log::info('Updating market indices...');
            $indicesResults = $marketIndexService->updateAllIndices();
            Log::info('Market indices update completed', $indicesResults);
            
            // Get all stocks from database
            $stocks = Stock::all();
            $totalStocks = $stocks->count();
            
            if ($totalStocks === 0) {
                Log::warning('No stocks found in database to update');
                return;
            }
            
            Log::info("Updating prices for {$totalStocks} stocks");
            
            $successCount = 0;
            $failureCount = 0;
            $errors = [];
            
            // Process each stock
            foreach ($stocks as $stock) {
                try {
                    // Get latest quote from API
                    $quote = $stockService->getQuote($stock->symbol);
                    
                    if ($quote && isset($quote['current_price'])) {
                        // Store/update price data in database
                        $stockService->storePriceData($stock, $quote);
                        
                        $successCount++;
                        Log::debug("Updated price for {$stock->symbol}: \${$quote['current_price']}");
                    } else {
                        $failureCount++;
                        $errors[] = "{$stock->symbol}: No quote data available";
                        Log::warning("No quote data available for {$stock->symbol}");
                    }
                    
                    // Add small delay to avoid API rate limits
                    usleep(100000); // 100ms delay between requests
                    
                } catch (\Exception $e) {
                    $failureCount++;
                    $errors[] = "{$stock->symbol}: {$e->getMessage()}";
                    Log::error("Failed to update {$stock->symbol}: " . $e->getMessage());
                    continue;
                }
            }
            
            // Log summary
            Log::info("Stock prices and market indices update completed", [
                'stocks' => [
                    'total' => $totalStocks,
                    'success' => $successCount,
                    'failed' => $failureCount,
                    'success_rate' => round(($successCount / $totalStocks) * 100, 2) . '%'
                ],
                'indices' => $indicesResults
            ]);
            
            if (!empty($errors)) {
                Log::warning("Stock update errors:", $errors);
            }
            
        } catch (\Exception $e) {
            Log::error('Stock prices update job failed: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('UpdateStockPricesJob permanently failed: ' . $exception->getMessage());
    }
}
