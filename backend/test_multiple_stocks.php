<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Stock;
use App\Services\PredictionService;

$symbols = ['AVGO', 'MSFT', 'NVDA', 'MA', 'V', 'AAPL'];

echo "=== TESTING MULTIPLE STOCKS ===\n\n";

$predictionService = app(PredictionService::class);

foreach ($symbols as $symbol) {
    try {
        $stock = Stock::where('symbol', $symbol)->firstOrFail();
        $stock->load('category');
        
        $result = $predictionService->getPredictionForHorizon($stock, 'today', 'v6');
        
        $categoryName = $stock->category ? $stock->category->name : 'NONE';
        $multiplier = $stock->category ? $stock->category->volatility_multiplier : 'N/A';
        
        echo "{$symbol}:\n";
        echo "  Category: {$categoryName} (×{$multiplier})\n";
        echo "  Label: {$result['label']}\n";
        echo "  Expected Move: {$result['expected_pct_move']}%\n";
        echo "  Composite Score: " . round($result['final_score'], 4) . "\n";
        echo "  Price: \${$result['current_price']}\n";
        echo "\n";
        
    } catch (\Exception $e) {
        echo "{$symbol}: ERROR - {$e->getMessage()}\n\n";
    }
}
