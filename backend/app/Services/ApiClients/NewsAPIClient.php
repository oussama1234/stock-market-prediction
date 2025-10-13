<?php

namespace App\Services\ApiClients;

class NewsAPIClient extends BaseApiClient
{
    public function __construct()
    {
        $this->baseUrl = 'https://newsapi.org/v2';
        $this->apiKey = config('services.newsapi.key', env('NEWSAPI_KEY'));
        $this->rateLimit = config('services.newsapi.rate_limit', 100);
        $this->cacheTtl = 3600; // 1 hour
    }
    
    protected function getAuthParams(): array
    {
        return ['apiKey' => $this->apiKey];
    }
    
    /**
     * Search everything for a query (stock symbol or company name)
     */
    public function searchEverything(string $query, ?string $from = null, ?string $to = null, int $pageSize = 50, int $page = 1): array
    {
        $from = $from ?? now()->subDays(7)->format('Y-m-d');
        $to = $to ?? now()->format('Y-m-d');
        
        $data = $this->get('/everything', [
            'q' => $query,
            'from' => $from,
            'to' => $to,
            'sortBy' => 'publishedAt',
            'pageSize' => min($pageSize, 100),
            'language' => 'en',
            'page' => max(1, (int)$page),
        ]);
        
        if (!$data || !isset($data['articles'])) {
            return [];
        }
        
        return array_map(function($article) {
            return [
                'title' => $article['title'] ?? '',
                'description' => $article['description'] ?? '',
                'content' => $article['content'] ?? null,
                'url' => $article['url'] ?? '',
                'image_url' => $article['urlToImage'] ?? null,
                'source' => $article['source']['name'] ?? 'newsapi',
                'author' => $article['author'] ?? null,
                'published_at' => $article['publishedAt'] ?? null,
            ];
        }, $data['articles']);
    }
    
    /**
     * Get top headlines for business/finance
     */
    public function getTopHeadlines(string $category = 'business', string $country = 'us', int $pageSize = 100, int $page = 1): array
    {
        $data = $this->get('/top-headlines', [
            'category' => $category,
            'country' => $country,
            'pageSize' => min($pageSize, 100),
            'page' => max(1, (int)$page),
        ]);
        
        if (!$data || !isset($data['articles'])) {
            return [];
        }
        
        return array_map(function($article) use ($category) {
            return [
                'title' => $article['title'] ?? '',
                'description' => $article['description'] ?? '',
                'content' => $article['content'] ?? null,
                'url' => $article['url'] ?? '',
                'image_url' => $article['urlToImage'] ?? null,
                'source' => $article['source']['name'] ?? 'newsapi',
                'author' => $article['author'] ?? null,
                'published_at' => $article['publishedAt'] ?? null,
                'category' => $category,
            ];
        }, $data['articles']);
    }
    
    /**
     * Get news from specific sources
     */
    public function getFromSources(array $sources, int $pageSize = 100, int $page = 1): array
    {
        $data = $this->get('/top-headlines', [
            'sources' => implode(',', $sources),
            'pageSize' => min($pageSize, 100),
            'page' => max(1, (int)$page),
        ]);
        
        if (!$data || !isset($data['articles'])) {
            return [];
        }
        
        return array_map(function($article) {
            return [
                'title' => $article['title'] ?? '',
                'description' => $article['description'] ?? '',
                'content' => $article['content'] ?? null,
                'url' => $article['url'] ?? '',
                'image_url' => $article['urlToImage'] ?? null,
                'source' => $article['source']['name'] ?? 'newsapi',
                'author' => $article['author'] ?? null,
                'published_at' => $article['publishedAt'] ?? null,
            ];
        }, $data['articles']);
    }
    
    /**
     * Get general market news (alias for getTopHeadlines)
     */
    public function getMarketNews(int $pageSize = 20): array
    {
        return $this->getTopHeadlines('business', 'us', $pageSize);
    }
    
    /**
     * Search news (alias for searchEverything)
     */
    public function searchNews(string $query, int $pageSize = 10): array
    {
        return $this->searchEverything($query, null, null, $pageSize);
    }
}
