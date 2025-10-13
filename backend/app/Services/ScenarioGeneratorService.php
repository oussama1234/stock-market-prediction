<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\StockPrice;
use App\Models\MarketScenario;
use App\Models\NewsArticle;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

/**
 * Multi-Scenario Market Prediction Service
 * Generates 3-5 predictive scenarios using 90 days of historical data
 * Optimized with caching and efficient calculations
 */
class ScenarioGeneratorService
{
    protected FearGreedIndexService $fearGreedService;
    protected NewsService $newsService;
    protected StockService $stockService;
    protected AIPatternLearningService $aiLearningService;
    protected $finnhubClient;
    
    // Technical indicator periods
    protected const RSI_PERIOD = 14;
    protected const MACD_FAST = 12;
    protected const MACD_SLOW = 26;
    protected const MACD_SIGNAL = 9;
    protected const EMA_PERIODS = [20, 50, 200];
    protected const BOLLINGER_PERIOD = 20;
    protected const BOLLINGER_STD = 2;
    protected const ATR_PERIOD = 14;
    protected const VWAP_PERIOD = 20;
    protected const OBV_PERIOD = 10;
    protected const HISTORICAL_DAYS = 90;

    public function __construct(
        FearGreedIndexService $fearGreedService,
        NewsService $newsService,
        StockService $stockService,
        AIPatternLearningService $aiLearningService
    ) {
        $this->fearGreedService = $fearGreedService;
        $this->newsService = $newsService;
        $this->stockService = $stockService;
        $this->aiLearningService = $aiLearningService;
        $this->finnhubClient = app('App\Services\ApiClients\FinnhubClient');
    }

    /**
     * Generate multiple predictive scenarios for a stock
     * Cached differently for live vs future timeframes
     * @param bool $force Force regeneration even if winner already determined
     */
    public function generateScenarios(Stock $stock, string $timeframe = 'today', bool $force = false): Collection
    {
        // For "today" timeframe, check if market is closed and winner already determined
        // UNLESS force=true (manual regeneration via UI)
        if ($timeframe === 'today' && !$force) {
            // Check if there are active scenarios with winner determined
            $existingScenarios = MarketScenario::active()
                ->forStock($stock->id)
                ->forTimeframe('today')
                ->whereNotNull('actual_close_price')
                ->get();
            
            if ($existingScenarios->isNotEmpty()) {
                Log::info("Not regenerating scenarios for {$stock->symbol} - market closed and winner already determined");
                return $existingScenarios;
            }
            
            // Cache for only 15 seconds during market hours - traders need live data!
            $cacheKey = "scenario_generation:{$stock->id}:{$timeframe}:" . now()->format('Y-m-d-H-i');
            $cacheDuration = now()->addSeconds(15);
        } else {
            $cacheKey = "scenario_generation:{$stock->id}:{$timeframe}:" . now()->format('Y-m-d-H');
            $cacheDuration = now()->addMinutes(30);
        }
        
        return Cache::remember($cacheKey, $cacheDuration, function () use ($stock, $timeframe) {
            return $this->generateScenariosUncached($stock, $timeframe);
        });
    }
    
    /**
     * Generate scenarios with a specific quote (for tomorrow predictions)
     */
    public function generateScenariosWithQuote(Stock $stock, string $timeframe, array $customQuote): Collection
    {
        return $this->generateScenariosUncached($stock, $timeframe, $customQuote);
    }

    /**
     * Generate scenarios without caching (internal use)
     */
    protected function generateScenariosUncached(Stock $stock, string $timeframe, ?array $customQuote = null): Collection
    {
        Log::info("Generating market scenarios for {$stock->symbol} ({$timeframe})");

        // Step 0: Get price data - use custom quote if provided (for tomorrow), otherwise fetch live
        if ($customQuote) {
            $liveQuote = $customQuote;
            $liveCurrentPrice = $customQuote['current_price'];
            Log::info("Using custom quote for {$stock->symbol} ({$timeframe}): " . json_encode($customQuote));
        } else {
            // Get LIVE current price from Finnhub (real-time accurate data)
            $liveQuote = $this->finnhubClient->getQuote($stock->symbol);
            Log::info("Finnhub quote for {$stock->symbol}: " . json_encode($liveQuote));
            
            $liveCurrentPrice = null;
            if ($liveQuote && isset($liveQuote['current_price'])) {
                $liveCurrentPrice = $liveQuote['current_price'];
                Log::info("✅ LIVE PRICE from Finnhub for {$stock->symbol}: \${$liveCurrentPrice}");
            } else {
                Log::warning("⚠️ Finnhub failed for {$stock->symbol}, using database price");
            }
        }

        // Step 1: Get historical data (90 days)
        $historicalData = $this->getHistoricalData($stock);
        
        if ($historicalData->count() < 20) {
            Log::warning("Insufficient historical data for {$stock->symbol}");
            return collect();
        }

        // Step 2: Calculate all technical indicators
        $indicators = $this->calculateAllIndicators($historicalData);
        
        // Step 2.1: OVERRIDE with live current price if available
        if ($liveCurrentPrice) {
            $indicators['current_price'] = $liveCurrentPrice;
            $indicators['live_price_used'] = true;
            Log::info("Overriding calculated price with LIVE price: \${$liveCurrentPrice}");
        }
        
        // Step 2.2: Add opening price
        // For "End of Day" after market close, use current price as "open" for predictions
        // For "today" during market hours, use actual morning open
        // For "tomorrow", use the custom quote's open
        if ($timeframe === 'today' && $this->calculateIntradayTimeFactor() <= 0.15) {
            // Market is closed - use current price as "open" for End of Day scenarios
            $indicators['open_price'] = $indicators['current_price'];
            Log::info("Market closed - using current price as open for {$stock->symbol}: \${$indicators['current_price']}");
        } elseif ($liveQuote && isset($liveQuote['open'])) {
            $indicators['open_price'] = $liveQuote['open'];
            Log::info("Today's open price for {$stock->symbol}: \${$liveQuote['open']}");
        } else {
            // Fallback to historical data
            $indicators['open_price'] = $historicalData->last()->open ?? $indicators['current_price'];
        }
        
        // Step 2.5: Calculate intraday time remaining factor
        if ($timeframe === 'today') {
            $indicators['intraday_factor'] = $this->calculateIntradayTimeFactor();
            Log::info("Intraday factor for {$stock->symbol}: {$indicators['intraday_factor']}");
        } else {
            $indicators['intraday_factor'] = 1.0; // Full day for tomorrow
        }
        
        // Step 3: Get sentiment and news data
        $sentimentData = $this->getSentimentData($stock);
        
        // Step 4: Get Fear & Greed Index
        $fearGreed = $this->fearGreedService->getFearGreedIndex();
        
        // Step 4.5: Generate AI-powered prediction
        $aiPrediction = $this->aiLearningService->generateAIPrediction($stock, $indicators['current_price'], $indicators);
        Log::info("AI Prediction for {$stock->symbol}", $aiPrediction);
        
        // Step 5: Generate scenarios based on different conditions
        $scenarios = $this->buildScenarios($stock, $indicators, $sentimentData, $fearGreed, $timeframe, $aiPrediction);
        
        // Step 6: Store scenarios in database
        $this->storeScenarios($stock, $scenarios, $timeframe);
        
        return collect($scenarios);
    }

    /**
     * Calculate intraday time factor (how much of trading day remains)
     * Returns 0.0 to 1.0 representing percentage of day remaining
     */
    protected function calculateIntradayTimeFactor(): float
    {
        $now = now('America/New_York');
        $hours = $now->hour;
        $minutes = $now->minute;
        $currentMinutes = $hours * 60 + $minutes;
        
        // Market hours: 9:30 AM - 4:00 PM ET (390 minutes total)
        $marketOpen = 9 * 60 + 30;  // 570 minutes
        $marketClose = 16 * 60;      // 960 minutes
        $totalMarketMinutes = $marketClose - $marketOpen; // 390 minutes
        
        // Before market open - full day prediction
        if ($currentMinutes < $marketOpen) {
            return 1.0;
        }
        
        // After market close - minimal prediction for after hours
        if ($currentMinutes >= $marketClose) {
            // Still return a small factor for after-hours/pre-market trading
            return 0.15; // 15% factor for extended hours
        }
        
        // During market hours - calculate remaining time
        $minutesElapsed = $currentMinutes - $marketOpen;
        $minutesRemaining = $totalMarketMinutes - $minutesElapsed;
        $factor = $minutesRemaining / $totalMarketMinutes;
        
        // Ensure minimum of 10% even late in day
        return round(max(0.1, $factor), 3);
    }

