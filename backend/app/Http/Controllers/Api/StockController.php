<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    protected StockService $stockService;
    protected \App\Services\StockDetailsService $detailsService;
    
    public function __construct(StockService $stockService, \App\Services\StockDetailsService $detailsService)
    {
        $this->stockService = $stockService;
        $this->detailsService = $detailsService;
    }
    
    /**
     * Search for stocks
     * GET /api/stocks/search?q={query}
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:50',
        ]);
        
        $results = $this->stockService->search($request->q);
        
        return response()->json([
            'success' => true,
            'data' => $results,
            'count' => count($results),
        ]);
    }
    
    /**
     * Get stock details with current quote
     * GET /api/stocks/{symbol}
     */
    public function show(string $symbol): JsonResponse
    {
        try {
            $symbol = strtoupper($symbol);
            \Illuminate\Support\Facades\Log::info("Fetching stock details for {$symbol}");
            
            // First, ensure stock exists in database
            $stock = $this->stockService->getOrCreateStock($symbol);
            
            if (!$stock) {
                \Illuminate\Support\Facades\Log::warning("Stock creation failed: {$symbol}");
                return response()->json([
                    'success' => false,
                    'error' => 'Stock not found',
                    'message' => "Stock symbol '{$symbol}' not found in database",
                    'symbol' => $symbol,
                    'hint' => 'Please verify the stock symbol is correct. The stock may not be available in our system yet.',
                ], 404);
            }
            
            // Wait a moment for stock data to be available
            sleep(1);
            
            // Use StockDetailsService to orchestrate details for the new UI
            $data = $this->detailsService->getDetails($symbol);
            
            if (!$data) {
                \Illuminate\Support\Facades\Log::warning("Stock data not available: {$symbol}");
                
                // Return basic stock info if details failed
                return response()->json([
                    'success' => true,
                    'data' => [
                        'stock' => $stock->toArray(),
                        'quote' => [
                            'current_price' => 0,
                            'change' => 0,
                            'change_percent' => 0,
                            'market_status' => 'unknown',
                        ],
                        'news' => [],
                        'scenarios' => ['momentum' => [], 'volume_volatility' => []],
                        'prediction' => null,
                        'override' => null,
                    ],
                    'warning' => "Stock data for {$symbol} is being fetched. Some information may be incomplete.",
                ]);
            }
            
            \Illuminate\Support\Facades\Log::info("Successfully loaded stock: {$symbol}");
            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error fetching stock {$symbol}: " . $e->getMessage());
            \Illuminate\Support\Facades\Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => "An error occurred while loading {$symbol}",
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Get current quote for a stock
     * GET /api/stocks/{symbol}/quote
     */
    public function quote(string $symbol): JsonResponse
    {
        $quote = $this->stockService->getQuote($symbol);
        
        if (!$quote) {
            return response()->json([
                'success' => false,
                'message' => "Quote not available for: {$symbol}",
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $quote,
        ]);
    }
    
    /**
     * Get popular stocks with live quotes
     * GET /api/stocks/popular
     */
    public function popular(): JsonResponse
    {
        $stocks = \App\Models\Stock::popular()
            ->with(['latestPrice', 'activePrediction'])
            ->limit(20)
            ->get();
        
        // Enrich with live quotes (with error handling)
        $enrichedStocks = $stocks->map(function ($stock) {
            $stockArray = $stock->toArray();
            
            try {
                // Try to get live quote
                $quote = $this->stockService->getQuote($stock->symbol);
                $stockArray['quote'] = $quote;
            } catch (\Exception $e) {
                // If quote fetch fails, ensure we have latestPrice data
                \Illuminate\Support\Facades\Log::warning("Quote fetch failed for {$stock->symbol}: " . $e->getMessage());
                $stockArray['quote'] = null;
            }
            
            return $stockArray;
        });
        
        return response()->json([
            'success' => true,
            'data' => $enrichedStocks,
            'count' => $enrichedStocks->count(),
        ]);
    }
    
    /**
     * Delete a stock and all its related data
     * DELETE /api/stocks/{symbol}
     */
    public function destroy(string $symbol): JsonResponse
    {
        $symbol = strtoupper($symbol);
        $stock = \App\Models\Stock::where('symbol', $symbol)->first();
        
        if (!$stock) {
            return response()->json([
                'success' => false,
                'message' => "Stock not found: {$symbol}",
            ], 404);
        }
        
        try {
            \Illuminate\Support\Facades\DB::beginTransaction();
            
            // Delete all related data explicitly
            $deletedPrices = \App\Models\StockPrice::where('stock_id', $stock->id)->delete();
            $deletedPredictions = \App\Models\Prediction::where('stock_id', $stock->id)->delete();
            $deletedNews = \App\Models\NewsArticle::where('stock_id', $stock->id)->delete();
            
            // Delete from watchlists if exists
            if (\Illuminate\Support\Facades\Schema::hasTable('watchlists')) {
                \Illuminate\Support\Facades\DB::table('watchlists')
                    ->where('stock_id', $stock->id)
                    ->delete();
            }
            
            // Delete the stock itself
            $stock->delete();
            
            // Clear all cache related to this stock
            \Illuminate\Support\Facades\Cache::forget("stock_quote_{$symbol}");
            \Illuminate\Support\Facades\Cache::forget("stock_data_{$symbol}");
            \Illuminate\Support\Facades\Cache::forget("stock_analytics_{$symbol}");
            \Illuminate\Support\Facades\Cache::forget("prediction_{$symbol}");
            \Illuminate\Support\Facades\Cache::forget("stock_news_{$symbol}");
            
            // Clear popular stocks cache
            \Illuminate\Support\Facades\Cache::forget('popular_stocks');
            
            \Illuminate\Support\Facades\DB::commit();
            
            \Illuminate\Support\Facades\Log::info("Stock deleted: {$symbol}", [
                'prices_deleted' => $deletedPrices,
                'predictions_deleted' => $deletedPredictions,
                'news_deleted' => $deletedNews,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "Stock {$symbol} and all related data deleted successfully",
                'details' => [
                    'prices_deleted' => $deletedPrices,
                    'predictions_deleted' => $deletedPredictions,
                    'news_deleted' => $deletedNews,
                ]
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            \Illuminate\Support\Facades\Log::error("Failed to delete stock {$symbol}: " . $e->getMessage());
            \Illuminate\Support\Facades\Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => "Failed to delete stock: {$symbol}",
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
