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
            
            // Get horizon from request (default: today)
            $horizon = request()->get('horizon', 'today');
            
            // CRITICAL: Use Python quick_model_v4 for predictions via getPredictionForHorizon
            // This ensures we get sector-aware predictions with volatility multipliers
            $predictionData = $this->predictionService->getPredictionForHorizon($stock, $horizon);
            
            if (!$predictionData) {
                return response()->json([
                    'success' => false,
                    'message' => "Unable to generate prediction for {$symbol}",
                    'data' => null,
                ], 200);
            }
            
            // Store the Python model prediction in database for tracking
            $this->storePredictionFromPythonModel($stock, $predictionData, $horizon);
            
            // Get the stored prediction to return (with expected_pct_move accessor)
            $prediction = $this->predictionService->getActivePrediction($stock);
            
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
     * Store prediction from Python model
     */
    protected function storePredictionFromPythonModel($stock, array $pythonData, string $horizon): void
    {
        try {
            // Deactivate old predictions
            \App\Models\Prediction::where('stock_id', $stock->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
            
            // CRITICAL: Calculate predicted_price from PREVIOUS CLOSE, not current price
            // The prediction is: "From previous close, we expect X% move to reach target price"
            $previousClose = $pythonData['db_previous_close'] ?? $pythonData['api_previous_close'] ?? $pythonData['current_price'] ?? 0;
            $expectedMove = $pythonData['expected_pct_move'] ?? 0;
            $predictedPrice = $previousClose * (1 + $expectedMove / 100);
            
            // Create new prediction from Python model data
            \App\Models\Prediction::create([
                'stock_id' => $stock->id,
                'direction' => $pythonData['label'] === 'BULLISH' ? 'up' : 'down',
                'label' => $pythonData['label'] ?? 'NEUTRAL',
                'confidence_score' => (int) round(($pythonData['probability'] ?? 0.5) * 100),
                'probability' => $pythonData['probability'] ?? 0.5,
                'predicted_change_percent' => $expectedMove,
                'current_price' => $pythonData['current_price'] ?? 0,
                'predicted_price' => $predictedPrice,
                'reasoning' => implode('; ', $pythonData['top_reasons'] ?? []),
                'model_version' => $pythonData['model_version'] ?? 'quick_model_v4',
                'prediction_date' => now(),
                'target_date' => now()->endOfDay(),
                'horizon' => $horizon,
                'timeframe' => $horizon,
                'is_active' => true,
                'indicators_snapshot' => [
                    'base_score' => $pythonData['base_score'] ?? 0,
                    'final_score' => $pythonData['final_score'] ?? 0,
                    'european_influence' => $pythonData['european_influence_score'] ?? 0,
                    'asian_influence' => $pythonData['asian_influence_score'] ?? 0,
                    'previous_close' => $previousClose,
                    'db_previous_close' => $pythonData['db_previous_close'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to store Python model prediction: " . $e->getMessage());
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
