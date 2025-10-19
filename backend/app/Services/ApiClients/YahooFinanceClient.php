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
            // Use quote endpoint for most up-to-date data including pre/post market with proper headers
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Referer' => 'https://finance.yahoo.com/',
                ])
                ->retry(2, 1000) // Retry twice with 1 second delay
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
     * Get fundamental data for a symbol
     * Fetches P/E, EPS, revenue growth, margins, etc.
     */
    public function getFundamentals(string $symbol): ?array
    {
        $cacheKey = "yahoo_fundamentals_{$symbol}";
        
        // Cache for 1 hour (fundamentals don't change frequently)
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        
        try {
            // Use quoteSummary endpoint for detailed fundamental data with proper headers
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Referer' => 'https://finance.yahoo.com/',
                    'Origin' => 'https://finance.yahoo.com',
                ])
                ->retry(2, 1000) // Retry twice with 1 second delay
                ->get("https://query2.finance.yahoo.com/v10/finance/quoteSummary/{$symbol}", [
                    'modules' => 'defaultKeyStatistics,financialData,summaryDetail',
                ]);
            
            if (!$response->successful()) {
                Log::warning("Yahoo Finance fundamentals API failed for {$symbol}");
                return null;
            }
            
            $data = $response->json();
            
            if (!isset($data['quoteSummary']['result'][0])) {
                Log::warning("No fundamental data in Yahoo Finance response for {$symbol}");
                return null;
            }
            
            $result = $data['quoteSummary']['result'][0];
            $stats = $result['defaultKeyStatistics'] ?? [];
            $financial = $result['financialData'] ?? [];
            $summary = $result['summaryDetail'] ?? [];
            
            $fundamentals = [
                'pe_ratio' => $stats['trailingPE']['raw'] ?? $stats['forwardPE']['raw'] ?? null,
                'pb_ratio' => $stats['priceToBook']['raw'] ?? null,
                'ps_ratio' => $summary['priceToSalesTrailing12Months']['raw'] ?? null,
                'eps_growth' => $stats['earningsQuarterlyGrowth']['raw'] ?? null,
                'revenue_growth' => $financial['revenueGrowth']['raw'] ?? null,
                'roe' => $financial['returnOnEquity']['raw'] ?? null,
                'profit_margin' => $financial['profitMargins']['raw'] ?? null,
                'debt_to_equity' => $financial['debtToEquity']['raw'] ?? null,
                'dividend_yield' => $summary['dividendYield']['raw'] ?? null,
                'market_cap' => $summary['marketCap']['raw'] ?? null,
                'beta' => $stats['beta']['raw'] ?? null,
            ];
            
            // Convert percentages to proper format (0.15 = 15%)
            if ($fundamentals['eps_growth'] !== null) {
                $fundamentals['eps_growth'] = $fundamentals['eps_growth'] * 100;
            }
            if ($fundamentals['revenue_growth'] !== null) {
                $fundamentals['revenue_growth'] = $fundamentals['revenue_growth'] * 100;
            }
            if ($fundamentals['roe'] !== null) {
                $fundamentals['roe'] = $fundamentals['roe'] * 100;
            }
            if ($fundamentals['profit_margin'] !== null) {
                $fundamentals['profit_margin'] = $fundamentals['profit_margin'] * 100;
            }
            if ($fundamentals['dividend_yield'] !== null) {
                $fundamentals['dividend_yield'] = $fundamentals['dividend_yield'] * 100;
            }
            
            // Cache for 1 hour
            Cache::put($cacheKey, $fundamentals, 3600);
            
            Log::info("Yahoo Finance fundamentals fetched for {$symbol}", [
                'pe' => $fundamentals['pe_ratio'],
                'eps_growth' => $fundamentals['eps_growth'],
                'roe' => $fundamentals['roe'],
            ]);
            
            return $fundamentals;
            
        } catch (\Exception $e) {
            Log::error("Yahoo Finance fundamentals error for {$symbol}: " . $e->getMessage());
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
