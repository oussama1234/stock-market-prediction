<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing prediction API...\n\n";

// Simulate the prediction request
$request = \Illuminate\Http\Request::create(
    '/api/predictions/predict',
    'POST',
    ['symbol' => 'AAPL', 'horizon' => 'today', 'model' => 'v6']
);

$controller = new \App\Http\Controllers\PredictionController(
    app(\App\Services\PredictionService::class),
    app(\App\Services\AsianMarketService::class),
    app(\App\Services\EuropeanMarketService::class)
);

try {
    $response = $controller->predictWithBody($request);
    $content = $response->getContent();
    $data = json_decode($content, true);
    
    echo "=== API RESPONSE ===\n";
    echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
    
    if (isset($data['data'])) {
        echo "Model Version: " . ($data['data']['model_version'] ?? 'MISSING') . "\n";
        echo "Label: " . ($data['data']['label'] ?? 'MISSING') . "\n";
        echo "Probability: " . ($data['data']['probability'] ?? 'MISSING') . "\n";
        echo "Expected Move: " . ($data['data']['expected_pct_move'] ?? 'MISSING') . "%\n";
        
        if (isset($data['data']['is_fallback']) && $data['data']['is_fallback']) {
            echo "\n❌ FALLBACK MODEL!\n";
        } else if (($data['data']['model_version'] ?? '') === 'quick_model_v6') {
            echo "\n✅ V6 MODEL WORKING!\n";
        } else {
            echo "\n⚠️  Unknown model: " . ($data['data']['model_version'] ?? 'none') . "\n";
        }
    } else {
        echo "No data in response!\n";
    }
    
    echo "\nFull response:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
