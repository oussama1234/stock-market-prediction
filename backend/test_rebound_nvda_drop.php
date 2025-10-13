<?php

require __DIR__ . '/vendor/autoload.php';

use App\Models\Stock;
use App\Models\StockPrice;
use Illuminate\Support\Facades\Log;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=====================================\n";
echo "NVDA HISTORICAL DROP ANALYSIS\n";
echo "Testing -$9.41 Drop Scenario\n";
echo "=====================================\n\n";

// Get NVDA stock
$stock = Stock::where('symbol', 'NVDA')->first();

if (!$stock) {
    die("❌ NVDA stock not found in database\n");
}

echo "📊 Stock: NVDA (ID: {$stock->id})\n";
echo "📅 Analysis Date: 2025-10-10 (Day of -$9.41 drop)\n\n";

// Get price history
$prices = StockPrice::where('stock_id', $stock->id)
    ->where('interval', '1day')
    ->orderBy('price_date', 'asc')
    ->get(['price_date', 'close', 'open', 'high', 'low']);

echo "📈 Historical Price Data:\n";
echo str_repeat('-', 80) . "\n";
echo sprintf("%-20s %12s %12s %15s %12s\n", "Date", "Close", "Open", "$ Change", "% Change");
echo str_repeat('-', 80) . "\n";

$priceData = [];
$previousClose = null;

foreach ($prices as $price) {
    $dollarChange = $previousClose ? ($price->close - $previousClose) : 0;
    $pctChange = $previousClose && $previousClose > 0 ? (($price->close - $previousClose) / $previousClose) * 100 : 0;
    
    $changeColor = $dollarChange > 0 ? '+' : '';
    
    echo sprintf(
        "%-20s $%10.2f $%10.2f %s$%12.2f %s%10.2f%%\n",
        $price->price_date,
        $price->close,
        $price->open,
        $changeColor,
        $dollarChange,
        $changeColor,
        $pctChange
    );
    
    $priceData[] = [
        'date' => $price->price_date,
        'close' => $price->close,
        'open' => $price->open,
        'dollar_change' => $dollarChange,
        'pct_change' => $pctChange
    ];
    
    $previousClose = $price->close;
}

echo "\n\n🎯 KEY DROP ANALYSIS:\n";
echo str_repeat('-', 80) . "\n";

// Find the -$9.41 drop
$dropDate = '2025-10-10';
$dropIndex = null;

foreach ($priceData as $index => $data) {
    if (strpos($data['date'], $dropDate) !== false && abs($data['dollar_change'] + 9.41) < 0.5) {
        $dropIndex = $index;
        break;
    }
}

