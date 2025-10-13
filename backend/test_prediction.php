<?php
/**
 * Test Prediction Endpoint
 * 
 * Quick script to test if predictions are working correctly
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Stock;
use App\Services\PredictionService;
use App\Services\AsianMarketService;
use Illuminate\Support\Facades\Log;

echo "=== Testing Prediction Service ===\n\n";

// Test 1: Check if stock exists
echo "Test 1: Looking for a stock...\n";
$stock = Stock::first();

if (!$stock) {
    echo "❌ No stocks found in database. Please add stocks first.\n";
    exit(1);
}

echo "✅ Found stock: {$stock->symbol} - {$stock->name}\n\n";

// Test 2: Check Asian Market Service
echo "Test 2: Testing Asian Market Service...\n";
$asianService = app(AsianMarketService::class);

try {
    $asianData = $asianService->getTodayChanges();
    echo "✅ Asian market data retrieved successfully\n";
    echo "   Markets: " . count($asianData) . "\n";
    
    foreach ($asianData as $key => $market) {
        $status = isset($market['error']) ? '❌ Error' : '✅ OK';
        $change = isset($market['change_percent']) ? $market['change_percent'] . '%' : 'N/A';
        echo "   - {$market['name']}: $status ($change)\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "❌ Asian market service failed: " . $e->getMessage() . "\n\n";
}

// Test 3: Check Python environment
echo "Test 3: Checking Python environment...\n";
$pythonExecutable = config('services.python.executable', 'python');
$pythonPath = base_path('python/models/quick_model_v2.py');

if (!file_exists($pythonPath)) {
    echo "❌ Python script not found at: $pythonPath\n";
} else {
    echo "✅ Python script found\n";
    
    // Test Python execution
    $testCommand = "$pythonExecutable --version 2>&1";
    $pythonVersion = shell_exec($testCommand);
    
    if ($pythonVersion) {
        echo "✅ Python executable works: " . trim($pythonVersion) . "\n";
    } else {
        echo "❌ Python executable failed. Command: $pythonExecutable\n";
        echo "   Try: python, python3, or set in config\n";
    }
    echo "\n";
}

// Test 4: Try to generate prediction
echo "Test 4: Generating prediction for {$stock->symbol}...\n";
$predictionService = app(PredictionService::class);

try {
    $prediction = $predictionService->getPredictionForHorizon($stock, 'today');
    
    echo "✅ Prediction generated successfully!\n";
    echo "\n=== Prediction Results ===\n";
    echo "Label: " . ($prediction['label'] ?? 'N/A') . "\n";
    echo "Probability: " . (isset($prediction['probability']) ? round($prediction['probability'] * 100, 1) . '%' : 'N/A') . "\n";
    echo "Expected Move: " . ($prediction['expected_pct_move'] ?? 'N/A') . "%\n";
    echo "Model: " . ($prediction['model_version'] ?? 'N/A') . "\n";
    
    if (isset($prediction['is_fallback']) && $prediction['is_fallback']) {
        echo "\n⚠️  WARNING: Using fallback prediction (Python model not available)\n";
    }
    
    if (isset($prediction['top_reasons'])) {
        echo "\nTop Reasons:\n";
        foreach ($prediction['top_reasons'] as $i => $reason) {
            echo "  " . ($i + 1) . ". $reason\n";
        }
    }
    
    echo "\n✅ All tests passed! Prediction service is working.\n";
    
} catch (Exception $e) {
    echo "❌ Prediction failed: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== Installation Tips ===\n";
echo "If Python model is not working:\n";
echo "1. Install Python dependencies:\n";
echo "   cd backend/python\n";
echo "   pip install -r requirements.txt\n\n";
echo "2. Test Python script directly:\n";
echo "   python python/models/quick_model_v2.py predict --features '{\"close\": 150}'\n\n";
echo "3. Check logs for detailed errors:\n";
echo "   tail -f storage/logs/laravel.log\n\n";
