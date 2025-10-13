<?php

namespace App\Console\Commands;

use App\Jobs\ProcessAllStocksReboundDetectionJob;
use App\Jobs\AnalyzeNewsSentimentJob;
use App\Jobs\DetectReboundAndRegenerateJob;
use App\Models\Stock;
use Illuminate\Console\Command;

class AnalyzeNewsRebounds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analyze:news-rebounds 
                            {--stock= : Specific stock symbol to analyze (optional)}
                            {--all : Process all stocks}
                            {--reprocess : Reprocess articles that already have sentiment scores}
                            {--sync : Run synchronously instead of queuing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze news sentiment for rebound patterns and regenerate predictions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $stockSymbol = $this->option('stock');
        $processAll = $this->option('all');
        $reprocess = $this->option('reprocess');
        $sync = $this->option('sync');

        if (!$stockSymbol && !$processAll) {
            $this->error('Please specify either --stock=SYMBOL or --all');
            return 1;
        }

        $this->info('🔍 Starting news sentiment and rebound analysis...');
        $this->newLine();

        if ($processAll) {
            return $this->processAllStocks($reprocess, $sync);
        } else {
            return $this->processSingleStock($stockSymbol, $reprocess, $sync);
        }
    }

    /**
     * Process all stocks
     */
    protected function processAllStocks(bool $reprocess, bool $sync): int
    {
        $stocks = Stock::with('newsArticles')->get();
        
        $this->info("Found {$stocks->count()} stocks to process");
        
        if ($stocks->isEmpty()) {
            $this->warn('No stocks found in database');
            return 0;
        }

        if ($sync) {
            // Run synchronously
            $bar = $this->output->createProgressBar($stocks->count());
            $bar->start();

            foreach ($stocks as $stock) {
                try {
                    // Analyze sentiment
                    $job = new AnalyzeNewsSentimentJob($stock, $reprocess);
                    $job->handle(app(\App\Services\SentimentService::class));
                    
                    // Detect rebound and regenerate
                    $reboundJob = new DetectReboundAndRegenerateJob($stock);
                    $reboundJob->handle(app(\App\Services\PredictionService::class));
                    
                    $bar->advance();
                } catch (\Exception $e) {
                    $this->error("\nFailed to process {$stock->symbol}: " . $e->getMessage());
                }
            }

            $bar->finish();
            $this->newLine(2);
            $this->info('✅ Processing complete!');
        } else {
            // Queue jobs
            ProcessAllStocksReboundDetectionJob::dispatch($reprocess)
                ->onQueue('default');
            
            $this->info('✅ Jobs dispatched to queue!');
            $this->info('Monitor progress with: php artisan queue:work');
        }

        return 0;
    }

    /**
     * Process a single stock
     */
    protected function processSingleStock(string $symbol, bool $reprocess, bool $sync): int
    {
        $stock = Stock::where('symbol', $symbol)->with('newsArticles')->first();

        if (!$stock) {
            $this->error("Stock {$symbol} not found");
            return 1;
        }

        $this->info("Processing {$stock->symbol} ({$stock->name})");
        $this->newLine();

        if ($sync) {
            // Run synchronously
            $this->info('Step 1/2: Analyzing news sentiment...');
            
            try {
                $job = new AnalyzeNewsSentimentJob($stock, $reprocess);
                $job->handle(app(\App\Services\SentimentService::class));
                $this->info('✓ Sentiment analysis complete');
            } catch (\Exception $e) {
                $this->error('✗ Sentiment analysis failed: ' . $e->getMessage());
                return 1;
            }

            $this->newLine();
            $this->info('Step 2/2: Detecting rebound patterns and regenerating prediction...');
            
            try {
                $reboundJob = new DetectReboundAndRegenerateJob($stock, true);
                $reboundJob->handle(app(\App\Services\PredictionService::class));
                $this->info('✓ Rebound detection and prediction regeneration complete');
            } catch (\Exception $e) {
                $this->error('✗ Rebound detection failed: ' . $e->getMessage());
                return 1;
            }

            $this->newLine();
            $this->info('✅ All tasks completed successfully!');
            
            // Show results
            $this->showStockSummary($stock);
        } else {
            // Queue jobs
            AnalyzeNewsSentimentJob::dispatch($stock, $reprocess)
                ->chain([
                    new DetectReboundAndRegenerateJob($stock, true)
                ])
                ->onQueue('default');
            
            $this->info('✅ Jobs dispatched to queue!');
            $this->info('Monitor progress with: php artisan queue:work');
        }

        return 0;
    }

    /**
     * Show stock summary after processing
     */
    protected function showStockSummary(Stock $stock): void
    {
        $stock->refresh();
        
        $sentiment = $stock->getAverageSentiment() ?? 0;
        $newsCount = $stock->newsArticles()->count();
        $recentNews = $stock->newsArticles()
            ->where('published_at', '>=', now()->subHours(48))
            ->count();

        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total News Articles', $newsCount],
                ['Recent News (48h)', $recentNews],
                ['Average Sentiment', number_format($sentiment, 2) . ' / 10'],
                ['Sentiment Status', $this->getSentimentStatus($sentiment)],
            ]
        );
    }

    /**
     * Get sentiment status with color
     */
    protected function getSentimentStatus(float $sentiment): string
    {
        if ($sentiment >= 5) {
            return '<fg=green>● Bullish</>';
        } elseif ($sentiment >= 2) {
            return '<fg=yellow>● Slightly Bullish</>';
        } elseif ($sentiment >= -2) {
            return '<fg=gray>● Neutral</>';
        } elseif ($sentiment >= -5) {
            return '<fg=yellow>● Slightly Bearish</>';
        } else {
            return '<fg=red>● Bearish</>';
        }
    }
}
