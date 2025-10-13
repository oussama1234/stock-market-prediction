<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "=== Checking TSM Stock Issue ===" . PHP_EOL . PHP_EOL;

// Check if TSM or 2330.TW exists
$stocks = DB::table('stocks')
    ->where('symbol', 'LIKE', '%TSM%')
    ->orWhere('symbol', 'LIKE', '%2330%')
    ->get(['id', 'symbol', 'name', 'exchange']);

echo "Found " . count($stocks) . " related stocks:" . PHP_EOL;
foreach ($stocks as $stock) {
    echo "  ID: {$stock->id}, Symbol: {$stock->symbol}, Name: {$stock->name}, Exchange: {$stock->exchange}" . PHP_EOL;
}
echo PHP_EOL;

// The issue: TSM on US markets should map to TSM, not 2330.TW
// 2330.TW is the Taiwan listing
// TSM is the US ADR listing

// Solution: Create TSM as separate stock
try {
    $tsm = DB::table('stocks')->where('symbol', 'TSM')->first();
    
    if (!$tsm) {
        echo "Creating TSM stock (US ADR)..." . PHP_EOL;
        DB::table('stocks')->insert([
            'symbol' => 'TSM',
            'name' => 'Taiwan Semiconductor Manufacturing Company Limited',
            'exchange' => 'NYSE',
            'currency' => 'USD',
            'country' => 'US',
            'industry' => 'Semiconductors',
            'sector' => 'Technology',
            'description' => 'Taiwan Semiconductor Manufacturing Company Limited manufactures and sells integrated circuits and semiconductors.',
            'logo_url' => 'https://static2.finnhub.io/file/publicdatany/finnhubimage/stock_logo/TSM.png',
            'website' => 'https://www.tsmc.com/',
            'market_cap' => null,
            'shares_outstanding' => null,
            'last_fetched_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "✅ TSM stock created successfully!" . PHP_EOL;
    } else {
        echo "✅ TSM stock already exists (ID: {$tsm->id})" . PHP_EOL;
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;
echo "=== Check Complete ===" . PHP_EOL;
