<?php

require __DIR__ . '/vendor/autoload.php';

use App\Models\Stock;
use App\Jobs\DetectReboundAndRegenerateJob;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Dispatching rebound detection for NVDA...\n";

$stock = Stock::where('symbol', 'NVDA')->first();

if (!$stock) {
    die("❌ NVDA stock not found!\n");
}

DetectReboundAndRegenerateJob::dispatch($stock);

echo "✅ Job dispatched to queue!\n";
echo "📊 The queue worker will process it and:\n";
echo "   1. Detect the rebound pattern (Pattern 8 + early recovery)\n";
echo "   2. Calculate ~103% confidence\n";
echo "   3. Clear prediction cache\n";
echo "   4. Regenerate prediction with rebound signal\n\n";
echo "Check logs: docker logs market-prediction-queue-worker --tail 50\n";
