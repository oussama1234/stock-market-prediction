<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Services\StockDetailsService;
use App\Models\Stock;

echo "=== Regenerating TSM Prediction ===" . PHP_EOL . PHP_EOL;

$detailsService = app(StockDetailsService::class);

// Get stock
$stock = Stock::where('symbol', 'TSM')->first();

if (!$stock) {
    echo "❌ TSM not found" . PHP_EOL;
    exit(1);
}

echo "Regenerating prediction for TSM..." . PHP_EOL;

try {
    // This will fetch fresh data and regenerate prediction
    $result = $detailsService->regenerateToday('TSM', 'today');
    
    echo "✅ Prediction regenerated!" . PHP_EOL . PHP_EOL;
    
    if (isset($result['prediction'])) {
        $pred = $result['prediction'];
        echo "New Prediction:" . PHP_EOL;
        echo "  Direction: " . ($pred['direction'] ?? 'unknown') . PHP_EOL;
        echo "  Label: " . ($pred['label'] ?? 'unknown') . PHP_EOL;
        echo "  Confidence: " . (($pred['probability'] ?? 0) * 100) . "%" . PHP_EOL;
        echo "  Current Price: $" . ($pred['current_price'] ?? 0) . PHP_EOL;
        echo "  Predicted Price: $" . ($pred['predicted_price'] ?? 0) . PHP_EOL;
        echo "  Expected Change: " . ($pred['predicted_change_percent'] ?? 0) . "%" . PHP_EOL;
        echo PHP_EOL;
    }
    
    if (isset($result['override'])) {
        $override = $result['override'];
        echo "News Override:" . PHP_EOL;
        echo "  Applied: " . ($override['applied'] ? 'YES' : 'NO') . PHP_EOL;
        echo "  Sentiment: " . ($override['sentiment'] ?? 'none') . PHP_EOL;
        echo "  Score: " . ($override['score'] ?? 0) . PHP_EOL;
        echo "  Keywords: " . implode(', ', $override['trigger_keywords'] ?? []) . PHP_EOL;
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}

echo PHP_EOL;
echo "=== Complete ===" . PHP_EOL;
