<?php

require __DIR__ . '/vendor/autoload.php';

use App\Models\Stock;
use App\Jobs\DetectReboundAndRegenerateJob;
use App\Jobs\ProcessAllStocksReboundDetectionJob;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=====================================\n";
echo "FINAL REBOUND DETECTION DISPATCH\n";
echo "With 0% Recovery Support\n";
echo "=====================================\n\n";

// Dispatch individual high-priority stocks first
$priorityStocks = ['META', 'NVDA', 'AAPL', 'GOOGL', 'TSLA'];

echo "🎯 Dispatching Priority Stocks:\n";
echo str_repeat('-', 50) . "\n";

foreach ($priorityStocks as $symbol) {
    $stock = Stock::where('symbol', $symbol)->first();
    if ($stock) {
        DetectReboundAndRegenerateJob::dispatch($stock);
        echo "  ✅ $symbol dispatched\n";
    }
}

echo "\n🚀 Dispatching Batch Job for All Stocks:\n";
echo str_repeat('-', 50) . "\n";

ProcessAllStocksReboundDetectionJob::dispatch();

echo "  ✅ Batch job dispatched\n\n";

echo "📊 Enhanced Detection Features:\n";
echo str_repeat('=', 50) . "\n";
echo "✅ Pattern 8: >$5 drop + ≥0% recovery (was >0.1%)\n";
echo "✅ Pattern 10: >$7 drop + 0-1% movement\n";
echo "✅ Stabilization: 0% counts as rebound signal\n";
echo "✅ Confidence: Up to 150% for large drops\n";
echo "✅ Weights: 3x multiplier on dollar drops\n\n";

echo "🎯 Expected Results:\n";
echo str_repeat('=', 50) . "\n";
echo "NVDA: ~101% confidence (was 0%)\n";
echo "META: ~150% confidence (was 0%) - MAXED OUT!\n";
echo "Other drops: Proportional to drop size\n\n";

echo "📝 Monitor:\n";
echo "docker logs market-prediction-queue-worker --tail 50 | grep -i rebound\n\n";

echo "✨ Done! Wait 1-2 minutes for queue processing.\n";
