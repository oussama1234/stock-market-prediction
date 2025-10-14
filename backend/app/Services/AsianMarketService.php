<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asian Market Service
 * 
 * Fetches and normalizes data from major Asian markets:
 * - Nikkei 225 (Japan)
 * - Hang Seng (Hong Kong/China)
 * - Shanghai Composite (China)
 * - Nifty 50 (India)
 * 
 * Provides normalized features for quick_model_v2 predictions
 * with caching and fallback mechanisms.
 */
class AsianMarketService
{
    // Market index symbols and their mappings
    protected const MARKETS = [
        'nikkei' => [
            'symbol' => '^N225',
            'name' => 'Nikkei 225',
            'weight' => 0.3,
            'timezone' => 'Asia/Tokyo',
        ],
        'hang_seng' => [
            'symbol' => '^HSI',
            'name' => 'Hang Seng',
            'weight' => 0.3,
            'timezone' => 'Asia/Hong_Kong',
        ],
        'shanghai' => [
            'symbol' => '000001.SS',
            'name' => 'Shanghai Composite',
            'weight' => 0.2,
            'timezone' => 'Asia/Shanghai',
        ],
        'nifty' => [
            'symbol' => '^NSEI',
            'name' => 'Nifty 50',
            'weight' => 0.2,
            'timezone' => 'Asia/Kolkata',
        ],
    ];

    protected $cachePrefix = 'asian_market_';
    protected $cacheTTL = 300; // 5 minutes

    /**
     * Get today's changes for all Asian markets
     * 
     * @return array
     */
    public function getTodayChanges(): array
    {
        $cacheKey = $this->cachePrefix . 'today_changes';
        
        return Cache::remember($cacheKey, $this->cacheTTL, function () {
            $changes = [];
            
            foreach (self::MARKETS as $key => $config) {
                try {
                    $data = $this->fetchMarketData($config['symbol']);
                    $changes[$key] = [
                        'symbol' => $config['symbol'],
                        'name' => $config['name'],
                        'change_percent' => $data['change_percent'] ?? 0,
                        'price' => $data['price'] ?? null,
                        'volume' => $data['volume'] ?? null,
                        'timestamp' => $data['timestamp'] ?? now()->toIso8601String(),
                        'weight' => $config['weight'],
                    ];
                } catch (\Exception $e) {
                    Log::warning("Failed to fetch {$config['name']}: " . $e->getMessage());
                    $changes[$key] = [
                        'symbol' => $config['symbol'],
                        'name' => $config['name'],
                        'change_percent' => 0,
                        'price' => null,
                        'volume' => null,
                        'timestamp' => now()->toIso8601String(),
                        'weight' => $config['weight'],
                        'error' => true,
                    ];
                }
            }
            
            return $changes;
        });
    }

    /**
     * Get rolling changes for specified window
     * 
     * @param int $windowDays
     * @return array
     */
    public function getRollingChanges(int $windowDays = 7): array
    {
        $cacheKey = $this->cachePrefix . "rolling_{$windowDays}d";
        
        return Cache::remember($cacheKey, $this->cacheTTL * 2, function () use ($windowDays) {
            $rolling = [];
            
            foreach (self::MARKETS as $key => $config) {
                try {
                    $data = $this->fetchHistoricalData($config['symbol'], $windowDays);
                    $rolling[$key] = [
                        'symbol' => $config['symbol'],
                        'name' => $config['name'],
                        'total_change_percent' => $data['total_change'] ?? 0,
                        'avg_daily_change' => $data['avg_change'] ?? 0,
                        'volatility' => $data['volatility'] ?? 0,
                        'weight' => $config['weight'],
                    ];
                } catch (\Exception $e) {
                    Log::warning("Failed to fetch rolling data for {$config['name']}: " . $e->getMessage());
                    $rolling[$key] = [
                        'symbol' => $config['symbol'],
                        'name' => $config['name'],
                        'total_change_percent' => 0,
                        'avg_daily_change' => 0,
                        'volatility' => 0,
                        'weight' => $config['weight'],
                        'error' => true,
                    ];
                }
            }
            
            return $rolling;
        });
    }

