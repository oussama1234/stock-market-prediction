<?php

namespace App\Jobs;

use App\Models\Stock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Master job to orchestrate sentiment analysis and rebound detection for all stocks
 * This job should be scheduled to run periodically (e.g., hourly or after market close)
 */
class ProcessAllStocksReboundDetectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected bool $reprocessSentiment;
    protected ?array $symbols;

    /**
     * Create a new job instance.
     *
     * @param bool $reprocessSentiment Whether to reprocess existing sentiment scores
     * @param array|null $symbols Specific symbols to process, or null for all
     */
    public function __construct(bool $reprocessSentiment = false, ?array $symbols = null)
    {
        $this->reprocessSentiment = $reprocessSentiment;
        $this->symbols = $symbols;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Starting batch rebound detection and sentiment analysis", [
                'reprocess_sentiment' => $this->reprocessSentiment,
                'specific_symbols' => $this->symbols ? implode(', ', $this->symbols) : 'ALL'
            ]);

            // Get stocks to process
            $query = Stock::query()->with('newsArticles', 'latestPrice');

            if ($this->symbols) {
                $query->whereIn('symbol', $this->symbols);
            }

            $stocks = $query->get();

            if ($stocks->isEmpty()) {
                Log::warning("No stocks found to process");
                return;
            }

            Log::info("Processing {$stocks->count()} stocks");

            $dispatched = 0;
            $skipped = 0;

            foreach ($stocks as $index => $stock) {
                try {
                    // Check if stock has news articles
                    $hasNews = $stock->newsArticles()->exists();
                    
                    if (!$hasNews) {
                        Log::debug("Skipping {$stock->symbol} - no news articles");
                        $skipped++;
                        continue;
                    }

                    // Dispatch sentiment analysis job
                    AnalyzeNewsSentimentJob::dispatch($stock, $this->reprocessSentiment)
                        ->onQueue('sentiment')
                        ->delay(now()->addSeconds($index * 5)); // Stagger jobs to avoid overwhelming system

                    $dispatched++;

                } catch (\Exception $e) {
                    Log::error("Failed to dispatch job for {$stock->symbol}: " . $e->getMessage());
                }
            }

            Log::info("Batch processing initiated", [
                'total_stocks' => $stocks->count(),
                'jobs_dispatched' => $dispatched,
                'skipped' => $skipped
            ]);

            // Schedule a follow-up job to check rebound patterns after sentiment analysis
            $this->scheduleBatchReboundDetection($stocks, $dispatched);

        } catch (\Exception $e) {
            Log::error("Batch rebound detection job failed: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Schedule batch rebound detection after sentiment analysis completes
     */
    protected function scheduleBatchReboundDetection($stocks, int $dispatchedCount): void
    {
        // Calculate delay: give time for sentiment jobs to complete
        // Estimate 10 seconds per job + buffer
        $estimatedDelay = ($dispatchedCount * 10) + 60;

        Log::info("Scheduling batch rebound detection in {$estimatedDelay} seconds");

        // Dispatch individual rebound detection jobs
        foreach ($stocks as $index => $stock) {
            DetectReboundAndRegenerateJob::dispatch($stock)
                ->onQueue('predictions')
                ->delay(now()->addSeconds($estimatedDelay + ($index * 3)));
        }
    }

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 300;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 600;
}
