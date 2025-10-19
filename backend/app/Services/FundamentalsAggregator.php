<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Aggregates fundamental data from multiple sources
 * Falls back gracefully if Yahoo Finance is unavailable
 */
class FundamentalsAggregator
{
    protected $yahooClient;
    
    public function __construct(\App\Services\ApiClients\YahooFinanceClient $yahooClient)
    {
        $this->yahooClient = $yahooClient;
    }
    
    /**
     * Get fundamental data with fallback sources
     */
    public function getFundamentals(string $symbol): ?array
    {
        $cacheKey = "fundamentals_aggregated_{$symbol}";
        
        // Cache for 1 hour
        if ($cached = Cache::get($cacheKey)) {
            Log::info("Using cached fundamentals for {$symbol}");
            return $cached;
        }
        
        // Try Yahoo Finance first
        try {
            $fundamentals = $this->yahooClient->getFundamentals($symbol);
            if ($fundamentals && $this->hasRealData($fundamentals)) {
                Log::info("Yahoo Finance provided fundamentals for {$symbol}");
                Cache::put($cacheKey, $fundamentals, 3600);
                return $fundamentals;
            }
        } catch (\Exception $e) {
            Log::warning("Yahoo Finance failed for {$symbol}: " . $e->getMessage());
        }
        
        // Fallback to Finnhub
        try {
            $fundamentals = $this->getFinnhubFundamentals($symbol);
            if ($fundamentals && $this->hasRealData($fundamentals)) {
                Log::info("Finnhub provided fundamentals for {$symbol}");
                Cache::put($cacheKey, $fundamentals, 3600);
                return $fundamentals;
            }
        } catch (\Exception $e) {
            Log::warning("Finnhub failed for {$symbol}: " . $e->getMessage());
        }
        
        // Fallback to Alpha Vantage
        try {
            $fundamentals = $this->getAlphaVantageFundamentals($symbol);
            if ($fundamentals && $this->hasRealData($fundamentals)) {
                Log::info("Alpha Vantage provided fundamentals for {$symbol}");
                Cache::put($cacheKey, $fundamentals, 3600);
                return $fundamentals;
            }
        } catch (\Exception $e) {
            Log::warning("Alpha Vantage failed for {$symbol}: " . $e->getMessage());
        }
        
        Log::error("All fundamental data sources failed for {$symbol}");
        return null;
    }
    
    /**
     * Check if fundamentals contain real data (not just defaults)
     */
    protected function hasRealData(?array $fundamentals): bool
    {
        if (!$fundamentals) return false;
        
        // Check if at least 3 key metrics are non-zero/non-null
        $count = 0;
        $keys = ['pe_ratio', 'eps_growth', 'revenue_growth', 'roe', 'profit_margin'];
        
        foreach ($keys as $key) {
            if (isset($fundamentals[$key]) && $fundamentals[$key] !== null && $fundamentals[$key] != 0) {
                $count++;
            }
        }
        
        return $count >= 3;
    }
    