    /**
     * Get futures status (if available)
     * 
     * @return array
     */
    public function getFuturesStatus(): array
    {
        // Futures data often requires premium APIs
        // For now, return empty array or implement if free source available
        return [
            'available' => false,
            'data' => [],
        ];
    }

    /**
     * Normalize market data for model input
     * 
     * @param array $changes
     * @return array
     */
    public function normalizeForModel(array $changes): array
    {
        // Calculate weighted average change
        $totalWeight = 0;
        $weightedSum = 0;
        $validMarkets = 0;
        
        foreach ($changes as $key => $data) {
            if (!isset($data['error']) || !$data['error']) {
                $weightedSum += $data['change_percent'] * $data['weight'];
                $totalWeight += $data['weight'];
                $validMarkets++;
            }
        }
        
        $asianAvgChange = $totalWeight > 0 ? $weightedSum / $totalWeight : 0;
        
        // Calculate influence score using tanh for bounded output
        $scale = config('prediction.asian_influence_scale', 2.0);
        $asianInfluenceScore = tanh($asianAvgChange / $scale);
        
        // Calculate impact percentage (max 20% for Asian markets)
        $maxImpact = config('prediction.influence_scales.asian.max_impact', 0.2);
        $asianImpactPercent = abs($asianInfluenceScore) * $maxImpact;
        
        // Determine sentiment based on average change
        $sentiment = $asianAvgChange > 0.5 ? 'positive' : ($asianAvgChange < -0.5 ? 'negative' : 'neutral');
        
        return [
            // Python model V5 expected fields
            'asian_market_change' => $asianAvgChange,           // Primary field for Python model
            'asian_market_sentiment' => $sentiment,             // Primary field for Python model
            
            // Legacy fields for backward compatibility
            'asian_avg_change' => $asianAvgChange,
            'asian_influence_score' => $asianInfluenceScore,
            'asian_impact_percent' => $asianImpactPercent,
            'valid_markets' => $validMarkets,
            'total_markets' => count($changes),
            'individual_changes' => array_map(function ($data) {
                return [
                    'name' => $data['name'],
                    'change_percent' => $data['change_percent'],
                    'weight' => $data['weight'],
                ];
            }, $changes),
        ];
    }

