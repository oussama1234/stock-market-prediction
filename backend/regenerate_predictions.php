<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Stock;
use App\Services\EnhancedPredictionService;
use App\Services\StockService;

echo "=== Regenerating Enhanced AI Predictions ===\n\n";

$enhancedService = app(EnhancedPredictionService::class);
$stockService = app(StockService::class);

$stocks = Stock::limit(10)->get();

echo "Found {$stocks->count()} stocks\n\n";

foreach ($stocks as $stock) {
    echo "Processing {$stock->symbol} ({$stock->name})...\n";
    
    try {
        // Fetch latest quote
        $quote = $stockService->getQuote($stock->symbol);
        if (!$quote) {
            echo "  ✗ Could not fetch quote\n\n";
            continue;
        }
        
        echo "  Quote: \${$quote['current_price']}\n";
        
        $prediction = $enhancedService->generateAdvancedPrediction($stock, $quote);
        
        if ($prediction) {
            echo "  ✓ Prediction generated (ID: {$prediction->id})\n";
            echo "    Direction: {$prediction->direction}\n";
            echo "    Confidence: {$prediction->confidence_score}%\n";
            echo "    Has indicators: " . ($prediction->indicators ? "YES" : "NO") . "\n";
            
            if ($prediction->indicators) {
                $indicators = $prediction->indicators;
                if (isset($indicators['technical']['rsi'])) {
                    echo "    RSI: {$indicators['technical']['rsi']['value']}\n";
                }
                if (isset($indicators['signals']['combined'])) {
                    echo "    Combined Signal: {$indicators['signals']['combined']}\n";
                }
            }
            
            echo "    Reasoning: " . substr($prediction->reasoning, 0, 80) . "...\n";
        } else {
            echo "  ✗ Failed to generate prediction\n";
        }
    } catch (\Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
        echo "    File: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo "    Trace: " . substr($e->getTraceAsString(), 0, 200) . "...\n";
    }
    
    echo "\n";
}

echo "=== Complete ===\n";