if ($dropIndex !== null) {
    $dropData = $priceData[$dropIndex];
    $priceBeforeDrop = $priceData[$dropIndex - 1]['close'];
    $currentPrice = $dropData['close'];
    
    echo "📉 DROP EVENT DETECTED:\n";
    echo "   Date: {$dropData['date']}\n";
    echo "   Price before: \${$priceBeforeDrop}\n";
    echo "   Price after: \${$currentPrice}\n";
    echo "   Dollar drop: \${$dropData['dollar_change']}\n";
    echo "   Percentage drop: " . number_format($dropData['pct_change'], 2) . "%\n\n";
    
    // Calculate what would happen with our detection logic
    $absoluteDrop1d = abs($dropData['dollar_change']);
    
    // Calculate severity score
    $absoluteDropSeverity = 0;
    if ($currentPrice > 100 && $absoluteDrop1d > 5) {
        $absoluteDropSeverity = min(25, ($absoluteDrop1d / 5) * 5);
    }
    
    echo "🔍 REBOUND DETECTION ANALYSIS:\n";
    echo "   Absolute 1-day drop: \$" . number_format($absoluteDrop1d, 2) . "\n";
    echo "   Current price: \$" . number_format($currentPrice, 2) . "\n";
    echo "   Price tier: " . ($currentPrice > 100 ? ">$100 (High-value)" : "< $100") . "\n";
    echo "   Calculated severity score: " . number_format($absoluteDropSeverity, 2) . " points\n\n";
    
    // Check next day for recovery
    if ($dropIndex + 1 < count($priceData)) {
        $nextDay = $priceData[$dropIndex + 1];
        $nextDayChange = $nextDay['pct_change'];
        
        echo "📊 NEXT DAY ANALYSIS (Recovery Check):\n";
        echo "   Date: {$nextDay['date']}\n";
        echo "   Price: \$" . number_format($nextDay['close'], 2) . "\n";
        echo "   Change: " . sprintf("%+.2f%%", $nextDayChange) . " (\$" . sprintf("%+.2f", $nextDay['dollar_change']) . ")\n\n";
        
        // Simulate Pattern 8 detection
        $sentiment = 0; // Neutral
        $willTriggerPattern8 = $absoluteDrop1d > 5 && $nextDayChange > 0.5 && $sentiment >= 0;
        
        echo "🎯 PATTERN 8 TRIGGER CHECK (Large Dollar Drop Recovery):\n";
        echo "   Condition 1: Absolute drop > $5? " . ($absoluteDrop1d > 5 ? "✅ YES ($" . number_format($absoluteDrop1d, 2) . ")" : "❌ NO") . "\n";
        echo "   Condition 2: Next day change > 0.5%? " . ($nextDayChange > 0.5 ? "✅ YES (" . number_format($nextDayChange, 2) . "%)" : "❌ NO (" . number_format($nextDayChange, 2) . "%)") . "\n";
        echo "   Condition 3: Sentiment >= 0? " . ($sentiment >= 0 ? "✅ YES" : "❌ NO") . "\n";
        echo "   \n";
        echo "   🔔 RESULT: " . ($willTriggerPattern8 ? "✅ PATTERN 8 WOULD TRIGGER!" : "❌ Pattern 8 would NOT trigger") . "\n\n";
        
        if ($willTriggerPattern8) {
            $confidence = min(90, 65 + ($absoluteDrop1d * 2));
            echo "   📈 Expected Confidence: " . number_format($confidence, 1) . "%\n";
            echo "   📊 Rebound Type: STRONG\n";
            echo "   🎯 Pattern: large_dollar_drop_recovery\n\n";
            
            echo "   💡 EXPLANATION:\n";
            echo "      - NVDA dropped \$" . number_format($absoluteDrop1d, 2) . " which triggered the $5+ threshold\n";
            echo "      - Absolute drop severity boost: +" . number_format($absoluteDropSeverity, 2) . " points\n";
            echo "      - Base confidence (65) + (drop × 2) = 65 + (" . number_format($absoluteDrop1d, 2) . " × 2) = " . number_format($confidence, 1) . "%\n";
            echo "      - This is exactly what we designed for high-value stocks!\n";
        } else {
            echo "   📉 Next day showed " . number_format($nextDayChange, 2) . "% change\n";
            echo "   💡 Pattern 8 requires >0.5% recovery on next day to trigger\n";
            
            // Check subsequent days
            $foundRecovery = false;
            for ($i = $dropIndex + 1; $i < min($dropIndex + 5, count($priceData)); $i++) {
                $day = $priceData[$i];
                if ($day['pct_change'] > 0.5) {
                    echo "\n   ✅ Recovery found on {$day['date']}: " . sprintf("%+.2f%%", $day['pct_change']) . "\n";
                    $foundRecovery = true;
                    
                    // Calculate 3-day drop at this point
                    $price3DaysAgo = $i >= 3 ? $priceData[$i - 3]['close'] : $priceData[0]['close'];
                    $absoluteDrop3d = $price3DaysAgo - $day['close'];
                    
                    if ($absoluteDrop3d > 10) {
                        echo "   🎯 Pattern 9 would trigger (3-day drop: \$" . number_format($absoluteDrop3d, 2) . ")\n";
                        $confidence = min(88, 60 + ($absoluteDrop3d * 1.5));
                        echo "   📈 Expected Confidence: " . number_format($confidence, 1) . "%\n";
                    }
                    break;
                }
            }
            
            if (!$foundRecovery) {
                echo "\n   ⚠️  No significant recovery (+0.5%) in next 3 days\n";
            }
        }
    }
} else {
    echo "❌ Could not find the -$9.41 drop in data\n";
}

echo "\n\n" . str_repeat('=', 80) . "\n";
echo "SIMULATION: Running Actual Rebound Detection Job\n";
echo str_repeat('=', 80) . "\n\n";

// Now run the actual job to see what it detects currently
use App\Jobs\DetectReboundAndRegenerateJob;

echo "🚀 Dispatching DetectReboundAndRegenerateJob for NVDA...\n";
DetectReboundAndRegenerateJob::dispatch($stock);
echo "✅ Job dispatched to queue\n\n";

echo "📝 Check queue worker logs for actual detection results:\n";
echo "   docker exec market-prediction-queue-worker tail -f /var/www/html/storage/logs/laravel.log | grep -i nvda\n\n";

echo "✨ Test Complete!\n";
