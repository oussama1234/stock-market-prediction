<?php

namespace App\Http\Controllers;

use App\Services\AsianMarketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Asian Market Controller
 * 
 * Provides endpoints for Asian market data used in predictions
 */
class AsianMarketController extends Controller
{
    protected $asianMarketService;
    
    public function __construct(AsianMarketService $asianMarketService)
    {
        $this->asianMarketService = $asianMarketService;
    }
    
    /**
     * Get current Asian market data
     * 
     * GET /api/asian-markets
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $changes = $this->asianMarketService->getTodayChanges();
            $normalized = $this->asianMarketService->normalizeForModel($changes);
            
            return response()->json([
                'success' => true,
                'data' => $changes,
                'meta' => [
                    'asian_avg_change' => $normalized['asian_avg_change'],
                    'asian_influence_score' => $normalized['asian_influence_score'],
                    'asian_impact_percent' => $normalized['asian_impact_percent'],
                    'valid_markets' => $normalized['valid_markets'],
                    'total_markets' => $normalized['total_markets'],
                    'timestamp' => now()->toIso8601String(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch Asian market data', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch Asian market data',
                'message' => 'An error occurred while fetching market data'
            ], 500);
        }
    }
    
    /**
     * Get rolling changes for Asian markets
     * 
     * GET /api/asian-markets/rolling
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
            $rolling = $this->asianMarketService->getRollingChanges($days);
            
            return response()->json([
                'success' => true,
                'data' => $rolling,
                'meta' => [
                    'window_days' => $days,
                    'timestamp' => now()->toIso8601String(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch rolling data', [
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
     * Clear Asian market cache
     * 
     * POST /api/asian-markets/clear-cache
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearCache()
    {
        try {
            $this->asianMarketService->clearCache();
            
            return response()->json([
                'success' => true,
                'message' => 'Asian market cache cleared successfully',
                'timestamp' => now()->toIso8601String(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to clear cache', [
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
     * GET /api/asian-markets/weights
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function weights()
    {
        try {
            $weights = $this->asianMarketService->getMarketWeights();
            $markets = $this->asianMarketService->getMarkets();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'weights' => $weights,
                    'markets' => array_map(function($market) {
                        return [
                            'symbol' => $market['symbol'],
                            'name' => $market['name'],
                            'weight' => $market['weight'],
                            'timezone' => $market['timezone'],
                        ];
                    }, $markets)
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
