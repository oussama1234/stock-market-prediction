<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Stock;
use App\Models\StockPrice;
use App\Services\StockService;

echo "=== Fetching Historical Price Data ===\n\n";

$stockService = app(StockService::class);
$stocks = Stock::limit(10)->get();

echo "Found {$stocks->count()} stocks\n\n";

foreach ($stocks as $stock) {
    echo "Processing {$stock->symbol} ({$stock->name})...\n";
    
    try {
        // Get current quote
        $quote = $stockService->getQuote($stock->symbol);
        
        if (!$quote) {
            echo "  ✗ Could not fetch quote\n\n";
            continue;
        }
        
        echo "  Current price: \${$quote['current_price']}\n";
        
        // Store current price
        $priceData = $stockService->storePriceData($stock, $quote);
        
        if ($priceData) {
            echo "  ✓ Stored current price\n";
        }
        
        // Generate some historical data by storing prices from last 30 days
        // In a real scenario, you'd fetch from historical API
        // For now, we'll create simulated data with realistic variations
        
        $currentPrice = $quote['current_price'];
        $daysBack = 50;
        
        echo "  Generating {$daysBack} days of historical data...\n";
        
        for ($i = $daysBack; $i > 0; $i--) {
            $date = now()->subDays($i);
            
            // Skip if already exists
            $exists = StockPrice::where('stock_id', $stock->id)
                ->where('interval', '1day')
                ->whereDate('price_date', $date->toDateString())
                ->exists();
            
            if ($exists) {
                continue;
            }
            
            // Simulate realistic price movement (-2% to +2% daily change)
            $changePercent = (rand(-200, 200) / 100); // -2% to +2%
            $dayPrice = $currentPrice * (1 + ($changePercent * ($i / $daysBack))); // Trend back
            
            $open = $dayPrice * (1 + (rand(-50, 50) / 10000)); // Small variation
            $high = max($open, $dayPrice) * (1 + (rand(0, 100) / 10000)); // Higher than open/close
            $low = min($open, $dayPrice) * (1 - (rand(0, 100) / 10000)); // Lower than open/close
            $close = $dayPrice;
            
            $volume = rand(30000000, 80000000); // Random volume
            
            StockPrice::create([
                'stock_id' => $stock->id,
                'price_date' => $date,
                'interval' => '1day',
                'open' => round($open, 2),
                'high' => round($high, 2),
                'low' => round($low, 2),
                'close' => round($close, 2),
                'previous_close' => round($close * 0.99, 2),
                'change' => round($close - ($close * 0.99), 2),
                'change_percent' => $changePercent,
                'volume' => $volume,
                'source' => 'simulated',
            ]);
        }
        
        echo "  ✓ Generated historical data\n";
        
        // Check count
        $priceCount = StockPrice::where('stock_id', $stock->id)
            ->where('interval', '1day')
            ->count();
        
        echo "  Total prices in DB: {$priceCount}\n";
        
    } catch (\Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "=== Complete ===\n";
echo "\nNow run: docker exec market-prediction-php-fpm php regenerate_predictions.php\n";
