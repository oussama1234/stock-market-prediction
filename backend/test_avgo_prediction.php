<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Stock;
use App\Services\PredictionService;

$symbol = 'AVGO';

echo "=== TESTING {$symbol} PREDICTION ===\n\n";

try {
    $stock = Stock::where('symbol', $symbol)->firstOrFail();
    $stock->load('category');
    
    if ($stock->category) {
        echo "Category: {$stock->category->name}\n";
        echo "Volatility Multiplier: {$stock->category->volatility_multiplier}\n";
        echo "Range: {$stock->category->typical_daily_range_min}% to {$stock->category->typical_daily_range_max}%\n\n";
    } else {
        echo "❌ NO CATEGORY!\n\n";
    }
    
    $predictionService = app(PredictionService::class);
    
    echo "Generating prediction...\n\n";
    $result = $predictionService->getPredictionForHorizon($stock, 'today', 'v6');
    
    echo "=== PREDICTION RESULT ===\n";
    echo "Model Version: {$result['model_version']}\n";
    echo "Label: {$result['label']}\n";
    echo "Probability: " . round($result['probability'] * 100, 1) . "%\n";
    echo "Expected Move: {$result['expected_pct_move']}%\n";
    echo "Current Price: \${$result['current_price']}\n";
    echo "Final Score (Composite): {$result['final_score']}\n\n";
    
    echo "=== FACTOR SCORES ===\n";
    foreach ($result['scores'] as $factor => $score) {
        printf("%-15s: %+.4f\n", ucfirst($factor), $score);
    }
    
    echo "\n=== CONTRIBUTIONS ===\n";
    foreach ($result['contributions'] as $factor => $contrib) {
        printf("%-15s: %+.4f\n", ucfirst($factor), $contrib);
    }
    
    if (isset($result['is_fallback']) && $result['is_fallback']) {
        echo "\n❌ WARNING: Using fallback model!\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
