<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\EuropeanMarketService;
use App\Services\AsianMarketService;
use App\Services\PredictionService;
use App\Models\Stock;

echo "=== Testing European Market Influence ===\n\n";

// Test 1: Fetch European Market Data
echo "1. Fetching European Market Data...\n";
$europeanService = app(EuropeanMarketService::class);

try {
    $changes = $europeanService->getTodayChanges();
    echo "✓ Successfully fetched " . count($changes) . " European markets:\n";
    foreach ($changes as $key => $market) {
        $status = isset($market['error']) ? '✗ ERROR' : '✓';
        $change = $market['change_percent'];
        $color = $change > 0 ? '+' : '';
        echo "  $status {$market['name']}: {$color}{$change}%\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "✗ Error fetching European markets: " . $e->getMessage() . "\n\n";
}

// Test 2: Check Normalization
echo "2. Testing Normalization for Model...\n";
try {
    $normalized = $europeanService->normalizeForModel($changes);
    echo "✓ Normalized European Data:\n";
    echo "  - Average Change: " . round($normalized['european_avg_change'], 2) . "%\n";
    echo "  - Influence Score: " . round($normalized['european_influence_score'], 3) . "\n";
    echo "  - Impact Percent: " . round($normalized['european_impact_percent'] * 100, 1) . "%\n";
    echo "  - Sentiment: {$normalized['european_sentiment']}\n";
    echo "  - Valid Markets: {$normalized['valid_markets']}/{$normalized['total_markets']}\n";
    echo "\n";
    
    // Show individual market features
    echo "  Individual Market Features for Model:\n";
    echo "  - FTSE: " . ($normalized['ftse_change_pct'] ?? 0) . "%\n";
    echo "  - DAX: " . ($normalized['dax_change_pct'] ?? 0) . "%\n";
    echo "  - CAC: " . ($normalized['cac_change_pct'] ?? 0) . "%\n";
    echo "  - STOXX: " . ($normalized['stoxx_change_pct'] ?? 0) . "%\n";
    echo "  - IBEX: " . ($normalized['ibex_change_pct'] ?? 0) . "%\n";
    echo "\n";
} catch (Exception $e) {
    echo "✗ Error normalizing data: " . $e->getMessage() . "\n\n";
}

// Test 3: Compare Asian Markets
echo "3. Fetching Asian Market Data for comparison...\n";
$asianService = app(AsianMarketService::class);
try {
    $asianChanges = $asianService->getTodayChanges();
    $asianNormalized = $asianService->normalizeForModel($asianChanges);
    echo "✓ Asian Markets:\n";
    echo "  - Average Change: " . round($asianNormalized['asian_avg_change'], 2) . "%\n";
    echo "  - Influence Score: " . round($asianNormalized['asian_influence_score'], 3) . "\n";
    echo "  - Impact Percent: " . round($asianNormalized['asian_impact_percent'] * 100, 1) . "%\n";
    echo "\n";
} catch (Exception $e) {
    echo "✗ Error fetching Asian markets: " . $e->getMessage() . "\n\n";
}

// Test 4: Simulate Prediction with Market Influences
echo "4. Testing Market Influence Weighting...\n";
echo "   Market Weight Distribution:\n";
echo "   - European: 50%\n";
echo "   - Asian: 20%\n";
echo "   - Local US: 30%\n\n";

// Calculate weighted impact
$europeanWeight = 0.50;
$asianWeight = 0.20;
$localWeight = 0.30;

$europeanContribution = ($normalized['european_influence_score'] ?? 0) * $europeanWeight;
$asianContribution = ($asianNormalized['asian_influence_score'] ?? 0) * $asianWeight;

echo "   Contribution to Final Score:\n";
echo "   - European: " . round($europeanContribution, 3) . " (score: " . round($normalized['european_influence_score'] ?? 0, 3) . " × 50%)\n";
echo "   - Asian: " . round($asianContribution, 3) . " (score: " . round($asianNormalized['asian_influence_score'] ?? 0, 3) . " × 20%)\n";
echo "   - Local: To be calculated with sentiment & technicals (30%)\n\n";

$marketTotal = $europeanContribution + $asianContribution;
echo "   Market Total Impact: " . round($marketTotal, 3) . "\n";

if ($normalized['european_avg_change'] > 1.0) {
    echo "\n✓ RESULT: European markets are GREENISH (+" . round($normalized['european_avg_change'], 2) . "%)\n";
    echo "   This should have MASSIVE INFLUENCE on predictions (50% weight)!\n";
    echo "   Expected: Strong BULLISH predictions across stocks\n";
} elseif ($normalized['european_avg_change'] < -1.0) {
    echo "\n✓ RESULT: European markets are REDDISH (" . round($normalized['european_avg_change'], 2) . "%)\n";
    echo "   This should have MASSIVE INFLUENCE on predictions (50% weight)!\n";
    echo "   Expected: Strong BEARISH predictions across stocks\n";
} else {
    echo "\n○ RESULT: European markets are NEUTRAL (" . round($normalized['european_avg_change'], 2) . "%)\n";
    echo "   Impact will be moderate on predictions.\n";
}

echo "\n=== Test Complete ===\n";
