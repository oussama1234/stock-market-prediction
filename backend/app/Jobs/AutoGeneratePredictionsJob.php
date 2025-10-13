<?php

namespace App\Jobs;

use App\Models\Stock;
use App\Services\EnhancedPredictionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * AutoGeneratePredictionsJob
 * 
 * Automatically generates predictions for all stocks in the background.
 * Uses EnhancedPredictionService with all features:
 * - Sentiment analysis
 * - Keywords detection (tariff, seasons, etc.)
 * - Technical indicators
 * - Fear & Greed Index
 * - News analysis
 * 
 * This job runs automatically via scheduler.
 * The "Generate" button in UI only triggers regeneration.
 */
class AutoGeneratePredictionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 300; // 5 minutes

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 1800; // 30 minutes

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
     * Generates predictions for all stocks that need updates:
     * 1. Stocks without any predictions
     * 2. Stocks with old predictions (>24 hours)
     * 3. Stocks with inactive predictions
     */
    public function handle(EnhancedPredictionService $predictionService): void
    {
        try {
            Log::info('🚀 AutoGeneratePredictionsJob started');
            
            $startTime = microtime(true);
            $stats = [
                'total' => 0,
                'generated' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'errors' => [],
            ];

            // Get all stocks that need predictions
            $stocks = $this->getStocksNeedingPredictions();
            $stats['total'] = $stocks->count();

            if ($stats['total'] === 0) {
                Log::info('✅ No stocks need prediction updates');
                return;
            }

            Log::info("📊 Processing {$stats['total']} stocks for prediction generation/update");

            foreach ($stocks as $stock) {
                try {
                    $hadPrediction = $stock->activePrediction !== null;
                    
                    // Generate prediction using EnhancedPredictionService
                    // This automatically uses all features:
                    // - Sentiment analysis from news
                    // - Priority keywords (tariff, seasons, etc.)
                    // - Technical indicators (RSI, MACD, Bollinger Bands, etc.)
                    // - Fear & Greed Index
                    // - Volume analysis
                    // - Market trend analysis
                    $prediction = $predictionService->generateAdvancedPrediction($stock);

                    if ($prediction) {
                        if ($hadPrediction) {
                            $stats['updated']++;
                            Log::debug("🔄 Updated prediction for {$stock->symbol}: {$prediction->direction} (confidence: {$prediction->confidence_score}%)");
                        } else {
                            $stats['generated']++;
                            Log::debug("✨ Generated new prediction for {$stock->symbol}: {$prediction->direction} (confidence: {$prediction->confidence_score}%)");
                        }
                    } else {
                        $stats['skipped']++;
                        Log::warning("⏭️ Skipped {$stock->symbol}: Could not generate prediction");
                    }

                    // Small delay to avoid overwhelming APIs
                    usleep(200000); // 200ms delay between stocks

                } catch (\Exception $e) {
                    $stats['failed']++;
                    $errorMsg = "{$stock->symbol}: {$e->getMessage()}";
                    $stats['errors'][] = $errorMsg;
                    Log::error("❌ Failed to generate prediction for {$stock->symbol}: {$e->getMessage()}");
                    
                    // Continue with next stock instead of failing entire job
                    continue;
                }
            }

            $duration = round(microtime(true) - $startTime, 2);

            // Log comprehensive summary
            Log::info('✅ AutoGeneratePredictionsJob completed', [
                'duration_seconds' => $duration,
                'total_stocks' => $stats['total'],
                'new_predictions' => $stats['generated'],
                'updated_predictions' => $stats['updated'],
                'skipped' => $stats['skipped'],
                'failed' => $stats['failed'],
                'success_rate' => $stats['total'] > 0 
                    ? round((($stats['generated'] + $stats['updated']) / $stats['total']) * 100, 2) . '%' 
                    : '0%',
            ]);

            if (!empty($stats['errors'])) {
                Log::warning('⚠️ Prediction generation errors', [
                    'error_count' => count($stats['errors']),
                    'errors' => array_slice($stats['errors'], 0, 10), // Log first 10 errors
                ]);
            }

        } catch (\Exception $e) {
            Log::error('💥 AutoGeneratePredictionsJob failed critically: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Get stocks that need predictions
     * 
     * Returns stocks that:
     * 1. Have no active prediction
     * 2. Have predictions older than 24 hours
     * 3. Have inactive predictions
     */
    protected function getStocksNeedingPredictions()
    {
        $cutoffTime = Carbon::now()->subHours(24);

        return Stock::with('activePrediction')
            ->where(function ($query) use ($cutoffTime) {
                // Stocks without active predictions
                $query->whereDoesntHave('activePrediction')
                    // OR stocks with old predictions
                    ->orWhereHas('activePrediction', function ($q) use ($cutoffTime) {
                        $q->where('created_at', '<', $cutoffTime);
                    })
                    // OR stocks with inactive predictions
                    ->orWhereHas('predictions', function ($q) {
                        $q->where('is_active', false);
                    });
            })
            ->orderBy('updated_at', 'asc') // Process oldest first
            ->get();
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('💥 AutoGeneratePredictionsJob failed permanently: ' . $exception->getMessage());
    }
}
