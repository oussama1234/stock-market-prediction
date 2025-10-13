<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Keyword Statistics ===" . PHP_EOL;
echo "Total keywords: " . DB::table('priority_keywords')->count() . PHP_EOL;
echo "Bullish: " . DB::table('priority_keywords')->where('sentiment', 'bullish')->count() . PHP_EOL;
echo "Bearish: " . DB::table('priority_keywords')->where('sentiment', 'bearish')->count() . PHP_EOL;
echo PHP_EOL;

// Check some new crypto keywords
echo "=== Sample Crypto Keywords ===" . PHP_EOL;
$cryptoKeywords = DB::table('priority_keywords')
    ->whereIn('keyword', ['xrp jump', 'bitcoin jump', 'btc crash', 'crypto crash'])
    ->get(['keyword', 'sentiment', 'score']);

foreach ($cryptoKeywords as $kw) {
    echo sprintf("%s - %s (%+d)" . PHP_EOL, $kw->keyword, $kw->sentiment, $kw->score);
}
echo PHP_EOL;

// Check Q1-Q4 keywords
echo "=== Sample Q1-Q4 Keywords ===" . PHP_EOL;
$quarterlyKeywords = DB::table('priority_keywords')
    ->where('keyword', 'like', 'q%profit%')
    ->limit(5)
    ->get(['keyword', 'sentiment', 'score']);

foreach ($quarterlyKeywords as $kw) {
    echo sprintf("%s - %s (%+d)" . PHP_EOL, $kw->keyword, $kw->sentiment, $kw->score);
}
echo PHP_EOL;

echo "✅ Keywords seeded successfully!" . PHP_EOL;
