<?php

namespace App\Services\ApiClients;

class AlphaVantageClient extends BaseApiClient
{
    public function __construct()
    {
        $this->baseUrl = 'https://www.alphavantage.co/query';
        $this->apiKey = config('services.alphavantage.key', env('ALPHAVANTAGE_API_KEY', 'demo'));
        $this->rateLimit = config('services.alphavantage.rate_limit', 25);
        $this->cacheTtl = 1800; // 30 minutes - rate limit is very restrictive
    }
    
    protected function getAuthParams(): array
    {
        return ['apikey' => $this->apiKey];
    }
    
    /**
     * Get global quote (fallback option)
     */
    public function getGlobalQuote(string $symbol): ?array
    {
        $data = $this->get('', [
            'function' => 'GLOBAL_QUOTE',
            'symbol' => strtoupper($symbol),
        ]);
        
        if (!$data || !isset($data['Global Quote'])) {
            return null;
        }
        
        $quote = $data['Global Quote'];
        
        return [
            'symbol' => $quote['01. symbol'] ?? strtoupper($symbol),
            'open' => floatval($quote['02. open'] ?? 0),
            'high' => floatval($quote['03. high'] ?? 0),
            'low' => floatval($quote['04. low'] ?? 0),
            'current_price' => floatval($quote['05. price'] ?? 0),
            'volume' => intval($quote['06. volume'] ?? 0),
            'previous_close' => floatval($quote['08. previous close'] ?? 0),
            'change' => floatval($quote['09. change'] ?? 0),
            'change_percent' => floatval(str_replace('%', '', $quote['10. change percent'] ?? '0')),
            'source' => 'alphavantage',
        ];
    }
    
    /**
     * Get company overview
     */
    public function getCompanyOverview(string $symbol): ?array
    {
        $data = $this->get('', [
            'function' => 'OVERVIEW',
            'symbol' => strtoupper($symbol),
        ], 86400); // Cache 24h
        
        if (!$data || empty($data) || isset($data['Error Message'])) {
            return null;
        }
        
        return [
            'symbol' => $data['Symbol'] ?? strtoupper($symbol),
            'name' => $data['Name'] ?? null,
            'exchange' => $data['Exchange'] ?? null,
            'currency' => $data['Currency'] ?? 'USD',
            'country' => $data['Country'] ?? null,
            'sector' => $data['Sector'] ?? null,
            'industry' => $data['Industry'] ?? null,
            'description' => $data['Description'] ?? null,
            'market_cap' => $data['MarketCapitalization'] ?? null,
            'pe_ratio' => $data['PERatio'] ?? null,
            'dividend_yield' => $data['DividendYield'] ?? null,
        ];
    }
}
