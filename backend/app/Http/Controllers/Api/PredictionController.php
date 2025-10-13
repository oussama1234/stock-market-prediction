<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PredictionService;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PredictionController extends Controller
{
    protected PredictionService $predictionService;
    protected StockService $stockService;
    
    public function __construct(PredictionService $predictionService, StockService $stockService)
    {
        $this->predictionService = $predictionService;
        $this->stockService = $stockService;
    }
    
    /**
     * Get active prediction for a stock
     * GET /api/predictions/{symbol}
     */
    public function show(string $symbol): JsonResponse
    {
        try {
            $stock = $this->stockService->getOrCreateStock($symbol);
            
            if (!$stock) {
                return response()->json([
                    'success' => false,
                    'message' => "Stock not found: {$symbol}",
                ], 404);
            }
            
            $prediction = $this->predictionService->getActivePrediction($stock);
            
            if (!$prediction) {
                return response()->json([
                    'success' => false,
                    'message' => "No active prediction available for {$symbol}",
                    'data' => null,
                ], 200); // Return 200 with null data instead of 404
            }
            
            return response()->json([
                'success' => true,
                'data' => $prediction,
                'stock' => [
                    'symbol' => $stock->symbol,
                    'name' => $stock->name,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error("Error fetching prediction for {$symbol}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch prediction at this time',
                'data' => null,
            ], 200);
        }
    }
    
    /**
     * Generate new prediction for a stock (uses enhanced AI)
     * POST /api/predictions/{symbol}/generate
     */
    public function generate(string $symbol): JsonResponse
    {
        try {
            // Use StockService which already has enhanced prediction integrated
            $prediction = $this->stockService->regeneratePrediction($symbol);
            
            if (!$prediction) {
                return response()->json([
                    'success' => false,
                    'message' => "Unable to generate prediction for {$symbol}",
                ], 200);
            }
            
            return response()->json([
                'success' => true,
                'data' => $prediction,
                'message' => 'Enhanced AI prediction generated successfully',
            ], 201);
        } catch (\Exception $e) {
            \Log::error("Error generating prediction for {$symbol}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to generate prediction at this time',
            ], 200);
        }
    }
    
    /**
     * Get prediction history for a stock
     * GET /api/predictions/{symbol}/history
     */
    public function history(string $symbol, Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'integer|min:1|max:50',
        ]);
        
        $limit = $request->input('limit', 10);
        
        $stock = $this->stockService->getOrCreateStock($symbol);
        
        if (!$stock) {
            return response()->json([
                'success' => false,
                'message' => "Stock not found: {$symbol}",
            ], 404);
        }
        
        $history = $this->predictionService->getPredictionHistory($stock, $limit);
        
        return response()->json([
            'success' => true,
            'data' => $history,
            'count' => $history->count(),
            'stock' => [
                'symbol' => $stock->symbol,
                'name' => $stock->name,
            ],
        ]);
    }
}
