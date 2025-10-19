<?php
/**
 * Test FundamentalsAggregator
 * Run: docker exec market-prediction-php-fpm php test-aggregator.php AAPL
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$symbol = $argv[1] ?? 'AAPL';

echo "🧪 Testing FundamentalsAggregator for {$symbol}...\n\n";

// Test the aggregator
$aggregator = app(\App\Services\FundamentalsAggregator::class);

try {
    echo "📊 Fetching fundamentals...\n";
    $result = $aggregator->getFundamentals($symbol);
    
    if ($result) {
        echo "✅ Success! Got data:\n\n";
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
        
        // Check if real data
        $isReal = false;
        $nonZeroCount = 0;
        foreach (['pe_ratio', 'eps_growth', 'revenue_growth', 'roe', 'profit_margin'] as $key) {
            if (isset($result[$key]) && $result[$key] != 0 && $result[$key] !== null) {
                $nonZeroCount++;
            }
        }
        
        if ($nonZeroCount >= 3) {
            echo "✅ This appears to be REAL DATA (not defaults)\n";
        } else {
            echo "⚠️  This appears to be DEFAULT values\n";
        }
    } else {
        echo "❌ Aggregator returned NULL\n";
        echo "This means all 3 sources (Yahoo Finance, Finnhub, Alpha Vantage) failed.\n";
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

// Check API keys
echo "\n🔑 Checking API keys:\n";
echo "   Finnhub: " . (config('services.finnhub.key') ? "✅ Set" : "❌ Not set") . "\n";
echo "   Alpha Vantage: " . (config('services.alpha_vantage.key') ? "✅ Set" : "❌ Not set") . "\n";
