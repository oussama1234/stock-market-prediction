<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HealthController;

use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\WatchlistController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\PredictionController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\MarketController;
use App\Http\Controllers\Api\ScenarioController;
use App\Http\Controllers\Api\StockRegenerateController;
use App\Http\Controllers\Api\KeywordController;
use App\Http\Controllers\PredictionController as QuickModelPredictionController;
use App\Http\Controllers\AsianMarketController;
use App\Http\Controllers\EuropeanMarketController;

// Public routes
Route::get('/ping', [HealthController::class, 'ping']);
Route::get('/health', [HealthController::class, 'health']);

// Public stock routes (no auth required for browsing)
Route::prefix('stocks')->group(function () {
    Route::get('/search', [StockController::class, 'search']);
    Route::get('/popular', [StockController::class, 'popular']);
    Route::get('/{symbol}', [StockController::class, 'show']);
    Route::get('/{symbol}/quote', [StockController::class, 'quote']);
    Route::delete('/{symbol}', [StockController::class, 'destroy']);
    Route::post('/{symbol}/regenerate-today', [StockRegenerateController::class, 'regenerateToday']);
    
    // Analytics routes - New comprehensive analytics
    Route::get('/{symbol}/analytics', [AnalyticsController::class, 'index']);
    Route::post('/{symbol}/analytics/regenerate-today', [AnalyticsController::class, 'regenerateToday']);
});

// Public news routes
Route::prefix('news')->group(function () {
    Route::get('/market', [NewsController::class, 'market']);
    Route::get('/market-advanced', [NewsController::class, 'marketAdvanced']);
    Route::get('/feed', [NewsController::class, 'feed']);
    Route::get('/tracked-stocks', [NewsController::class, 'trackedStocks']);
    Route::get('/stock/{symbol}', [NewsController::class, 'stock']);
    Route::get('/recent', [NewsController::class, 'recent']);
    Route::get('/search', [NewsController::class, 'search']);
});

// Public prediction routes
Route::prefix('predictions')->group(function () {
    Route::get('/{symbol}', [PredictionController::class, 'show']);
    Route::get('/{symbol}/history', [PredictionController::class, 'history']);
    Route::post('/{symbol}/generate', [PredictionController::class, 'generate']);
});

// Public market data routes
Route::prefix('market')->group(function () {
    Route::get('/fear-greed-index', [MarketController::class, 'fearGreedIndex']);
    Route::get('/indices', [MarketController::class, 'indices']);
    Route::get('/sentiment', [MarketController::class, 'sentiment']);
});

// Public keywords route
Route::get('/keywords', [KeywordController::class, 'index']);

// Quick Model V2 Prediction routes (new)
Route::prefix('predict')->group(function () {
    Route::get('/{ticker}', [QuickModelPredictionController::class, 'predict'])
        ->where('ticker', '[A-Z]+');
    Route::post('/{ticker}/regenerate-today', [QuickModelPredictionController::class, 'regenerateToday'])
        ->where('ticker', '[A-Z]+')
        ->middleware('throttle:10,1');
    Route::get('/{ticker}/history', [QuickModelPredictionController::class, 'history'])
        ->where('ticker', '[A-Z]+');
    Route::get('/stats', [QuickModelPredictionController::class, 'stats']);
});

// New prediction API with body parameters (for frontend compatibility)
Route::prefix('predictions')->group(function () {
    Route::post('/predict', [QuickModelPredictionController::class, 'predictWithBody']);
    Route::post('/batch', [QuickModelPredictionController::class, 'batchPredictWithBody']);
});

// Asian Market routes
Route::prefix('asian-markets')->group(function () {
    Route::get('/', [AsianMarketController::class, 'index']);
    Route::get('/rolling', [AsianMarketController::class, 'rolling']);
    Route::get('/weights', [AsianMarketController::class, 'weights']);
    Route::post('/clear-cache', [AsianMarketController::class, 'clearCache'])
        ->middleware('throttle:10,1');
});

// European Market routes
Route::prefix('european-markets')->group(function () {
    Route::get('/', [EuropeanMarketController::class, 'index']);
    Route::get('/rolling', [EuropeanMarketController::class, 'rolling']);
    Route::get('/weights', [EuropeanMarketController::class, 'weights']);
    Route::post('/clear-cache', [EuropeanMarketController::class, 'clearCache'])
        ->middleware('throttle:10,1');
});

// Public scenario routes
Route::prefix('scenarios')->group(function () {
    Route::get('/{symbol}', [ScenarioController::class, 'index']);
    Route::post('/{symbol}/generate', [ScenarioController::class, 'generate']);
    Route::post('/{id}/vote', [ScenarioController::class, 'vote']);
    Route::post('/{id}/bookmark', [ScenarioController::class, 'bookmark']);
});

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // Watchlist routes
    Route::prefix('watchlist')->group(function () {
        Route::get('/', [WatchlistController::class, 'index']);
        Route::get('/favorites', [WatchlistController::class, 'favorites']);
        Route::post('/', [WatchlistController::class, 'store']);
        Route::put('/{id}', [WatchlistController::class, 'update']);
        Route::delete('/{id}', [WatchlistController::class, 'destroy']);
    });
});
