<?php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=====================================\n";
echo "NVDA MICRO-RECOVERY TEST\n";
echo "Testing 0.24% Recovery After -4% Drop\n";
echo "=====================================\n\n";

// NVDA Real Scenario
$currentPrice = 183.16;
$previousPrice = 192.57;
$absoluteDrop1d = $previousPrice - $currentPrice; // $9.41
$priceChange1d = 0.24; // Current tiny recovery
$sentiment = 0.105; // Slightly positive

echo "📊 NVDA Real Situation:\n";
echo "   Previous Price: $" . number_format($previousPrice, 2) . "\n";
echo "   Current Price: $" . number_format($currentPrice, 2) . "\n";
echo "   Absolute Drop: $" . number_format($absoluteDrop1d, 2) . " (-4.89%)\n";
echo "   Today's Movement: +" . $priceChange1d . "%\n";
echo "   Sentiment: " . number_format($sentiment, 3) . "\n\n";

echo str_repeat('=', 80) . "\n";
echo "PATTERN DETECTION\n";
echo str_repeat('=', 80) . "\n\n";

$patterns = [];
$confidence = 0;
$reboundType = null;

// Calculate severity
$absoluteDropSeverity = ($absoluteDrop1d / 5) * 15; // $9.41 = 28.23 points

echo "🎯 Absolute Drop Severity Score: " . number_format($absoluteDropSeverity, 2) . " points\n\n";

// Check Pattern 8 (lowered threshold to 0.1%)
if ($absoluteDrop1d > 5 && $priceChange1d > 0.1 && $sentiment >= 0) {
    $patterns[] = 'large_dollar_drop_recovery';
    echo "✅ Pattern 8: LARGE DOLLAR DROP RECOVERY\n";
    
    $dollarDropConfidence = 70 + ($absoluteDrop1d * 3);
    echo "   Base: 70 + ($9.41 × 3) = " . number_format($dollarDropConfidence, 1) . "%\n";
    
    $confidence = $dollarDropConfidence;
    
    // Check recovery strength
    if ($priceChange1d > 2) {
        $patterns[] = 'large_drop_strong_recovery';
        $confidence += 20;
        echo "   + Strong recovery (>2%): +20%\n";
    } elseif ($priceChange1d > 0.5) {
        $patterns[] = 'large_drop_moderate_recovery';
        $confidence += 10;
        echo "   + Moderate recovery (>0.5%): +10%\n";
    } elseif ($priceChange1d > 0.1) {
        $patterns[] = 'large_drop_early_recovery';
        $confidence += 5;
        echo "   + Early recovery (>0.1%): +5%\n";
    }
    
    // Sentiment boost
    if ($sentiment > 0.3) {
        $patterns[] = 'dollar_drop_with_positive_sentiment';
        $confidence += 20;
        echo "   + Positive sentiment (>0.3): +20%\n";
    }
    
    $reboundType = 'strong';
    echo "\n";
}

// Check Pattern 10 (new stabilization pattern)
if ($absoluteDrop1d > 7 && $priceChange1d >= 0 && $priceChange1d <= 1) {
    if (!in_array('large_dollar_drop_recovery', $patterns)) {
        $patterns[] = 'post_drop_stabilization';
        echo "✅ Pattern 10: POST-DROP STABILIZATION\n";
        
        $stabilizationConfidence = 70 + ($absoluteDrop1d * 3.5);
        echo "   Base: 70 + ($9.41 × 3.5) = " . number_format($stabilizationConfidence, 1) . "%\n";
        
        $confidence = max($confidence, $stabilizationConfidence);
        
        if ($priceChange1d > 0.1) {
            $patterns[] = 'early_bounce_signal';
            $confidence += 10;
            echo "   + Early bounce signal: +10%\n";
        }
        
        if ($sentiment > 0.2) {
            $patterns[] = 'stabilization_with_positive_outlook';
            $confidence += 15;
            echo "   + Positive outlook: +15%\n";
        }
        
        $reboundType = 'strong';
        echo "\n";
    } else {
        echo "ℹ️  Pattern 10 not checked (Pattern 8 already matched)\n\n";
    }
}

$confidence = min(150, max(0, $confidence));

echo str_repeat('=', 80) . "\n";
echo "FINAL RESULTS\n";
echo str_repeat('=', 80) . "\n\n";

echo "🎯 Detected Patterns:\n";
foreach ($patterns as $pattern) {
    echo "   ✓ " . str_replace('_', ' ', ucwords($pattern, '_')) . "\n";
}
echo "\n";

echo "📊 Rebound Type: " . strtoupper($reboundType ?? 'none') . "\n";
echo "💯 Confidence Level: " . number_format($confidence, 1) . "%\n\n";

if (count($patterns) > 0) {
    echo "✅ REBOUND DETECTED!\n\n";
    echo "📈 Expected Behavior:\n";
    echo "   - Prediction cache will be cleared\n";
    echo "   - New prediction will be generated with rebound signal\n";
    echo "   - ML model will receive boosted confidence\n";
    echo "   - Frontend will show rebound indicator\n\n";
} else {
    echo "❌ No rebound detected\n\n";
}

echo str_repeat('=', 80) . "\n";
echo "COMPARISON\n";
echo str_repeat('=', 80) . "\n\n";

echo "Old System (0.5% threshold):\n";
echo "   Pattern 8: ❌ Would NOT trigger (0.24% < 0.5%)\n";
echo "   Result: No rebound detected\n";
echo "   Confidence: 0%\n\n";

echo "New System (0.1% threshold + stabilization):\n";
echo "   Pattern 8: ✅ TRIGGERS (0.24% > 0.1%)\n";
echo "   Pattern 10: Available as fallback\n";
echo "   Result: REBOUND DETECTED\n";
echo "   Confidence: ~" . number_format($confidence, 0) . "%\n\n";

echo "🎯 Improvement: " . number_format($confidence, 0) . "% confidence (was 0%)\n\n";

echo "💡 Key Points:\n";
echo "   • Even 0.24% recovery after $9 drop is significant\n";
echo "   • System now treats stabilization as rebound signal\n";
echo "   • Confidence is weighted by absolute drop size\n";
echo "   • Small positive moves are early bounce indicators\n\n";

echo "✨ Test complete!\n";
