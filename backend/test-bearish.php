<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Stock;
use App\Services\ScenarioGeneratorService;

// Get the stock
$symbol = 'AVGO';
$stock = Stock::where('symbol', $symbol)->first();

if (!$stock) {
    echo "Stock $symbol not found!\n";
    exit(1);
}

// Create service
$service = app(ScenarioGeneratorService::class);

// Generate scenarios
echo "Generating scenarios for $symbol...\n";
echo "========================================\n\n";

$scenarios = $service->generateScenarios($stock, 'today');

echo "\nTotal scenarios generated: " . $scenarios->count() . "\n";
echo "========================================\n\n";

foreach ($scenarios as $index => $scenario) {
    echo "Scenario " . ($index + 1) . ": " . $scenario['scenario_name'] . "\n";
    echo "Type: " . $scenario['scenario_type'] . "\n";
    echo "Expected Change: " . $scenario['expected_change_percent'] . "%\n";
    echo "Confidence: " . $scenario['confidence_level'] . "%\n";
    echo "========================================\n\n";
}

// Check if bearish exists
$hasBearish = $scenarios->contains(function($s) {
    return str_contains(strtolower($s['scenario_type']), 'bearish');
});

echo $hasBearish ? "✅ BEARISH SCENARIO FOUND!\n" : "❌ NO BEARISH SCENARIO!\n";
