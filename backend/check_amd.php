<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Stock;
use App\Services\PredictionService;

$stock = Stock::where('symbol', 'AMD')->first();
if (!$stock) {
    echo "AMD not found\n";
    exit;
}

echo "AMD Recent Price Action:\n";
echo str_repeat('=', 60) . "\n";

$prices = \App\Models\StockPrice::where('stock_id', $stock->id)
    ->where('interval', '1day')
    ->orderBy('price_date', 'desc')
    ->limit(7)
    ->get();

foreach ($prices as $p) {
    $chg = $p->change_percent ?? 0;
    $sign = $chg >= 0 ? '+' : '';
    echo sprintf("%s: %.2f (%s%.2f%%)\n", 
        $p->price_date->format('Y-m-d'), 
        $p->close, 
        $sign, 
        $chg
    );
}

echo "\n\nAMD V6 Prediction Breakdown:\n";
echo str_repeat('=', 60) . "\n";

$svc = app(PredictionService::class);
$pred = $svc->getPredictionForHorizon($stock, 'today', 'v6');

echo "Label: " . ($pred['label'] ?? 'N/A') . "\n";
echo "Expected Move: " . sprintf("%+.2f%%", $pred['expected_pct_move'] ?? 0) . "\n";
echo "Probability: " . round(($pred['probability'] ?? 0) * 100, 1) . "%\n";

if (isset($pred['factors']['technical'])) {
    echo "\nTechnical Indicators:\n";
    $tech = $pred['factors']['technical'];
    echo "  RSI-14: " . ($tech['rsi_14'] ?? 'N/A') . "\n";
    echo "  MACD Hist: " . ($tech['macd_hist'] ?? 'N/A') . "\n";
    echo "  1-Day Change: " . sprintf("%+.2f%%", $tech['price_change_1d'] ?? 0) . "\n";
    echo "  3-Day Change: " . sprintf("%+.2f%%", $tech['price_change_3d'] ?? 0) . "\n";
    echo "  7-Day Change: " . sprintf("%+.2f%%", $tech['price_change_7d'] ?? 0) . "\n";
    echo "  BB %: " . number_format(($tech['bb_pct'] ?? 0) * 100, 1) . "%\n";
    echo "  Vol Ratio: " . number_format($tech['volume_sma_ratio'] ?? 1, 2) . "x\n";
    echo "  Score: " . sprintf("%+.4f", $tech['score'] ?? 0) . "\n";
}

echo "\nAll Factor Scores:\n";
foreach (['technical', 'fundamentals', 'sentiment', 'regional', 'liquidity', 'fear_index'] as $factor) {
    $score = $pred['scores'][$factor] ?? 0;
    $weight = ($pred['weights'][$factor] ?? 0) * 100;
    $contrib = $pred['contributions'][$factor] ?? 0;
    echo sprintf("  %-14s: Score %+.4f, Weight %2.0f%%, Contribution %+.4f\n", 
        ucfirst($factor), $score, $weight, $contrib);
}

echo "\nComposite Score: " . sprintf("%+.4f", $pred['contributions']['composite'] ?? 0) . "\n";
