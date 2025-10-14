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
     * 
     * Auto-updates stale data to ensure homepage always shows fresh values
     */
    public function indices(): JsonResponse
    {
        try {
            // Check if indices are stale and auto-update if needed
            $this->autoUpdateStaleIndices();
            
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
     * Auto-update indices if data is stale
     * 
     * During market hours (9:30 AM - 4:00 PM ET): Update if older than 5 minutes
     * After hours: Update if older than 30 minutes
     */
    protected function autoUpdateStaleIndices(): void
    {
        try {
            $indices = $this->marketIndexService->getAllIndices();
            
            // Check if any index exists and get the oldest update time
            $oldestUpdate = null;
            foreach ($indices as $index) {
                if (isset($index['last_updated'])) {
                    $updateTime = \Carbon\Carbon::parse($index['last_updated']);
                    if (!$oldestUpdate || $updateTime->lt($oldestUpdate)) {
                        $oldestUpdate = $updateTime;
                    }
                }
            }
            
            // If no indices or no update time, force update
            if (!$oldestUpdate) {
                \Log::info('No market indices found or no update time - forcing update');
                $this->marketIndexService->updateAllIndices();
                return;
            }
            
            // Determine staleness threshold based on market hours
            $now = \Carbon\Carbon::now('America/New_York');
            $marketOpen = $now->copy()->setTime(9, 30);
            $marketClose = $now->copy()->setTime(16, 0);
            
            $isMarketHours = $now->isWeekday() && $now->between($marketOpen, $marketClose);
            $staleThreshold = $isMarketHours ? 5 : 30; // 5 minutes during market hours, 30 after
            
            $minutesSinceUpdate = $oldestUpdate->diffInMinutes($now);
            
            if ($minutesSinceUpdate >= $staleThreshold) {
                \Log::info("Market indices stale (last update: {$minutesSinceUpdate} min ago) - auto-updating", [
                    'is_market_hours' => $isMarketHours,
                    'threshold' => $staleThreshold,
                    'oldest_update' => $oldestUpdate->toDateTimeString(),
                ]);
                $this->marketIndexService->updateAllIndices();
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to auto-update stale indices: ' . $e->getMessage());
            // Don't throw - let the request continue with potentially stale data
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
