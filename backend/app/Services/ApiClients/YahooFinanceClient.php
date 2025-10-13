<?php

namespace App\Services\ApiClients;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Yahoo Finance API Client for real-time stock quotes
 * Yahoo Finance provides free real-time stock data without requiring an API key
 */
class YahooFinanceClient
{
    protected string $baseUrl = 'https://query1.finance.yahoo.com/v8/finance';
    protected int $cacheTtl = 60; // Cache for 1 minute only for real-time data
    
    /**
     * Get real-time quote for a symbol
     * This uses Yahoo Finance's public API which provides real-time data
     */
    public function getQuote(string $symbol): ?array
    {
        $cacheKey = "yahoo_quote_{$symbol}";
        
        // Check cache first (30 seconds for real-time data)
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        
        try {
            // Use quote endpoint for most up-to-date data including pre/post market
            $response = Http::timeout(10)
                ->get("https://query1.finance.yahoo.com/v7/finance/quote", [
                    'symbols' => strtoupper($symbol),
                ]);
            
            if (!$response->successful()) {
                Log::warning("Yahoo Finance quote API failed for {$symbol}: " . $response->status());
                return null;
            }
            
            $data = $response->json();
            
            if (!isset($data['quoteResponse']['result'][0])) {
                Log::warning("No quote data in Yahoo Finance response for {$symbol}");
                return null;
            }
            
            $quote = $data['quoteResponse']['result'][0];
            
            // Extract price data
            $regularPrice = $quote['regularMarketPrice'] ?? null;
            $previousClose = $quote['regularMarketPreviousClose'] ?? null;
            
            if (!$regularPrice || !$previousClose) {
                Log::warning("Missing price data for {$symbol}");
                return null;
            }
            
            // Determine market status
            $marketState = $quote['marketState'] ?? 'CLOSED';
            $isExtendedHours = in_array($marketState, ['PRE', 'POST']);
            
            $marketStatus = match($marketState) {
                'PRE' => 'pre_market',
                'REGULAR' => 'open',
                'POST' => 'after_hours',
                default => 'closed'
            };
            
            // Determine which price to use (pre/post market or regular)
            $currentPrice = $regularPrice;
            $change = $currentPrice - $previousClose;
            $changePercent = ($change / $previousClose) * 100;
            
            // Override with pre-market data if available
            if ($marketState === 'PRE' && isset($quote['preMarketPrice'])) {
                $currentPrice = $quote['preMarketPrice'];
                $change = $quote['preMarketChange'] ?? ($currentPrice - $previousClose);
                $changePercent = $quote['preMarketChangePercent'] ?? (($change / $previousClose) * 100);
                Log::info("Using pre-market price for {$symbol}: {$currentPrice}");
            }
            // Override with post-market data if available
            elseif ($marketState === 'POST' && isset($quote['postMarketPrice'])) {
                $currentPrice = $quote['postMarketPrice'];
                $change = $quote['postMarketChange'] ?? ($currentPrice - $previousClose);
                $changePercent = $quote['postMarketChangePercent'] ?? (($change / $previousClose) * 100);
                Log::info("Using post-market price for {$symbol}: {$currentPrice}");
            }
            
            $quoteData = [
                'symbol' => strtoupper($symbol),
                'current_price' => round($currentPrice, 2),
                'open' => $quote['regularMarketOpen'] ?? $regularPrice,
                'high' => $quote['regularMarketDayHigh'] ?? $regularPrice,
                'low' => $quote['regularMarketDayLow'] ?? $regularPrice,
                'previous_close' => $previousClose,
                'change' => round($change, 2),
                'change_percent' => round($changePercent, 2),
                'volume' => $quote['regularMarketVolume'] ?? 0,
                'timestamp' => $quote['regularMarketTime'] ?? time(),
                'market_status' => $marketStatus,
                'is_extended_hours' => $isExtendedHours,
                'source' => 'yahoo',
            ];
            
            // Add extended hours specific data
            if ($isExtendedHours) {
                if ($marketState === 'PRE') {
                    $quoteData['extended_hours_price'] = $quote['preMarketPrice'] ?? null;
                    $quoteData['extended_hours_change'] = $quote['preMarketChange'] ?? null;
                    $quoteData['extended_hours_change_percent'] = $quote['preMarketChangePercent'] ?? null;
                } elseif ($marketState === 'POST') {
                    $quoteData['extended_hours_price'] = $quote['postMarketPrice'] ?? null;
                    $quoteData['extended_hours_change'] = $quote['postMarketChange'] ?? null;
                    $quoteData['extended_hours_change_percent'] = $quote['postMarketChangePercent'] ?? null;
                }
            }
            
            // Cache for 30 seconds for real-time extended hours data
            Cache::put($cacheKey, $quoteData, 30);
            
            Log::info("Yahoo Finance quote fetched for {$symbol}", [
                'price' => $currentPrice,
                'change' => $change,
                'market_status' => $marketStatus
            ]);
            
            return $quoteData;
            
        } catch (\Exception $e) {
            Log::error("Yahoo Finance API error for {$symbol}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get historical data for a symbol
     */
    public function getHistoricalData(string $symbol, int $daysBack = 90): array
    {
        try {
            $to = time();
            $from = $to - ($daysBack * 86400);
            
            $response = Http::timeout(15)
                ->get("{$this->baseUrl}/chart/{$symbol}", [
                    'interval' => '1d',
                    'period1' => $from,
                    'period2' => $to,
                ]);
            
            if (!$response->successful()) {
                return [];
            }
            
            $data = $response->json();
            
            if (!isset($data['chart']['result'][0]['timestamp'])) {
                return [];
            }
            
            $result = $data['chart']['result'][0];
            $timestamps = $result['timestamp'];
            $indicators = $result['indicators']['quote'][0];
            
            $candles = [];
            
            foreach ($timestamps as $index => $timestamp) {
                if (!isset($indicators['close'][$index])) {
                    continue;
                }
                
                $candles[] = [
                    'timestamp' => $timestamp,
                    'date' => date('Y-m-d H:i:s', $timestamp),
                    'open' => $indicators['open'][$index] ?? null,
                    'high' => $indicators['high'][$index] ?? null,
                    'low' => $indicators['low'][$index] ?? null,
                    'close' => $indicators['close'][$index],
                    'volume' => $indicators['volume'][$index] ?? null,
                ];
            }
            
            Log::info("Yahoo Finance historical data fetched for {$symbol}: " . count($candles) . " candles");
            
            return $candles;
            
        } catch (\Exception $e) {
            Log::error("Yahoo Finance historical data error for {$symbol}: " . $e->getMessage());
            return [];
        }
    }
}