    /**
     * Fetch current market data from API
     * 
     * @param string $symbol
     * @return array
     */
    protected function fetchMarketData(string $symbol): array
    {
        // Try Yahoo Finance API (free, no key required)
        try {
            return $this->fetchFromYahoo($symbol);
        } catch (\Exception $e) {
            Log::warning("Yahoo API failed for $symbol: " . $e->getMessage());
        }
        
        // Fallback to Finnhub (requires API key)
        try {
            return $this->fetchFromFinnhub($symbol);
        } catch (\Exception $e) {
            Log::warning("Finnhub API failed for $symbol: " . $e->getMessage());
        }
        
        // Last resort: return neutral data
        return [
            'change_percent' => 0,
            'price' => null,
            'volume' => null,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Fetch from Yahoo Finance
     * 
     * @param string $symbol
     * @return array
     */
    protected function fetchFromYahoo(string $symbol): array
    {
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}";
        
        $response = Http::timeout(10)->get($url, [
            'interval' => '1d',
            'range' => '2d',
        ]);
        
        if (!$response->successful()) {
            throw new \Exception("Yahoo API request failed");
        }
        
        $data = $response->json();
        
        if (!isset($data['chart']['result'][0])) {
            throw new \Exception("Invalid Yahoo API response");
        }
        
        $result = $data['chart']['result'][0];
        $quote = $result['indicators']['quote'][0];
        
        // Get most recent close and previous close
        $closes = array_filter($quote['close'] ?? []);
        if (count($closes) < 2) {
            throw new \Exception("Insufficient data");
        }
        
        $closesArray = array_values($closes);
        $currentClose = end($closesArray);
        $previousClose = prev($closesArray);
        
        $changePercent = (($currentClose - $previousClose) / $previousClose) * 100;
        
        // Get volume (end() requires a variable reference)
        $volumes = $quote['volume'] ?? [0];
        $lastVolume = !empty($volumes) ? end($volumes) : 0;
        
        return [
            'change_percent' => round($changePercent, 2),
            'price' => $currentClose,
            'volume' => $lastVolume,
            'timestamp' => now()->toIso8601String(),
            'source' => 'yahoo',
        ];
    }

    /**
     * Fetch from Finnhub
     * 
     * @param string $symbol
     * @return array
     */
    protected function fetchFromFinnhub(string $symbol): array
    {
        $apiKey = config('services.finnhub.key');
        
        if (!$apiKey) {
            throw new \Exception("Finnhub API key not configured");
        }
        
        // Convert Yahoo symbols to Finnhub format
        $finnhubSymbol = $this->convertToFinnhubSymbol($symbol);
        
        $response = Http::timeout(10)->get('https://finnhub.io/api/v1/quote', [
            'symbol' => $finnhubSymbol,
            'token' => $apiKey,
        ]);
        
        if (!$response->successful()) {
            throw new \Exception("Finnhub API request failed");
        }
        
        $data = $response->json();
        
        return [
            'change_percent' => $data['dp'] ?? 0,
            'price' => $data['c'] ?? null,
            'volume' => null,
            'timestamp' => now()->toIso8601String(),
            'source' => 'finnhub',
        ];
    }

    /**
     * Fetch historical data for rolling window
     * 
     * @param string $symbol
     * @param int $days
     * @return array
     */
    protected function fetchHistoricalData(string $symbol, int $days): array
    {
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}";
        
        $response = Http::timeout(15)->get($url, [
            'interval' => '1d',
            'range' => "{$days}d",
        ]);
        
        if (!$response->successful()) {
            throw new \Exception("Failed to fetch historical data");
        }
        
        $data = $response->json();
        $result = $data['chart']['result'][0] ?? null;
        
        if (!$result) {
            throw new \Exception("Invalid historical data response");
        }
        
        $closes = array_filter($result['indicators']['quote'][0]['close'] ?? []);
        
        if (empty($closes)) {
            throw new \Exception("No historical close prices");
        }
        
        $closesArray = array_values($closes);
        $firstClose = reset($closesArray);
        $lastClose = end($closesArray);
        
        $totalChange = (($lastClose - $firstClose) / $firstClose) * 100;
        
        // Calculate daily changes
        $dailyChanges = [];
        for ($i = 1; $i < count($closesArray); $i++) {
            $dailyChanges[] = (($closesArray[$i] - $closesArray[$i - 1]) / $closesArray[$i - 1]) * 100;
        }
        
        $avgChange = !empty($dailyChanges) ? array_sum($dailyChanges) / count($dailyChanges) : 0;
        $volatility = !empty($dailyChanges) ? $this->calculateStdDev($dailyChanges) : 0;
        
        return [
            'total_change' => round($totalChange, 2),
            'avg_change' => round($avgChange, 2),
            'volatility' => round($volatility, 2),
        ];
    }

    /**
     * Convert Yahoo symbol to Finnhub format
     * 
     * @param string $symbol
     * @return string
     */
    protected function convertToFinnhubSymbol(string $symbol): string
    {
        $map = [
            '^N225' => 'NI225',
            '^HSI' => 'HSI',
            '000001.SS' => '000001.SS',
            '^NSEI' => 'NSEI',
        ];
        
        return $map[$symbol] ?? $symbol;
    }

    /**
     * Calculate standard deviation
     * 
     * @param array $values
     * @return float
     */
    protected function calculateStdDev(array $values): float
    {
        if (empty($values)) {
            return 0.0;
        }
        
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(function ($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values)) / count($values);
        
        return sqrt($variance);
    }

    /**
     * Clear all caches
     * 
     * @return void
     */
    public function clearCache(): void
    {
        $keys = [
            $this->cachePrefix . 'today_changes',
            $this->cachePrefix . 'rolling_7d',
            $this->cachePrefix . 'rolling_14d',
        ];
        
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        
        Log::info('Asian market cache cleared');
    }

    /**
     * Get market weights from config
     * 
     * @return array
     */
    public function getMarketWeights(): array
    {
        $weights = [];
        foreach (self::MARKETS as $key => $config) {
            $weights[$key] = $config['weight'];
        }
        return $weights;
    }

    /**
     * Get all market configurations
     * 
     * @return array
     */
    public function getMarkets(): array
    {
        return self::MARKETS;
    }
}
