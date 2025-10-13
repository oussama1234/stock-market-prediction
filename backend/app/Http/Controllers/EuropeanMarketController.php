<?php

namespace App\Http\Controllers;

use App\Services\EuropeanMarketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * European Market Controller
 * 
 * Provides endpoints for European market data used in predictions
 */
class EuropeanMarketController extends Controller
{
    protected $europeanMarketService;
    
    public function __construct(EuropeanMarketService $europeanMarketService)
    {
        $this->europeanMarketService = $europeanMarketService;
    }
    
    /**
     * Get current European market data
     * 
     * GET /api/european-markets
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $changes = $this->europeanMarketService->getTodayChanges();
            $normalized = $this->europeanMarketService->normalizeForModel($changes);
            $sentiment = $this->europeanMarketService->getSentiment();
            
            return response()->json([
                'success' => true,
                'data' => $changes,
                'meta' => [
                    'european_avg_change' => $normalized['european_avg_change'],
                    'european_influence_score' => $normalized['european_influence_score'],
                    'european_impact_percent' => $normalized['european_impact_percent'],
                    'european_sentiment' => $normalized['european_sentiment'],
                    'valid_markets' => $normalized['valid_markets'],
                    'total_markets' => $normalized['total_markets'],
                    'sentiment_confidence' => $sentiment['confidence'],
                    'impact_weight' => 50,
                    'timestamp' => now()->toIso8601String(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch European market data', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch European market data',
                'message' => 'An error occurred while fetching market data'
            ], 500);
        }
    }
    
    /**
     * Get rolling changes for European markets
     * 
     * GET /api/european-markets/rolling
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function rolling(Request $request)
    {
        $request->validate([
            'days' => 'nullable|integer|min:1|max:30',
        ]);
        
        $days = $request->get('days', 7);
        
        try {
            $rolling = $this->europeanMarketService->getRollingChanges($days);
            
            return response()->json([
                'success' => true,
                'data' => $rolling,
                'meta' => [
                    'window_days' => $days,
                    'timestamp' => now()->toIso8601String(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch European rolling data', [
                'days' => $days,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch rolling data'
            ], 500);
        }
    }
    
    /**
     * Clear European market cache
     * 
     * POST /api/european-markets/clear-cache
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearCache()
    {
        try {
            $this->europeanMarketService->clearCache();
            
            return response()->json([
                'success' => true,
                'message' => 'European market cache cleared successfully',
                'timestamp' => now()->toIso8601String(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to clear European market cache', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to clear cache'
            ], 500);
        }
    }
    
    /**
     * Get market weights configuration
     * 
     * GET /api/european-markets/weights
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function weights()
    {
        try {
            $weights = $this->europeanMarketService->getMarketWeights();
            $markets = $this->europeanMarketService->getMarkets();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'weights' => $weights,
                    'markets' => array_map(function($market) {
                        return [
                            'symbol' => $market['symbol'],
                            'name' => $market['name'],
                            'country' => $market['country'],
                            'weight' => $market['weight'],
                            'timezone' => $market['timezone'],
                        ];
                    }, $markets),
                    'total_impact_weight' => 50,
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch weights'
            ], 500);
        }
    }
}
