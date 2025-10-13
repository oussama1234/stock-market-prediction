<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Models\MarketScenario;
use App\Services\ScenarioGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ScenarioController extends Controller
{
    protected ScenarioGeneratorService $scenarioService;

    public function __construct(ScenarioGeneratorService $scenarioService)
    {
        $this->scenarioService = $scenarioService;
    }

    /**
     * Get scenarios for a specific stock and timeframe
     * GET /api/scenarios/{symbol}?timeframe=today
     */
    public function index(string $symbol, Request $request): JsonResponse
    {
        try {
            $timeframe = $request->query('timeframe', 'today');
            
            if (!in_array($timeframe, ['today', 'tomorrow'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid timeframe. Must be "today" or "tomorrow".',
                ], 400);
            }

            $stock = Stock::where('symbol', strtoupper($symbol))->first();

            if (!$stock) {
                return response()->json([
                    'success' => false,
                    'message' => "Stock {$symbol} not found.",
                ], 404);
            }

            // Use cached scenarios for performance
            $scenarios = MarketScenario::getCachedScenariosForStock($stock->id, $timeframe);

            // For "today" scenarios, check if stale UNLESS winner is already determined
            // For "tomorrow" scenarios, NEVER regenerate here - only through Analytics
            $shouldRegenerate = false;
            
            if ($timeframe === 'today' && $scenarios->isNotEmpty()) {
                // If winner is determined, scenarios are final - don't regenerate
                $winnerDetermined = $scenarios->contains('is_winner', true);
                
                if (!$winnerDetermined) {
                    $lastUpdated = $scenarios->first()->created_at;
                    // Force regenerate if data is older than 30 seconds during trading
                    if ($lastUpdated->diffInSeconds(now()) > 30) {
                        $shouldRegenerate = true;
                        Log::info("Regenerating scenarios for {$symbol} - data is " . $lastUpdated->diffInSeconds(now()) . " seconds old");
                    }
                } else {
                    Log::info("Not regenerating scenarios for {$symbol} - winner already determined (final results)");
                }
            }

            // If no scenarios found or they need regeneration, generate them
            // BUT: Never auto-generate tomorrow scenarios here - they need Analytics context
            if ($scenarios->isEmpty() || $shouldRegenerate) {
                if ($timeframe === 'tomorrow') {
                    // Tomorrow scenarios need to come from Analytics with predicted prices
                    Log::info("Tomorrow scenarios for {$symbol} not found - they should be generated through Analytics");
                } else {
                    Log::info("Generating fresh scenarios for {$symbol} ({$timeframe})" . 
                             ($shouldRegenerate ? " (stale data)" : " (no data)"));
                    $this->scenarioService->generateScenarios($stock, $timeframe);
                    $scenarios = MarketScenario::getCachedScenariosForStock($stock->id, $timeframe);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'stock' => [
                        'symbol' => $stock->symbol,
                        'name' => $stock->name,
                        'current_price' => $stock->latestPrice?->close,
                    ],
                    'timeframe' => $timeframe,
                    'scenarios' => $scenarios->map(fn($s) => $this->formatScenario($s)),
                    'generated_at' => $scenarios->first()?->created_at,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching scenarios for {$symbol}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch scenarios.',
            ], 500);
        }
    }

    /**
     * Generate new scenarios for a stock
     * POST /api/scenarios/{symbol}/generate
     */
    public function generate(string $symbol, Request $request): JsonResponse
    {
        try {
            $timeframe = $request->input('timeframe', 'today');
            
            if (!in_array($timeframe, ['today', 'tomorrow'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid timeframe.',
                ], 400);
            }

            $stock = Stock::where('symbol', strtoupper($symbol))->first();

            if (!$stock) {
                return response()->json([
                    'success' => false,
                    'message' => "Stock {$symbol} not found.",
                ], 404);
            }
            
            // IMPORTANT: Tomorrow scenarios need predicted prices
            // If generating tomorrow scenarios, get predictions from AnalyticsService
            if ($timeframe === 'tomorrow') {
                // Check if predicted prices are provided, otherwise fetch from Analytics
                $predictedPrices = $request->input('predicted_prices');
                
                if (!$predictedPrices || !isset($predictedPrices['open'], $predictedPrices['current'])) {
                    // Fetch tomorrow's forecast from Analytics to get predicted prices
                    $analyticsService = app('App\Services\AnalyticsService');
                    $stockService = app('App\Services\StockService');
                    
                    $quote = $stockService->getQuote($stock->symbol);
                    $tomorrowForecast = $analyticsService->getTomorrowForecast($stock, $quote);
                    
                    $predictedPrices = [
                        'current' => $tomorrowForecast['predicted_price'] ?? $quote['current_price'],
                        'open' => $tomorrowForecast['predicted_open'] ?? $quote['current_price'],
                        'high' => $tomorrowForecast['predicted_high'] ?? $quote['high'],
                        'low' => $tomorrowForecast['predicted_low'] ?? $quote['low'],
                        'previous_close' => $quote['current_price'],
                    ];
                    
                    Log::info("Auto-fetched predicted prices for {$symbol} tomorrow: " . json_encode($predictedPrices));
                }
                
                // Generate with custom quote
                $customQuote = [
                    'current_price' => $predictedPrices['current'],
                    'open' => $predictedPrices['open'],
                    'high' => $predictedPrices['high'] ?? $predictedPrices['current'],
                    'low' => $predictedPrices['low'] ?? $predictedPrices['current'],
                    'previous_close' => $predictedPrices['previous_close'] ?? $stock->latestPrice?->close ?? $predictedPrices['current'],
                    'market_status' => 'pre_market',
                ];
                
                $scenarios = $this->scenarioService->generateScenariosWithQuote($stock, $timeframe, $customQuote);
                
                // Fetch the stored scenarios from database (generateScenariosWithQuote stores them)
                $dbScenarios = MarketScenario::getCachedScenariosForStock($stock->id, $timeframe);
                
                return response()->json([
                    'success' => true,
                    'message' => "Generated {$dbScenarios->count()} tomorrow scenarios for {$symbol}",
                    'data' => [
                        'scenarios' => $dbScenarios->map(fn($s) => $this->formatScenario($s)),
                    ],
                ]);
            }

            // Force regeneration even after market close when user explicitly requests it
            $this->scenarioService->generateScenarios($stock, $timeframe, $force = true);
            $dbScenarios = MarketScenario::getCachedScenariosForStock($stock->id, $timeframe);

            return response()->json([
                'success' => true,
                'message' => "Generated {$dbScenarios->count()} scenarios for {$symbol}",
                'data' => [
                    'scenarios' => $dbScenarios->map(fn($s) => $this->formatScenario($s)),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error("Error generating scenarios: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate scenarios.',
            ], 500);
        }
    }

    /**
     * Vote for a scenario
     * POST /api/scenarios/{id}/vote
     */
    public function vote(int $id): JsonResponse
    {
        try {
            $scenario = MarketScenario::find($id);
            if (!$scenario) {
                return response()->json(['success' => false, 'message' => 'Scenario not found.'], 404);
            }

            $scenario->increment('votes_count');
            Cache::forget("scenarios:stock:{$scenario->stock_id}:timeframe:{$scenario->timeframe}");

            return response()->json([
                'success' => true,
                'data' => ['scenario_id' => $scenario->id, 'votes_count' => $scenario->votes_count],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to vote.'], 500);
        }
    }

    /**
     * Bookmark a scenario
     * POST /api/scenarios/{id}/bookmark
     */
    public function bookmark(int $id): JsonResponse
    {
        try {
            $scenario = MarketScenario::find($id);
            if (!$scenario) {
                return response()->json(['success' => false, 'message' => 'Scenario not found.'], 404);
            }

            $scenario->increment('bookmarks_count');
            Cache::forget("scenarios:stock:{$scenario->stock_id}:timeframe:{$scenario->timeframe}");

            return response()->json([
                'success' => true,
                'data' => ['scenario_id' => $scenario->id, 'bookmarks_count' => $scenario->bookmarks_count],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to bookmark.'], 500);
        }
    }

    /**
     * Check if market is currently open (US market hours: 9:30 AM - 4:00 PM ET)
     */
    protected function isMarketHours(): bool
    {
        $now = now('America/New_York');
        $dayOfWeek = $now->dayOfWeek;
        
        // Weekend check
        if ($dayOfWeek === 0 || $dayOfWeek === 6) {
            return false;
        }
        
        // Market hours: 9:30 AM - 4:00 PM ET
        $marketOpen = $now->copy()->setTime(9, 30);
        $marketClose = $now->copy()->setTime(16, 0);
        
        return $now->between($marketOpen, $marketClose);
    }

    protected function formatScenario(MarketScenario $scenario): array
    {
        $formattedScenario = [
            'id' => $scenario->id,
            'scenario_type' => $scenario->scenario_type,
            'scenario_name' => $scenario->scenario_name,
            'description' => $scenario->description,
            'expected_change' => [
                'percent' => (float) $scenario->expected_change_percent,
                'min' => (float) $scenario->expected_change_min,
                'max' => (float) $scenario->expected_change_max,
            ],
            'target_price' => (float) $scenario->target_price,
            'current_price' => (float) $scenario->current_price,
            'open_price' => $scenario->open_price !== null ? (float) $scenario->open_price : null,
            'actual_close_price' => $scenario->actual_close_price !== null ? (float) $scenario->actual_close_price : null,
            'actual_change_percent' => $scenario->actual_change_percent !== null ? (float) $scenario->actual_change_percent : null,
            'is_winner' => (bool) $scenario->is_winner,
            'confidence_level' => $scenario->confidence_level,
            'confidence_label' => $scenario->getConfidenceLabel(),
            'trigger_indicators' => $scenario->trigger_indicators,
            'related_news' => $scenario->related_news ?? [],
            'suggested_action' => $scenario->suggested_action,
            'action_reasoning' => $scenario->action_reasoning,
            'votes_count' => $scenario->votes_count,
            'bookmarks_count' => $scenario->bookmarks_count,
            'color_class' => $scenario->getColorClass(),
            'timeframe' => $scenario->timeframe,
            'created_at' => $scenario->created_at->toIso8601String(),
        ];
        
        // Add AI-specific fields if this is an AI prediction
        if ($scenario->isAIPrediction()) {
            $formattedScenario['ai_confidence'] = $scenario->ai_confidence;
            $formattedScenario['ai_reasoning'] = $scenario->ai_reasoning;
            $formattedScenario['ai_final_score'] = $scenario->ai_final_score !== null ? (float) $scenario->ai_final_score : null;
            $formattedScenario['is_ai_prediction'] = true;
        } else {
            $formattedScenario['is_ai_prediction'] = false;
        }
        
        return $formattedScenario;
    }
}
