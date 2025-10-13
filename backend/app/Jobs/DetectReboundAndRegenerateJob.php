<?php

namespace App\Jobs;

use App\Models\Stock;
use App\Models\StockPrice;
use App\Services\PredictionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Detect rebound patterns and regenerate predictions
 * This job analyzes stock data for rebound signals and triggers prediction updates
 */
class DetectReboundAndRegenerateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Stock $stock;
    protected bool $forceRegenerate;

    /**
     * Create a new job instance.
     *
     * @param Stock $stock The stock to analyze
     * @param bool $forceRegenerate Force regeneration even if no rebound detected
     */
    public function __construct(Stock $stock, bool $forceRegenerate = false)
    {
        $this->stock = $stock;
        $this->forceRegenerate = $forceRegenerate;
    }

    /**
     * Execute the job.
     */
    public function handle(PredictionService $predictionService): void
    {
        try {
            Log::info("Analyzing rebound patterns for {$this->stock->symbol}");

            // Get recent price data
            $recentPrices = StockPrice::where('stock_id', $this->stock->id)
                ->where('interval', '1day')
                ->where('price_date', '>=', now()->subDays(10))
                ->orderBy('price_date', 'desc')
                ->get();

            if ($recentPrices->count() < 3) {
                Log::warning("Insufficient price data for {$this->stock->symbol}");
                return;
            }

            // Calculate price changes
            $prices = $recentPrices->pluck('close')->reverse()->values();
            $priceChange1d = $this->calculatePriceChange($prices, 1);
            $priceChange3d = $this->calculatePriceChange($prices, 3);
            $priceChange7d = $this->calculatePriceChange($prices, 7);
            
            // Calculate absolute price drops (in dollars)
            $currentPrice = $prices->last();
            $price1DayAgo = $prices->count() > 1 ? $prices[$prices->count() - 2] : $currentPrice;
            $price3DayAgo = $prices->count() > 3 ? $prices[$prices->count() - 4] : $currentPrice;
            $absoluteDrop1d = $price1DayAgo - $currentPrice;
            $absoluteDrop3d = $price3DayAgo - $currentPrice;

            // Get news sentiment
            $sentiment = $this->stock->getAverageSentiment() ?? 0.0;
            $normalizedSentiment = $sentiment / 10.0; // Normalize to 0-1

            // Get recent news sentiment (last 48 hours)
            $recentNews = $this->stock->newsArticles()
                ->where('published_at', '>=', now()->subHours(48))
                ->whereNotNull('sentiment_score')
                ->get();

            $recentSentiment = $normalizedSentiment;
            if ($recentNews->count() > 0) {
                $recentSentiment = ($recentNews->avg('sentiment_score') / 10.0 * 0.7) + ($normalizedSentiment * 0.3);
            }

            // Detect rebound patterns
            $reboundDetection = $this->detectReboundPatterns(
                $recentSentiment,
                $priceChange1d,
                $priceChange3d,
                $priceChange7d,
                $recentNews->count(),
                $absoluteDrop1d,
                $absoluteDrop3d,
                $currentPrice
            );

            if ($reboundDetection['is_rebound'] || $this->forceRegenerate) {
                Log::info("Rebound detected for {$this->stock->symbol}", [
                    'pattern' => $reboundDetection['pattern'],
                    'confidence' => $reboundDetection['confidence'],
                    'rebound_type' => $reboundDetection['rebound_type'] ?? 'N/A',
                    'forced' => $this->forceRegenerate,
                    'metrics' => $reboundDetection['metrics'] ?? [
                        'price_1d' => round($priceChange1d, 2),
                        'price_3d' => round($priceChange3d, 2),
                        'price_7d' => round($priceChange7d, 2),
                        'sentiment' => round($recentSentiment, 3),
                        'recent_news' => $recentNews->count()
                    ]
                ]);

                // Clear prediction cache (use simple forget since tags may not be supported)
                Cache::forget("prediction_{$this->stock->id}_today");
                Cache::forget("stock_details_{$this->stock->symbol}");

                // Regenerate prediction
                $prediction = $predictionService->getPredictionForHorizon($this->stock, 'today');

                Log::info("Prediction regenerated for {$this->stock->symbol}", [
                    'label' => $prediction['label'] ?? 'N/A',
                    'probability' => $prediction['probability'] ?? 'N/A',
                    'expected_move' => $prediction['expected_pct_move'] ?? 'N/A'
                ]);

                // Store rebound event for tracking
                $this->storeReboundEvent($reboundDetection);
            } else {
                Log::info("No rebound detected for {$this->stock->symbol}", [
                    'metrics' => [
                        'price_1d' => round($priceChange1d, 2),
                        'price_3d' => round($priceChange3d, 2),
                        'price_7d' => round($priceChange7d, 2),
                        'sentiment' => round($recentSentiment, 3),
                        'recent_news' => $recentNews->count()
                    ],
                    'reason' => 'Conditions not met for any rebound pattern'
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Rebound detection failed for {$this->stock->symbol}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Detect various rebound patterns
     * Priority: Actual price action > News sentiment
     */
    protected function detectReboundPatterns(
        float $sentiment,
        float $priceChange1d,
        float $priceChange3d,
        float $priceChange7d,
        int $recentNewsCount,
        float $absoluteDrop1d = 0,
        float $absoluteDrop3d = 0,
        float $currentPrice = 0
    ): array {
        $patterns = [];
        $confidence = 0;
        $reboundType = null; // 'strong', 'moderate', 'weak'

        // Calculate absolute drop severity (for high-priced stocks like NVDA)
        // HEAVILY WEIGHTED for large dollar drops
        $absoluteDropSeverity = 0;
        if ($currentPrice > 0) {
            // For stocks > $100, large $ drops get MASSIVE confidence boost
            if ($currentPrice > 100 && $absoluteDrop1d > 5) {
                // $5 drop = +15, $10 drop = +30, $15 drop = +45 (no max!)
                $absoluteDropSeverity = ($absoluteDrop1d / 5) * 15;
            } elseif ($currentPrice > 50 && $absoluteDrop1d > 3) {
                // $3 drop = +12, $6 drop = +24, $9 drop = +36
                $absoluteDropSeverity = ($absoluteDrop1d / 3) * 12;
            } elseif ($currentPrice > 20 && $absoluteDrop1d > 1) {
                // $1 drop = +8, $2 drop = +16, $3 drop = +24
                $absoluteDropSeverity = ($absoluteDrop1d / 1) * 8;
            }
            
            // Also check 3-day absolute drop - even BIGGER boost
            if ($currentPrice > 100 && $absoluteDrop3d > 10) {
                // $10 drop = +20, $20 drop = +40, $30 drop = +60
                $absoluteDropSeverity = max($absoluteDropSeverity, ($absoluteDrop3d / 10) * 20);
            }
        }

        // PRIORITY 1: Strong price-based rebounds (actual movement trumps sentiment)
        // Pattern 1: V-shaped recovery (strongest signal)
        if ($priceChange7d < -3 && $priceChange3d > 1 && $priceChange1d > 0) {
            $patterns[] = 'v_shaped_recovery';
            $confidence = 85; // Increased base
            $reboundType = 'strong';
            
            // MASSIVE boost for large absolute drops
            $confidence += ($absoluteDropSeverity * 1.5); // 1.5x multiplier
            
            // Even stronger if supported by positive sentiment
            if ($sentiment > 0.3) {
                $confidence += 20; // Increased from 15
            }
        }

        // Pattern 2: Confirmed multi-day recovery
        if ($priceChange3d > 2 && $priceChange1d > 0.5) {
            $patterns[] = 'confirmed_multi_day_recovery';
            $confidence = max($confidence, 80 + ($absoluteDropSeverity * 1.2)); // Much stronger
            $reboundType = $reboundType ?? 'strong';
        }

        // Pattern 3: Significant daily bounce
        if ($priceChange1d > 2.5) {
            $patterns[] = 'strong_daily_bounce';
            $confidence = max($confidence, 75 + ($absoluteDropSeverity * 1.5)); // Much stronger
            $reboundType = $reboundType ?? 'strong'; // Upgrade to strong
            
            // Enhanced if supported by news
            if ($sentiment > 0.2) {
                $patterns[] = 'bounce_with_news_support';
                $confidence += 15; // Increased from 10
            }
        }

        // PRIORITY 2: Price recovery after decline (with sentiment support)
        // Pattern 4: Price turning positive after decline with bullish news
        if ($priceChange7d < -2 && $priceChange1d > 0.3 && $sentiment > 0.3) {
            $patterns[] = 'recovery_with_bullish_sentiment';
            $baseConfidence = 65 + ($sentiment * 40) + (abs($priceChange7d) * 3) + ($absoluteDropSeverity * 1.3);
            $confidence = max($confidence, $baseConfidence); // Remove cap
            $reboundType = $reboundType ?? 'strong'; // Upgrade to strong
        }

        // PRIORITY 3: Sentiment-driven potential (weaker signal, requires confirmation)
        // Pattern 5: Strong positive news after decline (needs price confirmation)
        if ($sentiment > 0.4 && $priceChange7d < -3 && !$reboundType) {
            // Only trigger if no strong price pattern detected yet
            $patterns[] = 'strong_sentiment_after_decline';
            $confidence = max($confidence, 50 + ($sentiment * 30));
            $reboundType = 'weak';
            
            // Upgrade confidence if any positive price movement
            if ($priceChange1d > 0) {
                $confidence += 15;
                $patterns[] = 'sentiment_with_price_confirmation';
            }
        }

        // Pattern 6: News momentum (multiple positive articles)
        if ($recentNewsCount >= 3 && $sentiment > 0.4) {
            $patterns[] = 'news_momentum';
            // Add modest boost, don't override price-based confidence
            $confidence = $reboundType === 'strong' ? $confidence + 5 : max($confidence, 60);
        }

        // Pattern 7: Intraday recovery detection (current vs previous close)
        // This catches Monday rebounds after Friday drops
        if ($priceChange1d > 1.5 && $priceChange3d < 0) {
            $patterns[] = 'intraday_reversal';
            $confidence = max($confidence, 75 + ($absoluteDropSeverity * 1.3)); // Much stronger
            $reboundType = $reboundType ?? 'strong'; // Upgrade to strong
        }

        // Pattern 8: Large absolute price drop detection (e.g., NVDA $9 drop)
        // This is critical for high-value stocks where $ drop matters more than %
        // HEAVILY WEIGHTED - this should be the PRIMARY signal
        // ANY positive movement OR STABILIZATION after a large drop is significant!
        if ($absoluteDrop1d > 5 && $priceChange1d >= 0 && $sentiment >= 0) {
            $patterns[] = 'large_dollar_drop_recovery';
            // MASSIVE confidence boost: $5 = 80%, $10 = 100%, $15 = 120%
            $dollarDropConfidence = 70 + ($absoluteDrop1d * 3); // 3x multiplier!
            $confidence = max($confidence, $dollarDropConfidence); // No cap!
            $reboundType = 'strong'; // Always strong
            
            // HUGE boost if sentiment is positive
            if ($sentiment > 0.3) {
                $patterns[] = 'dollar_drop_with_positive_sentiment';
                $confidence += 20; // Doubled from 10
            }
            
            // Scale boost based on recovery strength
            if ($priceChange1d > 2) {
                $patterns[] = 'large_drop_strong_recovery';
                $confidence += 20;
            } elseif ($priceChange1d > 0.5) {
                $patterns[] = 'large_drop_moderate_recovery';
                $confidence += 10;
            } elseif ($priceChange1d > 0.1) {
                $patterns[] = 'large_drop_early_recovery';
                $confidence += 5;
            } else {
                // Even 0% (stabilization) gets a small boost
                $patterns[] = 'large_drop_stabilization';
                $confidence += 3;
            }
        }
        
        // Pattern 9: Multi-day large absolute drop with recovery signal
        // Even tiny positive movement matters after big drops
        if ($absoluteDrop3d > 10 && $priceChange1d >= -0.5 && !in_array('large_dollar_drop_recovery', $patterns)) {
            $patterns[] = 'multi_day_dollar_drop_recovery';
            // HUGE confidence: $10 = 85%, $20 = 110%, $30 = 135%
            $multiDayConfidence = 65 + ($absoluteDrop3d * 2.5); // 2.5x multiplier!
            $confidence = max($confidence, $multiDayConfidence); // No cap!
            $reboundType = 'strong'; // Always strong
            
            // Scale boost based on recovery strength
            if ($priceChange1d > 2) {
                $confidence += 20; // Strong recovery
            } elseif ($priceChange1d > 0.5) {
                $confidence += 10; // Moderate recovery
            } elseif ($priceChange1d > 0) {
                $confidence += 5; // Any recovery
            }
        }
        
        // Pattern 10: Post-drop stabilization (catches micro-recoveries and flat prices)
        // If there was a big drop recently but price is holding (even at 0%), it's a rebound signal
        if ($absoluteDrop1d > 7 && $priceChange1d >= -0.1 && $priceChange1d <= 1 && !in_array('large_dollar_drop_recovery', $patterns)) {
            $patterns[] = 'post_drop_stabilization';
            // High confidence even for tiny moves: $7 drop = 90%, $10 drop = 105%
            $stabilizationConfidence = 70 + ($absoluteDrop1d * 3.5);
            $confidence = max($confidence, $stabilizationConfidence);
            $reboundType = $reboundType ?? 'strong';
            
            // Bonus if showing ANY positive movement
            if ($priceChange1d > 0.1) {
                $patterns[] = 'early_bounce_signal';
                $confidence += 10;
            }
            
            // Even more if sentiment is positive (means market expects recovery)
            if ($sentiment > 0.2) {
                $confidence += 15;
                $patterns[] = 'stabilization_with_positive_outlook';
            }
        }

        $isRebound = count($patterns) > 0;
        // Allow confidence to go way above 100% for strong signals, then cap at 150%
        $confidence = min(150, max(0, $confidence));

        return [
            'is_rebound' => $isRebound,
            'pattern' => implode(', ', $patterns),
            'confidence' => $confidence,
            'patterns' => $patterns,
            'rebound_type' => $reboundType,
            'metrics' => [
                'price_1d' => round($priceChange1d, 2),
                'price_3d' => round($priceChange3d, 2),
                'price_7d' => round($priceChange7d, 2),
                'sentiment' => round($sentiment, 3),
                'news_count' => $recentNewsCount,
                'absolute_drop_1d' => round($absoluteDrop1d, 2),
                'absolute_drop_3d' => round($absoluteDrop3d, 2),
                'current_price' => round($currentPrice, 2),
                'absolute_drop_severity_score' => round($absoluteDropSeverity, 2)
            ]
        ];
    }

    /**
     * Calculate price change percentage
     */
    protected function calculatePriceChange($prices, int $period): float
    {
        if ($prices->count() < $period + 1) {
            return 0.0;
        }

        $latest = $prices->last();
        $previous = $prices[$prices->count() - $period - 1];

        if ($previous == 0) {
            return 0.0;
        }

        return (($latest - $previous) / $previous) * 100;
    }

    /**
     * Store rebound event for analytics
     */
    protected function storeReboundEvent(array $detection): void
    {
        try {
            // Store in cache for recent rebound tracking
            $cacheKey = "rebound_events_{$this->stock->symbol}_" . now()->format('Y-m-d');
            $events = Cache::get($cacheKey, []);
            
            $events[] = [
                'detected_at' => now()->toIso8601String(),
                'pattern' => $detection['pattern'],
                'confidence' => $detection['confidence'],
            ];
            
            Cache::put($cacheKey, $events, now()->addDays(7));
            
        } catch (\Exception $e) {
            Log::warning("Failed to store rebound event: " . $e->getMessage());
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
