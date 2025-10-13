<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Stock;
use App\Models\NewsArticle;

$avgo = Stock::where('symbol', 'AVGO')->first();

if (!$avgo) {
    echo "AVGO stock not found\n";
    exit(1);
}

echo "Found AVGO stock (ID: {$avgo->id})\n";

// Add important news for TODAY
$news = NewsArticle::updateOrCreate(
    ['url' => 'https://test.com/avgo-important-test-' . date('Ymd')],
    [
        'stock_id' => $avgo->id,
        'title' => 'CRITICAL: Broadcom Stock Surges on Major OpenAI Chip Partnership',
        'description' => 'Broadcom announces groundbreaking deal with OpenAI expected to boost stock significantly.',
        'source' => 'TestNews',
        'published_at' => now(),
        'sentiment_score' => 0.9,
        'is_important' => true,
        'importance_date' => now()->toDateString(),
        'expected_surge_percent' => 10.0,
        'surge_keywords' => json_encode(['openai', 'chip partnership', 'stock surges']),
    ]
);

echo "✅ Created important news:\n";
echo "   Title: {$news->title}\n";
echo "   Important: " . ($news->is_important ? 'YES' : 'NO') . "\n";
echo "   Date: {$news->importance_date}\n";
echo "   Expected Surge: {$news->expected_surge_percent}%\n";
echo "\n";
echo "Today's date: " . now()->toDateString() . "\n";
echo "\n";
echo "Testing query...\n";

$test = $avgo->newsArticles()
    ->where('is_important', true)
    ->whereDate('importance_date', now()->toDateString())
    ->where('expected_surge_percent', '>=', 6.0)
    ->first();

if ($test) {
    echo "✅ Query works! Found: {$test->title}\n";
} else {
    echo "❌ Query returned null\n";
}
