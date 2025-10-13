<?php

namespace App\Jobs;

use App\Models\Stock;
use App\Models\NewsArticle;
use App\Services\SentimentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Analyze and update news sentiment scores for stock articles
 * Uses enhanced keyword detection for rebound patterns
 */
class AnalyzeNewsSentimentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ?Stock $stock;
    protected bool $reprocessAll;

    /**
     * Create a new job instance.
     *
     * @param Stock|null $stock Specific stock to analyze, or null for all stocks
     * @param bool $reprocessAll Whether to reprocess articles that already have sentiment scores
     */
    public function __construct(?Stock $stock = null, bool $reprocessAll = false)
    {
        $this->stock = $stock;
        $this->reprocessAll = $reprocessAll;
    }

    /**
     * Execute the job.
     */
    public function handle(SentimentService $sentimentService): void
    {
        try {
            $query = NewsArticle::query();

            // Filter by stock if specified
            if ($this->stock) {
                $query->where('stock_id', $this->stock->id);
                Log::info("Analyzing sentiment for {$this->stock->symbol}");
            } else {
                Log::info("Analyzing sentiment for all stocks");
            }

            // Filter by sentiment status
            if (!$this->reprocessAll) {
                $query->whereNull('sentiment_score');
            }

            $articles = $query->with('stock')->get();
            
            if ($articles->isEmpty()) {
                Log::info("No articles to analyze");
                return;
            }

            $updated = 0;
            $reboundDetected = 0;

            foreach ($articles as $article) {
                try {
                    // Calculate sentiment using enhanced keywords
                    $text = $article->title . ' ' . $article->description;
                    $sentiment = $this->calculateEnhancedSentiment($text);
                    
                    // Update article sentiment
                    $article->sentiment_score = $sentiment['score'];
                    $article->save();
                    
                    $updated++;
                    
                    if ($sentiment['is_rebound']) {
                        $reboundDetected++;
                        Log::info("Rebound article detected for {$article->stock->symbol}: {$article->title}", [
                            'sentiment_score' => $sentiment['score'],
                            'matched_keywords' => $sentiment['matched_keywords']
                        ]);
                    }
                    
                } catch (\Exception $e) {
                    Log::error("Failed to analyze sentiment for article {$article->id}: " . $e->getMessage());
                }
            }

            Log::info("Sentiment analysis complete", [
                'total_articles' => $articles->count(),
                'updated' => $updated,
                'rebound_articles' => $reboundDetected
            ]);

            // Dispatch prediction regeneration for affected stocks
            if ($updated > 0) {
                $this->dispatchPredictionUpdates($articles);
            }

        } catch (\Exception $e) {
            Log::error("Sentiment analysis job failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calculate enhanced sentiment with rebound keyword detection
     */
    protected function calculateEnhancedSentiment(string $text): array
    {
        $text = strtolower($text);
        $score = 0;
        $matchedKeywords = [];
        $isRebound = false;

        // HIGH IMPACT BULLISH KEYWORDS (Score: +6 to +9)
        $highBullishKeywords = [
            // Rebound & Recovery
            'rebound' => 8.0,
            'major rebound' => 9.0,
            'strong rebound' => 9.0,
            'rebounds strongly' => 8.5,
            'recovery' => 6.5,
            'major recovery' => 8.0,
            'strong recovery' => 8.0,
            
            // Trump / Tariff Relief (CRITICAL)
            'trump dismisses' => 9.0,
            'trump dismiss' => 9.0,
            'tariff relief' => 8.5,
            'tariff cut' => 8.0,
            'tariffs eased' => 8.0,
            'tariffs lifted' => 8.5,
            'tariff reduction' => 7.5,
            
            // Market Movement
            'surge' => 7.5,
            'surges' => 7.5,
            'soar' => 7.5,
            'soars' => 7.5,
            'rally' => 7.0,
            'rallies' => 7.0,
            'jump' => 7.0,
            'jumps' => 7.0,
            'spike' => 6.5,
            'spikes' => 6.5,
        ];

        // MEDIUM IMPACT BULLISH (Score: +3 to +5)
        $mediumBullishKeywords = [
            'mega cap' => 4.5,
            'mega-cap' => 4.5,
            'ai boom' => 5.0,
            'ai growth' => 4.5,
            'strong ai' => 4.5,
            'upgrade' => 4.0,
            'upgraded' => 4.0,
            'price target raised' => 5.0,
            'target raised' => 4.5,
            'bullish' => 4.0,
            'buy rating' => 4.0,
            'outperform' => 3.5,
        ];

        // LOW IMPACT BULLISH (Score: +1 to +2)
        $lowBullishKeywords = [
            'positive' => 2.0,
            'growth' => 1.5,
            'gain' => 1.5,
            'gains' => 1.5,
            'rise' => 1.5,
            'rises' => 1.5,
            'up' => 1.0,
        ];

        // HIGH IMPACT BEARISH (Score: -6 to -9)
        $highBearishKeywords = [
            'crash' => -8.0,
            'plunge' => -7.5,
            'plunges' => -7.5,
            'collapse' => -8.0,
            'collapses' => -8.0,
            'tariff' => -6.0,  // Generic tariff mention (negative unless relief)
            'tariffs' => -6.0,
        ];

        // MEDIUM IMPACT BEARISH (Score: -3 to -5)
        $mediumBearishKeywords = [
            'downgrade' => -4.0,
            'downgraded' => -4.0,
            'sell rating' => -4.5,
            'bearish' => -4.0,
            'decline' => -3.5,
            'declines' => -3.5,
        ];

        // LOW IMPACT BEARISH (Score: -1 to -2)
        $lowBearishKeywords = [
            'concern' => -2.0,
            'concerns' => -2.0,
            'risk' => -1.5,
            'risks' => -1.5,
            'weak' => -1.5,
            'weakness' => -2.0,
            'down' => -1.0,
        ];

        // Process keywords in priority order
        $allKeywords = array_merge(
            $highBullishKeywords,
            $mediumBullishKeywords,
            $lowBullishKeywords,
            $highBearishKeywords,
            $mediumBearishKeywords,
            $lowBearishKeywords
        );

        foreach ($allKeywords as $keyword => $weight) {
            if (strpos($text, $keyword) !== false) {
                $score += $weight;
                $matchedKeywords[] = $keyword;
                
                // Mark as rebound article if rebound keywords found
                if (in_array($keyword, ['rebound', 'major rebound', 'strong rebound', 'rebounds strongly', 
                    'recovery', 'major recovery', 'trump dismisses', 'trump dismiss', 'tariff relief'])) {
                    $isRebound = true;
                }
            }
        }

        // Cap score between -10 and +10
        $score = max(-10, min(10, $score));

        return [
            'score' => round($score, 2),
            'matched_keywords' => $matchedKeywords,
            'is_rebound' => $isRebound,
        ];
    }

    /**
     * Dispatch prediction regeneration for affected stocks
     */
    protected function dispatchPredictionUpdates($articles): void
    {
        $stockIds = $articles->pluck('stock_id')->unique();
        
        foreach ($stockIds as $stockId) {
            $stock = Stock::find($stockId);
            if ($stock) {
                // Dispatch with delay to avoid overwhelming the system
                DetectReboundAndRegenerateJob::dispatch($stock)
                    ->delay(now()->addSeconds(30));
            }
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
