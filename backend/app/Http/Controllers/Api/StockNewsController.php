<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Services\NewsService;
use App\Jobs\FetchNewsArticlesJob;
use App\Jobs\AnalyzeNewsSentimentJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockNewsController extends Controller
{
    protected NewsService $newsService;
    
    public function __construct(NewsService $newsService)
    {
        $this->newsService = $newsService;
    }
    
    /**
     * Get today's news for a stock from database
     * GET /api/stocks/{symbol}/news/today
     */
    public function getTodayNews(string $symbol): JsonResponse
    {
        $symbol = strtoupper($symbol);
        $stock = Stock::where('symbol', $symbol)->first();
        
        if (!$stock) {
            return response()->json([
                'success' => false,
                'message' => "Stock not found: {$symbol}",
            ], 404);
        }
        
        // Get today's news articles from database
        $today = now()->startOfDay();
        $articles = $stock->newsArticles()
            ->where('published_at', '>=', $today)
            ->whereNotNull('sentiment_score')
            ->orderBy('published_at', 'desc')
            ->get();
        
        // Calculate aggregate sentiment
        $avgSentiment = $articles->avg('sentiment_score') ?? 0;
        $bullishCount = $articles->where('sentiment_score', '>', 0)->count();
        $bearishCount = $articles->where('sentiment_score', '<', 0)->count();
        
        return response()->json([
            'success' => true,
            'data' => [
                'articles' => $articles,
                'count' => $articles->count(),
                'avg_sentiment' => round($avgSentiment, 4),
                'bullish_count' => $bullishCount,
                'bearish_count' => $bearishCount,
                'neutral_count' => $articles->count() - $bullishCount - $bearishCount,
            ],
            'stock' => [
                'symbol' => $stock->symbol,
                'name' => $stock->name,
            ],
        ]);
    }
    
    /**
     * Fetch fresh news for a stock and store in database
     * POST /api/stocks/{symbol}/news/fetch
     */
    public function fetchNews(string $symbol): JsonResponse
    {
        $symbol = strtoupper($symbol);
        $stock = Stock::where('symbol', $symbol)->first();
        
        if (!$stock) {
            return response()->json([
                'success' => false,
                'message' => "Stock not found: {$symbol}",
            ], 404);
        }
        
        try {
            // Fetch news from APIs
            $news = $this->newsService->getStockNews($symbol, 20);
            
            // Store in database
            $stored = $this->newsService->bulkStoreForStock($stock, $news);
            
            // Dispatch sentiment analysis job
            if ($stored > 0) {
                AnalyzeNewsSentimentJob::dispatch($stock)->delay(now()->addSeconds(5));
            }
            
            return response()->json([
                'success' => true,
                'message' => "Fetched and stored {$stored} news articles for {$symbol}",
                'data' => [
                    'fetched' => count($news),
                    'stored' => $stored,
                ],
            ]);
            
        } catch (\Exception $e) {
            \Log::error("Failed to fetch news for {$symbol}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => "Failed to fetch news for {$symbol}",
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Get recent news with sentiment for stock details page
     * GET /api/stocks/{symbol}/news
     */
    public function getNews(string $symbol, Request $request): JsonResponse
    {
        $symbol = strtoupper($symbol);
        $stock = Stock::where('symbol', $symbol)->first();
        
        if (!$stock) {
            return response()->json([
                'success' => false,
                'message' => "Stock not found: {$symbol}",
            ], 404);
        }
        
        $request->validate([
            'limit' => 'integer|min:1|max:100',
            'days' => 'integer|min:1|max:30',
        ]);
        
        $limit = $request->input('limit', 20);
        $days = $request->input('days', 7);
        
        // Get recent news from database
        $articles = $stock->newsArticles()
            ->where('published_at', '>=', now()->subDays($days))
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $articles,
            'count' => $articles->count(),
            'stock' => [
                'symbol' => $stock->symbol,
                'name' => $stock->name,
            ],
        ]);
    }
}
