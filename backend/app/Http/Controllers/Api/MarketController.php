<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FearGreedIndexService;
use App\Services\MarketIndexService;
use Illuminate\Http\JsonResponse;

class MarketController extends Controller
{
    protected FearGreedIndexService $fearGreedService;
    protected MarketIndexService $marketIndexService;
    
    public function __construct(
        FearGreedIndexService $fearGreedService,
        MarketIndexService $marketIndexService
    ) {
        $this->fearGreedService = $fearGreedService;
        $this->marketIndexService = $marketIndexService;
    }
    
    /**
     * Get Fear & Greed Index
     * GET /api/market/fear-greed-index
     */
    public function fearGreedIndex(): JsonResponse
    {
        try {
            $fearGreed = $this->fearGreedService->getFearGreedIndex();
            
            return response()->json([
                'success' => true,
                'data' => $fearGreed,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching fear & greed index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch fear & greed index at this time',
                'data' => null,
            ], 200);
        }
    }
    
    /**
     * Get Market Indices (S&P 500, NASDAQ, DOW)
     * GET /api/market/indices
     */
    public function indices(): JsonResponse
    {
        try {
            $indices = $this->marketIndexService->getAllIndices();
            
            return response()->json([
                'success' => true,
                'data' => $indices,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching market indices: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch market indices at this time',
                'data' => [],
            ], 200);
        }
    }
    
    /**
     * Get Market Sentiment from Indices
     * GET /api/market/sentiment
     */
    public function sentiment(): JsonResponse
    {
        try {
            $sentiment = $this->marketIndexService->getMarketSentiment();
            
            return response()->json([
                'success' => true,
                'data' => $sentiment,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching market sentiment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch market sentiment at this time',
                'data' => null,
            ], 200);
        }
    }
}
