<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Watchlist;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WatchlistController extends Controller
{
    protected StockService $stockService;
    
    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }
    
    /**
     * Get user's watchlist
     * GET /api/watchlist
     */
    public function index(Request $request): JsonResponse
    {
        $watchlist = Watchlist::where('user_id', $request->user()->id)
            ->with(['stock.latestPrice', 'stock.activePrediction'])
            ->ordered()
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $watchlist,
            'count' => $watchlist->count(),
        ]);
    }
    
    /**
     * Add stock to watchlist
     * POST /api/watchlist
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'symbol' => 'required|string|max:10',
            'notes' => 'nullable|string|max:500',
            'is_favorite' => 'boolean',
            'target_price' => 'nullable|numeric|min:0',
            'stop_loss' => 'nullable|numeric|min:0',
        ]);
        
        $symbol = strtoupper($request->symbol);
        
        // Get or create stock
        $stock = $this->stockService->getOrCreateStock($symbol);
        
        if (!$stock) {
            return response()->json([
                'success' => false,
                'message' => "Could not find stock: {$symbol}",
            ], 404);
        }
        
        // Check if already in watchlist
        $existing = Watchlist::where('user_id', $request->user()->id)
            ->where('stock_id', $stock->id)
            ->first();
        
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Stock is already in your watchlist',
            ], 409);
        }
        
        // Add to watchlist
        $watchlist = Watchlist::create([
            'user_id' => $request->user()->id,
            'stock_id' => $stock->id,
            'notes' => $request->notes,
            'is_favorite' => $request->is_favorite ?? false,
            'target_price' => $request->target_price,
            'stop_loss' => $request->stop_loss,
            'display_order' => Watchlist::where('user_id', $request->user()->id)->count(),
        ]);
        
        $watchlist->load(['stock.latestPrice', 'stock.activePrediction']);
        
        return response()->json([
            'success' => true,
            'message' => 'Stock added to watchlist',
            'data' => $watchlist,
        ], 201);
    }
    
    /**
     * Update watchlist item
     * PUT /api/watchlist/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:500',
            'is_favorite' => 'boolean',
            'target_price' => 'nullable|numeric|min:0',
            'stop_loss' => 'nullable|numeric|min:0',
            'display_order' => 'nullable|integer|min:0',
        ]);
        
        $watchlist = Watchlist::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();
        
        if (!$watchlist) {
            return response()->json([
                'success' => false,
                'message' => 'Watchlist item not found',
            ], 404);
        }
        
        $watchlist->update($request->only([
            'notes', 'is_favorite', 'target_price', 'stop_loss', 'display_order'
        ]));
        
        $watchlist->load(['stock.latestPrice', 'stock.activePrediction']);
        
        return response()->json([
            'success' => true,
            'message' => 'Watchlist item updated',
            'data' => $watchlist,
        ]);
    }
    
    /**
     * Remove from watchlist
     * DELETE /api/watchlist/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $watchlist = Watchlist::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();
        
        if (!$watchlist) {
            return response()->json([
                'success' => false,
                'message' => 'Watchlist item not found',
            ], 404);
        }
        
        $watchlist->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Stock removed from watchlist',
        ]);
    }
    
    /**
     * Get favorites only
     * GET /api/watchlist/favorites
     */
    public function favorites(Request $request): JsonResponse
    {
        $favorites = Watchlist::where('user_id', $request->user()->id)
            ->favorites()
            ->with(['stock.latestPrice', 'stock.activePrediction'])
            ->ordered()
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $favorites,
            'count' => $favorites->count(),
        ]);
    }
}
