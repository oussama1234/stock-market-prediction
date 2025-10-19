<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Stock;
use App\Services\PredictionService;

// Test v6 prediction
$symbol = 'AAPL';

echo "Testing v6 prediction for {$symbol}...\n\n";

try {
    $stock = Stock::where('symbol', $symbol)->firstOrFail();
    $stock->load('category');
    
    $predictionService = app(PredictionService::class);
    
    echo "Calling getPredictionForHorizon with model='v6'...\n";
    $result = $predictionService->getPredictionForHorizon($stock, 'today', 'v6');
    
    echo "\n=== RESULT ===\n";
    echo "Model Version: " . ($result['model_version'] ?? 'MISSING') . "\n";
    echo "Label: " . ($result['label'] ?? 'MISSING') . "\n";
    echo "Probability: " . ($result['probability'] ?? 'MISSING') . "\n";
    echo "Expected Move: " . ($result['expected_pct_move'] ?? 'MISSING') . "%\n";
    echo "Current Price: $" . ($result['current_price'] ?? 'MISSING') . "\n";
    
    if (isset($result['is_fallback']) && $result['is_fallback']) {
        echo "\n❌ FALLBACK MODEL USED!\n";
    } else if (($result['model_version'] ?? '') === 'quick_model_v6') {
        echo "\n✅ V6 MODEL WORKING!\n";
    } else {
        echo "\n⚠️  UNEXPECTED MODEL: " . ($result['model_version'] ?? 'unknown') . "\n";
    }
    
    echo "\nFull Result:\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
