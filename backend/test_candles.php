<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$finnhub = app(\App\Services\ApiClients\FinnhubClient::class);

echo "Testing Finnhub Candles API for AAPL...\n\n";

$candles = $finnhub->getCandles('AAPL', 'D', 30);

echo "Candle count: " . count($candles) . "\n";

if (count($candles) > 0) {
    echo "\nFirst 3 candles:\n";
    foreach (array_slice($candles, 0, 3) as $candle) {
        echo "  Date: {$candle['date']}, Close: {$candle['close']}, Volume: {$candle['volume']}\n";
    }
} else {
    echo "\nNo candles returned. Checking raw response...\n";
}
