<?php

namespace App\Console\Commands;

use App\Jobs\DetectReboundAndRegenerateJob;
use App\Jobs\UpdateStockPricesJob;
use App\Jobs\ProcessAllStocksReboundDetectionJob;
use App\Models\Stock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Test command for TSM stock dispatch
 * Tests the full job dispatch pipeline: price update -> rebound detection -> prediction regeneration
 */
class TestTsmDispatch extends Command
{
    protected $signature = 'test:tsm-dispatch 
                            {--force : Force regenerate even without rebound}
                            {--sync : Run synchronously instead of queuing}
                            {--update-price : Update price first before analysis}
                            {--all : Test all stocks (not just TSM)}';

    protected $description = 'Test job dispatch for TSM or all stocks: price update, rebound detection, and prediction regeneration';

    public function handle(): int
    {
        $forceRegenerate = $this->option('force');
        $sync = $this->option('sync');
        $updatePrice = $this->option('update-price');
        $testAll = $this->option('all');

        $this->info('🧪 Testing Stock Job Dispatch System');
        $this->newLine();

        if ($testAll) {
            return $this->testAllStocks($forceRegenerate, $sync, $updatePrice);
        } else {
            return $this->testTsmDispatch($forceRegenerate, $sync, $updatePrice);
        }
    }

    /**
     * Test TSM stock dispatch
     */
    protected function testTsmDispatch(bool $force, bool $sync, bool $updatePrice): int
    {
        $stock = Stock::where('symbol', 'TSM')->first();

        if (!$stock) {
            $this->error('❌ TSM stock not found in database');
            return 1;
        }

        $this->info("Testing dispatch for: {$stock->symbol} - {$stock->name}");
        $this->newLine();

        // Step 1: Update price if requested
        if ($updatePrice) {
            $this->info('📈 Step 1: Updating stock price...');
            try {
                $stockService = app(\App\Services\StockService::class);
                $quote = $stockService->getQuote($stock->symbol);
                
                if ($quote && isset($quote['current_price'])) {
                    $stockService->storePriceData($stock, $quote);
                    $this->info("✓ Price updated: \${$quote['current_price']}");
                    $this->line("  Change: {$quote['change_percent']}%");
                } else {
                    $this->warn('⚠️  Failed to fetch price quote');
                }
            } catch (\Exception $e) {
                $this->error("❌ Price update failed: {$e->getMessage()}");
            }
            $this->newLine();
        }

        // Step 2: Clear caches
        $this->info('🗑️  Step 2: Clearing prediction caches...');
        Cache::forget("prediction_{$stock->id}_today");
        Cache::forget("stock_details_{$stock->symbol}");
        $this->info('✓ Caches cleared');
        $this->newLine();

        // Step 3: Dispatch or run rebound detection
        $this->info('🔍 Step 3: Dispatching rebound detection and prediction regeneration...');
        
        if ($sync) {
            // Run synchronously
            try {
                $this->line('Running synchronously...');
                
                $job = new DetectReboundAndRegenerateJob($stock, $force);
                $job->handle(app(\App\Services\PredictionService::class));
                
                $this->info('✅ Job completed successfully!');
            } catch (\Exception $e) {
                $this->error("❌ Job failed: {$e->getMessage()}");
                $this->line($e->getTraceAsString());
                return 1;
            }
        } else {
            // Dispatch to queue
            try {
                DetectReboundAndRegenerateJob::dispatch($stock, $force)
                    ->onQueue('predictions');
                
                $this->info('✅ Job dispatched to queue: predictions');
                $this->line('Monitor with: php artisan queue:work');
            } catch (\Exception $e) {
                $this->error("❌ Failed to dispatch job: {$e->getMessage()}");
                return 1;
            }
        }
        
        $this->newLine();

        // Step 4: Show results
        $this->showStockStatus($stock);

        return 0;
    }

    /**
     * Test all stocks dispatch
     */
    protected function testAllStocks(bool $force, bool $sync, bool $updatePrice): int
    {
        $this->info('Testing dispatch for ALL stocks');
        $this->newLine();

        if ($updatePrice) {
            $this->warn('⚠️  Skipping price update for all stocks (use --update-price with single stock only)');
            $this->newLine();
        }

        $stocks = Stock::count();
        $this->info("Found {$stocks} stocks in database");
        $this->newLine();

        if ($sync) {
            $this->warn('⚠️  Synchronous mode not recommended for all stocks');
            if (!$this->confirm('Continue anyway?', false)) {
                return 0;
            }
        }

        $this->info('📤 Dispatching batch job...');
        
        try {
            if ($sync) {
                // Run synchronously
                $job = new ProcessAllStocksReboundDetectionJob(false);
                $job->handle();
                $this->info('✅ Batch job completed!');
            } else {
                // Dispatch to queue
                ProcessAllStocksReboundDetectionJob::dispatch(false)
                    ->onQueue('default');
                
                $this->info('✅ Batch job dispatched to queue!');
                $this->line('Monitor with: php artisan queue:work');
            }
        } catch (\Exception $e) {
            $this->error("❌ Batch job failed: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }

    /**
     * Show current stock status
     */
    protected function showStockStatus(Stock $stock): void
    {
        $stock->refresh();

        $this->info('📊 Current Stock Status:');
        $this->newLine();

        // Latest price
        $latestPrice = $stock->latestPrice;
        if ($latestPrice) {
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Symbol', $stock->symbol],
                    ['Current Price', '$' . number_format($latestPrice->close, 2)],
                    ['Open', '$' . number_format($latestPrice->open, 2)],
                    ['High', '$' . number_format($latestPrice->high, 2)],
                    ['Low', '$' . number_format($latestPrice->low, 2)],
                    ['Volume', number_format($latestPrice->volume)],
                    ['Updated', $latestPrice->price_date->format('Y-m-d H:i:s')],
                ]
            );
        } else {
            $this->warn('No price data available');
        }

        $this->newLine();

        // News sentiment
        $newsCount = $stock->newsArticles()->count();
        $recentNews = $stock->newsArticles()
            ->where('published_at', '>=', now()->subHours(48))
            ->count();
        $avgSentiment = $stock->getAverageSentiment() ?? 0;

        $this->info('📰 News & Sentiment:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Articles', $newsCount],
                ['Recent (48h)', $recentNews],
                ['Avg Sentiment', number_format($avgSentiment, 2) . ' / 10'],
                ['Status', $this->getSentimentLabel($avgSentiment)],
            ]
        );

        $this->newLine();

        // Latest prediction
        $prediction = $stock->predictions()
            ->where('horizon', 'today')
            ->latest()
            ->first();

        if ($prediction) {
            $this->info('🎯 Latest Prediction:');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Direction', strtoupper($prediction->label)],
                    ['Confidence', $prediction->confidence . '%'],
                    ['Expected Move', $prediction->expected_pct_move . '%'],
                    ['Generated', $prediction->created_at->diffForHumans()],
                ]
            );
        } else {
            $this->warn('No prediction available yet');
        }
    }

    /**
     * Get sentiment label
     */
    protected function getSentimentLabel(float $sentiment): string
    {
        if ($sentiment >= 5) return '🟢 Bullish';
        if ($sentiment >= 2) return '🟡 Slightly Bullish';
        if ($sentiment >= -2) return '⚪ Neutral';
        if ($sentiment >= -5) return '🟡 Slightly Bearish';
        return '🔴 Bearish';
    }
}
