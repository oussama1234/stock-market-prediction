<?php

namespace App\Services\ApiClients;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Polygon.io API Client
 * Free tier: 5 API calls per minute
 * Provides real-time and extended hours data
 */
class PolygonClient
{
    protected string $baseUrl = 'https://api.polygon.io';
    protected ?string $apiKey;
    
    public function __construct()
    {
        $this->apiKey = config('services.polygon.api_key');
    }
    
    /**
     * Get real-time quote with extended hours support
     * Using free tier endpoint: previous close + aggregates
     */
    public function getQuote(string $symbol): ?array
    {
        if (!$this->apiKey) {
            Log::warning("Polygon API key not configured");
            return null;
        }
        
        $cacheKey = "polygon_quote_{$symbol}";
        
        // Check cache (30 seconds for real-time data)
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        
        try {
            $symbol = strtoupper($symbol);
            
            // Free tier: Use previous close endpoint
            $response = Http::timeout(10)
                ->get("{$this->baseUrl}/v2/aggs/ticker/{$symbol}/prev", [
                    'apiKey' => $this->apiKey,
                    'adjusted' => 'true',
                ]);
            
            if (!$response->successful()) {
                Log::warning("Polygon API failed for {$symbol}: " . $response->status());
                return null;
            }
            
            $data = $response->json();
            
            if (!isset($data['results'][0])) {
                Log::warning("No data in Polygon response for {$symbol}");
                return null;
            }
            
            $result = $data['results'][0];
            
            // Get current price (close from previous day)
            $currentPrice = $result['c'] ?? null;
            // Get previous close from the bar before
            $previousClose = $result['o'] ?? $result['c'];
            
            if (!$currentPrice) {
                Log::warning("Missing price data from Polygon for {$symbol}");
                return null;
            }
            
            // Try to get today's data for more current info
            $todayResponse = Http::timeout(10)
                ->get("{$this->baseUrl}/v2/aggs/ticker/{$symbol}/range/1/day/" . date('Y-m-d') . "/" . date('Y-m-d'), [
                    'apiKey' => $this->apiKey,
                ]);
            
            // Use today's data if available, otherwise use previous close
            if ($todayResponse->successful()) {
                $todayData = $todayResponse->json();
                if (isset($todayData['results'][0])) {
                    $todayResult = $todayData['results'][0];
                    $currentPrice = $todayResult['c'] ?? $currentPrice;
                    $openPrice = $todayResult['o'] ?? $result['c'];
                    $highPrice = $todayResult['h'] ?? $currentPrice;
                    $lowPrice = $todayResult['l'] ?? $currentPrice;
                    $volume = $todayResult['v'] ?? 0;
                    // Previous close is yesterday's close
                    $previousClose = $result['c'];
                } else {
                    // Use previous day data
                    $openPrice = $result['o'];
                    $highPrice = $result['h'];
                    $lowPrice = $result['l'];
                    $volume = $result['v'] ?? 0;
                }
            } else {
                // Use previous day data
                $openPrice = $result['o'];
                $highPrice = $result['h'];
                $lowPrice = $result['l'];
                $volume = $result['v'] ?? 0;
            }
            
            // Calculate change
            $change = $currentPrice - $previousClose;
            $changePercent = ($change / $previousClose) * 100;
            
            // Determine market status
            $now = new \DateTime('now', new \DateTimeZone('America/New_York'));
            $hour = (int)$now->format('H');
            $minute = (int)$now->format('i');
            $dayOfWeek = (int)$now->format('N');
            
            $isWeekend = $dayOfWeek >= 6;
            $isPreMarket = !$isWeekend && ($hour < 9 || ($hour == 9 && $minute < 30));
            $isRegularHours = !$isWeekend && (($hour == 9 && $minute >= 30) || ($hour > 9 && $hour < 16));
            $isAfterHours = !$isWeekend && $hour >= 16 && $hour < 20;
            
            $marketStatus = 'closed';
            $isExtendedHours = false;
            
            if ($isRegularHours) {
                $marketStatus = 'open';
            } elseif ($isPreMarket && $hour >= 4) {
                $marketStatus = 'pre_market';
                $isExtendedHours = true;
            } elseif ($isAfterHours) {
                $marketStatus = 'after_hours';
                $isExtendedHours = true;
            }
            
            $quoteData = [
                'symbol' => $symbol,
                'current_price' => round($currentPrice, 2),
                'open' => $openPrice,
                'high' => $highPrice,
                'low' => $lowPrice,
                'previous_close' => $previousClose,
                'change' => round($change, 2),
                'change_percent' => round($changePercent, 2),
                'volume' => $volume,
                'timestamp' => $result['t'] ?? time() * 1000,
                'market_status' => $marketStatus,
                'is_extended_hours' => $isExtendedHours,
                'source' => 'polygon',
            ];
            
            
            // Cache for 30 seconds
            Cache::put($cacheKey, $quoteData, 30);
            
            Log::info("Polygon quote fetched for {$symbol}", [
                'price' => $currentPrice,
                'change' => $change,
                'market_status' => $marketStatus,
                'timestamp' => $result['t'] ?? 'unknown',
                'date_requested' => date('Y-m-d H:i:s'),
                'prev_close_data' => isset($data['results'][0]),
                'today_data' => isset($todayData['results'][0]) ? 'yes' : 'no'
            ]);
            
            return $quoteData;
            
        } catch (\Exception $e) {
            Log::error("Polygon API error for {$symbol}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get previous day OHLC data
     */
    public function getPreviousClose(string $symbol): ?array
    {
        if (!$this->apiKey) {
            return null;
        }
        
        try {
            $symbol = strtoupper($symbol);
            
            $response = Http::timeout(10)
                ->get("{$this->baseUrl}/v2/aggs/ticker/{$symbol}/prev", [
                    'apiKey' => $this->apiKey,
                ]);
            
            if (!$response->successful()) {
                return null;
            }
            
            $data = $response->json();
            
            if (!isset($data['results'][0])) {
                return null;
            }
            
            $result = $data['results'][0];
            
            return [
                'date' => date('Y-m-d', $result['t'] / 1000),
                'open' => $result['o'],
                'high' => $result['h'],
                'low' => $result['l'],
                'close' => $result['c'],
                'volume' => $result['v'],
            ];
            
        } catch (\Exception $e) {
            Log::error("Polygon previous close error for {$symbol}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get historical aggregates (daily bars)
     */
    public function getHistoricalData(string $symbol, int $daysBack = 90): array
    {
        if (!$this->apiKey) {
            return [];
        }
        
        try {
            $symbol = strtoupper($symbol);
            $to = date('Y-m-d');
            $from = date('Y-m-d', strtotime("-{$daysBack} days"));
            
            $response = Http::timeout(15)
                ->get("{$this->baseUrl}/v2/aggs/ticker/{$symbol}/range/1/day/{$from}/{$to}", [
                    'apiKey' => $this->apiKey,
                    'adjusted' => 'true',
                    'sort' => 'asc',
                ]);
            
            if (!$response->successful()) {
                return [];
            }
            
            $data = $response->json();
            
            if (!isset($data['results'])) {
                return [];
            }
            
            $candles = [];
            
            foreach ($data['results'] as $bar) {
                $candles[] = [
                    'timestamp' => $bar['t'] / 1000,
                    'date' => date('Y-m-d H:i:s', $bar['t'] / 1000),
                    'open' => $bar['o'],
                    'high' => $bar['h'],
                    'low' => $bar['l'],
                    'close' => $bar['c'],
                    'volume' => $bar['v'],
                ];
            }
            
            Log::info("Polygon historical data fetched for {$symbol}: " . count($candles) . " candles");
            
            return $candles;
            
        } catch (\Exception $e) {
            Log::error("Polygon historical data error for {$symbol}: " . $e->getMessage());
            return [];
        }
    }
}
