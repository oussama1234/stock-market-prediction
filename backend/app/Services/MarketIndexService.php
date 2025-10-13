<?php

namespace App\Services;

use App\Models\MarketIndex;
use App\Services\StockService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MarketIndexService
{
    protected StockService $stockService;

    /**
     * Market indices configuration
     */
    protected array $indices = [
        [
            'symbol' => 'SPY',
            'name' => 'S&P 500',
            'index_name' => 'sp500',
        ],
        [
            'symbol' => 'QQQ',
            'name' => 'NASDAQ-100',
            'index_name' => 'nasdaq',
        ],
        [
            'symbol' => 'DIA',
            'name' => 'Dow Jones Industrial Average',
            'index_name' => 'dow',
        ],
    ];

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Initialize market indices in the database
     */
    public function initializeIndices(): void
    {
        foreach ($this->indices as $indexConfig) {
            MarketIndex::firstOrCreate(
                ['symbol' => $indexConfig['symbol']],
                [
                    'name' => $indexConfig['name'],
                    'index_name' => $indexConfig['index_name'],
                ]
            );
        }

        Log::info('Market indices initialized');
    }

    /**
     * Update all market indices
     */
    public function updateAllIndices(): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($this->indices as $indexConfig) {
            try {
                $updated = $this->updateIndex($indexConfig['symbol']);
                if ($updated) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "{$indexConfig['symbol']}: Update returned false";
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "{$indexConfig['symbol']}: {$e->getMessage()}";
                Log::error("Failed to update {$indexConfig['symbol']}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Update a single market index
     */
    public function updateIndex(string $symbol): bool
    {
        try {
            // Get quote from API
            $quote = $this->stockService->getQuote($symbol);

            if (!$quote || !isset($quote['current_price'])) {
                Log::warning("No quote data available for {$symbol}");
                return false;
            }

            // Get or create the market index record
            $index = MarketIndex::where('symbol', $symbol)->first();

            if (!$index) {
                $config = collect($this->indices)->firstWhere('symbol', $symbol);
                if (!$config) {
                    Log::error("Unknown market index symbol: {$symbol}");
                    return false;
                }

                $index = MarketIndex::create([
                    'symbol' => $symbol,
                    'name' => $config['name'],
                    'index_name' => $config['index_name'],
                ]);
            }

            // Calculate momentum and trend
            $momentumData = $this->calculateMomentumAndTrend($index, $quote);

            // Update the index with new data
            $index->update([
                'current_price' => $quote['current_price'],
                'change' => $quote['change'] ?? null,
                'change_percent' => $quote['change_percent'] ?? null,
                'volume' => $quote['volume'] ?? null,
                'avg_volume' => $quote['avg_volume'] ?? null,
                'day_high' => $quote['high'] ?? null,
                'day_low' => $quote['low'] ?? null,
                'open_price' => $quote['open'] ?? null,
                'previous_close' => $quote['previous_close'] ?? null,
                'week_52_high' => $quote['52_week_high'] ?? null,
                'week_52_low' => $quote['52_week_low'] ?? null,
                'trend' => $momentumData['trend'],
                'momentum_score' => $momentumData['momentum_score'],
                'momentum_strength' => $momentumData['momentum_strength'],
                'last_updated' => now(),
            ]);

            Log::debug("Updated market index {$symbol}: \${$quote['current_price']} ({$momentumData['trend']})");

            return true;

        } catch (\Exception $e) {
            Log::error("Error updating market index {$symbol}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calculate momentum and trend for an index
     */
    protected function calculateMomentumAndTrend(MarketIndex $index, array $quote): array
    {
        $changePercent = $quote['change_percent'] ?? 0;
        $currentPrice = $quote['current_price'];

        // Initialize result
        $result = [
            'trend' => 'neutral',
            'momentum_score' => 0,
            'momentum_strength' => 'weak',
        ];

        // Calculate momentum score based on multiple factors
        $momentumScore = 0;
        $factorCount = 0;

        // 1. Day's change percent (50% weight)
        if ($changePercent !== null) {
            // Normalize to -100 to +100 scale (assuming ±5% is extreme)
            $dayScore = ($changePercent / 5) * 100;
            $dayScore = max(-100, min(100, $dayScore));
            $momentumScore += $dayScore * 0.5;
            $factorCount++;
        }

        // 2. Position relative to 52-week range (30% weight)
        if (isset($quote['52_week_high']) && isset($quote['52_week_low'])) {
            $high = $quote['52_week_high'];
            $low = $quote['52_week_low'];
            
            if ($high > $low) {
                $rangePosition = (($currentPrice - $low) / ($high - $low)) * 100;
                // Convert to -100 to +100 scale (0-50 = bearish, 50-100 = bullish)
                $rangeScore = ($rangePosition - 50) * 2;
                $momentumScore += $rangeScore * 0.3;
                $factorCount++;
            }
        }

        // 3. Intraday momentum (20% weight)
        if (isset($quote['high']) && isset($quote['low']) && isset($quote['open'])) {
            $high = $quote['high'];
            $low = $quote['low'];
            $open = $quote['open'];
            
            if ($high > $low) {
                // Where is current price in today's range?
                $intradayPosition = (($currentPrice - $low) / ($high - $low)) * 100;
                $intradayScore = ($intradayPosition - 50) * 2;
                $momentumScore += $intradayScore * 0.2;
                $factorCount++;
            }
        }

        // Calculate final momentum score
        if ($factorCount > 0) {
            $result['momentum_score'] = round($momentumScore, 2);
        }

        // Determine trend based on momentum score
        if ($momentumScore > 20) {
            $result['trend'] = 'bullish';
        } elseif ($momentumScore < -20) {
            $result['trend'] = 'bearish';
        } else {
            $result['trend'] = 'neutral';
        }

        // Determine momentum strength
        $absScore = abs($momentumScore);
        if ($absScore > 60) {
            $result['momentum_strength'] = 'strong';
        } elseif ($absScore > 30) {
            $result['momentum_strength'] = 'moderate';
        } else {
            $result['momentum_strength'] = 'weak';
        }

        return $result;
    }

    /**
     * Get specific market index
     */
    public function getIndex(string $indexName): ?MarketIndex
    {
        return MarketIndex::byIndexName($indexName)->first();
    }

    /**
     * Get all market indices
     */
    public function getAllIndices(): array
    {
        return MarketIndex::getAllIndicesArray();
    }

    /**
     * Get market sentiment from indices
     */
    public function getMarketSentiment(): array
    {
        $indices = MarketIndex::all();

        if ($indices->isEmpty()) {
            return [
                'overall_sentiment' => 'neutral',
                'bullish_count' => 0,
                'bearish_count' => 0,
                'neutral_count' => 0,
                'average_momentum' => 0,
            ];
        }

        $bullish = $indices->where('trend', 'bullish')->count();
        $bearish = $indices->where('trend', 'bearish')->count();
        $neutral = $indices->where('trend', 'neutral')->count();
        $avgMomentum = $indices->avg('momentum_score');

        // Determine overall sentiment
        $overallSentiment = 'neutral';
        if ($bullish > $bearish && $bullish > $neutral) {
            $overallSentiment = 'bullish';
        } elseif ($bearish > $bullish && $bearish > $neutral) {
            $overallSentiment = 'bearish';
        }

        return [
            'overall_sentiment' => $overallSentiment,
            'bullish_count' => $bullish,
            'bearish_count' => $bearish,
            'neutral_count' => $neutral,
            'average_momentum' => round($avgMomentum, 2),
        ];
    }
}
