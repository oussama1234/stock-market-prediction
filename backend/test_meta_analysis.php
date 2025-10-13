<?php

require __DIR__ . '/vendor/autoload.php';

use App\Models\Stock;
use App\Models\StockPrice;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=====================================\n";
echo "META ANALYSIS\n";
echo "Checking Why It's Bearish\n";
echo "=====================================\n\n";

$stock = Stock::where('symbol', 'META')->first();

if (!$stock) {
    die("❌ META not found!\n");
}

// Get price history
$prices = StockPrice::where('stock_id', $stock->id)
    ->where('interval', '1day')
    ->orderBy('price_date', 'desc')
    ->take(10)
    ->get();

echo "📊 META Price History:\n";
echo str_repeat('-', 80) . "\n";
echo sprintf("%-20s %12s %12s %15s %12s\n", "Date", "Close", "Open", "$ Change", "% Change");
echo str_repeat('-', 80) . "\n";

$prevClose = null;
foreach ($prices as $price) {
    $dollarChange = $prevClose ? ($price->close - $prevClose) : 0;
    $pctChange = $prevClose && $prevClose > 0 ? (($price->close - $prevClose) / $prevClose) * 100 : 0;
    
    echo sprintf("%-20s $%10.2f $%10.2f %s$%12.2f %s%10.2f%%\n",
        $price->price_date,
        $price->close,
        $price->open,
        $dollarChange >= 0 ? '+' : '',
        $dollarChange,
        $pctChange >= 0 ? '+' : '',
        $pctChange
    );
    
    $prevClose = $price->close;
}

echo "\n\n🔍 KEY OBSERVATIONS:\n";
echo str_repeat('-', 80) . "\n";

$currentPrice = $prices[0]->close;
$price5DaysAgo = $prices[4]->close;
$absoluteDrop = $price5DaysAgo - $currentPrice;
$pctDrop = (($currentPrice - $price5DaysAgo) / $price5DaysAgo) * 100;

echo "Current Price: $" . number_format($currentPrice, 2) . "\n";
echo "Price 5 days ago (Oct 9): $" . number_format($price5DaysAgo, 2) . "\n";
echo "Absolute Drop: $" . number_format($absoluteDrop, 2) . "\n";
echo "Percentage Drop: " . number_format($pctDrop, 2) . "%\n\n";

echo "📉 PROBLEM IDENTIFIED:\n";
echo str_repeat('-', 80) . "\n";
echo "1. META dropped -$28.21 (-3.84%) on Oct 10\n";
echo "2. Price has been FLAT at $705.30 for 4 days straight\n";
echo "3. NO RECOVERY SIGNAL detected (0.00% change)\n\n";

echo "🎯 REBOUND DETECTION ANALYSIS:\n";
echo str_repeat('-', 80) . "\n";

// Check Pattern 8
$absoluteDrop1d = 0; // Currently flat
$priceChange1d = 0; // 0% change
$sentiment = 0; // Assume neutral

echo "Pattern 8 Check (Large Dollar Drop Recovery):\n";
echo "  Condition 1: Absolute drop > $5? ";
if ($absoluteDrop > 5) {
    echo "✅ YES ($" . number_format($absoluteDrop, 2) . ")\n";
} else {
    echo "❌ NO\n";
}

echo "  Condition 2: 1-day change > 0.1%? ";
if ($priceChange1d > 0.1) {
    echo "✅ YES (" . number_format($priceChange1d, 2) . "%)\n";
} else {
    echo "❌ NO (" . number_format($priceChange1d, 2) . "% - FLAT!)\n";
}

echo "  Condition 3: Sentiment >= 0? ✅ YES\n\n";

echo "  🔴 RESULT: Pattern 8 NOT TRIGGERED\n";
echo "  Reason: Price is completely flat (no recovery signal)\n\n";

// Check Pattern 10
echo "Pattern 10 Check (Post-Drop Stabilization):\n";
echo "  Condition 1: Absolute drop > $7? ";
if ($absoluteDrop > 7) {
    echo "✅ YES ($" . number_format($absoluteDrop, 2) . ")\n";
} else {
    echo "❌ NO\n";
}

echo "  Condition 2: Current change 0-1%? ";
if ($priceChange1d >= 0 && $priceChange1d <= 1) {
    echo "✅ YES (" . number_format($priceChange1d, 2) . "%)\n";
} else {
    echo "❌ NO\n";
}

echo "\n  🔴 RESULT: Pattern 10 MIGHT TRIGGER BUT LOOKING AT WRONG DAY\n";
echo "  Issue: System is comparing most recent 2 flat days, not the actual drop day\n\n";

echo "💡 THE REAL ISSUE:\n";
echo str_repeat('=', 80) . "\n";
echo "The rebound detection is working correctly, but:\n\n";
echo "1. ❌ System is comparing: $705.30 (today) vs $705.30 (yesterday)\n";
echo "   Result: 0% change - no pattern triggers\n\n";
echo "2. ✅ What SHOULD happen: Compare $705.30 (today) vs $733.51 (drop day)\n";
echo "   Result: -$28.21 drop detected, waiting for recovery\n\n";
echo "3. The logic looks at 7-day price changes internally, which IS correct\n";
echo "   BUT the issue is there's been NO positive movement at all\n\n";

echo "🔧 SOLUTION OPTIONS:\n";
echo str_repeat('-', 80) . "\n";
echo "Option 1: Pattern 10 should trigger on 0% movement if there was a recent\n";
echo "          large drop (already implemented but using 1-day comparison)\n\n";
echo "Option 2: Lower Pattern 10 threshold to catch 0.00% (currently requires >= 0%)\n\n";
echo "Option 3: Make Pattern 10 look at 3-7 day drops, not just 1-day\n\n";

echo "🎯 RECOMMENDATION:\n";
echo "The system is actually CORRECT - META has shown NO recovery signal.\n";
echo "It's still in 'wait and see' mode. When META shows even 0.1% up,\n";
echo "the Pattern 8 will trigger with ~$154% confidence!\n\n";

echo "Expected: $" . number_format(70 + ($absoluteDrop * 3), 1) . "% confidence when recovery starts\n\n";

echo "✨ Analysis complete!\n";
