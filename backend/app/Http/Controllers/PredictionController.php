<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Services\PredictionService;
use App\Services\AsianMarketService;
use App\Services\EuropeanMarketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Prediction Controller
 * 
 * Handles stock prediction endpoints with quick_model_v4 integration
 * Supports predictions with European (50%), Asian (20%), and local (30%) market influence
 */
class PredictionController extends Controller
{
    protected $predictionService;
    protected $asianMarketService;
    protected $europeanMarketService;
    
    public function __construct(
        PredictionService $predictionService, 
        AsianMarketService $asianMarketService,
        EuropeanMarketService $europeanMarketService
    ) {
        $this->predictionService = $predictionService;
        $this->asianMarketService = $asianMarketService;
        $this->europeanMarketService = $europeanMarketService;
    }
    
    /**
     * Get prediction for a stock
     * 
     * GET /api/predict/{ticker}?horizon=today
     * 
     * @param Request $request
     * @param string $ticker
     * @return \Illuminate\Http\JsonResponse
     */
    public function predict(Request $request, string $ticker)
    {
        $request->validate([
            'horizon' => 'nullable|in:today,tomorrow,week,month',
        ]);
        
        $horizon = $request->get('horizon', 'today');
        
        try {
            $stock = Stock::where('symbol', strtoupper($ticker))->firstOrFail();
            
            // Check cache first
            $cacheKey = "prediction_{$horizon}_{$stock->symbol}";
            $cacheTTL = $horizon === 'today' ? 60 : 300; // 1 min for today, 5 min for others
            
            $prediction = Cache::remember($cacheKey, $cacheTTL, function () use ($stock, $horizon) {
                return $this->predictionService->getPredictionForHorizon($stock, $horizon);
            });
            
            return response()->json([
                'success' => true,
                'data' => $prediction,
                'meta' => [
                    'symbol' => $stock->symbol,
                    'name' => $stock->name,
                    'horizon' => $horizon,
                    'timestamp' => now()->toIso8601String(),
                    'cached' => Cache::has($cacheKey),
                ]
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Stock not found',
                'message' => "Stock symbol '{$ticker}' not found in database"
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('Prediction failed', [
                'ticker' => $ticker,
                'horizon' => $horizon,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Prediction failed',
                'message' => 'An error occurred while generating prediction'
            ], 500);
        }
    }
    
    /**
     * Regenerate today prediction
     * 
     * POST /api/predict/{ticker}/regenerate-today
     * 
     * @param Request $request
     * @param string $ticker
     * @return \Illuminate\Http\JsonResponse
     */
    public function regenerateToday(Request $request, string $ticker)
    {
        try {
            $stock = Stock::where('symbol', strtoupper($ticker))->firstOrFail();
            
            // Clear cache
            $cacheKey = "prediction_today_{$stock->symbol}";
            Cache::forget($cacheKey);
            
            // Clear market caches
            $this->asianMarketService->clearCache();
            $this->europeanMarketService->clearCache();
            
            // Generate new prediction
            $prediction = $this->predictionService->getPredictionForHorizon($stock, 'today');
            
            // Cache it
            Cache::put($cacheKey, $prediction, 60);
            
            return response()->json([
                'success' => true,
                'message' => 'Prediction regenerated successfully',
                'data' => $prediction,
                'meta' => [
                    'symbol' => $stock->symbol,
                    'timestamp' => now()->toIso8601String(),
                ]
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Stock not found',
                'message' => "Stock symbol '{$ticker}' not found in database"
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('Prediction regeneration failed', [
                'ticker' => $ticker,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Regeneration failed',
                'message' => 'An error occurred while regenerating prediction'
            ], 500);
        }
    }
    
    /**
     * Get prediction history for a stock
     * 
     * GET /api/predict/{ticker}/history
     * 
     * @param Request $request
     * @param string $ticker
     * @return \Illuminate\Http\JsonResponse
     */
    public function history(Request $request, string $ticker)
    {
        $request->validate([
            'days' => 'nullable|integer|min:1|max:90',
        ]);
        
        $days = $request->get('days', 7);
        
        try {
            $stock = Stock::where('symbol', strtoupper($ticker))->firstOrFail();
            
            // TODO: Implement prediction history retrieval from database
            // For now, return placeholder
            
            return response()->json([
                'success' => true,
                'data' => [
                    'symbol' => $stock->symbol,
                    'days' => $days,
                    'predictions' => [],
                    'message' => 'History feature coming soon'
                ]
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Stock not found'
            ], 404);
        }
    }
    
    /**
     * Get prediction accuracy stats
     * 
     * GET /api/predict/stats
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats()
    {
        // TODO: Implement accuracy statistics
        // Track predictions vs actual outcomes
        
        return response()->json([
            'success' => true,
            'data' => [
                'total_predictions' => 0,
                'accuracy' => 0,
                'message' => 'Stats feature coming soon'
            ]
        ]);
    }
    
    /**
     * Get prediction with body parameters (for frontend compatibility)
     * 
     * POST /api/predictions/predict
     * Body: { "symbol": "AAPL", "horizon": "today" }
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function predictWithBody(Request $request)
    {
        $validated = $request->validate([
            'symbol' => 'required|string|max:10',
            'horizon' => 'nullable|in:today,tomorrow,week,month',
        ]);
        
        $ticker = strtoupper($validated['symbol']);
        $horizon = $validated['horizon'] ?? 'today';
        
        // Delegate to predict method
        $request->merge(['horizon' => $horizon]);
        return $this->predict($request, $ticker);
    }
    
    /**
     * Get batch predictions with body parameters
     * 
     * POST /api/predictions/batch
     * Body: { "symbols": ["AAPL", "MSFT"], "horizon": "today" }
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchPredictWithBody(Request $request)
    {
        $validated = $request->validate([
            'symbols' => 'required|array|min:1|max:10',
            'symbols.*' => 'required|string|max:10',
            'horizon' => 'nullable|in:today,tomorrow,week,month',
        ]);
        
        $symbols = array_map('strtoupper', $validated['symbols']);
        $horizon = $validated['horizon'] ?? 'today';
        
        $results = [];
        $errors = [];
        
        foreach ($symbols as $symbol) {
            try {
                $stock = Stock::where('symbol', $symbol)->firstOrFail();
                
                $cacheKey = "prediction_{$horizon}_{$stock->symbol}";
                $cacheTTL = $horizon === 'today' ? 60 : 300;
                
                $prediction = Cache::remember($cacheKey, $cacheTTL, function () use ($stock, $horizon) {
                    return $this->predictionService->getPredictionForHorizon($stock, $horizon);
                });
                
                $results[] = [
                    'symbol' => $stock->symbol,
                    'name' => $stock->name,
                    'prediction' => $prediction,
                ];
                
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                $errors[] = [
                    'symbol' => $symbol,
                    'error' => 'Stock not found'
                ];
            } catch (\Exception $e) {
                Log::error('Batch prediction failed', [
                    'symbol' => $symbol,
                    'error' => $e->getMessage(),
                ]);
                
                $errors[] = [
                    'symbol' => $symbol,
                    'error' => 'Prediction failed'
                ];
            }
        }
        
        return response()->json([
            'success' => empty($errors),
            'data' => $results,
            'errors' => $errors,
            'meta' => [
                'total_requested' => count($symbols),
                'successful' => count($results),
                'failed' => count($errors),
                'horizon' => $horizon,
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }
}
