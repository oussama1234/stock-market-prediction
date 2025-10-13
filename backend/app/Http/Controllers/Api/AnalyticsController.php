<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AnalyticsController
 * 
 * Handles all analytics endpoints for the new AnalyticsNew page
 * - GET /api/analytics/{symbol} - Get comprehensive analytics
 * - POST /api/analytics/{symbol}/regenerate-today - Regenerate today's prediction
 */
class AnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    /**
     * GET /api/analytics/{symbol}
     * Get comprehensive analytics for a stock (today only)
     */
    public function index(string $symbol): JsonResponse
    {
        try {
            $symbol = strtoupper($symbol);
            $analytics = $this->analyticsService->getAnalytics($symbol);
            
            if (!$analytics) {
                return response()->json([
                    'success' => false,
                    'message' => "Stock not found or data unavailable: {$symbol}",
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $analytics,
            ]);
        } catch (\Exception $e) {
            Log::error("Error fetching analytics for {$symbol}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch analytics at this time',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * POST /api/analytics/{symbol}/regenerate-today
     * Regenerate today's prediction and analytics
     * Respects cooldown period (30s default)
     */
    public function regenerateToday(string $symbol, Request $request): JsonResponse
    {
        $symbol = strtoupper($symbol);
        
        // Cooldown enforcement (30s default)
        $cooldownKey = "analytics_regen_cooldown:{$symbol}";
        $cooldownTtl = (int) config('services.predictions.regen_cooldown_seconds', 30);
        
        if (Cache::has($cooldownKey)) {
            $remainingSeconds = Cache::get($cooldownKey);
            return response()->json([
                'success' => false,
                'message' => 'Regeneration cooldown active. Please try again shortly.',
                'retry_after' => $remainingSeconds,
            ], 429);
        }

        try {
            // Set cooldown
            Cache::put($cooldownKey, $cooldownTtl, $cooldownTtl);
            
            // Regenerate
            $result = $this->analyticsService->regenerateToday($symbol);
            
            return response()->json([
                'success' => true,
                'data' => $result['data'] ?? null,
                'message' => 'Analytics regenerated successfully',
                'regenerated_at' => $result['regenerated_at'] ?? now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error("Error regenerating analytics for {$symbol}: " . $e->getMessage());
            
            // Clear cooldown on error so user can retry
            Cache::forget($cooldownKey);
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to regenerate analytics',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
