<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Services\StockService;
use App\Services\EnhancedPredictionService;
use App\Models\Stock;

echo "=== TSM Prediction Analysis ===" . PHP_EOL . PHP_EOL;

$stockService = app(StockService::class);
$predictionService = app(EnhancedPredictionService::class);

// Get stock
$stock = Stock::where('symbol', 'TSM')->first();

if (!$stock) {
    echo "❌ TSM not found in database" . PHP_EOL;
    exit(1);
}

echo "Stock Info:" . PHP_EOL;
echo "  ID: {$stock->id}" . PHP_EOL;
echo "  Symbol: {$stock->symbol}" . PHP_EOL;
echo "  Name: {$stock->name}" . PHP_EOL;
echo PHP_EOL;

// Get current quote
echo "Fetching current quote..." . PHP_EOL;
$quote = $stockService->getQuote('TSM');

if ($quote) {
    echo "Current Quote:" . PHP_EOL;
    echo "  Current Price: \${$quote['current_price']}" . PHP_EOL;
    echo "  Previous Close: \${$quote['previous_close']}" . PHP_EOL;
    echo "  Change: \${$quote['change']}" . PHP_EOL;
    echo "  Change %: {$quote['change_percent']}%" . PHP_EOL;
    echo "  Market Status: {$quote['market_status']}" . PHP_EOL;
    echo PHP_EOL;
}

// Check historical prices
echo "Recent Price History:" . PHP_EOL;
$prices = DB::table('stock_prices')
    ->where('stock_id', $stock->id)
    ->where('interval', '1day')
    ->orderBy('price_date', 'desc')
    ->limit(10)
    ->get(['price_date', 'close', 'volume']);

if ($prices->count() > 0) {
    foreach ($prices as $price) {
        echo "  {$price->price_date}: \${$price->close} (Volume: " . number_format($price->volume) . ")" . PHP_EOL;
    }
} else {
    echo "  ⚠️  No historical prices found!" . PHP_EOL;
}
echo PHP_EOL;

// Check prediction
echo "Current Prediction:" . PHP_EOL;
$prediction = DB::table('predictions')
    ->where('stock_id', $stock->id)
    ->where('is_active', true)
    ->orderBy('created_at', 'desc')
    ->first();

if ($prediction) {
    echo "  Direction: {$prediction->direction}" . PHP_EOL;
    echo "  Confidence: {$prediction->confidence_score}%" . PHP_EOL;
    echo "  Current Price: \${$prediction->current_price}" . PHP_EOL;
    echo "  Predicted Price: \${$prediction->predicted_price}" . PHP_EOL;
    echo "  Change %: {$prediction->predicted_change_percent}%" . PHP_EOL;
    echo "  Model: {$prediction->model_version}" . PHP_EOL;
    echo "  Date: {$prediction->prediction_date}" . PHP_EOL;
    echo "  Reasoning: " . substr($prediction->reasoning, 0, 100) . "..." . PHP_EOL;
} else {
    echo "  ⚠️  No active prediction found!" . PHP_EOL;
}
echo PHP_EOL;

// Generate new prediction using Python model
echo "Generating fresh prediction..." . PHP_EOL;
try {
    $newPrediction = $predictionService->predict($stock, 'today');
    
    if ($newPrediction) {
        echo "Fresh Prediction:" . PHP_EOL;
        echo "  Direction: " . ($newPrediction['direction'] ?? 'unknown') . PHP_EOL;
        echo "  Label: " . ($newPrediction['label'] ?? 'unknown') . PHP_EOL;
        echo "  Confidence: " . (($newPrediction['probability'] ?? 0) * 100) . "%" . PHP_EOL;
        echo "  Predicted Change %: " . ($newPrediction['expected_pct_move'] ?? 0) . "%" . PHP_EOL;
        
        // Check if bearish when should be bullish
        $actualChange = $quote['change_percent'] ?? 0;
        $predictedChange = $newPrediction['expected_pct_move'] ?? 0;
        
        echo PHP_EOL;
        echo "Analysis:" . PHP_EOL;
        echo "  Actual Change: {$actualChange}%" . PHP_EOL;
        echo "  Predicted Change: {$predictedChange}%" . PHP_EOL;
        
        if (($actualChange > 0 && $predictedChange < 0) || ($actualChange < 0 && $predictedChange > 0)) {
            echo "  ⚠️  PREDICTION MISMATCH - Direction is wrong!" . PHP_EOL;
        } else {
            echo "  ✅ Direction matches actual movement" . PHP_EOL;
        }
    }
} catch (\Exception $e) {
    echo "❌ Error generating prediction: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;
echo "=== Analysis Complete ===" . PHP_EOL;
