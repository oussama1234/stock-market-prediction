<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Stock;

$symbols = ['AVGO', 'MSFT', 'NVDA', 'MA', 'V', 'AAPL'];

echo "=== STOCK CATEGORY ASSIGNMENTS ===\n\n";

foreach ($symbols as $symbol) {
    $stock = Stock::with('category')->where('symbol', $symbol)->first();
    
    if (!$stock) {
        echo "{$symbol}: NOT FOUND\n\n";
        continue;
    }
    
    echo "{$symbol}:\n";
    
    if ($stock->category) {
        echo "  Category: {$stock->category->name}\n";
        echo "  Volatility Multiplier: {$stock->category->volatility_multiplier}\n";
        echo "  Range: {$stock->category->typical_daily_range_min}% to {$stock->category->typical_daily_range_max}%\n";
        echo "  High Momentum: " . ($stock->category->high_momentum ? 'Yes' : 'No') . "\n";
    } else {
        echo "  ❌ NO CATEGORY ASSIGNED!\n";
    }
    
    echo "\n";
}
