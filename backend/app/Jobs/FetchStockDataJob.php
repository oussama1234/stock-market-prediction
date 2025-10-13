<?php

namespace App\Jobs;

use App\Models\Stock;
use App\Services\StockService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchStockDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Stock $stock;

    /**
     * Create a new job instance.
     */
    public function __construct(Stock $stock)
    {
        $this->stock = $stock;
    }

    /**
     * Execute the job.
     */
    public function handle(StockService $stockService): void
    {
        try {
            Log::info("Fetching stock data for {$this->stock->symbol}");
            
            // Fetch and store latest quote
            $quote = $stockService->getQuote($this->stock->symbol);
            
            if ($quote && isset($quote['current_price'])) {
                // Store price data
                $stockService->storePrice($this->stock, $quote);
                
                Log::info("Successfully fetched stock data for {$this->stock->symbol}");
            } else {
                Log::warning("No quote data available for {$this->stock->symbol}");
            }
            
        } catch (\Exception $e) {
            Log::error("Failed to fetch stock data for {$this->stock->symbol}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;
}