    /**
     * Get historical OHLCV data (optimized query)
     * For today's predictions, include recent intraday data for accuracy
     */
    protected function getHistoricalData(Stock $stock): Collection
    {
        // Get 90 days of daily data
        $dailyData = StockPrice::where('stock_id', $stock->id)
            ->where('interval', '1day')
            ->where('price_date', '>=', now()->subDays(self::HISTORICAL_DAYS))
            ->orderBy('price_date', 'asc')
            ->select(['price_date', 'open', 'high', 'low', 'close', 'volume'])
            ->get();
        
        // Get today's intraday data (last 15 minutes intervals) to capture live price action
        $intradayData = StockPrice::where('stock_id', $stock->id)
            ->where('interval', '15min')
            ->where('price_date', '>=', now()->startOfDay())
            ->orderBy('price_date', 'asc')
            ->select(['price_date', 'open', 'high', 'low', 'close', 'volume'])
            ->get();
        
        // If we have intraday data, replace today's daily candle with aggregated intraday for accuracy
        if ($intradayData->isNotEmpty()) {
            // Remove today's daily candle if it exists
            $dailyData = $dailyData->filter(function ($item) {
                return $item->price_date->format('Y-m-d') !== now()->format('Y-m-d');
            });
            
            // Aggregate intraday data into a single today candle
            $todayCandle = [
                'price_date' => now(),
                'open' => $intradayData->first()->open,
                'high' => $intradayData->max('high'),
                'low' => $intradayData->min('low'),
                'close' => $intradayData->last()->close, // Most recent close
                'volume' => $intradayData->sum('volume'),
            ];
            
            // Add today's aggregated candle
            $dailyData->push((object)$todayCandle);
        }
        
        return $dailyData;
    }

    /**
     * Calculate all technical indicators in one pass (performance optimization)
     */
    protected function calculateAllIndicators(Collection $historicalData): array
    {
        $closes = $historicalData->pluck('close')->toArray();
        $highs = $historicalData->pluck('high')->toArray();
        $lows = $historicalData->pluck('low')->toArray();
        $volumes = $historicalData->pluck('volume')->toArray();
        $opens = $historicalData->pluck('open')->toArray();
        
        $currentPrice = end($closes);
        
        return [
            'rsi' => $this->calculateRSI($closes),
            'macd' => $this->calculateMACD($closes),
            'ema' => $this->calculateEMAs($closes, $currentPrice),
            'bollinger' => $this->calculateBollingerBands($closes, $currentPrice),
            'atr' => $this->calculateATR($highs, $lows, $closes),
            'vwap' => $this->calculateVWAP($closes, $highs, $lows, $volumes, $currentPrice),
            'obv' => $this->calculateOBV($closes, $volumes),
            'vfi' => $this->calculateVFI($closes, $highs, $lows, $volumes),
            'momentum' => $this->calculateMomentum($closes),
            'volatility' => $this->calculateVolatility($closes),
            'volume_profile' => $this->analyzeVolumeProfile($volumes),
            'support_resistance' => $this->findSupportResistance($highs, $lows, $currentPrice),
            'price_consolidation' => $this->detectPriceConsolidation($closes, $volumes),
            'obv_divergence' => $this->detectOBVDivergence($closes, $volumes),
            'current_price' => $currentPrice,
        ];
    }

    /**
     * RSI (Relative Strength Index) - Optimized calculation
     */
    protected function calculateRSI(array $closes): array
    {
        if (count($closes) < self::RSI_PERIOD + 1) {
            return ['value' => 50, 'signal' => 'neutral', 'strength' => 0];
        }

        $gains = [];
        $losses = [];

        for ($i = 1; $i < count($closes); $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            $gains[] = max(0, $change);
            $losses[] = max(0, -$change);
        }

        $avgGain = array_sum(array_slice($gains, -self::RSI_PERIOD)) / self::RSI_PERIOD;
        $avgLoss = array_sum(array_slice($losses, -self::RSI_PERIOD)) / self::RSI_PERIOD;

        $rsi = $avgLoss == 0 ? 100 : 100 - (100 / (1 + ($avgGain / $avgLoss)));

        $signal = 'neutral';
        $strength = 0;

        if ($rsi < 30) {
            $signal = 'oversold';
            $strength = (30 - $rsi) / 30;
        } elseif ($rsi > 70) {
            $signal = 'overbought';
            $strength = ($rsi - 70) / 30;
        }

        return [
            'value' => round($rsi, 2),
            'signal' => $signal,
            'strength' => round($strength, 2),
        ];
    }

