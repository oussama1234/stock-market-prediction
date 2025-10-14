<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Stock;

// Update NVIDIA volatility multiplier
$nvda = Stock::where('symbol', 'NVDA')->first();

if ($nvda) {
    echo "Current NVDA:\n";
    echo "  Symbol: {$nvda->symbol}\n";
    echo "  Name: {$nvda->name}\n";
    echo "  Volatility Multiplier: " . ($nvda->volatility_multiplier ?? 'NULL') . "\n";
    echo "  Category: " . ($nvda->category ?? 'NULL') . "\n";
    echo "\n";
    
    // NVIDIA is a mega-cap tech stock
    // Should have moderate volatility multiplier (1.5-1.8)
    // Not as high as TSLA (2.5) but higher than average (1.0)
    $nvda->update([
        'volatility_multiplier' => 1.6,  // Moderate-high volatility
        'category' => 'Technology - Semiconductors'
    ]);
    
    echo "Updated NVDA:\n";
    echo "  Volatility Multiplier: {$nvda->volatility_multiplier}\n";
    echo "  Category: {$nvda->category}\n";
    echo "\nSuccess! NVDA volatility multiplier set to 1.6\n";
    echo "This will produce more reasonable predictions (not too aggressive)\n";
} else {
    echo "NVDA stock not found in database!\n";
}
