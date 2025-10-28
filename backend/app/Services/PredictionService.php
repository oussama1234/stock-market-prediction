<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\Prediction;
use App\Models\StockPrice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PredictionService
{
    protected SentimentService $sentimentService;
    
    public function __construct(SentimentService $sentimentService)
    {
        $this->sentimentService = $sentimentService;
    }
    
    /**
     * Generate prediction for a stock
     */
    public function generatePrediction(Stock $stock, ?array $quoteData = null): ?Prediction
    {
        try {
            // Get current sentiment from recent news (default to 0 if no news)
            $sentiment = $stock->getAverageSentiment() ?? 0.0;
            
            // Get price trend from recent prices
            $priceTrend = $this->calculatePriceTrend($stock);
            
            // Get current price
            $currentPrice = $quoteData['current_price'] ?? $stock->latestPrice?->close;
            
            if (!$currentPrice) {
                Log::warning("No current price available for {$stock->symbol}");
                return null;
            }
            
            // Calculate prediction
            $prediction = $this->calculatePrediction($sentiment, $priceTrend, $currentPrice);
            
            // Store prediction
            return $this->storePrediction($stock, $prediction, $sentiment, $priceTrend);
            
        } catch (\Exception $e) {
            Log::error("Failed to generate prediction for {$stock->symbol}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Calculate price trend from recent prices
     */
    protected function calculatePriceTrend(Stock $stock): float
    {
        $prices = StockPrice::where('stock_id', $stock->id)
            ->where('interval', '1day')
            ->where('price_date', '>=', now()->subDays(7))
            ->orderBy('price_date', 'desc')
            ->limit(7)
            ->get();
        
        if ($prices->count() < 2) {
            return 0.0; // Not enough data
        }
        
        // Calculate average daily change
        $totalChange = 0.0;
        $count = 0;
        
        foreach ($prices as $index => $price) {
            if ($price->change_percent !== null) {
                $totalChange += $price->change_percent;
                $count++;
            }
        }
        
        return $count > 0 ? ($totalChange / $count) : 0.0;
    }
    
    /**
     * Calculate prediction based on sentiment and price trend
     */
    protected function calculatePrediction(float $sentiment, float $priceTrend, float $currentPrice): array
    {
        // Weights for different factors
        $sentimentWeight = 0.6;
        $trendWeight = 0.4;
        
        // Normalize sentiment (-10 to 10) to percentage (-20% to +20%)
        $sentimentImpact = ($sentiment / 10) * 20;
        
        // Cap price trend impact at -20% to +20%
        $trendImpact = max(-20, min(20, $priceTrend * 5));
        
        // Combined impact
        $predictedChange = ($sentimentImpact * $sentimentWeight) + ($trendImpact * $trendWeight);
        
        // Calculate predicted price
        $predictedPrice = $currentPrice * (1 + ($predictedChange / 100));
        
        // Determine direction
        if ($predictedChange > 2) {
            $direction = 'up';
        } elseif ($predictedChange < -2) {
            $direction = 'down';
        } else {
            $direction = 'neutral';
        }
        
        // Calculate confidence (0-100)
        $confidence = $this->calculateConfidence($sentiment, $priceTrend);
        
        return [
            'predicted_price' => round($predictedPrice, 2),
            'predicted_change_percent' => round($predictedChange, 2),
            'direction' => $direction,
            'confidence' => $confidence,
            'current_price' => $currentPrice,
        ];
    }
    
    /**
     * Calculate confidence score (0-100)
     */
    protected function calculateConfidence(float $sentiment, float $priceTrend): int
    {
        // Base confidence
        $confidence = 50;
        
        // Higher sentiment magnitude increases confidence
        $sentimentMagnitude = abs($sentiment);
        $confidence += min(20, $sentimentMagnitude * 2);
        
        // Strong trend increases confidence
        $trendMagnitude = abs($priceTrend);
        $confidence += min(15, $trendMagnitude * 3);
        
        // If sentiment and trend agree, increase confidence
        if (($sentiment > 0 && $priceTrend > 0) || ($sentiment < 0 && $priceTrend < 0)) {
            $confidence += 15;
        }
        
        return min(100, max(30, (int) $confidence));
    }
    
    /**
     * Store prediction in database
     */
    protected function storePrediction(
        Stock $stock,
        array $predictionData,
        float $sentiment,
        float $priceTrend
    ): Prediction {
        // Deactivate old predictions
        Prediction::where('stock_id', $stock->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);
        
        // Create new prediction
        return Prediction::create([
            'stock_id' => $stock->id,
            'predicted_price' => $predictionData['predicted_price'],
            'predicted_change_percent' => $predictionData['predicted_change_percent'],
            'current_price' => $predictionData['current_price'],
            'direction' => $predictionData['direction'],
            'confidence_score' => $predictionData['confidence'],
            'sentiment_score' => $sentiment,
            'price_trend' => $priceTrend,
            'prediction_date' => now(),
            'target_date' => now()->addDay(), // 24-hour prediction
            'is_active' => true,
        ]);
    }
    
    /**
     * Evaluate old predictions
     */
    public function evaluatePrediction(Prediction $prediction): bool
    {
        if ($prediction->actual_price === null) {
            // Get actual price from stock prices
            $actualPrice = StockPrice::where('stock_id', $prediction->stock_id)
                ->where('price_date', '>=', $prediction->target_date)
                ->orderBy('price_date', 'asc')
                ->value('close');
            
            if ($actualPrice) {
                $prediction->update([
                    'actual_price' => $actualPrice,
                    'accuracy' => $this->calculateAccuracy(
                        $prediction->predicted_price,
                        $actualPrice,
                        $prediction->current_price
                    ),
                ]);
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Calculate prediction accuracy
     */
    protected function calculateAccuracy(float $predicted, float $actual, float $baseline): float
    {
        $predictedDiff = abs($predicted - $baseline);
        $actualDiff = abs($actual - $baseline);
        
        if ($predictedDiff == 0 && $actualDiff == 0) {
            return 100.0;
        }
        
        $error = abs($predicted - $actual);
        $range = max($predictedDiff, $actualDiff);
        
        if ($range == 0) {
            return 100.0;
        }
        
        $accuracy = max(0, (1 - ($error / $range))) * 100;
        
        return round($accuracy, 2);
    }
    
    /**
     * Get active prediction for a stock
     */
    public function getActivePrediction(Stock $stock): ?Prediction
    {
        return $stock->activePrediction;
    }
    
    /**
     * Get prediction history for a stock
     */
    public function getPredictionHistory(Stock $stock, int $limit = 10)
    {
        return Prediction::where('stock_id', $stock->id)
            ->orderBy('prediction_date', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get prediction for a specific horizon using quick_model_v2
     * 
     * @param Stock $stock
     * @param string $horizon ('today', 'tomorrow', 'week', 'month')
     * @return array
     */
public function getPredictionForHorizon(Stock $stock, string $horizon = 'today', string $model = 'v6'): array
    {
        try {
            // Only 'today' is supported in quick_model_v2
            if ($horizon !== 'today') {
                Log::warning("Unsupported horizon '{$horizon}' requested for {$stock->symbol}. Falling back to 'today'.");
                $horizon = 'today';
            }
            
            // Call Python quick_model_v4.py for prediction (with European + Asian markets)
$pythonPath = base_path($model === 'v6' ? 'python/models/quick_model_v6.py' : 'python/models/quick_model_v4.py');
            $asianMarketService = app(AsianMarketService::class);
            $europeanMarketService = app(EuropeanMarketService::class);
            
            // Get Asian market data
            $asianMarkets = $asianMarketService->getTodayChanges();
            $asianNormalized = $asianMarketService->normalizeForModel($asianMarkets);
            
            // Get European market data
            $europeanMarkets = $europeanMarketService->getTodayChanges();
            $europeanNormalized = $europeanMarketService->normalizeForModel($europeanMarkets);
            
            // Get stock data with category information
            $stockData = $this->prepareStockData($stock);
            
            // CRITICAL: Ensure category relationship is loaded (may be lost during cache serialization)
            if (!$stock->relationLoaded('category')) {
                $stock->load('category');
            }
            
            // Add category-based volatility multiplier
            if ($stock->category && is_object($stock->category)) {
                $stockData['category_multiplier'] = (float) $stock->category->volatility_multiplier;
                $stockData['typical_daily_range_min'] = (float) $stock->category->typical_daily_range_min;
                $stockData['typical_daily_range_max'] = (float) $stock->category->typical_daily_range_max;
                $stockData['high_momentum'] = (bool) $stock->category->high_momentum;
                
                Log::info("Category data for {$stock->symbol}", [
                    'category' => $stock->category->name,
                    'multiplier' => $stockData['category_multiplier'],
                    'range' => [
                        'min' => $stockData['typical_daily_range_min'],
                        'max' => $stockData['typical_daily_range_max'],
                    ],
                ]);
            } else {
                // Default values if no category assigned
                $stockData['category_multiplier'] = 1.0;
                $stockData['typical_daily_range_min'] = 0.5;
                $stockData['typical_daily_range_max'] = 2.0;
                $stockData['high_momentum'] = false;
                
                Log::warning("No category assigned for {$stock->symbol}, using defaults");
            }
            
            // CRITICAL: Override 1-day change with FRESH API data (do this AFTER prepareStockData to avoid cache)
            try {
                $stockService = app(\App\Services\StockService::class);
                $freshQuote = $stockService->getQuote($stock->symbol);
                if ($freshQuote && isset($freshQuote['change_percent'])) {
                    $stockData['price_change_1d'] = (float) $freshQuote['change_percent'];
                    Log::info("Overrode price_change_1d with fresh API data for {$stock->symbol}: {$stockData['price_change_1d']}%");
                }
            } catch (\Exception $e) {
                Log::warning("Could not get fresh quote for price_change_1d override: " . $e->getMessage());
            }
            
            // Prepare input for Python script - merge all market data
            $input = array_merge($stockData, $asianNormalized, $europeanNormalized);

            // Add US factors (indices) and global sentiment for v6 model
            try {
                $marketIndexService = app(\App\Services\MarketIndexService::class);
                $indices = $marketIndexService->getAllIndices();
                $input['sp500_change'] = (float) ($indices['sp500']['change_percent'] ?? 0);
                $input['nasdaq_change'] = (float) ($indices['nasdaq']['change_percent'] ?? 0);
                $input['russell_2000_change'] = (float) ($indices['russell2000']['change_percent'] ?? 0);
            } catch (\Throwable $e) {
                // Safe defaults
                $input['sp500_change'] = $input['sp500_change'] ?? 0.0;
                $input['nasdaq_change'] = $input['nasdaq_change'] ?? 0.0;
                $input['russell_2000_change'] = $input['russell_2000_change'] ?? 0.0;
            }
            try {
                $globalSentimentService = app(\App\Services\GlobalMarketSentimentService::class);
                $gm = $globalSentimentService->getGlobalMarketSentiment();
                $input['fed_sentiment_score'] = (float) ($gm['overall_score'] ?? 0);
            } catch (\Throwable $e) {
                $input['fed_sentiment_score'] = $input['fed_sentiment_score'] ?? 0.0;
            }

            // Try to enrich with macro proxies (GLD, USO) and 10Y yield (^TNX)
            $stockService = app(\App\Services\StockService::class);
            
            // Gold
            if (!isset($input['gold_change'])) {
                try {
                    $gld = $stockService->getQuote('GLD');
                    if ($gld && isset($gld['change_percent'])) {
                        $input['gold_change'] = (float) $gld['change_percent'];
                        Log::info("Fetched gold_change: {$input['gold_change']}%");
                    }
                } catch (\Throwable $e) {
                    Log::warning("Failed to fetch gold_change: " . $e->getMessage());
                }
            }
            
            // Oil
            if (!isset($input['oil_change'])) {
                try {
                    $uso = $stockService->getQuote('USO');
                    if ($uso && isset($uso['change_percent'])) {
                        $input['oil_change'] = (float) $uso['change_percent'];
                        Log::info("Fetched oil_change: {$input['oil_change']}%");
                    }
                } catch (\Throwable $e) {
                    Log::warning("Failed to fetch oil_change: " . $e->getMessage());
                }
            }
            
            // 10Y Treasury Yield
            if (!isset($input['treasury_yield_10y'])) {
                try {
                    $tnx = $stockService->getQuote('^TNX');
                    Log::info("^TNX quote response: " . json_encode($tnx));
                    
                    // Yahoo ^TNX is yield*10; normalize to %
                    if ($tnx && isset($tnx['current_price'])) {
                        $input['treasury_yield_10y'] = round(((float)$tnx['current_price']) / 10.0, 2);
                        Log::info("Fetched treasury_yield_10y: {$input['treasury_yield_10y']}%");
                    } else {
                        // Default to reasonable current rate if fetch fails
                        $input['treasury_yield_10y'] = 4.50;
                        Log::warning("^TNX fetch returned no data, using default: 4.50%");
                    }
                } catch (\Throwable $e) {
                    $input['treasury_yield_10y'] = 4.50;
                    Log::warning("Failed to fetch ^TNX: " . $e->getMessage() . ", using default: 4.50%");
                }
            }
            
            // CRITICAL: Ensure all numeric values are proper floats/ints (not strings)
            $input = $this->sanitizeNumericValues($input);
            
            $inputJson = json_encode($input, JSON_NUMERIC_CHECK);
            
            // Log the actual input data being sent to Python (for debugging)
            Log::info("Python model input for {$stock->symbol}", [
                'fear_greed_index' => $input['fear_greed_index'] ?? 'NOT SET',
                'news_sentiment_score' => $input['news_sentiment_score'] ?? 'NOT SET',
                'price_change_1d' => $input['price_change_1d'] ?? 'NOT SET',
                'sp500_change' => $input['sp500_change'] ?? 'NOT SET',
                'nasdaq_change' => $input['nasdaq_change'] ?? 'NOT SET',
                'european_influence_score' => $input['european_influence_score'] ?? 'NOT SET',
                'asian_influence_score' => $input['asian_influence_score'] ?? 'NOT SET',
            ]);
            
            // Execute Python script
            $pythonExecutable = config('services.python.executable', 'python');
            $command = sprintf(
                '%s "%s" predict --features %s',
                $pythonExecutable,
                $pythonPath,
                escapeshellarg($inputJson)
            );
            
            Log::info("Executing prediction for {$stock->symbol}", ['command' => $command]);
            
            $output = shell_exec($command . ' 2>&1');
            
            if (empty($output)) {
                throw new \Exception('Python script produced no output');
            }
            
            $result = json_decode($output, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse Python output', [
                    'output' => $output,
                    'error' => json_last_error_msg()
                ]);
                throw new \Exception('Invalid JSON response from prediction script');
            }
            
            if (isset($result['error'])) {
                throw new \Exception($result['error']);
            }
            
            // Enhance result with market details and price
            $result['asian_markets'] = $asianMarkets;
            $result['european_markets'] = $europeanMarkets;
$result['model_version'] = $model === 'v6' ? 'quick_model_v6' : 'quick_model_v4';
            $result['horizon'] = $horizon;
            $result['generated_at'] = now()->toIso8601String();
            $result['market_influences'] = [
                'local' => [
                    'weight' => 50,
                    'impact_percent' => 0, // Calculated by model
                    'sentiment' => 'neutral',
                ],
                'european' => [
                    'weight' => 30,
                    'impact_percent' => $europeanNormalized['european_impact_percent'] ?? 0,
                    'sentiment' => $europeanNormalized['european_sentiment'] ?? 'neutral',
                ],
                'asian' => [
                    'weight' => 20,
                    'impact_percent' => $asianNormalized['asian_impact_percent'] ?? 0,
                    'sentiment' => $asianNormalized['asian_sentiment'] ?? 'neutral',
                ],
            ];
            
            // CRITICAL: Always include current_price with database-based change data
            $stockService = app(StockService::class);
            $quote = $stockService->getQuote($stock->symbol);
            
            // Use quote current price if available, otherwise fallback to latest DB price
            $currentPrice = $quote['current_price'] ?? $stock->latestPrice?->close ?? $stockData['close'] ?? 0.0;
            $result['current_price'] = (float) $currentPrice;
            $result['news_sentiment_score'] = $stockData['news_sentiment_score'] ?? 0.0;
            
            // Normalize expected move sign to match label (guard against UI inconsistencies)
            if (isset($result['label']) && isset($result['expected_pct_move']) && is_numeric($result['expected_pct_move'])) {
                $move = (float) $result['expected_pct_move'];
                if ($result['label'] === 'BULLISH' && $move < 0) {
                    $result['expected_pct_move'] = abs($move);
                } elseif ($result['label'] === 'BEARISH' && $move > 0) {
                    $result['expected_pct_move'] = -abs($move);
                }
            }
            
            // Add database-based change values if available
            if (isset($quote['db_change'])) {
                $result['db_change'] = (float) $quote['db_change'];
                $result['db_change_percent'] = (float) $quote['db_change_percent'];
                $result['db_previous_close'] = (float) $quote['db_previous_close'];
                $result['db_last_check_date'] = $quote['db_last_check_date'];
            }
            
            // Also include API-based values for reference
            if (isset($quote['change'])) {
                $result['api_change'] = (float) $quote['change'];
                $result['api_change_percent'] = (float) $quote['change_percent'];
                $result['api_previous_close'] = (float) $quote['previous_close'];
            }
            
            // Compute category-aware predicted price range if we have an expected move
            if (isset($result['expected_pct_move']) && is_numeric($result['expected_pct_move']) && $result['current_price'] > 0) {
                try {
                    $confidence = isset($result['probability']) && is_numeric($result['probability'])
                        ? max(0.0, min(1.0, (float) $result['probability']))
                        : 0.7;
                    $range = $this->calculateCategoryAwarePriceRange(
                        (float) $result['current_price'],
                        (float) $result['expected_pct_move'],
                        $stock,
                        (float) $confidence
                    );
                    $result['predicted_price'] = $range['predicted_price'];
                    $result['predicted_low'] = $range['predicted_low'];
                    $result['predicted_high'] = $range['predicted_high'];
                } catch (\Throwable $e) {
                    // Non-fatal
                }
            }
            
            Log::info("Prediction for {$stock->symbol} - current_price: {$result['current_price']}, db_change: " . ($result['db_change'] ?? 'N/A'));
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error("Prediction failed for {$stock->symbol}", [
                'horizon' => $horizon,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return fallback prediction
            return $this->getFallbackPrediction($stock);
        }
    }
    
    /**
     * Prepare stock data for prediction
     * 
     * @param Stock $stock
     * @return array
     */
    protected function prepareStockData(Stock $stock): array
    {
        $latestPrice = $stock->latestPrice;
        
        // Get news sentiment with normalization
        // Range: -10 to +10 in DB → normalize to -1 to +1 for model
        $rawSentiment = $stock->getAverageSentiment() ?? 0.0;
        $sentiment = $rawSentiment / 10.0; // Normalize to -1 to +1 range
        
        // IMPORTANT: Check for recent positive news (last 24-48 hours)
        $recentNews = $stock->newsArticles()
            ->where('published_at', '>=', now()->subHours(48))
            ->whereNotNull('sentiment_score')
            ->get();
        
        if ($recentNews->count() > 0) {
            $recentSentiment = $recentNews->avg('sentiment_score') / 10.0;
            // Weight recent news more heavily (70% recent, 30% overall)
            $sentiment = ($recentSentiment * 0.7) + ($sentiment * 0.3);
        }
        
        // CRITICAL: Apply global market sentiment (tariff relief, tech sector news)
        $globalSentimentService = app(GlobalMarketSentimentService::class);
        $globalBlend = $globalSentimentService->applyGlobalSentimentToStock($stock, $sentiment);
        $sentiment = $globalBlend['blended_sentiment'];
        
        // Log global sentiment influence
        if ($globalBlend['global_weight'] > 0.2) {
            Log::info("Global sentiment applied to {$stock->symbol}", [
                'original' => round($globalBlend['original_sentiment'], 3),
                'global' => round($globalBlend['global_sentiment'], 3),
                'blended' => round($sentiment, 3),
                'weight' => round($globalBlend['global_weight'], 2),
                'reason' => $globalBlend['reason']
            ]);
        }
        
        // Get recent prices for technical indicators (50 days for reliable calculations)
        $recentPrices = StockPrice::where('stock_id', $stock->id)
            ->where('interval', '1day')
            ->where('price_date', '>=', now()->subDays(50))
            ->orderBy('price_date', 'desc')
            ->limit(50)
            ->get();
        
        $close = $latestPrice?->close ?? 100.0;
        $volume = $latestPrice?->volume ?? 1000000;
        
        // Initialize base data with fundamentals defaults
        $data = [
            'symbol' => $stock->symbol,
            'close' => $close,
            'volume' => $volume,
            'news_sentiment_score' => $sentiment,
            'price_change_1d' => 0.0,
            'price_change_3d' => 0.0,
            'price_change_7d' => 0.0,
            'ema_12' => $close,
            'ema_26' => $close,
            'macd' => 0.0,
            'macd_signal' => 0.0,
            'macd_hist' => 0.0,
            'rsi_14' => 50.0,
            'rsi_7' => 50.0,
            'atr_14' => 0.0,
            'obv' => 0.0,
            'bb_upper' => $close,
            'bb_middle' => $close,
            'bb_lower' => $close,
            'bb_width' => 0.0,
            'bb_pct' => 0.5,
            'distance_to_support' => 0.0,
            'distance_to_resistance' => 0.0,
            'volume_sma_ratio' => 1.0,
            'volume_spike' => false,
            'fear_greed_index' => 50.0,
            'inst_flow_score' => 0.0,
            // Fundamentals (defaults - will be enriched below)
            'pe_ratio' => 20.0,
            'pb_ratio' => 3.0,
            'ps_ratio' => 5.0,
            'eps_growth' => 0.0,
            'revenue_growth' => 0.0,
            'roe' => 10.0,
            'profit_margin' => 10.0,
            'debt_to_equity' => 1.0,
            'dividend_yield' => 0.0,
        ];
        
        // Enrich with fundamental data using aggregator (Yahoo Finance + Finnhub + Alpha Vantage fallback)
        try {
            $aggregator = app(\App\Services\FundamentalsAggregator::class);
            $fundamentals = $aggregator->getFundamentals($stock->symbol);
            
            if ($fundamentals) {
                Log::info("Fundamentals API returned data for {$stock->symbol}", $fundamentals);
                
                // Merge non-null fundamental values
                $mergedCount = 0;
                foreach ($fundamentals as $key => $value) {
                    if ($value !== null && isset($data[$key])) {
                        $data[$key] = (float) $value;
                        $mergedCount++;
                    }
                }
                
                Log::info("Fundamental data enriched for {$stock->symbol}", [
                    'source' => 'aggregator',
                    'merged_fields' => $mergedCount,
                    'pe_ratio' => $data['pe_ratio'],
                    'pb_ratio' => $data['pb_ratio'],
                    'eps_growth' => $data['eps_growth'],
                    'revenue_growth' => $data['revenue_growth'],
                    'roe' => $data['roe'],
                    'profit_margin' => $data['profit_margin'],
                ]);
            } else {
                Log::warning("All fundamental data sources failed for {$stock->symbol} - using defaults");
            }
        } catch (\Exception $e) {
            Log::error("Could not enrich fundamental data for {$stock->symbol}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            // Continue with defaults
        }
        
        // If we have enough historical data, calculate technical indicators
        if ($recentPrices->count() >= 20) {
            $closes = $recentPrices->pluck('close')->reverse()->values()->toArray();
            $highs = $recentPrices->pluck('high')->reverse()->values()->toArray();
            $lows = $recentPrices->pluck('low')->reverse()->values()->toArray();
            $volumes = $recentPrices->pluck('volume')->reverse()->values()->toArray();
            
            // Price changes from historical data
            if (count($closes) >= 7) {
                $data['price_change_1d'] = $this->calculatePriceChangeFromArray($closes, 1);
                $data['price_change_3d'] = $this->calculatePriceChangeFromArray($closes, 3);
                $data['price_change_7d'] = $this->calculatePriceChangeFromArray($closes, 7);
            }
            // Note: Fresh API override happens in getPredictionForHorizon after this function
            
            // RSI
            $rsi14 = $this->calculateRSIValue($closes, 14);
            $rsi7 = $this->calculateRSIValue($closes, 7);
            $data['rsi_14'] = $rsi14;
            $data['rsi_7'] = $rsi7;
            
            // EMAs and MACD
            $ema12 = $this->calculateEMA($closes, 12);
            $ema26 = $this->calculateEMA($closes, 26);
            $data['ema_12'] = $ema12;
            $data['ema_26'] = $ema26;
            
            // Calculate MACD with proper signal line and histogram
            $macdData = $this->calculateMACD($closes, 12, 26, 9);
            $data['macd'] = $macdData['macd'];
            $data['macd_signal'] = $macdData['signal'];
            $data['macd_hist'] = $macdData['histogram'];
            
            // ATR
            if (count($highs) >= 14) {
                $data['atr_14'] = $this->calculateATRValue($highs, $lows, $closes, 14);
            }
            
            // Bollinger Bands
            if (count($closes) >= 20) {
                $bb = $this->calculateBollingerBandsValues($closes, $close, 20, 2);
                $data['bb_upper'] = $bb['upper'];
                $data['bb_middle'] = $bb['middle'];
                $data['bb_lower'] = $bb['lower'];
                $data['bb_width'] = $bb['width'];
                $data['bb_pct'] = $bb['pct'];
            }
            
            // OBV (On-Balance Volume)
            $data['obv'] = $this->calculateOBV($closes, $volumes);
            
            // Volume analysis
            if (count($volumes) >= 20) {
                $volumeSMA = array_sum(array_slice($volumes, -20)) / 20;
                $data['volume_sma_ratio'] = $volumeSMA > 0 ? $volume / $volumeSMA : 1.0;
                $data['volume_spike'] = $data['volume_sma_ratio'] > 1.5;
            }
            
            // Support/Resistance
            $sr = $this->calculateSupportResistance($closes, $highs, $lows, $close);
            $data['distance_to_support'] = $sr['distance_to_support'];
            $data['distance_to_resistance'] = $sr['distance_to_resistance'];
            
            // Institutional Flow Score (smart money indicator)
            // Calculate based on price-volume relationship and OBV trend
            if (count($closes) >= 10 && count($volumes) >= 10) {
                $data['inst_flow_score'] = $this->calculateInstitutionalFlow($closes, $volumes, $data['obv']);
            } else {
                $data['inst_flow_score'] = 0.0;
            }
        }
        
        // Try to get Fear & Greed Index
        try {
            $fearGreedService = app(FearGreedIndexService::class);
            $fearGreed = $fearGreedService->getFearGreedIndex();
            $data['fear_greed_index'] = $fearGreed['value'] ?? 50.0;
            Log::info("Fear & Greed Index for {$stock->symbol}", [
                'fear_greed_index' => $data['fear_greed_index'],
                'classification' => $fearGreed['classification'] ?? 'Unknown',
                'full_data' => $fearGreed
            ]);
        } catch (\Exception $e) {
            // Default to neutral if service fails
            Log::warning("Fear & Greed Index unavailable: " . $e->getMessage());
            $data['fear_greed_index'] = 50.0;
        }
        
        // Detect potential rebound patterns for logging
        $isRebounding = false;
        $reboundReason = '';
        
        // PRIMARY: Strong positive news after decline (most important rebound signal)
        if ($sentiment > 0.3 && $data['price_change_7d'] < 0) {
            $isRebounding = true;
            if ($data['price_change_1d'] > 0 || $data['price_change_3d'] > 0) {
                $reboundReason = sprintf('Strong bullish news (%.2f) with recovery from %.1f%% decline', 
                    $sentiment, $data['price_change_7d']);
            } else {
                $reboundReason = sprintf('Major rebound setup: Bullish news (%.2f) after %.1f%% decline', 
                    $sentiment, $data['price_change_7d']);
            }
        } elseif ($data['price_change_7d'] < -3 && $data['price_change_3d'] > 1 && $data['price_change_1d'] > 0) {
            $isRebounding = true;
            $reboundReason = 'V-shaped recovery pattern';
        } elseif ($data['price_change_1d'] > 2 && $sentiment > 0.2) {
            $isRebounding = true;
            $reboundReason = 'Strong daily bounce with positive news';
        }
        
        // Debug logging
        Log::info("Stock data prepared for {$stock->symbol}", [
            'price_count' => $recentPrices->count(),
            'news_sentiment' => round($sentiment, 3),
            'news_sentiment_raw_db' => round($rawSentiment, 2),
            'recent_news_count' => $recentNews->count() ?? 0,
            'fear_greed_index' => round($data['fear_greed_index'], 1),
            'price_change_1d' => round($data['price_change_1d'], 2),
            'price_change_3d' => round($data['price_change_3d'], 2),
            'price_change_7d' => round($data['price_change_7d'], 2),
            'rsi_14' => round($data['rsi_14'], 1),
            'rsi_7' => round($data['rsi_7'], 1),
            'macd' => round($data['macd'], 3),
            'atr_14' => round($data['atr_14'], 2),
            'bb_width' => round($data['bb_width'], 2),
            'volume_ratio' => round($data['volume_sma_ratio'], 2),
            'rebound_detected' => $isRebounding,
            'rebound_reason' => $reboundReason,
        ]);
        
        return $data;
    }
    
    /**
     * Calculate price change percentage over period
     * 
     * @param array $prices
     * @param int $period
     * @return float
     */
    protected function calculatePriceChange(array $prices, int $period): float
    {
        if (count($prices) < $period + 1) {
            return 0.0;
        }
        
        $latest = $prices[0];
        $previous = $prices[$period];
        
        if ($previous == 0) {
            return 0.0;
        }
        
        return (($latest - $previous) / $previous) * 100;
    }
    
    /**
     * Calculate price change from array (oldest to newest)
     */
    protected function calculatePriceChangeFromArray(array $closes, int $period): float
    {
        if (count($closes) < $period + 1) {
            return 0.0;
        }
        
        $latest = end($closes);
        $previous = $closes[count($closes) - $period - 1];
        
        if ($previous == 0) {
            return 0.0;
        }
        
        return (($latest - $previous) / $previous) * 100;
    }
    
    /**
     * Calculate RSI value
     */
    protected function calculateRSIValue(array $closes, int $period = 14): float
    {
        if (count($closes) < $period + 1) {
            return 50.0;
        }
        
        $gains = [];
        $losses = [];
        
        for ($i = 1; $i < count($closes); $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            $gains[] = $change > 0 ? $change : 0;
            $losses[] = $change < 0 ? abs($change) : 0;
        }
        
        $avgGain = array_sum(array_slice($gains, -$period)) / $period;
        $avgLoss = array_sum(array_slice($losses, -$period)) / $period;
        
        if ($avgLoss == 0) {
            return 100.0;
        }
        
        $rs = $avgGain / $avgLoss;
        return 100 - (100 / (1 + $rs));
    }
    
    /**
     * Calculate EMA
     */
    protected function calculateEMA(array $values, int $period): float
    {
        if (count($values) < $period) {
            return array_sum($values) / count($values);
        }
        
        $multiplier = 2 / ($period + 1);
        $ema = array_sum(array_slice($values, 0, $period)) / $period;
        
        for ($i = $period; $i < count($values); $i++) {
            $ema = ($values[$i] * $multiplier) + ($ema * (1 - $multiplier));
        }
        
        return $ema;
    }
    
    /**
     * Calculate MACD with signal line and histogram
     * 
     * @param array $closes Array of closing prices
     * @param int $fastPeriod Fast EMA period (default 12)
     * @param int $slowPeriod Slow EMA period (default 26)
     * @param int $signalPeriod Signal line EMA period (default 9)
     * @return array ['macd' => float, 'signal' => float, 'histogram' => float]
     */
    protected function calculateMACD(array $closes, int $fastPeriod = 12, int $slowPeriod = 26, int $signalPeriod = 9): array
    {
        $count = count($closes);
        
        // Need at least slow period + signal period data points
        if ($count < $slowPeriod + $signalPeriod) {
            return [
                'macd' => 0.0,
                'signal' => 0.0,
                'histogram' => 0.0,
            ];
        }
        
        // Calculate EMA series for fast and slow periods
        $fastEMA = [];
        $slowEMA = [];
        $macdLine = [];
        
        // Initialize EMAs with SMA
        $fastMultiplier = 2 / ($fastPeriod + 1);
        $slowMultiplier = 2 / ($slowPeriod + 1);
        
        $fastEMA[0] = array_sum(array_slice($closes, 0, $fastPeriod)) / $fastPeriod;
        $slowEMA[0] = array_sum(array_slice($closes, 0, $slowPeriod)) / $slowPeriod;
        
        // Calculate fast EMA series
        for ($i = $fastPeriod; $i < $count; $i++) {
            $fastEMA[$i - $fastPeriod + 1] = ($closes[$i] * $fastMultiplier) + ($fastEMA[$i - $fastPeriod] * (1 - $fastMultiplier));
        }
        
        // Calculate slow EMA series
        for ($i = $slowPeriod; $i < $count; $i++) {
            $slowEMA[$i - $slowPeriod + 1] = ($closes[$i] * $slowMultiplier) + ($slowEMA[$i - $slowPeriod] * (1 - $slowMultiplier));
        }
        
        // Calculate MACD line (fast EMA - slow EMA)
        $offset = $slowPeriod - $fastPeriod;
        for ($i = 0; $i < count($slowEMA); $i++) {
            $macdLine[] = $fastEMA[$i + $offset] - $slowEMA[$i];
        }
        
        // Calculate signal line (EMA of MACD line)
        if (count($macdLine) < $signalPeriod) {
            $signal = end($macdLine) ?: 0.0;
        } else {
            $signalMultiplier = 2 / ($signalPeriod + 1);
            $signal = array_sum(array_slice($macdLine, 0, $signalPeriod)) / $signalPeriod;
            
            for ($i = $signalPeriod; $i < count($macdLine); $i++) {
                $signal = ($macdLine[$i] * $signalMultiplier) + ($signal * (1 - $signalMultiplier));
            }
        }
        
        // Get latest MACD value
        $macd = end($macdLine) ?: 0.0;
        
        // Calculate histogram
        $histogram = $macd - $signal;
        
        return [
            'macd' => round($macd, 4),
            'signal' => round($signal, 4),
            'histogram' => round($histogram, 4),
        ];
    }
    
    /**
     * Calculate ATR value
     */
    protected function calculateATRValue(array $highs, array $lows, array $closes, int $period = 14): float
    {
        if (count($highs) < $period + 1) {
            return 0.0;
        }
        
        $trueRanges = [];
        
        for ($i = 1; $i < count($highs); $i++) {
            $high = $highs[$i];
            $low = $lows[$i];
            $prevClose = $closes[$i - 1];
            
            $tr = max(
                $high - $low,
                abs($high - $prevClose),
                abs($low - $prevClose)
            );
            
            $trueRanges[] = $tr;
        }
        
        return array_sum(array_slice($trueRanges, -$period)) / $period;
    }
    
    /**
     * Calculate Bollinger Bands
     */
    protected function calculateBollingerBandsValues(array $closes, float $currentPrice, int $period = 20, int $stdDev = 2): array
    {
        if (count($closes) < $period) {
            return [
                'upper' => $currentPrice,
                'middle' => $currentPrice,
                'lower' => $currentPrice,
                'width' => 0.0,
                'pct' => 0.5,
            ];
        }
        
        $slice = array_slice($closes, -$period);
        $sma = array_sum($slice) / count($slice);
        
        // Calculate standard deviation
        $variance = array_sum(array_map(function($val) use ($sma) {
            return pow($val - $sma, 2);
        }, $slice)) / count($slice);
        $sd = sqrt($variance);
        
        $upper = $sma + ($stdDev * $sd);
        $lower = $sma - ($stdDev * $sd);
        $width = ($upper - $lower) / $sma;
        
        // Calculate BB %
        $bbPct = 0.5;
        if ($upper > $lower) {
            $bbPct = ($currentPrice - $lower) / ($upper - $lower);
        }
        
        return [
            'upper' => $upper,
            'middle' => $sma,
            'lower' => $lower,
            'width' => $width,
            'pct' => $bbPct,
        ];
    }
    
    /**
     * Calculate OBV (On-Balance Volume)
     */
    protected function calculateOBV(array $closes, array $volumes): float
    {
        if (count($closes) < 2 || count($volumes) < 2) {
            return 0.0;
        }
        
        $obv = 0;
        for ($i = 1; $i < count($closes); $i++) {
            if ($closes[$i] > $closes[$i - 1]) {
                $obv += $volumes[$i];
            } elseif ($closes[$i] < $closes[$i - 1]) {
                $obv -= $volumes[$i];
            }
        }
        
        return $obv;
    }
    
    /**
     * Calculate institutional flow score (smart money indicator)
     * 
     * Measures institutional buying/selling pressure based on:
     * 1. Price-volume correlation (accumulation/distribution)
     * 2. Volume behavior on up days vs down days
     * 3. OBV trend
     * 
     * Returns: -1.0 (strong selling) to +1.0 (strong buying)
     */
    protected function calculateInstitutionalFlow(array $closes, array $volumes, float $obv): float
    {
        $count = min(count($closes), count($volumes));
        if ($count < 10) {
            return 0.0;
        }
        
        // Look at last 10 days for recent institutional activity
        $lookback = min(10, $count - 1);
        $closes = array_slice($closes, -$lookback - 1);
        $volumes = array_slice($volumes, -$lookback - 1);
        
        $upVolume = 0;
        $downVolume = 0;
        $upDays = 0;
        $downDays = 0;
        $priceVolumeCorrelation = 0;
        
        for ($i = 1; $i < count($closes); $i++) {
            $priceChange = $closes[$i] - $closes[$i - 1];
            $priceChangePercent = $closes[$i - 1] > 0 ? ($priceChange / $closes[$i - 1]) * 100 : 0;
            $volumeChange = $volumes[$i] - $volumes[$i - 1];
            
            if ($priceChangePercent > 0) {
                // Up day
                $upVolume += $volumes[$i];
                $upDays++;
                
                // Institutional buying: price up on increasing volume
                if ($volumeChange > 0) {
                    $priceVolumeCorrelation += abs($priceChangePercent) * ($volumes[$i] / ($volumes[$i - 1] + 1));
                }
            } elseif ($priceChangePercent < 0) {
                // Down day
                $downVolume += $volumes[$i];
                $downDays++;
                
                // Institutional selling: price down on increasing volume
                if ($volumeChange > 0) {
                    $priceVolumeCorrelation -= abs($priceChangePercent) * ($volumes[$i] / ($volumes[$i - 1] + 1));
                }
            }
        }
        
        // Calculate average volume per up/down day
        $avgUpVolume = $upDays > 0 ? $upVolume / $upDays : 0;
        $avgDownVolume = $downDays > 0 ? $downVolume / $downDays : 0;
        
        // Institutional accumulation: higher volume on up days than down days
        $volumeRatio = 0;
        if ($avgDownVolume > 0) {
            $volumeRatio = ($avgUpVolume - $avgDownVolume) / $avgDownVolume;
        } elseif ($avgUpVolume > 0) {
            $volumeRatio = 1.0; // All up days
        }
        
        // Normalize volume ratio to -1 to +1
        $volumeScore = max(-1.0, min(1.0, $volumeRatio));
        
        // Normalize price-volume correlation
        $correlationScore = max(-1.0, min(1.0, $priceVolumeCorrelation / 10));
        
        // OBV trend: positive OBV suggests accumulation
        $obvScore = 0;
        if ($obv != 0) {
            // Normalize OBV to reasonable range
            $obvScore = max(-1.0, min(1.0, $obv / (array_sum($volumes) * 2)));
        }
        
        // Weighted combination
        $instFlow = ($volumeScore * 0.4) + ($correlationScore * 0.4) + ($obvScore * 0.2);
        
        return round($instFlow, 4);
    }
    
    /**
     * Calculate support and resistance distances
     */
    protected function calculateSupportResistance(array $closes, array $highs, array $lows, float $currentPrice): array
    {
        if (count($closes) < 20) {
            return [
                'distance_to_support' => 0.0,
                'distance_to_resistance' => 0.0,
            ];
        }
        
        // Find local maxima and minima
        $resistanceLevels = [];
        $supportLevels = [];
        
        for ($i = 2; $i < count($highs) - 2; $i++) {
            // Peak (resistance)
            if ($highs[$i] > $highs[$i-1] && $highs[$i] > $highs[$i-2] && 
                $highs[$i] > $highs[$i+1] && $highs[$i] > $highs[$i+2]) {
                $resistanceLevels[] = $highs[$i];
            }
            
            // Trough (support)
            if ($lows[$i] < $lows[$i-1] && $lows[$i] < $lows[$i-2] && 
                $lows[$i] < $lows[$i+1] && $lows[$i] < $lows[$i+2]) {
                $supportLevels[] = $lows[$i];
            }
        }
        
        // Find nearest support/resistance
        $support = null;
        $resistance = null;
        
        foreach ($supportLevels as $level) {
            if ($level < $currentPrice && ($support === null || $level > $support)) {
                $support = $level;
            }
        }
        
        foreach ($resistanceLevels as $level) {
            if ($level > $currentPrice && ($resistance === null || $level < $resistance)) {
                $resistance = $level;
            }
        }
        
        $distanceToSupport = $support ? (($currentPrice - $support) / $currentPrice) * 100 : 0.0;
        $distanceToResistance = $resistance ? (($resistance - $currentPrice) / $currentPrice) * 100 : 0.0;
        
        return [
            'distance_to_support' => $distanceToSupport,
            'distance_to_resistance' => $distanceToResistance,
        ];
    }
    
    /**
     * Get fallback prediction when Python script fails
     * 
     * @param Stock $stock
     * @return array
     */
    protected function getFallbackPrediction(Stock $stock): array
    {
        $sentiment = $stock->getAverageSentiment() ?? 0.0;
        $latestPrice = $stock->latestPrice;
        $close = $latestPrice?->close ?? 100.0;
        
        // Simple sentiment-based prediction
        $isBullish = $sentiment >= 0;
        $expectedMove = min(abs($sentiment) * 0.5, 5.0); // Cap at 5%
        
        if (!$isBullish) {
            $expectedMove = -$expectedMove;
        }
        
        return [
            'label' => $isBullish ? 'BULLISH' : 'BEARISH',
            'probability' => 0.5 + (abs($sentiment) / 20), // 0.5 to 1.0
            'expected_pct_move' => round($expectedMove, 2),
            'current_price' => $close,
            'news_sentiment_score' => $sentiment,
            'base_score' => $sentiment,
            'final_score' => $sentiment,
            'asian_influence_score' => 0,
            'asian_impact_percent' => 0,
            'top_reasons' => [
                'Fallback prediction based on news sentiment',
                'Python model unavailable - using simplified algorithm',
                'Sentiment score: ' . round($sentiment, 2)
            ],
            'model_version' => 'fallback_v1',
            'is_fallback' => true,
        ];
    }
    
    /**
     * Sanitize numeric values to ensure proper type casting
     * 
     * @param array $data
     * @return array
     */
    protected function sanitizeNumericValues(array $data): array
    {
        $numericFields = [
            'close', 'volume', 'news_sentiment_score',
            'price_change_1d', 'price_change_3d', 'price_change_7d',
            'ema_12', 'ema_26', 'macd', 'macd_signal', 'macd_hist',
            'rsi_14', 'rsi_7', 'atr_14', 'obv',
            'bb_upper', 'bb_middle', 'bb_lower', 'bb_width', 'bb_pct',
            'distance_to_support', 'distance_to_resistance',
            'volume_sma_ratio', 'volume_spike', 'fear_greed_index',
            'asian_avg_change', 'asian_influence_score', 'asian_impact_percent',
            'nikkei_change_pct', 'hang_seng_change_pct',
            'shanghai_change_pct', 'nifty_change_pct',
            'european_avg_change', 'european_influence_score', 'european_impact_percent',
            'ftse_change_pct', 'dax_change_pct', 'cac_change_pct', 
            'stoxx_change_pct', 'ibex_change_pct',
            'category_multiplier', 'typical_daily_range_min', 'typical_daily_range_max'
        ];
        
        foreach ($numericFields as $field) {
            if (isset($data[$field])) {
                // Convert to float if it's numeric-like
                if (is_numeric($data[$field]) || is_bool($data[$field])) {
                    $data[$field] = (float) $data[$field];
                }
            }
        }
        
        // Handle nested arrays (like individual_changes)
        if (isset($data['individual_changes']) && is_array($data['individual_changes'])) {
            foreach ($data['individual_changes'] as $key => $market) {
                if (is_array($market)) {
                    foreach (['change_percent', 'weight'] as $numField) {
                        if (isset($market[$numField]) && is_numeric($market[$numField])) {
                            $data['individual_changes'][$key][$numField] = (float) $market[$numField];
                        }
                    }
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Calculate category-aware prediction ranges (low/high)
     * 
     * Uses category volatility multiplier and typical ranges to provide
     * realistic price bounds for the prediction.
     * 
     * @param float $currentPrice Current stock price
     * @param float $expectedPercentMove Expected % move from model
     * @param Stock $stock Stock with category loaded
     * @param float $confidence Prediction confidence (0-1)
     * @return array ['predicted_low' => float, 'predicted_high' => float, 'predicted_price' => float]
     */
    public function calculateCategoryAwarePriceRange(
        float $currentPrice,
        float $expectedPercentMove,
        Stock $stock,
        float $confidence = 0.7
    ): array {
        // Load category if not already loaded
        if (!$stock->relationLoaded('category')) {
            $stock->load('category');
        }
        
        // Get category parameters or use defaults
        if ($stock->category) {
            $volatilityMultiplier = (float) $stock->category->volatility_multiplier;
            $rangeMin = (float) $stock->category->typical_daily_range_min;
            $rangeMax = (float) $stock->category->typical_daily_range_max;
        } else {
            $volatilityMultiplier = 1.0;
            $rangeMin = 0.5;
            $rangeMax = 2.0;
        }
        
        // Calculate predicted price
        $predictedPrice = $currentPrice * (1 + ($expectedPercentMove / 100));
        
        // Calculate uncertainty range based on:
        // 1. Category volatility
        // 2. Confidence level (higher confidence = tighter range)
        // 3. Typical daily range for this category
        
        $baseUncertainty = ($rangeMax - $rangeMin) / 2; // Average of typical range
        $confidenceFactor = (1 - $confidence); // Lower confidence = wider range
        $uncertaintyPercent = $baseUncertainty * $volatilityMultiplier * (0.5 + $confidenceFactor);
        
        // For strong predictions, tighten the range
        if (abs($expectedPercentMove) > 3.0) {
            $uncertaintyPercent *= 0.8; // Reduce uncertainty for strong signals
        }
        
        // Calculate low and high bounds
        if ($expectedPercentMove >= 0) {
            // Bullish prediction: range extends more upward
            $predictedLow = $currentPrice * (1 + ($expectedPercentMove / 100) - ($uncertaintyPercent / 100));
            $predictedHigh = $currentPrice * (1 + ($expectedPercentMove / 100) + ($uncertaintyPercent * 1.5 / 100));
        } else {
            // Bearish prediction: range extends more downward
            $predictedLow = $currentPrice * (1 + ($expectedPercentMove / 100) - ($uncertaintyPercent * 1.5 / 100));
            $predictedHigh = $currentPrice * (1 + ($expectedPercentMove / 100) + ($uncertaintyPercent / 100));
        }
        
        // Ensure low < predicted < high
        $predictedLow = min($predictedLow, $predictedPrice);
        $predictedHigh = max($predictedHigh, $predictedPrice);
        
        // Never predict below zero
        $predictedLow = max(0.01, $predictedLow);
        
        return [
            'predicted_price' => round($predictedPrice, 2),
            'predicted_low' => round($predictedLow, 2),
            'predicted_high' => round($predictedHigh, 2),
            'range_percent' => round((($predictedHigh - $predictedLow) / $currentPrice) * 100, 2),
            'category_multiplier' => $volatilityMultiplier,
        ];
    }
}
