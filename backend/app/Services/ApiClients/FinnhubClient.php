<?php

namespace App\Services\ApiClients;

class FinnhubClient extends BaseApiClient
{
    public function __construct()
    {
        $this->baseUrl = 'https://finnhub.io/api/v1';
        $this->apiKey = config('services.finnhub.key', env('FINNHUB_API_KEY'));
        $this->rateLimit = config('services.finnhub.rate_limit', 60);
        $this->cacheTtl = config('cache.quote_ttl', 900); // 15 minutes
    }
    
    protected function getAuthParams(): array
    {
        return ['token' => $this->apiKey];
    }
    
    /**
     * Get real-time quote for a symbol (includes extended hours)
     */
    public function getQuote(string $symbol): ?array
    {
        $data = $this->get('/quote', ['symbol' => strtoupper($symbol)]);
        
        if (!$data || !isset($data['c'])) {
            return null;
        }
        
        // Determine market status based on current time (EST)
        $now = new \DateTime('now', new \DateTimeZone('America/New_York'));
        $hour = (int) $now->format('H');
        $minute = (int) $now->format('i');
        $dayOfWeek = (int) $now->format('N'); // 1 = Monday, 7 = Sunday
        
        $marketStatus = 'closed';
        $isExtendedHours = false;
        
        // Weekend
        if ($dayOfWeek >= 6) {
            $marketStatus = 'closed';
        }
        // Regular hours: 9:30 AM - 4:00 PM EST
        elseif (($hour == 9 && $minute >= 30) || ($hour > 9 && $hour < 16)) {
            $marketStatus = 'open';
        }
        // Pre-market: 4:00 AM - 9:30 AM EST
        elseif ($hour >= 4 && ($hour < 9 || ($hour == 9 && $minute < 30))) {
            $marketStatus = 'pre_market';
            $isExtendedHours = true;
        }
        // After-hours: 4:00 PM - 8:00 PM EST
        elseif ($hour >= 16 && $hour < 20) {
            $marketStatus = 'after_hours';
            $isExtendedHours = true;
        }
        
        return [
            'symbol' => strtoupper($symbol),
            'current_price' => $data['c'] ?? null,
            'high' => $data['h'] ?? null,
            'low' => $data['l'] ?? null,
            'open' => $data['o'] ?? null,
            'previous_close' => $data['pc'] ?? null,
            'change' => ($data['c'] ?? 0) - ($data['pc'] ?? 0),
            'change_percent' => $data['dp'] ?? null,
            'timestamp' => $data['t'] ?? time(),
            'market_status' => $marketStatus,
            'is_extended_hours' => $isExtendedHours,
            'source' => 'finnhub',
        ];
    }
    
    /**
     * Get company profile
     */
    public function getCompanyProfile(string $symbol): ?array
    {
        $data = $this->get('/stock/profile2', ['symbol' => strtoupper($symbol)], 86400); // Cache 24h
        
        if (!$data || empty($data)) {
            return null;
        }
        
        return [
            'symbol' => $data['ticker'] ?? strtoupper($symbol),
            'name' => $data['name'] ?? null,
            'exchange' => $data['exchange'] ?? null,
            'currency' => $data['currency'] ?? 'USD',
            'country' => $data['country'] ?? null,
            'industry' => $data['finnhubIndustry'] ?? null,
            'logo_url' => $data['logo'] ?? null,
            'website' => $data['weburl'] ?? null,
            'market_cap' => $data['marketCapitalization'] ?? null,
            'shares_outstanding' => $data['shareOutstanding'] ?? null,
        ];
    }
    
    /**
     * Get company news
     */
    public function getCompanyNews(string $symbol, ?string $from = null, ?string $to = null): array
    {
        $from = $from ?? now()->subDays(7)->format('Y-m-d');
        $to = $to ?? now()->format('Y-m-d');
        
        $data = $this->get('/company-news', [
            'symbol' => strtoupper($symbol),
            'from' => $from,
            'to' => $to,
        ], 3600); // Cache 1 hour
        
        if (!$data || !is_array($data)) {
            return [];
        }
        
        return array_map(function($article) use ($symbol) {
            return [
                'title' => $article['headline'] ?? '',
                'description' => $article['summary'] ?? '',
                'url' => $article['url'] ?? '',
                'image_url' => $article['image'] ?? null,
                'source' => $article['source'] ?? 'finnhub',
                'published_at' => isset($article['datetime']) ? date('Y-m-d H:i:s', $article['datetime']) : null,
                'category' => $article['category'] ?? null,
                'stock_symbol' => strtoupper($symbol),
            ];
        }, array_slice($data, 0, 50)); // Limit to 50 articles
    }
    
    /**
     * Search for symbols
     */
    public function searchSymbol(string $query): array
    {
        $data = $this->get('/search', ['q' => $query], 3600);
        
        if (!$data || !isset($data['result'])) {
            return [];
        }
        
        return array_map(function($result) {
            return [
                'symbol' => $result['symbol'] ?? '',
                'name' => $result['description'] ?? '',
                'type' => $result['type'] ?? '',
                'exchange' => $result['displaySymbol'] ?? '',
            ];
        }, $data['result']);
    }
    
    /**
     * Get market news
     */
    public function getMarketNews(string $category = 'general'): array
    {
        $data = $this->get('/news', ['category' => $category], 1800); // Cache 30 min
        
        if (!$data || !is_array($data)) {
            return [];
        }
        
        return array_map(function($article) {
            return [
                'title' => $article['headline'] ?? '',
                'description' => $article['summary'] ?? '',
                'url' => $article['url'] ?? '',
                'image_url' => $article['image'] ?? null,
                'source' => $article['source'] ?? 'finnhub',
                'published_at' => isset($article['datetime']) ? date('Y-m-d H:i:s', $article['datetime']) : null,
                'category' => $article['category'] ?? null,
            ];
        }, array_slice($data, 0, 100));
    }
    
    /**
     * Get historical candle data (OHLCV)
     * 
     * @param string $symbol Stock symbol
     * @param string $resolution D = Daily, W = Weekly, M = Monthly, 1/5/15/30/60 = minutes
     * @param int $daysBack Number of days to fetch back from today
     * @return array Array of candles with [timestamp, open, high, low, close, volume]
     */
    public function getCandles(string $symbol, string $resolution = 'D', int $daysBack = 90): array
    {
        $to = time();
        $from = $to - ($daysBack * 86400); // 86400 seconds in a day
        
        $data = $this->get('/stock/candle', [
            'symbol' => strtoupper($symbol),
            'resolution' => $resolution,
            'from' => $from,
            'to' => $to,
        ], 3600); // Cache for 1 hour
        
        if (!$data || !isset($data['s']) || $data['s'] !== 'ok') {
            \Illuminate\Support\Facades\Log::warning("No candle data available for {$symbol}", ['data' => $data]);
            return [];
        }
        
        $candles = [];
        $count = count($data['t'] ?? []);
        
        for ($i = 0; $i < $count; $i++) {
            $candles[] = [
                'timestamp' => $data['t'][$i] ?? null,
                'date' => isset($data['t'][$i]) ? date('Y-m-d H:i:s', $data['t'][$i]) : null,
                'open' => $data['o'][$i] ?? null,
                'high' => $data['h'][$i] ?? null,
                'low' => $data['l'][$i] ?? null,
                'close' => $data['c'][$i] ?? null,
                'volume' => $data['v'][$i] ?? null,
            ];
        }
        
        return $candles;
    }
}
