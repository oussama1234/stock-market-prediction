<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MarketNewsRequest;
use App\Services\NewsService;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    protected NewsService $newsService;
    protected StockService $stockService;
    
    public function __construct(NewsService $newsService, StockService $stockService)
    {
        $this->newsService = $newsService;
        $this->stockService = $stockService;
    }
    
    /**
     * Get general market news
     * GET /api/news/market
     */
    public function market(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'limit' => 'integer|min:1|max:100',
            ]);
            
            $limit = $request->input('limit', 20);
            $articles = $this->newsService->getMarketNews($limit);
            
            return response()->json([
                'success' => true,
                'data' => $articles,
                'count' => count($articles),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching market news: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch market news at this time',
                'data' => [],
                'count' => 0,
            ], 200); // Return 200 with empty data instead of 500
        }
    }

    /**
     * Aggregated market news with filters and pagination
     * GET /api/news/market-advanced
     */
    public function marketAdvanced(MarketNewsRequest $request): JsonResponse
    {
        try {
            $q = $request->input('q');
            $from = $request->input('from');
            $to = $request->input('to');
            $limit = (int) $request->input('limit', 20);
            $page = (int) $request->input('page', 1);
            $offset = max(0, ($page - 1) * $limit);
            $importantFirst = (bool) $request->input('important_first', true);

            $result = $this->newsService->getAggregatedMarketNews($q, $from, $to, $limit, $offset, $importantFirst);

            return response()->json([
                'success' => true,
                'data' => $result['items'] ?? [],
                'count' => count($result['items'] ?? []),
                'total' => $result['total'] ?? 0,
                'page' => $page,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < ($result['total'] ?? 0),
                'query' => $q,
                'from' => $from,
                'to' => $to,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching advanced market news: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch news at this time',
                'data' => [],
                'count' => 0,
                'total' => 0,
                'page' => $request->input('page', 1),
                'limit' => $request->input('limit', 20),
                'has_more' => false,
            ], 200); // Return 200 with empty data instead of 500
        }
    }
    
    /**
     * Get news for specific stock
     * GET /api/news/stock/{symbol}
     */
    public function stock(string $symbol, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'limit' => 'integer|min:1|max:50',
            ]);
            
            $limit = $request->input('limit', 10);
            
            // Check if stock exists
            $stock = $this->stockService->getOrCreateStock($symbol);
            
            if (!$stock) {
                return response()->json([
                    'success' => false,
                    'message' => "Stock not found: {$symbol}",
                    'data' => [],
                    'count' => 0,
                ], 404);
            }
            
            $articles = $this->newsService->getStockNews($symbol, $limit);
            
            return response()->json([
                'success' => true,
                'data' => $articles,
                'count' => count($articles),
                'stock' => [
                    'symbol' => $stock->symbol,
                    'name' => $stock->name,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error("Error fetching stock news for {$symbol}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch stock news at this time',
                'data' => [],
                'count' => 0,
            ], 200); // Return 200 with empty data instead of 500
        }
    }
    
    /**
     * Get recent news from database
     * GET /api/news/recent
     */
    public function recent(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'integer|min:1|max:100',
        ]);
        
        $limit = $request->input('limit', 20);
        $articles = $this->newsService->getRecentMarketNews($limit);
        
        return response()->json([
            'success' => true,
            'data' => $articles,
            'count' => $articles->count(),
        ]);
    }
    
    /**
     * Search news articles
     * GET /api/news/search?q={query}
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
            'limit' => 'integer|min:1|max:100',
        ]);
        
        $query = $request->input('q');
        $limit = $request->input('limit', 20);
        
        $articles = $this->newsService->searchInDatabase($query, $limit);
        
        return response()->json([
            'success' => true,
            'data' => $articles,
            'count' => $articles->count(),
            'query' => $query,
        ]);
    }
    
    /**
     * Get news feed with sentiment (mixed market + stock news)
     * GET /api/news/feed
     */
    public function feed(Request $request): JsonResponse
    {
        $request->validate([
            'symbols' => 'array',
            'symbols.*' => 'string|max:10',
            'limit' => 'integer|min:1|max:100',
        ]);
        
        $limit = $request->input('limit', 30);
        $symbols = $request->input('symbols', []);
        
        // Get stock-specific news only (no market news)
        $stockNews = [];
        foreach ($symbols as $symbol) {
            try {
                $articles = $this->newsService->getStockNews($symbol, 10);
                $stockNews = array_merge($stockNews, $articles);
            } catch (\Exception $e) {
                \Log::warning("Failed to get news for {$symbol}: " . $e->getMessage());
            }
        }
        
        // Remove duplicates by URL
        $uniqueNews = [];
        $seenUrls = [];
        foreach ($stockNews as $article) {
            $url = $article['url'] ?? '';
            if (!in_array($url, $seenUrls) && $url) {
                $uniqueNews[] = $article;
                $seenUrls[] = $url;
            }
        }
        
        // Sort by published date (most recent first)
        usort($uniqueNews, function($a, $b) {
            $dateA = strtotime($a['published_at'] ?? '');
            $dateB = strtotime($b['published_at'] ?? '');
            return $dateB - $dateA;
        });
        
        // Limit results
        $allNews = array_slice($uniqueNews, 0, $limit);
        
        return response()->json([
            'success' => true,
            'data' => $allNews,
            'count' => count($allNews),
            'symbols' => $symbols,
        ]);
    }
    
    /**
     * Get news from all tracked stocks
     * GET /api/news/tracked-stocks
     */
    public function trackedStocks(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'integer|min:1|max:500', // Increased limit to 500 for "unlimited" loading
        ]);
        
        $limit = $request->input('limit', 9);
        
        // Get all stocks from database
        $stocks = \App\Models\Stock::orderBy('created_at', 'desc')->limit(20)->get(); // Increased to 20 stocks
        
        if ($stocks->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
                'count' => 0,
                'message' => 'No stocks tracked yet',
            ]);
        }
        
        // Calculate articles per stock based on requested limit
        // This ensures we get enough articles to fill the limit
        $articlesPerStock = max(10, ceil($limit / $stocks->count()));
        
        // Get news for each stock
        $allNews = [];
        foreach ($stocks as $stock) {
            try {
                $articles = $this->newsService->getStockNews($stock->symbol, $articlesPerStock);
                // Add stock info to each article
                foreach ($articles as &$article) {
                    $article['stock_symbol'] = $stock->symbol;
                    $article['stock_name'] = $stock->name;
                }
                $allNews = array_merge($allNews, $articles);
            } catch (\Exception $e) {
                \Log::warning("Failed to get news for {$stock->symbol}: " . $e->getMessage());
            }
        }
        
        // Remove duplicates
        $uniqueNews = [];
        $seenUrls = [];
        foreach ($allNews as $article) {
            $url = $article['url'] ?? '';
            if (!in_array($url, $seenUrls) && $url) {
                $uniqueNews[] = $article;
                $seenUrls[] = $url;
            }
        }
        
        // Sort by date
        usort($uniqueNews, function($a, $b) {
            return strtotime($b['published_at'] ?? '') - strtotime($a['published_at'] ?? '');
        });
        
        // Limit to requested count
        $news = array_slice($uniqueNews, 0, $limit);
        
        return response()->json([
            'success' => true,
            'data' => $news,
            'count' => count($news),
            'stocks_checked' => $stocks->pluck('symbol')->toArray(),
        ]);
    }
}
