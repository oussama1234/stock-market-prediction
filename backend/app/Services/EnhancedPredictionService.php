<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\Prediction;
use App\Models\StockPrice;
use App\Models\NewsArticle;
use App\Services\ApiClients\FinnhubClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class EnhancedPredictionService
{
    protected SentimentService $sentimentService;
    protected FinnhubClient $finnhubClient;
    protected FearGreedIndexService $fearGreedService;
    protected NewsService $newsService;
    
    // Technical indicator constants
    protected const RSI_PERIOD = 14;
    protected const MA_SHORT = 5;
    protected const MA_LONG = 20;
    protected const BOLLINGER_PERIOD = 20;
    protected const BOLLINGER_STD = 2;
    protected const ATR_PERIOD = 14; // Average True Range
    
    public function __construct(
        SentimentService $sentimentService, 
        FinnhubClient $finnhubClient,
        FearGreedIndexService $fearGreedService,
        NewsService $newsService
    ) {
        $this->sentimentService = $sentimentService;
        $this->finnhubClient = $finnhubClient;
        $this->fearGreedService = $fearGreedService;
        $this->newsService = $newsService;
    }
    
    /**
     * Generate comprehensive AI prediction
     */
    public function generateAdvancedPrediction(Stock $stock, ?array $quoteData = null): ?Prediction
    {
        try {
            Log::info("Generating advanced prediction for {$stock->symbol}");
            
            // Step 1: Gather all data sources
            $dataSet = $this->gatherComprehensiveData($stock, $quoteData);
            
            if (!$dataSet['current_price']) {
                // Robust fallback: try latest stored close, then fundamentals, then conservative default
                $fallback = $stock->latestPrice?->close;
                if (!$fallback) {
                    $mc = $stock->market_cap; // in millions
                    $so = $stock->shares_outstanding; // in millions
                    if ($mc && $so && (float)$so > 0) {
                        $fallback = (float) ($mc / $so);
                        Log::info("Using fundamentals-derived price for {$stock->symbol}: {$fallback}");
                    }
                }
                if (!$fallback) {
                    $fallback = 100.00;
                    Log::warning("No price or fundamentals for {$stock->symbol}; defaulting to {$fallback}");
                }
                $dataSet['current_price'] = (float) $fallback;
                $dataSet['open'] = $dataSet['open'] ?? (float) $fallback;
                $dataSet['high'] = $dataSet['high'] ?? (float) $fallback;
                $dataSet['low'] = $dataSet['low'] ?? (float) $fallback;
                $dataSet['previous_close'] = $dataSet['previous_close'] ?? (float) $fallback;
            }
            
            // Step 2: Calculate technical indicators
            $technicalIndicators = $this->calculateTechnicalIndicators($stock, $dataSet);
            
            // Step 3: Analyze sentiment from news
            $sentimentAnalysis = $this->analyzeSentimentSignals($stock);
            
            // Step 4: Analyze volume patterns
            $volumeAnalysis = $this->analyzeVolumePatterns($stock);
            
            // Step 5: Calculate market conditions
            $marketConditions = $this->assessMarketConditions($dataSet);
            
            // Step 6: AI Prediction Algorithm
            $prediction = $this->advancedPredictionAlgorithm(
                $dataSet,
                $technicalIndicators,
                $sentimentAnalysis,
                $volumeAnalysis,
                $marketConditions
            );
            
            // Step 7: Store prediction with reasoning
            return $this->storePrediction($stock, $prediction, [
                'technical' => $technicalIndicators,
                'sentiment' => $sentimentAnalysis,
                'volume' => $volumeAnalysis,
                'market' => $marketConditions,
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to generate advanced prediction for {$stock->symbol}: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            return null;
        }
    }
    
    /**
     * Gather comprehensive data from all sources
     */
    protected function gatherComprehensiveData(Stock $stock, ?array $quoteData): array
    {
        return [
            'current_price' => $quoteData['current_price'] ?? $stock->latestPrice?->close ?? null,
            'open' => $quoteData['open'] ?? $stock->latestPrice?->open ?? null,
            'high' => $quoteData['high'] ?? $stock->latestPrice?->high ?? null,
            'low' => $quoteData['low'] ?? $stock->latestPrice?->low ?? null,
            'volume' => $quoteData['volume'] ?? $stock->latestPrice?->volume ?? 0,
            'previous_close' => $quoteData['previous_close'] ?? $stock->latestPrice?->close ?? null,
            'change' => $quoteData['change'] ?? 0,
            'change_percent' => $quoteData['change_percent'] ?? 0,
        ];
    }
    
    /**
     * Calculate comprehensive technical indicators
     */
    protected function calculateTechnicalIndicators(Stock $stock, array $dataSet): array
    {
        // Get historical prices (last 50 days for reliable indicators)
        $prices = StockPrice::where('stock_id', $stock->id)
            ->where('interval', '1day')
            ->where('price_date', '>=', now()->subDays(50))
            ->orderBy('price_date', 'desc')
            ->limit(50)
            ->get();
        
        if ($prices->count() < 20) {
            return $this->getDefaultIndicators();
        }
        
        $closes = $prices->pluck('close')->reverse()->values()->toArray();
        $highs = $prices->pluck('high')->reverse()->values()->toArray();
        $lows = $prices->pluck('low')->reverse()->values()->toArray();
        $volumes = $prices->pluck('volume')->reverse()->values()->toArray();
        
        $currentPrice = $dataSet['current_price'];
        
        return [
            'rsi' => $this->calculateRSI($closes),
            'macd' => $this->calculateMACD($closes),
            'moving_averages' => $this->calculateMovingAverages($closes, $currentPrice),
            'bollinger_bands' => $this->calculateBollingerBands($closes, $currentPrice),
            'momentum' => $this->calculateMomentum($closes),
            'volatility' => $this->calculateVolatility($closes),
            'support_resistance' => $this->findSupportResistance($closes, $highs, $lows, $currentPrice),
            'atr' => $this->calculateATR($highs, $lows, $closes),
        ];
    }
    
    /**
     * Calculate RSI (Relative Strength Index)
     */
    protected function calculateRSI(array $closes): array
    {
        $period = self::RSI_PERIOD;
        if (count($closes) < $period + 1) {
            return ['value' => 50, 'signal' => 'neutral', 'strength' => 0];
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
            $rsi = 100;
        } else {
            $rs = $avgGain / $avgLoss;
            $rsi = 100 - (100 / (1 + $rs));
        }
        
        // Determine signal
        $signal = 'neutral';
        $strength = 0;
        
        if ($rsi < 30) {
            $signal = 'oversold_buy';
            $strength = (30 - $rsi) / 30; // 0 to 1
        } elseif ($rsi > 70) {
            $signal = 'overbought_sell';
            $strength = ($rsi - 70) / 30; // 0 to 1
        }
        
        return [
            'value' => round($rsi, 2),
            'signal' => $signal,
            'strength' => round($strength, 2),
        ];
    }
    
    /**
     * Calculate MACD (Moving Average Convergence Divergence)
     */
    protected function calculateMACD(array $closes): array
    {
        if (count($closes) < 26) {
            return ['value' => 0, 'signal' => 'neutral', 'histogram' => 0];
        }
        
        $ema12 = $this->calculateEMA($closes, 12);
        $ema26 = $this->calculateEMA($closes, 26);
        $macdLine = $ema12 - $ema26;
        
        // Signal line (9-day EMA of MACD)
        $macdValues = [$macdLine]; // Simplified - in production, calculate full MACD history
        $signalLine = $macdLine; // Simplified
        
        $histogram = $macdLine - $signalLine;
        
        $signal = $histogram > 0 ? ($macdLine > 0 ? 'strong_buy' : 'buy') : ($macdLine < 0 ? 'strong_sell' : 'sell');
        
        return [
            'value' => round($macdLine, 2),
            'signal' => $signal,
            'histogram' => round($histogram, 2),
            'strength' => round(min(abs($histogram) / 5, 1), 2),
        ];
    }
    
    /**
     * Calculate Exponential Moving Average
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
     * Calculate Moving Averages
     */
    protected function calculateMovingAverages(array $closes, float $currentPrice): array
    {
        $ma5 = $this->calculateSMA($closes, self::MA_SHORT);
        $ma20 = $this->calculateSMA($closes, self::MA_LONG);
        
        $signal = 'neutral';
        $strength = 0;
        
        if ($ma5 && $ma20) {
            if ($ma5 > $ma20 && $currentPrice > $ma5) {
                $signal = 'bullish';
                $strength = min(($ma5 - $ma20) / $ma20, 0.5) * 2; // Normalize to 0-1
            } elseif ($ma5 < $ma20 && $currentPrice < $ma5) {
                $signal = 'bearish';
                $strength = min(($ma20 - $ma5) / $ma20, 0.5) * 2;
            }
        }
        
        return [
            'ma5' => $ma5 ? round($ma5, 2) : null,
            'ma20' => $ma20 ? round($ma20, 2) : null,
            'current' => round($currentPrice, 2),
            'signal' => $signal,
            'strength' => round($strength, 2),
        ];
    }
    
    /**
     * Calculate Simple Moving Average
     */
    protected function calculateSMA(array $values, int $period): ?float
    {
        if (count($values) < $period) {
            return null;
        }
        
        $slice = array_slice($values, -$period);
        return array_sum($slice) / $period;
    }
    
    /**
     * Calculate Bollinger Bands
     */
    protected function calculateBollingerBands(array $closes, float $currentPrice): array
    {
        if (count($closes) < self::BOLLINGER_PERIOD) {
            return ['position' => 'middle', 'signal' => 'neutral', 'width' => 0];
        }
        
        $sma = $this->calculateSMA($closes, self::BOLLINGER_PERIOD);
        $stdDev = $this->calculateStdDev($closes, self::BOLLINGER_PERIOD);
        
        $upperBand = $sma + (self::BOLLINGER_STD * $stdDev);
        $lowerBand = $sma - (self::BOLLINGER_STD * $stdDev);
        
        $bandWidth = ($upperBand - $lowerBand) / $sma;
        
        $position = 'middle';
        $signal = 'neutral';
        
        if ($currentPrice >= $upperBand) {
            $position = 'above_upper';
            $signal = 'overbought';
        } elseif ($currentPrice <= $lowerBand) {
            $position = 'below_lower';
            $signal = 'oversold';
        } elseif ($currentPrice > $sma) {
            $position = 'upper_half';
            $signal = 'bullish';
        } else {
            $position = 'lower_half';
            $signal = 'bearish';
        }
        
        return [
            'upper' => round($upperBand, 2),
            'middle' => round($sma, 2),
            'lower' => round($lowerBand, 2),
            'current' => round($currentPrice, 2),
            'position' => $position,
            'signal' => $signal,
            'width' => round($bandWidth, 4),
        ];
    }
    
    /**
     * Calculate standard deviation
     */
    protected function calculateStdDev(array $values, int $period): float
    {
        $slice = array_slice($values, -$period);
        $mean = array_sum($slice) / count($slice);
        
        $variance = array_sum(array_map(function($val) use ($mean) {
            return pow($val - $mean, 2);
        }, $slice)) / count($slice);
        
        return sqrt($variance);
    }
    
    /**
     * Calculate momentum indicators
     */
    protected function calculateMomentum(array $closes): array
    {
        if (count($closes) < 10) {
            return ['value' => 0, 'signal' => 'neutral', 'strength' => 0];
        }
        
        $current = end($closes);
        $past = $closes[count($closes) - 10];
        $momentum = (($current - $past) / $past) * 100;
        
        $signal = $momentum > 2 ? 'strong_bullish' : ($momentum > 0 ? 'bullish' : ($momentum < -2 ? 'strong_bearish' : 'bearish'));
        $strength = min(abs($momentum) / 10, 1);
        
        return [
            'value' => round($momentum, 2),
            'signal' => $signal,
            'strength' => round($strength, 2),
        ];
    }
    
    /**
     * Calculate volatility
     */
    protected function calculateVolatility(array $closes): array
    {
        if (count($closes) < 20) {
            return ['value' => 0, 'level' => 'medium'];
        }
        
        $returns = [];
        for ($i = 1; $i < count($closes); $i++) {
            $returns[] = ($closes[$i] - $closes[$i - 1]) / $closes[$i - 1];
        }
        
        $volatility = $this->calculateStdDev($returns, count($returns)) * sqrt(252); // Annualized
        
        $level = $volatility > 0.4 ? 'very_high' : ($volatility > 0.25 ? 'high' : ($volatility > 0.15 ? 'medium' : 'low'));
        
        return [
            'value' => round($volatility, 4),
            'level' => $level,
        ];
    }
    
    /**
     * Calculate Average True Range (ATR) - key for realistic price ranges
     */
    protected function calculateATR(array $highs, array $lows, array $closes): array
    {
        if (count($highs) < self::ATR_PERIOD + 1) {
            return ['value' => 0, 'percentage' => 0];
        }
        
        $trueRanges = [];
        
        for ($i = 1; $i < count($highs); $i++) {
            $high = $highs[$i];
            $low = $lows[$i];
            $prevClose = $closes[$i - 1];
            
            // True Range = max of:
            // 1. Current High - Current Low
            // 2. abs(Current High - Previous Close)
            // 3. abs(Current Low - Previous Close)
            $tr = max(
                $high - $low,
                abs($high - $prevClose),
                abs($low - $prevClose)
            );
            
            $trueRanges[] = $tr;
        }
        
        // ATR is average of last N true ranges
        $atr = array_sum(array_slice($trueRanges, -self::ATR_PERIOD)) / self::ATR_PERIOD;
        $atrPercentage = ($atr / end($closes)) * 100;
        
        return [
            'value' => round($atr, 2),
            'percentage' => round($atrPercentage, 2),
        ];
    }
    
    /**
     * Find support and resistance levels
     */
    protected function findSupportResistance(array $closes, array $highs, array $lows, float $currentPrice): array
    {
        if (count($closes) < 20) {
            return ['support' => null, 'resistance' => null, 'position' => 'unknown'];
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
        
        $position = 'neutral';
        if ($support && $resistance) {
            $range = $resistance - $support;
            $currentPosition = ($currentPrice - $support) / $range;
            
            if ($currentPosition < 0.3) $position = 'near_support';
            elseif ($currentPosition > 0.7) $position = 'near_resistance';
        }
        
        return [
            'support' => $support ? round($support, 2) : null,
            'resistance' => $resistance ? round($resistance, 2) : null,
            'current' => round($currentPrice, 2),
            'position' => $position,
        ];
    }
    
    /**
     * Analyze sentiment from news articles
     * Fetches and stores news if database is empty
     */
    protected function analyzeSentimentSignals(Stock $stock): array
    {
        // Check if we have recent news in database
        $recentNews = NewsArticle::where('stock_id', $stock->id)
            ->where('published_at', '>=', now()->subDays(7))
            ->orderBy('published_at', 'desc')
            ->limit(50)
            ->get();
        
        // If no news in database, fetch and store from APIs
        if ($recentNews->isEmpty()) {
            try {
                Log::info("No news in database for {$stock->symbol}, fetching from APIs...");
                
                // Fetch news from APIs
                $newsArticles = $this->newsService->getStockNews($stock->symbol, 100);
                
                if (!empty($newsArticles)) {
                    // Store articles in database
                    $stored = $this->newsService->bulkStoreForStock($stock, $newsArticles);
                    Log::info("Stored {$stored} news articles for {$stock->symbol}");
                    
                    // Re-fetch from database to get stored articles with IDs
                    $recentNews = NewsArticle::where('stock_id', $stock->id)
                        ->where('published_at', '>=', now()->subDays(7))
                        ->orderBy('published_at', 'desc')
                        ->limit(50)
                        ->get();
                }
            } catch (\Exception $e) {
                Log::warning("Failed to fetch/store news for {$stock->symbol}: " . $e->getMessage());
            }
        }
        
        // If still no news available, return default values
        if ($recentNews->isEmpty()) {
            return [
                'score' => 0,
                'count' => 0,
                'trend' => 'neutral',
                'strength' => 0,
                'recent_sentiment' => 0,
            ];
        }
        
        $scores = $recentNews->pluck('sentiment_score')->filter()->values();
        $avgSentiment = $scores->isNotEmpty() ? $scores->avg() : 0;
        
        // Weight recent news more heavily
        $recentScores = $recentNews->take(5)->pluck('sentiment_score')->filter();
        $recentSentiment = $recentScores->isNotEmpty() ? $recentScores->avg() : 0;
        
        $trend = $recentSentiment > $avgSentiment + 1 ? 'improving' : 
                ($recentSentiment < $avgSentiment - 1 ? 'deteriorating' : 'stable');
        
        $strength = min(abs($avgSentiment) / 10, 1);
        
        // Urgent bearish keywords in last 24 hours
        $urgentBearish = $this->detectUrgentBearishKeywords($stock);

        return [
            'score' => round($avgSentiment, 2),
            'count' => $recentNews->count(),
            'trend' => $trend,
            'strength' => round($strength, 2),
            'recent_sentiment' => round($recentSentiment, 2),
            'urgent_bearish' => $urgentBearish['flag'],
            'urgent_keywords' => $urgentBearish['keywords'],
        ];
    }
    
    /**
     * Analyze volume patterns
     */
    protected function analyzeVolumePatterns(Stock $stock): array
    {
        $prices = StockPrice::where('stock_id', $stock->id)
            ->where('interval', '1day')
            ->where('price_date', '>=', now()->subDays(20))
            ->orderBy('price_date', 'desc')
            ->limit(50)
            ->get();
        
        if ($prices->count() < 5) {
            return [
                'current' => 0,
                'average' => 0,
                'ratio' => 1,
                'trend' => 'unknown',
                'signal' => 'neutral',
                'strength' => 0
            ];
        }
        
        $volumes = $prices->pluck('volume')->values()->toArray();
        $avgVolume = array_sum($volumes) / count($volumes);
        $currentVolume = $volumes[0];
        
        $volumeRatio = $avgVolume > 0 ? $currentVolume / $avgVolume : 1;
        
        $trend = $volumeRatio > 1.5 ? 'high' : ($volumeRatio < 0.5 ? 'low' : 'normal');
        $signal = $volumeRatio > 2 ? 'strong_interest' : ($volumeRatio > 1.3 ? 'increased_interest' : 'normal');
        $strength = min(abs($volumeRatio - 1), 1);
        
        return [
            'current' => $currentVolume,
            'average' => round($avgVolume, 0),
            'ratio' => round($volumeRatio, 2),
            'trend' => $trend,
            'signal' => $signal,
            'strength' => round($strength, 2),
        ];
    }
    
    /**
     * Assess overall market conditions
     */
    protected function assessMarketConditions(array $dataSet): array
    {
        $change = $dataSet['change_percent'];
        $volume = $dataSet['volume'];
        
        $trend = $change > 2 ? 'strong_uptrend' : 
                ($change > 0.5 ? 'uptrend' : 
                ($change < -2 ? 'strong_downtrend' : 
                ($change < -0.5 ? 'downtrend' : 'sideways')));
        
        return [
            'trend' => $trend,
            'change_percent' => round($change, 2),
            'volume' => $volume,
        ];
    }
    
    /**
     * Advanced AI Prediction Algorithm with REALISTIC Trading Ranges
     * Uses ATR, Support/Resistance, and Fear & Greed Index
     */
    protected function advancedPredictionAlgorithm(
        array $dataSet,
        array $technical,
        array $sentiment,
        array $volume,
        array $market
    ): array {
        $currentPrice = $dataSet['current_price'];
        
        // Get Fear & Greed Index
        $fearGreed = $this->fearGreedService->getFearGreedIndex();
        $fearGreedValue = $fearGreed['value'];
        $marketImpact = $fearGreed['market_impact'];
        
        // Weights for each factor (total = 1.0)
        $weights = [
            'technical' => 0.35,
            'sentiment' => 0.25,
            'volume' => 0.15,
            'market' => 0.15,
            'fear_greed' => 0.10, // Fear & Greed influence
        ];
        
        // Calculate signals (-1 to +1 scale)
        $technicalSignal = $this->calculateTechnicalSignal($technical);
        $sentimentSignal = max(-1, min(1, $sentiment['score'] / 10));
        $volumeSignal = $this->calculateVolumeSignal($volume, $dataSet['change_percent']);
        $marketSignal = max(-1, min(1, $dataSet['change_percent'] / 5));
        $fearGreedSignal = $marketImpact['bias']; // -1 to +1
        
        // Weighted combined signal
        $combinedSignal = (
            $technicalSignal * $weights['technical'] +
            $sentimentSignal * $weights['sentiment'] +
            $volumeSignal * $weights['volume'] +
            $marketSignal * $weights['market'] +
            $fearGreedSignal * $weights['fear_greed']
        );

        // Prepare alerts payload for UI
        $alerts = [];

        // Urgent bearish override: if tariff/ban/sanctions detected in last 24h for this stock, bias bearish
        if (!empty($sentiment['urgent_bearish'])) {
            // Force combined signal negative with minimum strength
            $combinedSignal = min($combinedSignal, -0.7);
            $alerts['urgent_warning'] = true;
            $alerts['keywords'] = $sentiment['urgent_keywords'] ?? [];
            $alerts['message'] = 'Urgent bearish event detected today' . (!empty($alerts['keywords']) ? (': ' . implode(', ', array_slice($alerts['keywords'], 0, 5))) : '');
        }
        
        // Use ATR for REALISTIC price prediction range
        $atr = $technical['atr']['value'];
        $atrPercentage = $technical['atr']['percentage'];
        
        // Realistic prediction: 0.5x to 2x ATR based on signal strength
        $signalStrength = abs($combinedSignal);
        $predictedMoveMultiplier = 0.5 + ($signalStrength * 1.5); // 0.5 to 2.0
        $signalDirection = $combinedSignal >= 0 ? 1 : -1;
        $predictedMove = $atr * $predictedMoveMultiplier * $signalDirection;
        $predictedChangePercent = ($predictedMove / $currentPrice) * 100;
        
        // Apply Fear & Greed multiplier (increases volatility in extreme conditions)
        $predictedChangePercent *= $marketImpact['multiplier'];
        
        // Cap based on volatility level (more conservative for stable stocks)
        $volatility = $technical['volatility']['value'];
        $maxMove = $volatility > 0.4 ? 8 : ($volatility > 0.25 ? 5 : 3); // 3-8% max
        $predictedChangePercent = max(-$maxMove, min($maxMove, $predictedChangePercent));
        
        // Calculate predicted price
        $predictedPrice = $currentPrice * (1 + ($predictedChangePercent / 100));
        
        // Calculate predicted_low and predicted_high using ATR and support/resistance
        $support = $technical['support_resistance']['support'];
        $resistance = $technical['support_resistance']['resistance'];
        
        // Predicted low: current price - ATR (but respect support)
        $predicted_low = $currentPrice - ($atr * 1.5);
        if ($support && $predicted_low < $support) {
            $predicted_low = $support * 0.98; // Just below support
        }
        
        // Predicted high: current price + ATR (but respect resistance)
        $predicted_high = $currentPrice + ($atr * 1.5);
        if ($resistance && $predicted_high > $resistance) {
            $predicted_high = $resistance * 1.02; // Just above resistance
        }
        
        // Determine direction
        $direction = $predictedChangePercent > 0.8 ? 'up' : 
                    ($predictedChangePercent < -0.8 ? 'down' : 'neutral');
        
        // Calculate confidence (30-95%) adjusted by Fear & Greed
        $baseConfidence = $this->calculateAdvancedConfidence(
            $technical,
            $sentiment,
            $volume,
            $market,
            $combinedSignal
        );
        
        $confidence = $this->fearGreedService->adjustConfidence($baseConfidence, $fearGreedValue);
        
        // Append urgent news to reasoning
        if (!empty($sentiment['urgent_bearish']) && !empty($sentiment['urgent_keywords'])) {
            $urgentList = implode(', ', array_slice($sentiment['urgent_keywords'], 0, 3));
            $direction = 'down';
            $confidence = min(95, max(50, $confidence + 10));
            $predictedChangePercent = abs($predictedChangePercent) * -1; // ensure bearish
            $predictedPrice = $currentPrice * (1 + ($predictedChangePercent / 100));
            if (empty($alerts['urgent_warning'])) {
                $alerts['urgent_warning'] = true;
                $alerts['keywords'] = $sentiment['urgent_keywords'];
                $alerts['message'] = 'Urgent bearish event detected today: ' . $urgentList;
            }
        }

        // Generate reasoning
        $reasoning = $this->generateReasoning(
            $technical,
            $sentiment,
            $volume,
            $market,
            $direction,
            $confidence,
            $fearGreed
        );
        
        return [
            'predicted_price' => round($predictedPrice, 2),
            'predicted_low' => round($predicted_low, 2),
            'predicted_high' => round($predicted_high, 2),
            'predicted_change_percent' => round($predictedChangePercent, 2),
            'current_price' => $currentPrice,
            'direction' => $direction,
            'confidence' => $confidence,
            'reasoning' => $reasoning,
            'alerts' => !empty($alerts) ? $alerts : null,
            'fear_greed_index' => [
                'value' => $fearGreedValue,
                'classification' => $fearGreed['classification'],
                'impact' => $marketImpact['risk_level'],
            ],
            'signals' => [
                'technical' => round($technicalSignal, 3),
                'sentiment' => round($sentimentSignal, 3),
                'volume' => round($volumeSignal, 3),
                'market' => round($marketSignal, 3),
                'fear_greed' => round($fearGreedSignal, 3),
                'combined' => round($combinedSignal, 3),
            ],
        ];
    }
    
    /**
     * Calculate overall technical signal
     */
    protected function calculateTechnicalSignal(array $technical): float
    {
        $signals = [];
        
        // RSI signal
        if ($technical['rsi']['signal'] === 'oversold_buy') {
            $signals[] = 0.5 * $technical['rsi']['strength'];
        } elseif ($technical['rsi']['signal'] === 'overbought_sell') {
            $signals[] = -0.5 * $technical['rsi']['strength'];
        }
        
        // MACD signal
        if (str_contains($technical['macd']['signal'], 'buy')) {
            $signals[] = 0.6 * $technical['macd']['strength'];
        } elseif (str_contains($technical['macd']['signal'], 'sell')) {
            $signals[] = -0.6 * $technical['macd']['strength'];
        }
        
        // MA signal
        if ($technical['moving_averages']['signal'] === 'bullish') {
            $signals[] = 0.7 * $technical['moving_averages']['strength'];
        } elseif ($technical['moving_averages']['signal'] === 'bearish') {
            $signals[] = -0.7 * $technical['moving_averages']['strength'];
        }
        
        // Bollinger signal
        if ($technical['bollinger_bands']['signal'] === 'oversold') {
            $signals[] = 0.4;
        } elseif ($technical['bollinger_bands']['signal'] === 'overbought') {
            $signals[] = -0.4;
        }
        
        // Momentum signal
        if (str_contains($technical['momentum']['signal'], 'bullish')) {
            $signals[] = 0.5 * $technical['momentum']['strength'];
        } elseif (str_contains($technical['momentum']['signal'], 'bearish')) {
            $signals[] = -0.5 * $technical['momentum']['strength'];
        }
        
        return count($signals) > 0 ? array_sum($signals) / count($signals) : 0;
    }
    
    /**
     * Calculate volume signal
     */
    protected function calculateVolumeSignal(array $volume, float $priceChange): float
    {
        // High volume with price increase = bullish
        // High volume with price decrease = bearish
        // Low volume = neutral
        
        if (!isset($volume['ratio']) || !isset($volume['strength'])) {
            return 0;
        }
        
        if ($volume['ratio'] > 1.5) {
            return ($priceChange > 0 ? 0.7 : -0.7) * $volume['strength'];
        } elseif ($volume['ratio'] > 1.2) {
            return ($priceChange > 0 ? 0.4 : -0.4) * $volume['strength'];
        }
        
        return 0;
    }
    
    /**
     * Calculate advanced confidence score
     */
    protected function calculateAdvancedConfidence(
        array $technical,
        array $sentiment,
        array $volume,
        array $market,
        float $combinedSignal
    ): int {
        $confidence = 50; // Base confidence
        
        // Strong combined signal increases confidence
        $signalStrength = abs($combinedSignal);
        $confidence += $signalStrength * 20; // Up to +20
        
        // Agreement between indicators
        $agreementCount = 0;
        $totalIndicators = 0;
        
        $signalDirection = $combinedSignal > 0 ? 1 : -1;
        
        // Check technical agreement
        foreach (['rsi', 'macd', 'moving_averages', 'momentum'] as $indicator) {
            if (isset($technical[$indicator]['strength']) && $technical[$indicator]['strength'] > 0.3) {
                $totalIndicators++;
                $indicatorSignal = str_contains($technical[$indicator]['signal'], 'bull') || 
                                  str_contains($technical[$indicator]['signal'], 'buy') ? 1 : -1;
                if ($indicatorSignal === $signalDirection) {
                    $agreementCount++;
                }
            }
        }
        
        // Check sentiment agreement
        if (abs($sentiment['score']) > 2) {
            $totalIndicators++;
            if (($sentiment['score'] > 0 && $signalDirection > 0) || 
                ($sentiment['score'] < 0 && $signalDirection < 0)) {
                $agreementCount++;
            }
        }
        
        // Agreement bonus
        if ($totalIndicators > 0) {
            $agreementRatio = $agreementCount / $totalIndicators;
            $confidence += $agreementRatio * 25; // Up to +25
        }
        
        // High volume confirmation
        if (isset($volume['ratio']) && $volume['ratio'] > 1.5) {
            $confidence += 10;
        }
        
        // Strong sentiment bonus
        if (abs($sentiment['score']) > 5) {
            $confidence += 5;
        }
        
        // News recency bonus
        if ($sentiment['count'] > 5) {
            $confidence += 5;
        }
        
        // Cap confidence at 30-95%
        return min(95, max(30, (int) $confidence));
    }
    
    /**
     * Generate human-readable reasoning
     */
    protected function generateReasoning(
        array $technical,
        array $sentiment,
        array $volume,
        array $market,
        string $direction,
        int $confidence,
        array $fearGreed = null
    ): string {
        $reasons = [];
        
        // Technical reasons
        if ($technical['rsi']['value'] < 35) {
            $reasons[] = "RSI indicates oversold conditions ({$technical['rsi']['value']})";
        } elseif ($technical['rsi']['value'] > 65) {
            $reasons[] = "RSI shows overbought levels ({$technical['rsi']['value']})";
        }
        
        if ($technical['moving_averages']['signal'] === 'bullish') {
            $reasons[] = "Bullish MA crossover detected";
        } elseif ($technical['moving_averages']['signal'] === 'bearish') {
            $reasons[] = "Bearish MA crossover detected";
        }
        
        // Sentiment reasons
        if (abs($sentiment['score']) > 3) {
            $sentimentLabel = $sentiment['score'] > 0 ? 'positive' : 'negative';
            $reasons[] = "Strong {$sentimentLabel} sentiment from {$sentiment['count']} recent news articles";
        }
        
        // Volume reasons
        if ($volume['ratio'] > 1.5) {
            $reasons[] = "Significantly elevated trading volume ({$volume['ratio']}x average)";
        }
        
        // Market reasons
        if (abs($market['change_percent']) > 2) {
            $direction = $market['change_percent'] > 0 ? 'upward' : 'downward';
            $reasons[] = "Strong {$direction} momentum ({$market['change_percent']}%)";
        }
        
        // Support/Resistance
        if ($technical['support_resistance']['position'] === 'near_support') {
            $reasons[] = "Price near support level ($" . $technical['support_resistance']['support'] . ")";
        } elseif ($technical['support_resistance']['position'] === 'near_resistance') {
            $reasons[] = "Price approaching resistance ($" . $technical['support_resistance']['resistance'] . ")";
        }
        
        // Fear & Greed Index
        if ($fearGreed && isset($fearGreed['value'])) {
            $fgValue = $fearGreed['value'];
            if ($fgValue <= 24) {
                $reasons[] = "Market Fear & Greed Index shows EXTREME FEAR ({$fgValue}/100) - potential buying opportunity";
            } elseif ($fgValue <= 44) {
                $reasons[] = "Market sentiment shows FEAR ({$fgValue}/100) - cautious investors";
            } elseif ($fgValue >= 76) {
                $reasons[] = "Market Fear & Greed Index shows EXTREME GREED ({$fgValue}/100) - high risk of correction";
            } elseif ($fgValue >= 56) {
                $reasons[] = "Market sentiment shows GREED ({$fgValue}/100) - overvaluation risk";
            }
        }
        
        if (empty($reasons)) {
            $reasons[] = "Mixed signals suggest {$direction} movement";
        }
        
        return implode('. ', $reasons) . '.';
    }
    
    /**
     * Store prediction with full details
     */
    protected function storePrediction(Stock $stock, array $prediction, array $indicators): Prediction
    {
        // Deactivate old predictions
        Prediction::where('stock_id', $stock->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);
        
        // Create new prediction
        return Prediction::create([
            'stock_id' => $stock->id,
            'predicted_price' => $prediction['predicted_price'],
            'predicted_low' => $prediction['predicted_low'] ?? null,
            'predicted_high' => $prediction['predicted_high'] ?? null,
            'predicted_change_percent' => $prediction['predicted_change_percent'],
            'current_price' => $prediction['current_price'],
            'direction' => $prediction['direction'],
            'confidence_score' => $prediction['confidence'],
            'sentiment_score' => $indicators['sentiment']['score'],
            'price_trend' => $indicators['technical']['momentum']['value'] ?? 0,
            'news_count' => $indicators['sentiment']['count'],
            'reasoning' => $prediction['reasoning'],
            'indicators' => [
                'technical' => $indicators['technical'],
                'sentiment' => $indicators['sentiment'],
                'volume' => $indicators['volume'],
                'market' => $indicators['market'],
                'alerts' => $indicators['alerts'] ?? null,
                'signals' => $prediction['signals'],
                'fear_greed' => $prediction['fear_greed_index'] ?? null,
            ],
            'model_version' => 'v2.2_atr_feargreed_alerts',
            'prediction_date' => now(),
            'target_date' => now()->addDay(),
            'timeframe' => '1day',
            'is_active' => true,
        ]);
    }
    
    /**
     * Get default indicators when insufficient data
     */
    protected function getDefaultIndicators(): array
    {
        return [
            'rsi' => ['value' => 50, 'signal' => 'neutral', 'strength' => 0],
            'macd' => ['value' => 0, 'signal' => 'neutral', 'histogram' => 0],
            'moving_averages' => ['signal' => 'neutral', 'strength' => 0],
            'bollinger_bands' => ['position' => 'middle', 'signal' => 'neutral'],
            'momentum' => ['value' => 0, 'signal' => 'neutral', 'strength' => 0],
            'volatility' => ['value' => 0.2, 'level' => 'medium'],
            'support_resistance' => ['position' => 'unknown', 'support' => null, 'resistance' => null],
            'atr' => ['value' => 0, 'percentage' => 2.0], // Default ATR for stocks with insufficient data
        ];
    }
    /**
     * Detect urgent bearish keywords in last 24h for a stock
     */
    protected function detectUrgentBearishKeywords(Stock $stock): array
    {
        $keywords = ['tariff','tariffs','tarif','ban','banned','sanction','sanctions','embargo'];
        $found = [];
        
        // Prefer database news for speed
        $recent = NewsArticle::where('stock_id', $stock->id)
            ->where('published_at', '>=', now()->subHours(24))
            ->orderBy('published_at', 'desc')
            ->limit(50)
            ->get(['title','description']);
        
        // If DB empty, fetch from APIs (do not store, just scan)
        if ($recent->isEmpty()) {
            try {
                $apiArticles = $this->newsService->getStockNews($stock->symbol, 100);
                foreach ($apiArticles as $a) {
                    $text = strtolower(($a['title'] ?? '') . ' ' . ($a['description'] ?? ''));
                    foreach ($keywords as $kw) {
                        if ($kw && str_contains($text, $kw)) {
                            $found[$kw] = true;
                        }
                    }
                }
                return ['flag' => !empty($found), 'keywords' => array_keys($found)];
            } catch (\Exception $e) {
                Log::warning('Urgent keyword scan failed: ' . $e->getMessage());
            }
        }
        
        foreach ($recent as $n) {
            $text = strtolower(($n->title ?? '') . ' ' . ($n->description ?? ''));
            foreach ($keywords as $kw) {
                if ($kw && str_contains($text, $kw)) {
                    $found[$kw] = true;
                }
            }
        }
        
        return ['flag' => !empty($found), 'keywords' => array_keys($found)];
    }
}