    /**
     * MACD (Moving Average Convergence Divergence)
     */
    protected function calculateMACD(array $closes): array
    {
        if (count($closes) < self::MACD_SLOW) {
            return ['value' => 0, 'signal' => 'neutral', 'histogram' => 0, 'strength' => 0];
        }

        $ema12 = $this->calculateEMA($closes, self::MACD_FAST);
        $ema26 = $this->calculateEMA($closes, self::MACD_SLOW);
        $macdLine = $ema12 - $ema26;

        // For simplicity, using macdLine as signal (full history calculation would be more accurate)
        $histogram = $macdLine;

        $signal = 'neutral';
        if ($macdLine > 0) {
            $signal = $histogram > 1 ? 'strong_bullish' : 'bullish';
        } else {
            $signal = $histogram < -1 ? 'strong_bearish' : 'bearish';
        }

        return [
            'value' => round($macdLine, 2),
            'signal' => $signal,
            'histogram' => round($histogram, 2),
            'strength' => round(min(abs($histogram) / 5, 1), 2),
            'crossover' => $histogram > 0 ? 'bullish' : 'bearish',
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
     * Calculate multiple EMAs (20, 50, 200) for crossover detection
     */
    protected function calculateEMAs(array $closes, float $currentPrice): array
    {
        $emas = [];
        foreach (self::EMA_PERIODS as $period) {
            if (count($closes) >= $period) {
                $emas["ema{$period}"] = round($this->calculateEMA($closes, $period), 2);
            } else {
                $emas["ema{$period}"] = null;
            }
        }

        // Detect golden/death cross
        $crossover = 'none';
        if (isset($emas['ema50']) && isset($emas['ema200'])) {
            if ($emas['ema50'] > $emas['ema200'] && $currentPrice > $emas['ema50']) {
                $crossover = 'golden_cross'; // Bullish
            } elseif ($emas['ema50'] < $emas['ema200'] && $currentPrice < $emas['ema50']) {
                $crossover = 'death_cross'; // Bearish
            }
        }

        return array_merge($emas, [
            'current' => $currentPrice,
            'crossover' => $crossover,
        ]);
    }

    /**
     * Calculate Bollinger Bands
     */
    protected function calculateBollingerBands(array $closes, float $currentPrice): array
    {
        if (count($closes) < self::BOLLINGER_PERIOD) {
            return ['signal' => 'neutral', 'width' => 0];
        }

        $sma = $this->calculateSMA($closes, self::BOLLINGER_PERIOD);
        $stdDev = $this->calculateStdDev($closes, self::BOLLINGER_PERIOD);

        $upperBand = $sma + (self::BOLLINGER_STD * $stdDev);
        $lowerBand = $sma - (self::BOLLINGER_STD * $stdDev);
        $bandWidth = ($upperBand - $lowerBand) / $sma;

        $signal = 'neutral';
        $squeeze = $bandWidth < 0.1; // Bollinger Squeeze detection

        if ($currentPrice >= $upperBand) {
            $signal = 'overbought';
        } elseif ($currentPrice <= $lowerBand) {
            $signal = 'oversold';
        }

        return [
            'upper' => round($upperBand, 2),
            'middle' => round($sma, 2),
            'lower' => round($lowerBand, 2),
            'current' => $currentPrice,
            'signal' => $signal,
            'width' => round($bandWidth, 4),
            'squeeze' => $squeeze, // Indicates potential breakout
        ];
    }

    /**
     * Simple Moving Average
     */
    protected function calculateSMA(array $values, int $period): float
    {
        if (count($values) < $period) {
            return array_sum($values) / count($values);
        }
        $slice = array_slice($values, -$period);
        return array_sum($slice) / $period;
    }

    /**
     * Standard Deviation
     */
    protected function calculateStdDev(array $values, int $period): float
    {
        $slice = array_slice($values, -$period);
        $mean = array_sum($slice) / count($slice);
        $variance = array_sum(array_map(fn($val) => pow($val - $mean, 2), $slice)) / count($slice);
        return sqrt($variance);
    }

    /**
     * ATR (Average True Range) for volatility measurement
     */
    protected function calculateATR(array $highs, array $lows, array $closes): array
    {
        if (count($highs) < self::ATR_PERIOD + 1) {
            return ['value' => 0, 'percentage' => 2.0];
        }

        $trueRanges = [];
        for ($i = 1; $i < count($highs); $i++) {
            $tr = max(
                $highs[$i] - $lows[$i],
                abs($highs[$i] - $closes[$i - 1]),
                abs($lows[$i] - $closes[$i - 1])
            );
            $trueRanges[] = $tr;
        }

        $atr = array_sum(array_slice($trueRanges, -self::ATR_PERIOD)) / self::ATR_PERIOD;
        $atrPercentage = ($atr / end($closes)) * 100;

        return [
            'value' => round($atr, 2),
            'percentage' => round($atrPercentage, 2),
        ];
    }

    /**
     * VWAP (Volume Weighted Average Price)
     */
    protected function calculateVWAP(array $closes, array $highs, array $lows, array $volumes, float $currentPrice): array
    {
        $period = min(self::VWAP_PERIOD, count($closes));
        
        $typicalPrices = [];
        for ($i = count($closes) - $period; $i < count($closes); $i++) {
            $typicalPrices[] = ($highs[$i] + $lows[$i] + $closes[$i]) / 3;
        }

        $recentVolumes = array_slice($volumes, -$period);
        
        $numerator = 0;
        $denominator = 0;
        
        for ($i = 0; $i < count($typicalPrices); $i++) {
            $numerator += $typicalPrices[$i] * $recentVolumes[$i];
            $denominator += $recentVolumes[$i];
        }

        $vwap = $denominator > 0 ? $numerator / $denominator : end($closes);

        return [
            'value' => round($vwap, 2),
            'current' => $currentPrice,
            'signal' => $currentPrice > $vwap ? 'above_vwap' : 'below_vwap',
        ];
    }

    /**
     * OBV (On-Balance Volume) for volume trend analysis
     */
    protected function calculateOBV(array $closes, array $volumes): array
    {
        if (count($closes) < 2) {
            return ['value' => 0, 'trend' => 'neutral'];
        }

        $obv = 0;
        $obvValues = [0];

        for ($i = 1; $i < count($closes); $i++) {
            if ($closes[$i] > $closes[$i - 1]) {
                $obv += $volumes[$i];
            } elseif ($closes[$i] < $closes[$i - 1]) {
                $obv -= $volumes[$i];
            }
            $obvValues[] = $obv;
        }

        // Detect OBV trend
        $recentOBV = array_slice($obvValues, -10);
        $trend = end($recentOBV) > $recentOBV[0] ? 'rising' : 'falling';

        return [
            'value' => round($obv, 0),
            'trend' => $trend,
            'signal' => $trend === 'rising' ? 'bullish' : 'bearish',
        ];
    }

    /**
     * VFI (Volume Flow Index) - Detects institutional buying/selling
     */
    protected function calculateVFI(array $closes, array $highs, array $lows, array $volumes): array
    {
        if (count($closes) < 30) {
            return ['value' => 0, 'signal' => 'neutral', 'trend' => 'neutral'];
        }
        
        $vfiValues = [0];
        $period = 26;
        $coef = 0.2;
        $vcoef = 2.5;
        
        for ($i = 1; $i < count($closes); $i++) {
            $typical = ($highs[$i] + $lows[$i] + $closes[$i]) / 3;
            $inter = log($typical) - log($closes[$i - 1]);
            $vinter = $this->calculateStdDev(array_slice($closes, max(0, $i - 30), 30), 30);
            
            $cutoff = $coef * $vinter * $closes[$i];
            $vave = array_sum(array_slice($volumes, max(0, $i - $period), $period)) / $period;
            $vmax = $vave * $vcoef;
            $vc = min($volumes[$i], $vmax);
            
            $mf = $typical - $closes[$i - 1];
            $vcp = ($mf > $cutoff) ? $vc : (($mf < -$cutoff) ? -$vc : 0);
            $vfi = $vcp;
            $vfiValues[] = $vfi;
        }
        
        // Calculate trend from recent VFI values
        $recentVFI = array_slice($vfiValues, -10);
        $vfiSum = array_sum($recentVFI);
        $vfiAvg = $vfiSum / count($recentVFI);
        
        $signal = 'neutral';
        $trend = 'neutral';
        
        if ($vfiAvg > 0) {
            $signal = $vfiAvg > 1000000 ? 'strong_accumulation' : 'accumulation';
            $trend = 'rising';
        } elseif ($vfiAvg < 0) {
            $signal = $vfiAvg < -1000000 ? 'strong_distribution' : 'distribution';
            $trend = 'falling';
        }
        
        return [
            'value' => round($vfiAvg, 0),
            'signal' => $signal,
            'trend' => $trend,
        ];
    }
    
    /**
     * Detect price consolidation (sideways movement with increasing volume)
     */
    protected function detectPriceConsolidation(array $closes, array $volumes): array
    {
        if (count($closes) < 10) {
            return ['is_consolidating' => false, 'days' => 0, 'volume_trend' => 'neutral'];
        }
        
        // Check last 10 days
        $recentPrices = array_slice($closes, -10);
        $recentVolumes = array_slice($volumes, -10);
        
        // Calculate price volatility
        $priceRange = (max($recentPrices) - min($recentPrices)) / end($closes);
        $isConsolidating = $priceRange < 0.05; // Less than 5% range
        
        // Check volume trend
        $firstHalfVolume = array_sum(array_slice($recentVolumes, 0, 5)) / 5;
        $secondHalfVolume = array_sum(array_slice($recentVolumes, 5, 5)) / 5;
        $volumeGrowth = $secondHalfVolume / $firstHalfVolume;
        
        $volumeTrend = 'neutral';
        if ($volumeGrowth > 1.1) {
            $volumeTrend = 'increasing';
        } elseif ($volumeGrowth < 0.9) {
            $volumeTrend = 'decreasing';
        }
        
        return [
            'is_consolidating' => $isConsolidating,
            'days' => $isConsolidating ? 10 : 0,
            'volume_trend' => $volumeTrend,
            'price_range_percent' => round($priceRange * 100, 2),
            'volume_growth_ratio' => round($volumeGrowth, 2),
        ];
    }
    
    /**
     * Detect OBV divergence from price
     */
    protected function detectOBVDivergence(array $closes, array $volumes): array
    {
        if (count($closes) < 20) {
            return ['divergence' => 'none', 'strength' => 0];
        }
        
        // Calculate OBV
        $obv = 0;
        $obvValues = [0];
        
        for ($i = 1; $i < count($closes); $i++) {
            if ($closes[$i] > $closes[$i - 1]) {
                $obv += $volumes[$i];
            } elseif ($closes[$i] < $closes[$i - 1]) {
                $obv -= $volumes[$i];
            }
            $obvValues[] = $obv;
        }
        
        // Compare last 10 days
        $recentPrices = array_slice($closes, -10);
        $recentOBV = array_slice($obvValues, -10);
        
        $priceChange = (end($recentPrices) - $recentPrices[0]) / $recentPrices[0];
        $obvChange = (end($recentOBV) - $recentOBV[0]) / max(abs($recentOBV[0]), 1);
        
        $divergence = 'none';
        $strength = 0;
        
        // Bullish divergence: price down, OBV up
        if ($priceChange < -0.02 && $obvChange > 0) {
            $divergence = 'bullish';
            $strength = min(abs($priceChange) + $obvChange, 1);
        }
        // Bearish divergence: price up, OBV down
        elseif ($priceChange > 0.02 && $obvChange < 0) {
            $divergence = 'bearish';
            $strength = min($priceChange + abs($obvChange), 1);
        }
        
        return [
            'divergence' => $divergence,
            'strength' => round($strength, 2),
            'price_change' => round($priceChange * 100, 2),
            'obv_change' => round($obvChange * 100, 2),
        ];
    }

    /**
     * Calculate momentum - Enhanced to capture TODAY'S movement
     */
    protected function calculateMomentum(array $closes): array
    {
        if (count($closes) < 2) {
            return ['value' => 0, 'signal' => 'neutral'];
        }

        $current = end($closes);
        
        // Calculate TODAY's momentum (1-day change) - MOST IMPORTANT for intraday
        $yesterday = $closes[count($closes) - 2];
        $todayMomentum = (($current - $yesterday) / $yesterday) * 100;
        
        // Calculate 10-day momentum for context
        $tenDayMomentum = 0;
        if (count($closes) >= 10) {
            $past = $closes[count($closes) - 10];
            $tenDayMomentum = (($current - $past) / $past) * 100;
        }
        
        // Use TODAY's momentum as primary, with 10-day as secondary
        // Weight: 70% today, 30% 10-day trend
        $momentum = ($todayMomentum * 0.7) + ($tenDayMomentum * 0.3);

        return [
            'value' => round($momentum, 2),
            'today_momentum' => round($todayMomentum, 2),
            'ten_day_momentum' => round($tenDayMomentum, 2),
            'signal' => $momentum > 2 ? 'strong_bullish' : ($momentum > 0 ? 'bullish' : ($momentum < -2 ? 'strong_bearish' : 'bearish')),
        ];
    }

    /**
     * Calculate volatility
     */
    protected function calculateVolatility(array $closes): array
    {
        if (count($closes) < 20) {
            return ['value' => 0.2, 'level' => 'medium'];
        }

        $returns = [];
        for ($i = 1; $i < count($closes); $i++) {
            $returns[] = ($closes[$i] - $closes[$i - 1]) / $closes[$i - 1];
        }

        $volatility = $this->calculateStdDev($returns, count($returns)) * sqrt(252);

        $level = $volatility > 0.4 ? 'very_high' : ($volatility > 0.25 ? 'high' : ($volatility > 0.15 ? 'medium' : 'low'));

        return [
            'value' => round($volatility, 4),
            'level' => $level,
        ];
    }

    /**
     * Analyze volume profile
     */
    protected function analyzeVolumeProfile(array $volumes): array
    {
        if (count($volumes) < 5) {
            return ['trend' => 'unknown', 'ratio' => 1];
        }

        $avgVolume = array_sum($volumes) / count($volumes);
        $currentVolume = end($volumes);
        $ratio = $avgVolume > 0 ? $currentVolume / $avgVolume : 1;

        return [
            'current' => $currentVolume,
            'average' => round($avgVolume, 0),
            'ratio' => round($ratio, 2),
            'trend' => $ratio > 1.5 ? 'high' : ($ratio < 0.5 ? 'low' : 'normal'),
        ];
    }

    /**
     * Find support and resistance levels
     */
    protected function findSupportResistance(array $highs, array $lows, float $currentPrice): array
    {
        $resistanceLevels = [];
        $supportLevels = [];

        for ($i = 2; $i < count($highs) - 2; $i++) {
            if ($highs[$i] > $highs[$i-1] && $highs[$i] > $highs[$i-2] &&
                $highs[$i] > $highs[$i+1] && $highs[$i] > $highs[$i+2]) {
                $resistanceLevels[] = $highs[$i];
            }

            if ($lows[$i] < $lows[$i-1] && $lows[$i] < $lows[$i-2] &&
                $lows[$i] < $lows[$i+1] && $lows[$i] < $lows[$i+2]) {
                $supportLevels[] = $lows[$i];
            }
        }

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

        return [
            'support' => $support ? round($support, 2) : null,
            'resistance' => $resistance ? round($resistance, 2) : null,
        ];
    }

    /**
     * Get sentiment data from news
     */
    protected function getSentimentData(Stock $stock): array
    {
        $recentNews = NewsArticle::where('stock_id', $stock->id)
            ->where('published_at', '>=', now()->subDays(7))
            ->orderBy('published_at', 'desc')
            ->limit(20)
            ->get();

        if ($recentNews->isEmpty()) {
            return [
                'score' => 0,
                'count' => 0,
                'trend' => 'neutral',
                'headlines' => [],
            ];
        }

        $scores = $recentNews->pluck('sentiment_score')->filter();
        $avgSentiment = $scores->isNotEmpty() ? $scores->avg() : 0;

        $headlines = $recentNews->take(5)->map(fn($news) => [
            'title' => $news->title,
            'sentiment' => $news->sentiment_score,
            'published_at' => $news->published_at->diffForHumans(),
        ])->toArray();

        return [
            'score' => round($avgSentiment, 2),
            'count' => $recentNews->count(),
            'trend' => $avgSentiment > 2 ? 'positive' : ($avgSentiment < -2 ? 'negative' : 'neutral'),
            'headlines' => $headlines,
        ];
    }

    /**
     * Build multiple scenarios based on indicators
     * Always generate at least 3-5 DIFFERENT scenarios for comprehensive analysis
     */
    protected function buildScenarios(Stock $stock, array $indicators, array $sentiment, array $fearGreed, string $timeframe, array $aiPrediction = null): array
    {
        $scenarios = [];
        $scenarioTypes = []; // Track added scenario types to prevent duplicates
        $currentPrice = $indicators['current_price'];
        
        // STEP 0: Add AI High-Confidence Scenario FIRST (if confidence >= 50%)
        // Note: Lowered to 50% temporarily to show AI predictions more often during testing
        if ($aiPrediction && $aiPrediction['confidence'] >= 50) {
            $scenarios[] = $this->createAIHighConfidenceScenario($currentPrice, $indicators, $aiPrediction);
            $scenarioTypes[] = 'ai_high_confidence';
            Log::info("Added AI High-Confidence scenario for {$stock->symbol} with {$aiPrediction['confidence']}% confidence");
        }
        
        // Calculate overall market bias
        $bullishScore = $this->calculateBullishScore($indicators, $sentiment);
        $bearishScore = $this->calculateBearishScore($indicators, $sentiment);
        
        Log::info("Market bias for {$stock->symbol} - Bullish: {$bullishScore}, Bearish: {$bearishScore}, Momentum: {$indicators['momentum']['value']}");

        // CRITICAL: If stock dropped significantly (4%+), ALWAYS show bearish scenario
        $significantDrop = $indicators['momentum']['value'] < -3.5;
        
        // ALWAYS generate primary directional scenario based on dominant bias
        if ($bullishScore > $bearishScore && !$significantDrop) {
            // Primary: Bullish
            $scenarios[] = $this->createBullishScenario($currentPrice, $indicators, $sentiment, $fearGreed);
            $scenarioTypes[] = 'bullish';
            
            // Secondary: Bearish (contrarian view)
            if ($bearishScore > 2) {
                $scenarios[] = $this->createBearishScenario($currentPrice, $indicators, $sentiment, $fearGreed);
                $scenarioTypes[] = 'bearish';
            }
        } else if ($bearishScore > $bullishScore || $significantDrop) {
            // Primary: Bearish (or significant drop detected)
            $scenarios[] = $this->createBearishScenario($currentPrice, $indicators, $sentiment, $fearGreed);
            $scenarioTypes[] = 'bearish';
            
            // Secondary: Bullish (contrarian view) - ALWAYS add for balance
            if (!$significantDrop || $bullishScore > 1) {
                $scenarios[] = $this->createBullishScenario($currentPrice, $indicators, $sentiment, $fearGreed);
                $scenarioTypes[] = 'bullish';
            }
        } else {
            // Equal scores - add both
            $scenarios[] = $this->createBullishScenario($currentPrice, $indicators, $sentiment, $fearGreed);
            $scenarioTypes[] = 'bullish';
            $scenarios[] = $this->createBearishScenario($currentPrice, $indicators, $sentiment, $fearGreed);
            $scenarioTypes[] = 'bearish';
        }

        // Neutral scenarios removed - users don't want sideways predictions
        // if (!in_array('neutral', $scenarioTypes)) {
        //     $scenarios[] = $this->createNeutralScenario($currentPrice, $indicators, $fearGreed);
        //     $scenarioTypes[] = 'neutral';
        // }

        // Add momentum reversal if extreme conditions
        if ($this->detectMomentumReversal($indicators, $sentiment) && !in_array('momentum_reversal', $scenarioTypes)) {
            $scenarios[] = $this->createMomentumReversalScenario($currentPrice, $indicators, $sentiment);
            $scenarioTypes[] = 'momentum_reversal';
        }
        
        // === VOLUME FLOW & VOLATILITY EXPANSION SCENARIOS ===
        // These scenarios are now replaced by AI predictions
        // Keeping old logic as fallback if AI confidence is low
        
        // Only add these if AI scenario wasn't added (low confidence < 50%)
        if (!in_array('ai_high_confidence', $scenarioTypes)) {
            // Accumulation Phase (Hidden Bullish)
            if ($this->detectAccumulationPhase($indicators) && !in_array('accumulation_phase', $scenarioTypes)) {
                $scenarios[] = $this->createAccumulationPhaseScenario($currentPrice, $indicators);
                $scenarioTypes[] = 'accumulation_phase';
            }
            
            // Distribution Phase (Hidden Bearish)
            if ($this->detectDistributionPhase($indicators) && !in_array('distribution_phase', $scenarioTypes)) {
                $scenarios[] = $this->createDistributionPhaseScenario($currentPrice, $indicators);
                $scenarioTypes[] = 'distribution_phase';
            }
        }
        
        Log::info("Generated " . count($scenarios) . " unique scenarios for {$stock->symbol}: " . implode(', ', $scenarioTypes));

        return $scenarios;
    }

    // Calculate bullish/bearish scores for better scenario selection
    protected function calculateBullishScore(array $indicators, array $sentiment): int
    {
        $score = 0;
        
        // RSI (weight: 2)
        if ($indicators['rsi']['value'] < 30) $score += 3;
        elseif ($indicators['rsi']['value'] < 40) $score += 2;
        elseif ($indicators['rsi']['value'] < 50) $score += 1;
        
        // MACD (weight: 2)
        if ($indicators['macd']['signal'] === 'strong_bullish') $score += 3;
        elseif ($indicators['macd']['signal'] === 'bullish') $score += 2;
        
        // Momentum (weight: 1)
        if ($indicators['momentum']['value'] > 2) $score += 2;
        elseif ($indicators['momentum']['value'] > 0) $score += 1;
        
        // OBV (weight: 1)
        if ($indicators['obv']['signal'] === 'bullish') $score += 1;
        
        // EMA Crossover (weight: 2)
        if ($indicators['ema']['crossover'] === 'golden_cross') $score += 3;
        
        // Sentiment (weight: 1)
        if ($sentiment['trend'] === 'positive') $score += 2;
        
        // Volume (weight: 1)
        if ($indicators['volume_profile']['ratio'] > 1.3) $score += 1;
        
        return $score;
    }
    
    protected function calculateBearishScore(array $indicators, array $sentiment): int
    {
        $score = 0;
        
        // RSI (weight: 2)
        if ($indicators['rsi']['value'] > 70) $score += 3;
        elseif ($indicators['rsi']['value'] > 60) $score += 2;
        elseif ($indicators['rsi']['value'] > 50) $score += 1;
        
        // MACD (weight: 2)
        if ($indicators['macd']['signal'] === 'strong_bearish') $score += 3;
        elseif ($indicators['macd']['signal'] === 'bearish') $score += 2;
        
        // Momentum (weight: INCREASED - this is critical!)
        // Large negative moves should strongly indicate bearish scenario
        if ($indicators['momentum']['value'] < -4) $score += 4; // Big drop (4%+)
        elseif ($indicators['momentum']['value'] < -2) $score += 3; // Medium drop (2-4%)
        elseif ($indicators['momentum']['value'] < -1) $score += 2; // Small drop (1-2%)
        elseif ($indicators['momentum']['value'] < 0) $score += 1; // Slight negative
        
        // OBV (weight: 1)
        if ($indicators['obv']['signal'] === 'bearish') $score += 1;
        
        // EMA Crossover (weight: 2)
        if ($indicators['ema']['crossover'] === 'death_cross') $score += 3;
        
        // Sentiment (weight: 1)
        if ($sentiment['trend'] === 'negative') $score += 2;
        
        // Volume decline (weight: 1)
        if ($indicators['volume_profile']['ratio'] < 0.7) $score += 1;
        
        return $score;
    }

    protected function detectNeutralConditions(array $indicators): bool
    {
        return $indicators['volatility']['level'] === 'low' &&
               abs($indicators['momentum']['value']) < 2 &&
               $indicators['rsi']['signal'] === 'neutral';
    }

    protected function detectMomentumReversal(array $indicators, array $sentiment): bool
    {
        $rsiExtremes = $indicators['rsi']['value'] < 30 || $indicators['rsi']['value'] > 70;
        $macdCrossover = $indicators['macd']['crossover'] !== 'neutral';
        $sentimentShift = abs($sentiment['score']) > 3;
        
        return $rsiExtremes && ($macdCrossover || $sentimentShift);
    }

    protected function detectVolatilityBreakout(array $indicators): bool
    {
        return $indicators['bollinger']['squeeze'] === true ||
               $indicators['volatility']['level'] === 'very_high' ||
               $indicators['atr']['percentage'] > 5;
    }
    
    /**
     * Create AI High-Confidence Scenario
     * This scenario is shown when AI analysis has high confidence (>70%)
     */
    protected function createAIHighConfidenceScenario(float $currentPrice, array $indicators, array $aiPrediction): array
    {
        $openPrice = $indicators['open_price'] ?? $currentPrice;
        $changePercent = $aiPrediction['predicted_change_percent'];
        $confidence = $aiPrediction['confidence'];
        $direction = $aiPrediction['direction'];
        
        // Calculate target price from current price
        $targetPrice = $currentPrice * (1 + $changePercent / 100);
        
        // Calculate TOTAL change from OPEN
        $totalChangeFromOpen = (($targetPrice - $openPrice) / $openPrice) * 100;
        
        // Calculate realistic min/max based on AI confidence
        $confidenceMultiplier = $confidence / 100;
        $minChangeFromOpen = $totalChangeFromOpen * 0.4 * $confidenceMultiplier;
        $maxChangeFromOpen = $totalChangeFromOpen * 1.6 * $confidenceMultiplier;
        
        // Apply intraday time factor
        $intradayFactor = $indicators['intraday_factor'] ?? 1.0;
        $timeAdjustment = sqrt($intradayFactor);
        $minChangeFromOpen *= $timeAdjustment;
        $maxChangeFromOpen *= $timeAdjustment;
        $totalChangeFromOpen *= $timeAdjustment;
        
        // Determine scenario name based on direction
        $scenarioName = 'AI High-Confidence Prediction';
        if ($direction === 'bullish') {
            $scenarioName = '🤖 AI High-Confidence: Bullish';
        } elseif ($direction === 'bearish') {
            $scenarioName = '🤖 AI High-Confidence: Bearish';
        }
        
        // Build trigger indicators from AI analysis
        $triggers = [
            'AI Confidence' => $confidence . '%',
            'News Sentiment' => round($aiPrediction['news_sentiment_score']['score'], 2) . ' (Impact: ' . $aiPrediction['news_sentiment_score']['impact_level'] . ')',
            'Historical Pattern' => round($aiPrediction['historical_score'], 2),
            'Seasonal Bias' => round($aiPrediction['seasonal_score'], 2),
            'Technical Score' => round($aiPrediction['technical_score'], 2),
        ];
        
        // Add key themes if available
        if (!empty($aiPrediction['news_sentiment_score']['key_themes'])) {
            $triggers['Key Themes'] = implode(', ', array_slice($aiPrediction['news_sentiment_score']['key_themes'], 0, 3));
        }
        
        // Determine suggested action
        $suggestedAction = 'hold';
        if ($confidence >= 80 && $direction === 'bullish') {
            $suggestedAction = 'buy';
        } elseif ($confidence >= 80 && $direction === 'bearish') {
            $suggestedAction = 'sell';
        } elseif ($confidence >= 70 && $direction !== 'neutral') {
            $suggestedAction = $direction === 'bullish' ? 'buy' : 'wait';
        }
        
        return [
            'scenario_type' => 'ai_high_confidence',
            'scenario_name' => $scenarioName,
            'description' => $aiPrediction['reasoning'],
            'expected_change_percent' => round($totalChangeFromOpen, 2),
            'expected_change_min' => round($minChangeFromOpen, 2),
            'expected_change_max' => round($maxChangeFromOpen, 2),
            'target_price' => round($targetPrice, 2),
            'current_price' => round($currentPrice, 2),
            'open_price' => round($openPrice, 2),
            'confidence_level' => $confidence,
            'trigger_indicators' => $triggers,
            'related_news' => [],
            'suggested_action' => $suggestedAction,
            'action_reasoning' => "AI analysis with {$confidence}% confidence. " . $aiPrediction['reasoning'],
            'ai_confidence' => $confidence,
            'ai_reasoning' => $aiPrediction['reasoning'],
            'ai_final_score' => round($aiPrediction['final_score'], 3),
        ];
    }

    // Scenario creation methods continue in next part...
    
    protected function createBullishScenario(float $currentPrice, array $indicators, array $sentiment, array $fearGreed): array
    {
        $openPrice = $indicators['open_price'] ?? $currentPrice;
        $changePercent = $this->calculateExpectedChange($indicators, 'bullish');
        $confidence = $this->calculateConfidence($indicators, $sentiment, 'bullish');
        
        $triggers = $this->extractTriggers($indicators, $sentiment, 'bullish');
        
        // BULLISH scenarios must predict UPWARD movement
        // Use absolute value to ensure positive change
        $changePercent = abs($changePercent);
        
        // Calculate target price from CURRENT price (upward movement)
        $targetPrice = $currentPrice * (1 + $changePercent / 100);
        
        // Calculate TOTAL change from OPEN (this is what users want to see)
        $totalChangeFromOpen = (($targetPrice - $openPrice) / $openPrice) * 100;
        
        // If the totalChangeFromOpen is negative, that means target is still below open
        // For bullish, we should predict at least reaching or exceeding open
        if ($totalChangeFromOpen < 0 && $currentPrice < $openPrice) {
            // Adjust target to be above open price by at least 0.5%
            $targetPrice = $openPrice * 1.005;
            $totalChangeFromOpen = 0.5;
        }
        
        // Calculate realistic min/max using Bollinger Bands and resistance
        $upperBB = $indicators['bollinger']['upper'];
        $resistance = $indicators['support_resistance']['resistance'];
        
        // Min: Conservative target
        $minTargetPrice = $currentPrice * (1 + ($changePercent * 0.5) / 100);
        $minChangeFromOpen = (($minTargetPrice - $openPrice) / $openPrice) * 100;
        
        // Max: Use resistance or upper BB as ceiling
        if ($resistance && $resistance > $currentPrice) {
            $maxTargetPrice = min($resistance, $currentPrice * (1 + ($changePercent * 2) / 100));
        } else {
            $maxTargetPrice = min($upperBB, $currentPrice * (1 + ($changePercent * 2) / 100));
        }
        $maxChangeFromOpen = (($maxTargetPrice - $openPrice) / $openPrice) * 100;
        
        // Apply intraday time factor to range
        $intradayFactor = $indicators['intraday_factor'] ?? 1.0;
        $timeAdjustment = sqrt($intradayFactor);
        $minChangeFromOpen *= $timeAdjustment;
        $maxChangeFromOpen *= $timeAdjustment;
        $totalChangeFromOpen *= $timeAdjustment;
        
        return [
            'scenario_type' => 'bullish',
            'scenario_name' => 'Bullish Momentum Scenario',
            'description' => 'Strong buying signals detected. Multiple technical indicators suggest upward price movement.',
            'expected_change_percent' => round($totalChangeFromOpen, 2),
            'expected_change_min' => round($minChangeFromOpen, 2),
            'expected_change_max' => round($maxChangeFromOpen, 2),
            'target_price' => round($targetPrice, 2),
            'current_price' => round($currentPrice, 2),
            'open_price' => round($openPrice, 2),
            'confidence_level' => $confidence,
            'trigger_indicators' => $triggers,
            'related_news' => $sentiment['headlines'],
            'suggested_action' => $confidence > 70 ? 'buy' : 'hold',
            'action_reasoning' => $this->generateActionReasoning('bullish', $triggers, $confidence),
        ];
    }

    protected function createBearishScenario(float $currentPrice, array $indicators, array $sentiment, array $fearGreed): array
    {
        $openPrice = $indicators['open_price'] ?? $currentPrice;
        $changePercent = $this->calculateExpectedChange($indicators, 'bearish');
        $confidence = $this->calculateConfidence($indicators, $sentiment, 'bearish');
        
        $triggers = $this->extractTriggers($indicators, $sentiment, 'bearish');
        
        // BEARISH scenarios must predict DOWNWARD movement - use absolute negative value
        $changePercent = -abs($changePercent);
        
        // Calculate target price from current price
        $targetPrice = $currentPrice * (1 + $changePercent / 100);
        
        // Calculate TOTAL change from OPEN (this is what users want to see)
        $totalChangeFromOpen = (($targetPrice - $openPrice) / $openPrice) * 100;
        
        // Calculate realistic min/max using Bollinger Bands and support
        $lowerBB = $indicators['bollinger']['lower'];
        $support = $indicators['support_resistance']['support'];
        
        // Min (most negative): Use support or lower BB as floor
        if ($support && $support < $currentPrice) {
            $minTargetPrice = max($support, $currentPrice * (1 + ($changePercent * 2) / 100));
        } else {
            $minTargetPrice = max($lowerBB, $currentPrice * (1 + ($changePercent * 2) / 100));
        }
        $minChangeFromOpen = (($minTargetPrice - $openPrice) / $openPrice) * 100;
        
        // Max (least negative): Conservative target
        $maxTargetPrice = $currentPrice * (1 + ($changePercent * 0.5) / 100);
        $maxChangeFromOpen = (($maxTargetPrice - $openPrice) / $openPrice) * 100;
        
        // Apply intraday time factor to range
        $intradayFactor = $indicators['intraday_factor'] ?? 1.0;
        $timeAdjustment = sqrt($intradayFactor);
        $minChangeFromOpen *= $timeAdjustment;
        $maxChangeFromOpen *= $timeAdjustment;
        $totalChangeFromOpen *= $timeAdjustment;
        
        return [
            'scenario_type' => 'bearish',
            'scenario_name' => 'Bearish Pressure Scenario',
            'description' => 'Selling pressure detected. Technical indicators point to potential downward movement.',
            'expected_change_percent' => round($totalChangeFromOpen, 2),
            'expected_change_min' => round($minChangeFromOpen, 2),
            'expected_change_max' => round($maxChangeFromOpen, 2),
            'target_price' => round($targetPrice, 2),
            'current_price' => round($currentPrice, 2),
            'open_price' => round($openPrice, 2),
            'confidence_level' => $confidence,
            'trigger_indicators' => $triggers,
            'related_news' => $sentiment['headlines'],
            'suggested_action' => $confidence > 70 ? 'sell' : 'wait',
            'action_reasoning' => $this->generateActionReasoning('bearish', $triggers, $confidence),
        ];
    }

    protected function createNeutralScenario(float $currentPrice, array $indicators, array $fearGreed): array
    {
        $openPrice = $indicators['open_price'] ?? $currentPrice;
        
        // Adjust range based on actual volatility
        $volatility = $indicators['volatility']['level'];
        $atr = $indicators['atr']['percentage'];
        $rsi = $indicators['rsi']['value'];
        $momentum = $indicators['momentum']['value'];
        
        // Scale neutral range based on volatility
        if ($volatility === 'very_high' || $atr > 4) {
            $range = 1.5;
            $description = 'Mixed signals with elevated volatility. Price may oscillate in wider range.';
            $volatilityLabel = 'Very High';
        } elseif ($volatility === 'high' || $atr > 3) {
            $range = 1.0;
            $description = 'Neutral trend with moderate volatility. Price expected to trade in moderate range.';
            $volatilityLabel = 'Moderate';
        } else {
            $range = 0.5;
            $description = 'Low volatility environment. Price expected to trade in narrow range.';
            $volatilityLabel = 'Low';
        }
        
        // Determine momentum label
        $momentumLabel = 'Minimal';
        if (abs($momentum) > 2) {
            $momentumLabel = $momentum > 0 ? 'Slightly Positive' : 'Slightly Negative';
        } else if (abs($momentum) > 1) {
            $momentumLabel = 'Low';
        }
        
        return [
            'scenario_type' => 'neutral',
            'scenario_name' => 'Sideways Trading Scenario',
            'description' => $description,
            'expected_change_percent' => 0,
            'expected_change_min' => round(-$range, 2),
            'expected_change_max' => round($range, 2),
            'target_price' => round($currentPrice, 2),
            'current_price' => round($currentPrice, 2),
            'open_price' => round($openPrice, 2),
            'confidence_level' => 60,
            'trigger_indicators' => [
                'RSI' => 'Neutral (' . round($rsi, 2) . ')',
                'Volatility' => $volatilityLabel . ' (ATR: ' . round($atr, 2) . '%)',
                'Momentum' => $momentumLabel . ' (' . round($momentum, 2) . '%)',
            ],
            'related_news' => [],
            'suggested_action' => 'hold',
            'action_reasoning' => 'Market conditions suggest limited price movement. Consider waiting for clearer signals.',
        ];
    }

    protected function createMomentumReversalScenario(float $currentPrice, array $indicators, array $sentiment): array
    {
        $isReversingUp = $indicators['rsi']['value'] < 30 || $indicators['macd']['crossover'] === 'bullish';
        
        // Use actual momentum strength and ATR for reversal prediction
        $atr = $indicators['atr']['percentage'];
        $momentumStrength = abs($indicators['momentum']['value']);
        $rsiStrength = $indicators['rsi']['strength'];
        
        // Reversal moves are typically 1.5-3x normal ATR
        $changePercent = $atr * (1.5 + ($rsiStrength * 1.5));
        $changePercent = $isReversingUp ? $changePercent : -$changePercent;
        $changePercent = max(0.8, min(abs($changePercent), 6)) * ($isReversingUp ? 1 : -1);
        
        // Calculate min/max based on reversal strength
        $minChange = $changePercent * 0.6;
        $maxChange = $changePercent * 1.8;
        
        // Apply intraday time factor
        $intradayFactor = $indicators['intraday_factor'] ?? 1.0;
        $timeAdjustment = sqrt($intradayFactor);
        $minChange *= $timeAdjustment;
        $maxChange *= $timeAdjustment;
        
        return [
            'scenario_type' => 'momentum_reversal',
            'scenario_name' => $isReversingUp ? 'Bullish Reversal Scenario' : 'Bearish Reversal Scenario',
            'description' => 'Momentum indicators signal potential trend reversal. High-risk, high-reward opportunity.',
            'expected_change_percent' => $changePercent,
            'expected_change_min' => $minChange,
            'expected_change_max' => $maxChange,
            'target_price' => $currentPrice * (1 + $changePercent / 100),
            'current_price' => $currentPrice,
            'confidence_level' => 65,
            'trigger_indicators' => [
                'RSI' => $indicators['rsi']['signal'] . ' (' . $indicators['rsi']['value'] . ')',
                'MACD' => $indicators['macd']['crossover'],
                'Type' => 'Reversal Pattern',
                'Strength' => round($rsiStrength, 2),
            ],
            'related_news' => $sentiment['headlines'],
            'suggested_action' => $isReversingUp ? 'buy' : 'sell',
            'action_reasoning' => 'Momentum reversal detected. Consider entering position with tight stop-loss.',
        ];
    }

    protected function createVolatilityBreakoutScenario(float $currentPrice, array $indicators): array
    {
        $openPrice = $indicators['open_price'] ?? $currentPrice;
        $atrPercent = $indicators['atr']['percentage'];
        $bbWidth = $indicators['bollinger']['width'];
        $volatility = $indicators['volatility']['value'];
        $bbSignal = $indicators['bollinger']['signal'];
        
        // Calculate potential breakout magnitude (2-3x ATR)
        $potentialMove = $atrPercent * (2 + ($volatility * 2));
        $potentialMove = min($potentialMove, 10); // Cap at 10%
        
        // Determine breakout direction bias using multiple indicators
        $bullishSignals = 0;
        $bearishSignals = 0;
        
        // RSI bias
        if ($indicators['rsi']['value'] < 45) $bullishSignals += 2;
        else if ($indicators['rsi']['value'] > 55) $bearishSignals += 2;
        
        // MACD bias
        if (str_contains($indicators['macd']['signal'], 'bullish')) $bullishSignals += 3;
        else if (str_contains($indicators['macd']['signal'], 'bearish')) $bearishSignals += 3;
        
        // Volume/OBV bias
        if ($indicators['obv']['signal'] === 'bullish') $bullishSignals += 2;
        else if ($indicators['obv']['signal'] === 'bearish') $bearishSignals += 2;
        
        // Price position relative to Bollinger Bands
        if ($bbSignal === 'oversold') $bullishSignals += 2;
        else if ($bbSignal === 'overbought') $bearishSignals += 2;
        
        // Momentum bias
        if ($indicators['momentum']['value'] > 1) $bullishSignals += 1;
        else if ($indicators['momentum']['value'] < -1) $bearishSignals += 1;
        
        // Determine direction and calculate expected change
        $direction = 'neutral';
        $expectedChange = 0;
        $targetPrice = $currentPrice;
        $description = 'High volatility with mixed signals. Breakout direction unclear - could move sharply either way.';
        $actionReasoning = 'High volatility detected with no clear directional bias. Wait for breakout confirmation before entering.';
        
        if ($bullishSignals > $bearishSignals + 2) {
            // Strong bullish bias
            $direction = 'bullish';
            $expectedChange = $potentialMove * 0.65; // 65% of potential move
            $targetPrice = $currentPrice * (1 + $expectedChange / 100);
            $description = 'High volatility with BULLISH bias detected. Multiple indicators suggest upward breakout likely.';
            $actionReasoning = "Strong bullish signals ({$bullishSignals} vs {$bearishSignals}). Consider buying on breakout confirmation above resistance.";
        } else if ($bearishSignals > $bullishSignals + 2) {
            // Strong bearish bias
            $direction = 'bearish';
            $expectedChange = -($potentialMove * 0.65); // 65% of potential move
            $targetPrice = $currentPrice * (1 + $expectedChange / 100);
            $description = 'High volatility with BEARISH bias detected. Multiple indicators suggest downward breakout likely.';
            $actionReasoning = "Strong bearish signals ({$bearishSignals} vs {$bullishSignals}). Consider shorting or waiting on breakout confirmation below support.";
        } else if (abs($bullishSignals - $bearishSignals) <= 2) {
            // Truly neutral - show small directional bias if any
            if ($bullishSignals > $bearishSignals) {
                $expectedChange = $potentialMove * 0.25;
                $direction = 'slight_bullish';
            } else if ($bearishSignals > $bullishSignals) {
                $expectedChange = -($potentialMove * 0.25);
                $direction = 'slight_bearish';
            }
            $targetPrice = $currentPrice * (1 + $expectedChange / 100);
        }
        
        // Calculate realistic min/max range based on direction
        $intradayFactor = $indicators['intraday_factor'] ?? 1.0;
        $timeAdjustment = sqrt($intradayFactor);
        
        if ($direction === 'bullish') {
            // For bullish: min is conservative positive, max is full upside
            $minChange = $expectedChange * 0.5;  // Conservative case (50% of expected)
            $maxChange = $potentialMove;  // Full potential
        } else if ($direction === 'bearish') {
            // For bearish: min is full downside, max is conservative negative
            $minChange = -$potentialMove;  // Full downside risk
            $maxChange = $expectedChange * 0.5;  // Conservative case (least negative)
        } else {
            // Neutral: symmetric range (smaller)
            $minChange = -$potentialMove * 0.5;
            $maxChange = $potentialMove * 0.5;
        }
        
        // Apply intraday time factor to range as well
        $minChange *= $timeAdjustment;
        $maxChange *= $timeAdjustment;
        
        // Adjust confidence based on signal strength
        $signalDifference = abs($bullishSignals - $bearishSignals);
        $confidence = 60 + min($signalDifference * 3, 25); // 60-85% confidence
        
        // Determine suggested action
        $suggestedAction = 'wait';
        if ($direction === 'bullish' && $confidence > 75) {
            $suggestedAction = 'buy';
        } else if ($direction === 'bearish' && $confidence > 75) {
            $suggestedAction = 'sell';
        }
        
        // Log scenario calculation details for debugging
        Log::info("Volatility Breakout Scenario calculated", [
            'direction' => $direction,
            'expectedChange' => $expectedChange,
            'minChange' => $minChange,
            'maxChange' => $maxChange,
            'targetPrice' => $targetPrice,
            'currentPrice' => $currentPrice,
            'confidence' => $confidence,
            'bullishSignals' => $bullishSignals,
            'bearishSignals' => $bearishSignals,
            'intradayFactor' => $intradayFactor,
            'timeAdjustment' => $timeAdjustment,
        ]);
        
        return [
            'scenario_type' => 'volatility_breakout',
            'scenario_name' => 'Volatility Breakout Scenario',
            'description' => $description,
            'expected_change_percent' => round($expectedChange, 2),
            'expected_change_min' => round($minChange, 2),
            'expected_change_max' => round($maxChange, 2),
            'target_price' => round($targetPrice, 2),
            'current_price' => $currentPrice,
            'open_price' => $openPrice,
            'confidence_level' => $confidence,
            'trigger_indicators' => [
                'ATR' => round($atrPercent, 2) . '%',
                'Bollinger Bands' => $bbSignal . ' (Width: ' . round($bbWidth, 3) . ')',
                'Volatility' => $indicators['volatility']['level'],
                'Potential Move' => '±' . round($potentialMove, 1) . '%',
                'Direction Bias' => strtoupper($direction) . ' (' . $bullishSignals . ' bull / ' . $bearishSignals . ' bear signals)',
            ],
            'related_news' => [],
            'suggested_action' => $suggestedAction,
            'action_reasoning' => $actionReasoning,
        ];
    }

    protected function calculateExpectedChange(array $indicators, string $type): float
    {
        // Use multiple factors for more accurate prediction
        $atr = $indicators['atr']['percentage'];
        $momentum = $indicators['momentum']['value'];
        $rsi = $indicators['rsi']['value'];
        $macdStrength = $indicators['macd']['strength'];
        $volatility = $indicators['volatility']['value'];
        $intradayFactor = $indicators['intraday_factor'] ?? 1.0;
        
        // Base change from ATR (realistic daily move)
        $atrComponent = $atr * 0.8; // 80% of ATR is realistic intraday
        
        // Momentum component (if momentum is 5%, likely continues 30-50%)
        $momentumComponent = abs($momentum) * 0.4;
        
        // RSI component (distance from neutral affects strength)
        $rsiDistance = abs(50 - $rsi) / 50; // 0 to 1 scale
        $rsiComponent = $rsiDistance * $atr * 0.3;
        
        // MACD component
        $macdComponent = $macdStrength * $atr * 0.4;
        
        // Volatility adjustment (higher vol = wider range)
        $volMultiplier = 1 + ($volatility * 2); // 1.0 to 1.8x
        
        // Calculate weighted average
        $baseChange = ($atrComponent * 0.4) + 
                      ($momentumComponent * 0.25) + 
                      ($rsiComponent * 0.15) + 
                      ($macdComponent * 0.2);
        
        // Apply volatility multiplier
        $baseChange *= $volMultiplier;
        
        // Apply intraday time factor (scale down for remaining time)
        // Example: If 50% of day remains, expected move is 50-70% of full day ATR
        // We use square root to account for non-linear volatility
        $timeAdjustment = sqrt($intradayFactor);
        $baseChange *= $timeAdjustment;
        
        // Ensure realistic bounds (0.3% to 8% for full day, scales down with time)
        $minChange = 0.3 * $intradayFactor;
        $maxChange = 8.0;
        $baseChange = max($minChange, min($maxChange, $baseChange));
        
        return $type === 'bullish' ? $baseChange : -$baseChange;
    }

    protected function calculateConfidence(array $indicators, array $sentiment, string $type): int
    {
        $confidence = 50;
        
        // RSI contribution
        if ($indicators['rsi']['signal'] !== 'neutral') {
            $confidence += $indicators['rsi']['strength'] * 15;
        }
        
        // MACD contribution
        if (str_contains($indicators['macd']['signal'], $type === 'bullish' ? 'bullish' : 'bearish')) {
            $confidence += $indicators['macd']['strength'] * 20;
        }
        
        // Sentiment contribution
        if (($type === 'bullish' && $sentiment['trend'] === 'positive') ||
            ($type === 'bearish' && $sentiment['trend'] === 'negative')) {
            $confidence += 15;
        }
        
        return min(95, max(35, (int)$confidence));
    }

    protected function extractTriggers(array $indicators, array $sentiment, string $type): array
    {
        $triggers = [];
        
        if ($indicators['rsi']['signal'] !== 'neutral') {
            $triggers['RSI'] = $indicators['rsi']['signal'] . ' (' . $indicators['rsi']['value'] . ')';
        }
        
        if ($indicators['macd']['signal'] !== 'neutral') {
            $triggers['MACD'] = $indicators['macd']['signal'] . ' crossover';
        }
        
        if ($indicators['ema']['crossover'] !== 'none') {
            $triggers['EMA'] = $indicators['ema']['crossover'];
        }
        
        if ($indicators['volume_profile']['ratio'] > 1.3) {
            $triggers['Volume'] = 'Elevated (' . $indicators['volume_profile']['ratio'] . 'x avg)';
        }
        
        if ($sentiment['count'] > 0) {
            $triggers['Sentiment'] = $sentiment['trend'] . ' (' . $sentiment['score'] . ')';
        }
        
        return $triggers;
    }

    protected function generateActionReasoning(string $type, array $triggers, int $confidence): string
    {
        $triggerText = implode(', ', array_map(
            fn($k, $v) => "$k: $v",
            array_keys($triggers),
            array_values($triggers)
        ));
        
        $action = $type === 'bullish' ? 'buying' : 'selling';
        $confidenceText = $confidence > 70 ? 'Strong' : 'Moderate';
        
        return "{$confidenceText} {$action} signals detected: {$triggerText}. Confidence level: {$confidence}%.";
    }

    // === VOLUME FLOW & VOLATILITY EXPANSION DETECTION METHODS ===
    
    protected function detectAccumulationPhase(array $indicators): bool
    {
        // Price consolidating + OBV rising + BB tightening + Low ATR
        return $indicators['price_consolidation']['is_consolidating'] &&
               $indicators['price_consolidation']['volume_trend'] === 'increasing' &&
               $indicators['obv']['trend'] === 'rising' &&
               ($indicators['vfi']['signal'] === 'accumulation' || $indicators['vfi']['signal'] === 'strong_accumulation') &&
               $indicators['bollinger']['squeeze'] === true &&
               $indicators['atr']['percentage'] < 3;
    }
    
    protected function detectDistributionPhase(array $indicators): bool
    {
        // Price flat/up + OBV divergence (bearish) + VFI negative + Volume decreasing
        return ($indicators['price_consolidation']['is_consolidating'] || $indicators['momentum']['value'] < 2) &&
               $indicators['obv_divergence']['divergence'] === 'bearish' &&
               ($indicators['vfi']['signal'] === 'distribution' || $indicators['vfi']['signal'] === 'strong_distribution') &&
               $indicators['price_consolidation']['volume_trend'] === 'decreasing';
    }
    
    protected function detectVolatilityExpansion(array $indicators): bool
    {
        // BB widening after compression + price breaking upper/lower band
        $bbBreakout = ($indicators['bollinger']['current'] > $indicators['bollinger']['upper']) ||
                      ($indicators['bollinger']['current'] < $indicators['bollinger']['lower']);
        
        $priceVolatileRecently = $indicators['volatility']['level'] === 'high' || 
                                 $indicators['volatility']['level'] === 'very_high';
        
        return $bbBreakout || 
               ($indicators['bollinger']['width'] > 0.15 && $priceVolatileRecently) ||
               ($indicators['atr']['percentage'] > 4 && abs($indicators['momentum']['value']) > 3);
    }
    
    // === VOLUME FLOW & VOLATILITY EXPANSION SCENARIO CREATION ===
    
    protected function createAccumulationPhaseScenario(float $currentPrice, array $indicators): array
    {
        // Calculate based on consolidation range and volume strength
        $atr = $indicators['atr']['percentage'];
        $volumeGrowth = $indicators['price_consolidation']['volume_growth_ratio'];
        $vfiStrength = abs($indicators['vfi']['value']) / 1000000; // Normalize
        
        // Accumulation breakouts typically move 1.5-4% based on volume accumulation
        $changePercent = $atr * (1.2 + ($volumeGrowth * 0.8) + ($vfiStrength * 0.5));
        $changePercent = max(1.5, min($changePercent, 4.5)); // 1.5% to 4.5%
        
        // Confidence based on OBV and VFI correlation
        $confidence = 55;
        if ($indicators['obv']['trend'] === 'rising') $confidence += 15;
        if ($indicators['vfi']['signal'] === 'strong_accumulation') $confidence += 20;
        if ($indicators['bollinger']['squeeze']) $confidence += 10;
        
        $confidence = min(90, $confidence);
        
        return [
            'scenario_type' => 'accumulation_phase',
            'scenario_name' => 'Accumulation Phase (Hidden Bullish)',
            'description' => 'Price moving sideways but volume steadily increases. Suggests institutional buying before breakout.',
            'expected_change_percent' => $changePercent,
            'expected_change_min' => 1.5,
            'expected_change_max' => 4.0,
            'target_price' => $currentPrice * (1 + $changePercent / 100),
            'current_price' => $currentPrice,
            'confidence_level' => $confidence,
            'trigger_indicators' => [
                'OBV' => 'Rising (' . $indicators['obv']['trend'] . ')',
                'VFI' => $indicators['vfi']['signal'] . ' (' . number_format($indicators['vfi']['value']) . ')',
                'Bollinger Bands' => 'Tightening (Squeeze)',
                'ATR' => 'Low (' . $indicators['atr']['percentage'] . '%)',
                'Consolidation' => $indicators['price_consolidation']['days'] . ' days',
            ],
            'related_news' => [],
            'suggested_action' => $confidence > 65 ? 'buy' : 'watch',
            'action_reasoning' => "Hidden accumulation detected. Institutional buying likely occurring. Entry before breakout recommended. Confidence: {$confidence}%.",
        ];
    }
    
    protected function createDistributionPhaseScenario(float $currentPrice, array $indicators): array
    {
        // Calculate based on divergence strength and volume decline
        $atr = $indicators['atr']['percentage'];
        $divergenceStrength = $indicators['obv_divergence']['strength'];
        $volumeDecline = 1 - $indicators['price_consolidation']['volume_growth_ratio'];
        $vfiStrength = abs($indicators['vfi']['value']) / 1000000;
        
        // Distribution typically leads to -1% to -3.5% moves
        $changePercent = -($atr * (0.8 + ($divergenceStrength * 2) + ($volumeDecline * 0.5) + ($vfiStrength * 0.3)));
        $changePercent = max($changePercent, -4.0); // Cap at -4%
        $changePercent = min($changePercent, -0.8); // Minimum -0.8%
        
        // Confidence based on OBV divergence strength
        $confidence = 50;
        if ($indicators['obv_divergence']['strength'] > 0.05) $confidence += 20;
        if ($indicators['vfi']['signal'] === 'strong_distribution') $confidence += 15;
        if ($indicators['price_consolidation']['volume_trend'] === 'decreasing') $confidence += 10;
        
        $confidence = min(85, $confidence);
        
        return [
            'scenario_type' => 'distribution_phase',
            'scenario_name' => 'Distribution Phase (Hidden Bearish)',
            'description' => 'Price flat or slightly up, but volume dropping and OBV diverging negatively. Smart money exiting.',
            'expected_change_percent' => $changePercent,
            'expected_change_min' => -3.0,
            'expected_change_max' => -1.0,
            'target_price' => $currentPrice * (1 + $changePercent / 100),
            'current_price' => $currentPrice,
            'confidence_level' => $confidence,
            'trigger_indicators' => [
                'OBV Divergence' => 'Bearish (Strength: ' . $indicators['obv_divergence']['strength'] . ')',
                'VFI' => $indicators['vfi']['signal'] . ' (' . number_format($indicators['vfi']['value']) . ')',
                'Volume Trend' => 'Decreasing peaks',
                'Price Change' => $indicators['obv_divergence']['price_change'] . '%',
            ],
            'related_news' => [],
            'suggested_action' => $confidence > 65 ? 'sell' : 'reduce',
            'action_reasoning' => "Distribution pattern detected. Institutional selling while retail holds. Consider reducing position. Confidence: {$confidence}%.",
        ];
    }
    
    protected function createVolatilityExpansionScenario(float $currentPrice, array $indicators): array
    {
        // Calculate based on actual volatility and momentum
        $atr = $indicators['atr']['percentage'];
        $volatility = $indicators['volatility']['value'];
        $bbWidth = $indicators['bollinger']['width'];
        $momentum = abs($indicators['momentum']['value']);
        
        // Volatility expansion moves are typically 2-4x ATR
        $baseChange = $atr * (2 + ($volatility * 3) + ($bbWidth * 5));
        $baseChange = max(2.5, min($baseChange, 8.0)); // 2.5% to 8%
        
        // Determine direction based on RSI + MACD + Volume bias
        $bullishBias = 0;
        $bearishBias = 0;
        
        if ($indicators['rsi']['value'] < 50) $bullishBias++;
        if ($indicators['rsi']['value'] > 50) $bearishBias++;
        if (str_contains($indicators['macd']['signal'], 'bullish')) $bullishBias += 2;
        if (str_contains($indicators['macd']['signal'], 'bearish')) $bearishBias += 2;
        if ($indicators['obv']['signal'] === 'bullish') $bullishBias++;
        if ($indicators['obv']['signal'] === 'bearish') $bearishBias++;
        
        $direction = $bullishBias > $bearishBias ? 'bullish' : ($bearishBias > $bullishBias ? 'bearish' : 'neutral');
        $changePercent = $direction === 'bullish' ? $baseChange : ($direction === 'bearish' ? -$baseChange : 0);
        
        $confidence = 65 + min($bullishBias, $bearishBias) * 5;
        $confidence = min(85, $confidence);
        
        return [
            'scenario_type' => 'volatility_expansion',
            'scenario_name' => 'Volatility Expansion (Breakout Alert)',
            'description' => 'Bollinger Bands widening after compression. Sharp price move imminent. Direction: ' . strtoupper($direction) . '.',
            'expected_change_percent' => $changePercent,
            'expected_change_min' => -$baseChange,
            'expected_change_max' => $baseChange,
            'target_price' => $currentPrice * (1 + $changePercent / 100),
            'current_price' => $currentPrice,
            'confidence_level' => $confidence,
            'trigger_indicators' => [
                'Bollinger Band' => $indicators['bollinger']['signal'] . ' (Width: ' . $indicators['bollinger']['width'] . ')',
                'ATR' => $indicators['atr']['percentage'] . '%',
                'Volatility' => $indicators['volatility']['level'],
                'Direction Bias' => $direction . ' (' . ($direction === 'bullish' ? $bullishBias : $bearishBias) . ' signals)',
                'RSI' => $indicators['rsi']['value'],
                'MACD' => $indicators['macd']['signal'],
            ],
            'related_news' => [],
            'suggested_action' => $direction === 'bullish' ? 'buy' : ($direction === 'bearish' ? 'sell' : 'wait'),
            'action_reasoning' => "Volatility expansion detected with {$direction} bias. Wait for breakout confirmation or enter with tight stop-loss. Confidence: {$confidence}%.",
        ];
    }

    /**
     * Store scenarios in database with caching invalidation
     * Ensures no duplicate scenario types are stored
     */
    protected function storeScenarios(Stock $stock, array $scenarios, string $timeframe): void
    {
        // For "today" timeframe, don't deactivate scenarios if winner is already determined
        if ($timeframe === 'today') {
            $hasWinner = MarketScenario::where('stock_id', $stock->id)
                ->where('timeframe', 'today')
                ->where('is_active', true)
                ->whereNotNull('actual_close_price')
                ->exists();
            
            if ($hasWinner) {
                Log::warning("Not storing new scenarios for {$stock->symbol} - winner already determined for today");
                return;
            }
        }
        
        // Deactivate old scenarios for this stock and timeframe
        MarketScenario::where('stock_id', $stock->id)
            ->where('timeframe', $timeframe)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Remove duplicates by scenario_type (keep first occurrence)
        $uniqueScenarios = [];
        $seenTypes = [];
        
        foreach ($scenarios as $scenarioData) {
            $type = $scenarioData['scenario_type'];
            if (!in_array($type, $seenTypes)) {
                $uniqueScenarios[] = $scenarioData;
                $seenTypes[] = $type;
            } else {
                Log::warning("Skipping duplicate scenario type: {$type} for {$stock->symbol}");
            }
        }

        // Store unique scenarios
        foreach ($uniqueScenarios as $scenarioData) {
            MarketScenario::create(array_merge($scenarioData, [
                'stock_id' => $stock->id,
                'timeframe' => $timeframe,
                'valid_until' => $timeframe === 'today' ? now()->endOfDay() : now()->addDay()->endOfDay(),
                'is_active' => true,
                'model_version' => 'v3.0_multi_scenario',
            ]));
        }

        Log::info("Stored " . count($uniqueScenarios) . " unique scenarios for {$stock->symbol} ({$timeframe})");
    }
}
