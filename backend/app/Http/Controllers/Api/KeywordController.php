<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class KeywordController extends Controller
{
    /**
     * Get all active priority keywords
     * GET /api/keywords
     */
    public function index(): JsonResponse
    {
        try {
            $keywords = DB::table('priority_keywords')
                ->where('active', true)
                ->select('keyword', 'sentiment', 'score')
                ->get();

            // Group by sentiment
            $grouped = [
                'bearish' => [],
                'bullish' => []
            ];

            foreach ($keywords as $kw) {
                $sentiment = $kw->sentiment ?? 'bearish';
                if (!isset($grouped[$sentiment])) {
                    $grouped[$sentiment] = [];
                }
                $grouped[$sentiment][$kw->keyword] = $kw->score;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'bearish' => $grouped['bearish'],
                    'bullish' => $grouped['bullish'],
                    'total' => count($keywords),
                    'bearish_count' => count($grouped['bearish']),
                    'bullish_count' => count($grouped['bullish']),
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching keywords: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch keywords',
                'data' => [
                    'bearish' => [],
                    'bullish' => [],
                ]
            ], 500);
        }
    }
}
