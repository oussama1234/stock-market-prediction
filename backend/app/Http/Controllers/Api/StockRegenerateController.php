<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StockDetailsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StockRegenerateController extends Controller
{
    public function __construct(
        protected StockDetailsService $detailsService
    ) {}

    /**
     * POST /api/stocks/{symbol}/regenerate-today
     * Auth required via sanctum in routes. Returns 202 if queued, or 200 with immediate result.
     */
    public function regenerateToday(string $symbol, Request $request): JsonResponse
    {
        $symbol = strtoupper($symbol);
        $horizon = $request->query('horizon', 'today');
        $async = filter_var($request->query('async', 'false'), FILTER_VALIDATE_BOOLEAN);

        // Cooldown enforcement (30s default)
        $cooldownKey = "regen_cooldown:{$symbol}";
        $cooldownTtl = (int) config('services.predictions.regen_cooldown_seconds', 30);
        if (Cache::has($cooldownKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Regeneration cooldown active. Please try again shortly.',
            ], 429);
        }

        // If async requested or heavy load, queue job and return 202
        if ($async) {
            \App\Jobs\RegenerateTodayPredictionJob::dispatch($symbol, $horizon)->onQueue('low');
            Cache::put($cooldownKey, true, $cooldownTtl);
            return response()->json([
                'success' => true,
                'message' => 'Regeneration queued',
            ], 202);
        }

        try {
            $result = $this->detailsService->regenerateToday($symbol, $horizon);
            Cache::put($cooldownKey, true, $cooldownTtl);
            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Regenerated successfully',
            ]);
        } catch (\Throwable $e) {
            Log::error("Regenerate failed for {$symbol}: " . $e->getMessage());
            // Fallback to queue
            \App\Jobs\RegenerateTodayPredictionJob::dispatch($symbol, $horizon)->onQueue('low');
            Cache::put($cooldownKey, true, $cooldownTtl);
            return response()->json([
                'success' => true,
                'message' => 'Regeneration queued',
            ], 202);
        }
    }
}