    /**
     * Get fundamentals from Finnhub
     */
    protected function getFinnhubFundamentals(string $symbol): ?array
    {
        $apiKey = config('services.finnhub.key');
        if (!$apiKey) {
            return null;
        }
        
        $response = Http::timeout(10)
            ->get('https://finnhub.io/api/v1/stock/metric', [
                'symbol' => $symbol,
                'metric' => 'all',
                'token' => $apiKey,
            ]);
        
        if (!$response->successful()) {
            return null;
        }
        
        $data = $response->json();
        $metrics = $data['metric'] ?? [];
        
        if (empty($metrics)) {
            return null;
        }
        
        // DEBUG: Log raw Finnhub values
        Log::info("Raw Finnhub data for {$symbol}", [
            'epsGrowthTTMYoy' => $metrics['epsGrowthTTMYoy'] ?? null,
            'revenueGrowthTTMYoy' => $metrics['revenueGrowthTTMYoy'] ?? null,
            'roeTTM' => $metrics['roeTTM'] ?? null,
            'netProfitMarginTTM' => $metrics['netProfitMarginTTM'] ?? null,
            'dividendYieldIndicatedAnnual' => $metrics['dividendYieldIndicatedAnnual'] ?? null,
        ]);
        
        // Finnhub returns percentages in basis points or already multiplied (e.g., 597 for 5.97%)
        // We need percentages as numbers (5.97, not 0.0597 or 597)
        $epsGrowth = $metrics['epsGrowthTTMYoy'] ?? null;
        $revenueGrowth = $metrics['revenueGrowthTTMYoy'] ?? null;
        $roe = $metrics['roeTTM'] ?? null;
        $profitMargin = $metrics['netProfitMarginTTM'] ?? null;
        $dividendYield = $metrics['dividendYieldIndicatedAnnual'] ?? null;
        
        // Helper to normalize percentages
        // Finnhub returns mixed formats:
        // - Growth rates in decimal (0.16 = 16%)
        // - Margins/Ratios already as % (24.3 = 24.3%, 154.92 = 154.92%)
        // - Dividend yield in decimal (0.42 = 0.42%)
        $normalizePct = function($val, $isDividendOrGrowth = false) {
            if ($val === null) return null;
            
            // If value is very small (< 1), it's in decimal format, multiply by 100
            // Examples: 0.16 => 16%, 0.42 => 0.42%
            if (abs($val) < 1) {
                return $val * 100;
            }
            
            // Otherwise it's already in percentage format (5.97 = 5.97%, 154.92 = 154.92%)
            return $val;
        };
        
        return [
            'pe_ratio' => $metrics['peBasicExclExtraTTM'] ?? $metrics['peNormalizedAnnual'] ?? null,
            'pb_ratio' => $metrics['pbAnnual'] ?? null,
            'ps_ratio' => $metrics['psAnnual'] ?? null,
            'eps_growth' => $normalizePct($epsGrowth),
            'revenue_growth' => $normalizePct($revenueGrowth),
            'roe' => $normalizePct($roe),
            'profit_margin' => $normalizePct($profitMargin),
            'debt_to_equity' => $metrics['totalDebt/totalEquityAnnual'] ?? null,
            // Dividend yield from Finnhub is already in correct format (0.42 = 0.42%)
            'dividend_yield' => $dividendYield,
        ];
    }
    
    /**
     * Get fundamentals from Alpha Vantage
     */
    protected function getAlphaVantageFundamentals(string $symbol): ?array
    {
        $apiKey = config('services.alpha_vantage.key');
        if (!$apiKey) {
            return null;
        }
        
        $response = Http::timeout(10)
            ->get('https://www.alphavantage.co/query', [
                'function' => 'OVERVIEW',
                'symbol' => $symbol,
                'apikey' => $apiKey,
            ]);
        
        if (!$response->successful()) {
            return null;
        }
        
        $data = $response->json();
        
        if (empty($data) || isset($data['Error Message'])) {
            return null;
        }
        
        return [
            'pe_ratio' => isset($data['PERatio']) && $data['PERatio'] !== 'None' ? (float) $data['PERatio'] : null,
            'pb_ratio' => isset($data['PriceToBookRatio']) && $data['PriceToBookRatio'] !== 'None' ? (float) $data['PriceToBookRatio'] : null,
            'ps_ratio' => isset($data['PriceToSalesRatioTTM']) && $data['PriceToSalesRatioTTM'] !== 'None' ? (float) $data['PriceToSalesRatioTTM'] : null,
            'eps_growth' => isset($data['QuarterlyEarningsGrowthYOY']) && $data['QuarterlyEarningsGrowthYOY'] !== 'None' ? (float) $data['QuarterlyEarningsGrowthYOY'] * 100 : null,
            'revenue_growth' => isset($data['QuarterlyRevenueGrowthYOY']) && $data['QuarterlyRevenueGrowthYOY'] !== 'None' ? (float) $data['QuarterlyRevenueGrowthYOY'] * 100 : null,
            'roe' => isset($data['ReturnOnEquityTTM']) && $data['ReturnOnEquityTTM'] !== 'None' ? (float) $data['ReturnOnEquityTTM'] * 100 : null,
            'profit_margin' => isset($data['ProfitMargin']) && $data['ProfitMargin'] !== 'None' ? (float) $data['ProfitMargin'] * 100 : null,
            'debt_to_equity' => isset($data['DebtToEquity']) && $data['DebtToEquity'] !== 'None' ? (float) $data['DebtToEquity'] / 100 : null,
            'dividend_yield' => isset($data['DividendYield']) && $data['DividendYield'] !== 'None' ? (float) $data['DividendYield'] * 100 : null,
        ];
    }
}
