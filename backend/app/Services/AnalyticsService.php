<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\StockPrice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AnalyticsService
 * 
 * Comprehensive analytics service for AnalyticsNew page
 * - Today-only predictions with per-minute updates
 * - Support/Resistance detection with buy/sell zones
 * - Priority keyword overrides (BEARISH enforcement)
 * - Fear & Greed integration
 * - Chart data with indicators
 * - News sentiment analysis
 */
class AnalyticsService
{
    public function __construct(
        protected StockDetailsService $detailsService,
        protected StockService $stockService,
        protected NewsService $newsService,
        protected FearGreedIndexService $fearGreedService,
        protected SentimentService $sentimentService
    ) {}

    /**
     * Get comprehensive analytics for AnalyticsNew page
     * Caches results for 30 seconds
     */
    public function getAnalytics(string $symbol): ?array
    {
        $cacheKey = "analytics_new:{$symbol}";
        
        return Cache::remember($cacheKey, 30, function () use ($symbol) {
            $symbol = strtoupper($symbol);
            $stock = $this->stockService->getOrCreateStock($symbol);
            
            if (!$stock) {
                return null;
            }

            // Get live quote
            $quote = $this->stockService->getQuote($symbol) ?? [];
            $currentPrice = $quote['current_price'] ?? null;
            
            if (!$currentPrice) {
                Log::warning("No current price for {$symbol}");
                return null;
            }

            // Get historical prices for support/resistance and indicators
            $historicalPrices = $this->getHistoricalPrices($stock, 90); // 90 days
            
            // Calculate support/resistance levels
            $supportResistance = $this->calculateSupportResistance($historicalPrices, $currentPrice);
            
            // Get today's prediction from StockDetailsService (uses existing logic)
            $details = $this->detailsService->getDetails($symbol, 'today');
            
            // Calculate indicators
            $indicators = $this->calculateIndicators($historicalPrices, $currentPrice);
            
            // Get Fear & Greed Index
            $fearGreed = $this->fearGreedService->getFearGreedIndex();
            
            // Get news with sentiment
            $news = $this->newsService->getStockNews($symbol, 30);
            $newsSentiment = $this->analyzeNewsSentiment($news);
            
            // Generate buy/sell alerts
            $alerts = $this->generateAlerts(
                $currentPrice,
                $supportResistance,
                $details['prediction'] ?? [],
                $indicators,
                $fearGreed,
                $newsSentiment,
                $details['override'] ?? null
            );
            
            // Prepare chart data
            $chartData = $this->prepareChartData($historicalPrices, $supportResistance, $indicators);
            
            // Ensure prediction is never neutral - force BULLISH or BEARISH
            $prediction = $this->normalizePrediction($details['prediction'] ?? [], $details['override'] ?? null);
            
            return [
                'stock' => $stock->toArray(),
                'quote' => array_merge($quote, [
                    'next_open_estimate' => $details['quote']['next_open_estimate'] ?? null,
                ]),
                'prediction' => $prediction,
                'override' => $details['override'] ?? null,
                'scenarios' => $details['scenarios'] ?? [],
                'support_resistance' => $supportResistance,
                'indicators' => $indicators,
                'fear_greed' => $fearGreed,
                'news' => $news,
                'news_sentiment' => $newsSentiment,
                'alerts' => $alerts,
                'chart_data' => $chartData,
                'market_status' => $this->getMarketStatus(),
                'updated_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Regenerate today's prediction (invalidates cache)
     */
    public function regenerateToday(string $symbol): array
    {
        $symbol = strtoupper($symbol);
        
        // Clear cache
        Cache::forget("analytics_new:{$symbol}");
        
        // Regenerate using StockDetailsService
        $result = $this->detailsService->regenerateToday($symbol, 'today');
        
        // Get fresh analytics
        $analytics = $this->getAnalytics($symbol);
        
        return [
            'success' => true,
            'data' => $analytics,
            'regenerated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get historical prices for analysis
     */
    protected function getHistoricalPrices(Stock $stock, int $days = 90): array
    {
        $prices = StockPrice::where('stock_id', $stock->id)
            ->where('interval', '1day')
            ->where('price_date', '>=', now()->subDays($days))
            ->orderBy('price_date', 'desc')
            ->get(['price_date', 'open', 'high', 'low', 'close', 'volume'])
            ->toArray();
        
        return array_reverse($prices); // Oldest first for indicator calculations
    }

    /**
     * Calculate support and resistance levels using peak/trough detection
     * Returns strong and weak support/resistance with distance from current price
     */
    protected function calculateSupportResistance(array $prices, float $currentPrice): array
    {
        if (count($prices) < 20) {
            return [
                'supports' => [],
                'resistances' => [],
                'strong_support' => null,
                'strong_resistance' => null,
            ];
        }

        $supports = [];
        $resistances = [];
        
        // Find local minima (supports) and maxima (resistances)
        for ($i = 2; $i < count($prices) - 2; $i++) {
            $current = (float)$prices[$i]['low'];
            $prev1 = (float)$prices[$i - 1]['low'];
            $prev2 = (float)$prices[$i - 2]['low'];
            $next1 = (float)$prices[$i + 1]['low'];
            $next2 = (float)$prices[$i + 2]['low'];
            
            // Local minimum (support)
            if ($current < $prev1 && $current < $prev2 && $current < $next1 && $current < $next2) {
                $supports[] = [
                    'price' => $current,
                    'date' => $prices[$i]['price_date'],
                    'touches' => 1,
                ];
            }
            
            // Local maximum (resistance)
            $currentHigh = (float)$prices[$i]['high'];
            $prev1High = (float)$prices[$i - 1]['high'];
            $prev2High = (float)$prices[$i - 2]['high'];
            $next1High = (float)$prices[$i + 1]['high'];
            $next2High = (float)$prices[$i + 2]['high'];
            
            if ($currentHigh > $prev1High && $currentHigh > $prev2High && $currentHigh > $next1High && $currentHigh > $next2High) {
                $resistances[] = [
                    'price' => $currentHigh,
                    'date' => $prices[$i]['price_date'],
                    'touches' => 1,
                ];
            }
        }
        
        // Cluster nearby levels (within 2% of each other)
        $supports = $this->clusterLevels($supports, 0.02);
        $resistances = $this->clusterLevels($resistances, 0.02);
        
        // Filter to only levels near current price (within 10%)
        $supports = array_filter($supports, fn($s) => $s['price'] <= $currentPrice && $s['price'] >= $currentPrice * 0.90);
        $resistances = array_filter($resistances, fn($r) => $r['price'] >= $currentPrice && $r['price'] <= $currentPrice * 1.10);
        
        // Sort and classify by strength
        usort($supports, fn($a, $b) => $b['price'] <=> $a['price']); // Closest first
        usort($resistances, fn($a, $b) => $a['price'] <=> $b['price']); // Closest first
        
        // Add distance and strength classification
        foreach ($supports as &$s) {
            $s['distance_percent'] = (($currentPrice - $s['price']) / $currentPrice) * 100;
            $s['strength'] = $s['touches'] >= 3 ? 'strong' : 'weak';
        }
        
        foreach ($resistances as &$r) {
            $r['distance_percent'] = (($r['price'] - $currentPrice) / $currentPrice) * 100;
            $r['strength'] = $r['touches'] >= 3 ? 'strong' : 'weak';
        }
        
        return [
            'supports' => array_slice($supports, 0, 3), // Top 3
            'resistances' => array_slice($resistances, 0, 3), // Top 3
            'strong_support' => $supports[0] ?? null,
            'strong_resistance' => $resistances[0] ?? null,
        ];
    }

    /**
     * Cluster nearby price levels together
     */
    protected function clusterLevels(array $levels, float $threshold = 0.02): array
    {
        if (empty($levels)) return [];
        
        usort($levels, fn($a, $b) => $a['price'] <=> $b['price']);
        
        $clustered = [];
        $currentCluster = [$levels[0]];
        
        for ($i = 1; $i < count($levels); $i++) {
            $prev = $currentCluster[0]['price'];
            $current = $levels[$i]['price'];
            
            if (abs($current - $prev) / $prev <= $threshold) {
                $currentCluster[] = $levels[$i];
            } else {
                // Average the cluster
                $avgPrice = array_sum(array_column($currentCluster, 'price')) / count($currentCluster);
                $totalTouches = array_sum(array_column($currentCluster, 'touches'));
                $clustered[] = [
                    'price' => $avgPrice,
                    'date' => $currentCluster[0]['date'],
                    'touches' => $totalTouches,
                ];
                $currentCluster = [$levels[$i]];
            }
        }
        
        // Add last cluster
        if (!empty($currentCluster)) {
            $avgPrice = array_sum(array_column($currentCluster, 'price')) / count($currentCluster);
            $totalTouches = array_sum(array_column($currentCluster, 'touches'));
            $clustered[] = [
                'price' => $avgPrice,
                'date' => $currentCluster[0]['date'],
                'touches' => $totalTouches,
            ];
        }
        
        return $clustered;
    }

    /**
     * Calculate technical indicators
     */
    protected function calculateIndicators(array $prices, float $currentPrice): array
    {
        if (count($prices) < 50) {
            return ['available' => false];
        }

        $closes = array_column($prices, 'close');
        $volumes = array_column($prices, 'volume');
        
        return [
            'available' => true,
            'ema_20' => $this->calculateEMA($closes, 20),
            'ema_50' => $this->calculateEMA($closes, 50),
            'rsi' => $this->calculateRSI($closes, 14),
            'macd' => $this->calculateMACD($closes),
            'bollinger_bands' => $this->calculateBollingerBands($closes, 20, 2),
            'volume_trend' => $this->calculateVolumeTrend($volumes),
        ];
    }

    /**
     * Calculate EMA (Exponential Moving Average)
     */
    protected function calculateEMA(array $data, int $period): ?float
    {
        if (count($data) < $period) return null;
        
        $k = 2 / ($period + 1);
        $ema = (float)$data[0];
        
        for ($i = 1; $i < count($data); $i++) {
            $ema = ((float)$data[$i] * $k) + ($ema * (1 - $k));
        }
        
        return round($ema, 2);
    }

    /**
     * Calculate RSI (Relative Strength Index)
     */
    protected function calculateRSI(array $closes, int $period = 14): ?float
    {
        if (count($closes) < $period + 1) return null;
        
        $gains = [];
        $losses = [];
        
        for ($i = 1; $i < count($closes); $i++) {
            $change = (float)$closes[$i] - (float)$closes[$i - 1];
            $gains[] = max(0, $change);
            $losses[] = abs(min(0, $change));
        }
        
        $avgGain = array_sum(array_slice($gains, -$period)) / $period;
        $avgLoss = array_sum(array_slice($losses, -$period)) / $period;
        
        if ($avgLoss == 0) return 100;
        
        $rs = $avgGain / $avgLoss;
        $rsi = 100 - (100 / (1 + $rs));
        
        return round($rsi, 2);
    }

    /**
     * Calculate MACD (Moving Average Convergence Divergence)
     */
    protected function calculateMACD(array $closes): ?array
    {
        if (count($closes) < 26) return null;
        
        $ema12 = $this->calculateEMA($closes, 12);
        $ema26 = $this->calculateEMA($closes, 26);
        
        if (!$ema12 || !$ema26) return null;
        
        $macd = $ema12 - $ema26;
        
        return [
            'value' => round($macd, 2),
            'signal' => $macd > 0 ? 'bullish' : 'bearish',
        ];
    }

    /**
     * Calculate Bollinger Bands
     */
    protected function calculateBollingerBands(array $closes, int $period = 20, float $std = 2): ?array
    {
        if (count($closes) < $period) return null;
        
        $sma = array_sum(array_slice($closes, -$period)) / $period;
        
        $variance = 0;
        foreach (array_slice($closes, -$period) as $price) {
            $variance += pow((float)$price - $sma, 2);
        }
        $stdDev = sqrt($variance / $period);
        
        return [
            'upper' => round($sma + ($std * $stdDev), 2),
            'middle' => round($sma, 2),
            'lower' => round($sma - ($std * $stdDev), 2),
        ];
    }

    /**
     * Calculate volume trend
     */
    protected function calculateVolumeTrend(array $volumes): string
    {
        if (count($volumes) < 5) return 'neutral';
        
        $recent = array_slice($volumes, -5);
        $avg = array_sum($recent) / 5;
        $latest = (float)end($recent);
        
        if ($latest > $avg * 1.5) return 'high';
        if ($latest < $avg * 0.5) return 'low';
        return 'normal';
    }

    /**
     * Analyze news sentiment
     */
    protected function analyzeNewsSentiment(array $news): array
    {
        $sentiments = [];
        $totalScore = 0;
        $count = 0;
        
        foreach ($news as $article) {
            $score = $this->sentimentService->analyzeSentiment($article['title'] ?? '');
            $sentiments[] = [
                'title' => $article['title'] ?? '',
                'score' => $score,
                'label' => $this->getSentimentLabel($score),
            ];
            $totalScore += $score;
            $count++;
        }
        
        $avgScore = $count > 0 ? $totalScore / $count : 0;
        
        return [
            'average_score' => round($avgScore, 2),
            'overall_label' => $this->getSentimentLabel($avgScore),
            'articles' => $sentiments,
        ];
    }

    /**
     * Get sentiment label from score
     */
    protected function getSentimentLabel(float $score): string
    {
        if ($score >= 0.5) return 'Very Positive';
        if ($score >= 0.2) return 'Positive';
        if ($score >= -0.2) return 'Neutral';
        if ($score >= -0.5) return 'Negative';
        return 'Very Negative';
    }

    /**
     * Generate buy/sell alerts based on comprehensive analysis
     */
    protected function generateAlerts(
        float $currentPrice,
        array $supportResistance,
        array $prediction,
        array $indicators,
        array $fearGreed,
        array $newsSentiment,
        ?array $override
    ): array {
        $alerts = [];
        
        $strongSupport = $supportResistance['strong_support'] ?? null;
        $strongResistance = $supportResistance['strong_resistance'] ?? null;
        $rsi = $indicators['rsi'] ?? 50;
        $fgValue = $fearGreed['value'] ?? 50;
        $macdSignal = $indicators['macd']['signal'] ?? 'neutral';
        $sentimentScore = $newsSentiment['average_score'] ?? 0;
        
        // BUY ALERT: Near strong support + favorable conditions
        if ($strongSupport) {
            $distanceToSupport = (($currentPrice - $strongSupport['price']) / $currentPrice) * 100;
            
            // Within 5% of support (increased from 2%)
            if ($distanceToSupport <= 5 && $distanceToSupport >= 0) {
                $reasons = ["Price near strong support at $" . number_format($strongSupport['price'], 2)];
                
                // Check if sentiment is not heavily bearish
                $sentimentScore = $newsSentiment['average_score'] ?? 0;
                if ($sentimentScore >= -0.3) {
                    $reasons[] = "News sentiment is " . ($newsSentiment['overall_label'] ?? 'neutral');
                }
                
                // Check Fear & Greed
                $fgValue = $fearGreed['value'] ?? 50;
                if ($fgValue < 40) {
                    $reasons[] = "Market fear at {$fgValue} - potential buying opportunity";
                }
                
                // Check RSI
                $rsi = $indicators['rsi'] ?? 50;
                if ($rsi < 35) {
                    $reasons[] = "RSI at {$rsi} indicates oversold conditions";
                }
                
                // Only show buy alert if not overridden to bearish
                if (!$override || !($override['applied'] ?? false)) {
                    $alerts[] = [
                        'type' => 'BUY',
                        'priority' => 'high',
                        'title' => 'BUY OPPORTUNITY',
                        'description' => 'Price approaching strong support level',
                        'target_price' => $strongSupport['price'],
                        'current_distance' => round($distanceToSupport, 2),
                        'reasons' => $reasons,
                        'confidence' => min(0.9, 0.5 + (count($reasons) * 0.1)),
                    ];
                }
            }
        }
        
        // SELL ALERT: Near strong resistance + favorable conditions
        if ($strongResistance) {
            $distanceToResistance = (($strongResistance['price'] - $currentPrice) / $currentPrice) * 100;
            
            // Within 5% of resistance (increased from 2%)
            if ($distanceToResistance <= 5 && $distanceToResistance >= 0) {
                $reasons = ["Price near strong resistance at $" . number_format($strongResistance['price'], 2)];
                
                // Check Fear & Greed
                $fgValue = $fearGreed['value'] ?? 50;
                if ($fgValue > 70) {
                    $reasons[] = "Market greed at {$fgValue} - potential profit-taking opportunity";
                }
                
                // Check RSI
                $rsi = $indicators['rsi'] ?? 50;
                if ($rsi > 70) {
                    $reasons[] = "RSI at {$rsi} indicates overbought conditions";
                }
                
                // Check prediction
                if (($prediction['direction'] ?? '') === 'down') {
                    $reasons[] = "Prediction indicates downward movement";
                }
                
                $alerts[] = [
                    'type' => 'SELL',
                    'priority' => 'high',
                    'title' => 'SELL OPPORTUNITY',
                    'description' => 'Price approaching strong resistance level',
                    'target_price' => $strongResistance['price'],
                    'current_distance' => round($distanceToResistance, 2),
                    'reasons' => $reasons,
                    'confidence' => min(0.9, 0.5 + (count($reasons) * 0.1)),
                ];
            }
        }
        
        // RSI OVERSOLD ALERT: Strong buy signal
        if ($rsi < 30 && !$override) {
            $alerts[] = [
                'type' => 'BUY',
                'priority' => 'high',
                'title' => 'RSI OVERSOLD',
                'description' => 'Technical indicator shows oversold conditions',
                'reasons' => [
                    "RSI at {$rsi} indicates oversold",
                    "Historical probability of rebound is high",
                    $macdSignal === 'bullish' ? 'MACD also shows bullish signal' : 'Monitor MACD for confirmation'
                ],
                'confidence' => 0.75,
            ];
        }
        
        // RSI OVERBOUGHT ALERT: Potential sell signal
        if ($rsi > 70) {
            $alerts[] = [
                'type' => 'SELL',
                'priority' => 'high',
                'title' => 'RSI OVERBOUGHT',
                'description' => 'Technical indicator shows overbought conditions',
                'reasons' => [
                    "RSI at {$rsi} indicates overbought",
                    "Consider taking profits or tightening stops",
                    $macdSignal === 'bearish' ? 'MACD also shows bearish signal' : 'Watch for reversal signals'
                ],
                'confidence' => 0.75,
            ];
        }
        
        // BULLISH PREDICTION ALERT
        if (($prediction['direction'] ?? '') === 'up' && ($prediction['probability'] ?? 0) >= 0.65 && !$override) {
            $prob = round(($prediction['probability'] ?? 0) * 100);
            $targetPrice = $prediction['predicted_price'] ?? null;
            
            $reasons = ["AI model predicts upward movement with {$prob}% probability"];
            if ($macdSignal === 'bullish') $reasons[] = 'MACD shows bullish momentum';
            if ($sentimentScore > 0) $reasons[] = 'News sentiment is positive';
            if ($rsi < 50) $reasons[] = 'RSI has room to grow';
            
            $alerts[] = [
                'type' => 'BUY',
                'priority' => 'medium',
                'title' => 'BULLISH PREDICTION',
                'description' => 'AI model indicates positive price movement',
                'target_price' => $targetPrice,
                'reasons' => $reasons,
                'confidence' => $prediction['probability'] ?? 0.65,
            ];
        }
        
        // BEARISH PREDICTION ALERT
        if (($prediction['direction'] ?? '') === 'down' && ($prediction['probability'] ?? 0) >= 0.65) {
            $prob = round(($prediction['probability'] ?? 0) * 100);
            $targetPrice = $prediction['predicted_price'] ?? null;
            
            $reasons = ["AI model predicts downward movement with {$prob}% probability"];
            if ($macdSignal === 'bearish') $reasons[] = 'MACD shows bearish momentum';
            if ($sentimentScore < 0) $reasons[] = 'News sentiment is negative';
            if ($rsi > 50) $reasons[] = 'RSI shows weakness';
            
            $alerts[] = [
                'type' => 'SELL',
                'priority' => 'medium',
                'title' => 'BEARISH PREDICTION',
                'description' => 'AI model indicates negative price movement',
                'target_price' => $targetPrice,
                'reasons' => $reasons,
                'confidence' => $prediction['probability'] ?? 0.65,
            ];
        }
        
        // OVERRIDE ALERT: Show when bearish override is active
        if ($override && ($override['applied'] ?? false)) {
            $alerts[] = [
                'type' => 'WARNING',
                'priority' => 'critical',
                'title' => 'BEARISH OVERRIDE ACTIVE',
                'description' => $override['label'] ?? 'News-based bearish prediction',
                'reasons' => array_merge(
                    ['Priority keywords detected: ' . implode(', ', $override['trigger_keywords'] ?? [])],
                    $override['articles'] ?? []
                ),
                'confidence' => $override['confidence'] ?? 0.8,
            ];
        }
        
        // EXTREME FEAR/GREED ALERTS
        if ($fgValue < 25 && !$override) {
            $alerts[] = [
                'type' => 'INFO',
                'priority' => 'medium',
                'title' => 'EXTREME FEAR',
                'description' => 'Market showing extreme fear - contrarian buy opportunity',
                'reasons' => [
                    "Fear & Greed Index at {$fgValue} (Extreme Fear)",
                    "Historically strong buy signal",
                    "Market sentiment may be overly pessimistic"
                ],
                'confidence' => 0.70,
            ];
        } elseif ($fgValue > 75) {
            $alerts[] = [
                'type' => 'INFO',
                'priority' => 'medium',
                'title' => 'EXTREME GREED',
                'description' => 'Market showing extreme greed - consider profit taking',
                'reasons' => [
                    "Fear & Greed Index at {$fgValue} (Extreme Greed)",
                    "Market may be overheated",
                    "Consider taking profits or hedging positions"
                ],
                'confidence' => 0.70,
            ];
        }
        
        return $alerts;
    }

    /**
     * Prepare chart data with indicators and support/resistance markers
     */
    protected function prepareChartData(array $prices, array $supportResistance, array $indicators): array
    {
        // Last 60 days for chart
        $chartPrices = array_slice($prices, -60);
        
        return [
            'prices' => array_map(fn($p) => [
                'date' => $p['price_date'],
                'open' => (float)$p['open'],
                'high' => (float)$p['high'],
                'low' => (float)$p['low'],
                'close' => (float)$p['close'],
                'volume' => (float)($p['volume'] ?? 0),
            ], $chartPrices),
            'support_levels' => array_map(fn($s) => $s['price'], $supportResistance['supports'] ?? []),
            'resistance_levels' => array_map(fn($r) => $r['price'], $supportResistance['resistances'] ?? []),
            'indicators' => $indicators,
        ];
    }

    /**
     * Normalize prediction to ensure no neutral - force BULLISH or BEARISH
     */
    protected function normalizePrediction(array $prediction, ?array $override): array
    {
        // If override is active, it's always BEARISH
        if ($override && ($override['applied'] ?? false)) {
            $prediction['direction'] = 'down';
            $prediction['label'] = 'BEARISH';
            return $prediction;
        }
        
        // Ensure direction is up or down, never neutral
        $direction = $prediction['direction'] ?? 'neutral';
        if ($direction === 'neutral') {
            // Use probability to break tie
            $prob = $prediction['probability'] ?? 0.5;
            $prediction['direction'] = $prob >= 0.5 ? 'up' : 'down';
        }
        
        // Set label
        $prediction['label'] = $prediction['direction'] === 'up' ? 'BULLISH' : 'BEARISH';
        
        return $prediction;
    }

    /**
     * Get current market status
     */
    protected function getMarketStatus(): array
    {
        $now = new \DateTime('now', new \DateTimeZone('America/New_York'));
        $hour = (int)$now->format('G');
        $minute = (int)$now->format('i');
        $dayOfWeek = (int)$now->format('N'); // 1=Monday, 7=Sunday
        
        $isWeekend = $dayOfWeek >= 6;
        $currentMinutes = $hour * 60 + $minute;
        $marketOpen = 9 * 60 + 30; // 9:30 AM
        $marketClose = 16 * 60; // 4:00 PM
        
        if ($isWeekend) {
            return ['status' => 'closed', 'reason' => 'Weekend'];
        }
        
        if ($currentMinutes < $marketOpen) {
            $minutesUntilOpen = $marketOpen - $currentMinutes;
            return ['status' => 'pre_market', 'minutes_until_open' => $minutesUntilOpen];
        }
        
        if ($currentMinutes >= $marketOpen && $currentMinutes < $marketClose) {
            $minutesUntilClose = $marketClose - $currentMinutes;
            return ['status' => 'open', 'minutes_until_close' => $minutesUntilClose];
        }
        
        return ['status' => 'after_hours', 'reason' => 'Market closed'];
    }
}
