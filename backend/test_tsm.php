<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Services\StockService;
use Illuminate\Support\Facades\Log;

echo "=== Testing TSM Stock Creation ===" . PHP_EOL . PHP_EOL;

$stockService = app(StockService::class);

try {
    echo "Attempting to get or create TSM..." . PHP_EOL;
    $stock = $stockService->getOrCreateStock('TSM');
    
    if ($stock) {
        echo "✅ SUCCESS! Stock created/found:" . PHP_EOL;
        echo "  Symbol: {$stock->symbol}" . PHP_EOL;
        echo "  Name: {$stock->name}" . PHP_EOL;
        echo "  Exchange: {$stock->exchange}" . PHP_EOL;
        echo "  Industry: {$stock->industry}" . PHP_EOL;
        echo "  Sector: {$stock->sector}" . PHP_EOL;
        echo "  ID: {$stock->id}" . PHP_EOL;
        echo PHP_EOL;
        
        echo "Now testing quote fetch..." . PHP_EOL;
        $quote = $stockService->getQuote('TSM');
        
        if ($quote) {
            echo "✅ Quote fetched successfully:" . PHP_EOL;
            echo "  Current Price: \${$quote['current_price']}" . PHP_EOL;
            echo "  Change: {$quote['change']}" . PHP_EOL;
            echo "  Change %: {$quote['change_percent']}%" . PHP_EOL;
            echo "  Market Status: {$quote['market_status']}" . PHP_EOL;
        } else {
            echo "⚠️  Quote fetch failed (API might be unavailable)" . PHP_EOL;
        }
        
    } else {
        echo "❌ FAILED! Stock could not be created." . PHP_EOL;
        echo "   This usually means API keys are not configured or APIs are down." . PHP_EOL;
    }
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . PHP_EOL;
    echo "   " . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
}

echo PHP_EOL;
echo "=== Test Complete ===" . PHP_EOL;
