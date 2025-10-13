<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Market Prediction Platform API is running',
            'timestamp' => now()->toIso8601String(),
            'version' => '1.0.0'
        ]);
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
        ]);
    }

    private function checkDatabase(): string
    {
        try {
            \DB::connection()->getPdo();
            return 'connected';
        } catch (\Exception $e) {
            return 'disconnected';
        }
    }

    private function checkCache(): string
    {
        try {
            \Cache::put('health_check', 'ok', 10);
            return \Cache::get('health_check') === 'ok' ? 'working' : 'not working';
        } catch (\Exception $e) {
            return 'not working';
        }
    }
}
