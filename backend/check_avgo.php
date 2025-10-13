<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$stock = App\Models\Stock::where('symbol', 'AVGO')->first();

if (!$stock) {
    echo "AVGO stock not found in database!\n";
    exit(1);
}

echo "═══════════════════════════════════════════════\n";
echo "AVGO Stock Analysis\n";
echo "═══════════════════════════════════════════════\n\n";

echo "Stock ID: {$stock->id}\n";
echo "Symbol: {$stock->symbol}\n";
echo "Name: {$stock->name}\n";
echo "Created: {$stock->created_at}\n\n";

// Check stock prices
$prices = App\Models\StockPrice::where('stock_id', $stock->id)
    ->where('interval', '1day')
    ->orderBy('price_date', 'desc')
    ->limit(14)
    ->get();

echo "───────────────────────────────────────────────\n";
echo "Historical Price Data (1day interval)\n";
echo "───────────────────────────────────────────────\n";
echo "Total records: " . $prices->count() . "/14 needed\n\n";

if ($prices->isEmpty()) {
    echo "⚠️  NO PRICE DATA FOUND!\n";
    echo "This explains why volume_ratio = 1 and prediction is neutral.\n\n";
    
    echo "📊 To fix this, you need to:\n";
    echo "1. Run: php artisan fetch:historical-prices AVGO\n";
    echo "   OR\n";
    echo "2. Wait for the scheduled job to fetch prices\n";
    echo "   OR\n";
    echo "3. Make sure the FetchStockDataJob is running\n\n";
} else {
    echo "Price Records:\n";
    foreach ($prices as $p) {
        echo sprintf(
            "  %s: Close=$%.2f, Volume=%s\n",
            $p->price_date,
            $p->close,
            number_format($p->volume)
        );
    }
    
    $volumes = $prices->pluck('volume')->toArray();
    $avgVol = !empty($volumes) ? array_sum($volumes) / count($volumes) : 0;
    $lastVol = $prices->first()->volume ?? 0;
    $volRatio = $avgVol ? ($lastVol / $avgVol) : 1.0;
    
    echo "\n📈 Volume Analysis:\n";
    echo sprintf("  Average Volume: %s\n", number_format($avgVol));
    echo sprintf("  Last Volume: %s\n", number_format($lastVol));
    echo sprintf("  Volume Ratio: %.2f\n", $volRatio);
}

echo "\n───────────────────────────────────────────────\n";
echo "Current Prediction\n";
echo "───────────────────────────────────────────────\n";

$pred = App\Models\Prediction::where('stock_id', $stock->id)
    ->whereDate('prediction_date', now()->toDateString())
    ->where('is_active', true)
    ->latest()
    ->first();

if ($pred) {
    echo "Direction: {$pred->direction}\n";
    echo "Confidence: {$pred->confidence_score}%\n";
    echo "Predicted Price: \${$pred->predicted_price}\n";
    echo "Current Price: \${$pred->current_price}\n";
    echo "Model: {$pred->model_version}\n";
} else {
    echo "No active prediction for today\n";
}

echo "\n═══════════════════════════════════════════════\n";
