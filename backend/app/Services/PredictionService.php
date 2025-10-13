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
    public function getPredictionForHorizon(Stock $stock, string $horizon = 'today'): array
    {
        try {
            // Only 'today' is supported in quick_model_v2
            if ($horizon !== 'today') {
                Log::warning("Unsupported horizon '{$horizon}' requested for {$stock->symbol}. Falling back to 'today'.");
                $horizon = 'today';
            }
            
            // Call Python quick_model_v4.py for prediction (with European + Asian markets)
            $pythonPath = base_path('python/models/quick_model_v4.py');
            $asianMarketService = app(AsianMarketService::class);
            $europeanMarketService = app(EuropeanMarketService::class);
            
            // Get Asian market data
            $asianMarkets = $asianMarketService->getTodayChanges();
            $asianNormalized = $asianMarketService->normalizeForModel($asianMarkets);
            
            // Get European market data
            $europeanMarkets = $europeanMarketService->getTodayChanges();
            $europeanNormalized = $europeanMarketService->normalizeForModel($europeanMarkets);
            
            // Get stock data
            $stockData = $this->prepareStockData($stock);
            
            // Prepare input for Python script - merge all market data
            $input = array_merge($stockData, $asianNormalized, $europeanNormalized);
            
            // CRITICAL: Ensure all numeric values are proper floats/ints (not strings)
            $input = $this->sanitizeNumericValues($input);
            
            $inputJson = json_encode($input, JSON_NUMERIC_CHECK);
            
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
            $result['model_version'] = 'quick_model_v4';
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
        
        // Initialize base data
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
        ];
        
        // If we have enough historical data, calculate technical indicators
        if ($recentPrices->count() >= 20) {
            $closes = $recentPrices->pluck('close')->reverse()->values()->toArray();
            $highs = $recentPrices->pluck('high')->reverse()->values()->toArray();
            $lows = $recentPrices->pluck('low')->reverse()->values()->toArray();
            $volumes = $recentPrices->pluck('volume')->reverse()->values()->toArray();
            
            // Price changes
            if (count($closes) >= 7) {
                $data['price_change_1d'] = $this->calculatePriceChangeFromArray($closes, 1);
                $data['price_change_3d'] = $this->calculatePriceChangeFromArray($closes, 3);
                $data['price_change_7d'] = $this->calculatePriceChangeFromArray($closes, 7);
            }
            
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
            $data['macd'] = $ema12 - $ema26;
            $data['macd_signal'] = $data['macd']; // Simplified
            $data['macd_hist'] = 0.0;
            
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
        }
        
        // Try to get Fear & Greed Index
        try {
            $fearGreedService = app(FearGreedIndexService::class);
            $fearGreed = $fearGreedService->getFearGreedIndex();
            $data['fear_greed_index'] = $fearGreed['value'] ?? 50.0;
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
            'raw_sentiment' => round($rawSentiment, 2),
            'recent_news_count' => $recentNews->count() ?? 0,
            'price_change_1d' => round($data['price_change_1d'], 2),
            'price_change_3d' => round($data['price_change_3d'], 2),
            'price_change_7d' => round($data['price_change_7d'], 2),
            'rsi_14' => round($data['rsi_14'], 1),
            'rsi_7' => round($data['rsi_7'], 1),
            'macd' => round($data['macd'], 3),
            'atr_14' => round($data['atr_14'], 2),
            'bb_width' => round($data['bb_width'], 2),
            'fear_greed' => round($data['fear_greed_index'], 1),
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
            'stoxx_change_pct', 'ibex_change_pct'
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
}
