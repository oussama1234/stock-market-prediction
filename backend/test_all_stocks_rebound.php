<?php

require __DIR__ . '/vendor/autoload.php';

use App\Models\Stock;
use App\Models\StockPrice;
use App\Jobs\ProcessAllStocksReboundDetectionJob;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=====================================\n";
echo "ALL STOCKS REBOUND ANALYSIS\n";
echo "=====================================\n\n";

$stocks = Stock::all();
$reboundCandidates = [];
$summary = [
    'total_stocks' => 0,
    'with_data' => 0,
    'potential_rebounds' => 0,
    'large_drops' => 0,
    'micro_recoveries' => 0
];

echo "📊 Analyzing " . $stocks->count() . " stocks...\n\n";
echo str_repeat('=', 100) . "\n";
echo sprintf("%-6s %-12s %-12s %-12s %-12s %s\n", "Symbol", "Current", "Prev", "$ Change", "% Change", "Status");
echo str_repeat('=', 100) . "\n";

foreach ($stocks as $stock) {
    $summary['total_stocks']++;
    
    // Get recent prices
    $prices = StockPrice::where('stock_id', $stock->id)
        ->where('interval', '1day')
        ->orderBy('price_date', 'desc')
        ->take(10)
        ->get();
    
    if ($prices->count() < 2) {
        continue;
    }
    
    $summary['with_data']++;
    
    $currentPrice = $prices[0]->close;
    $prevPrice = $prices[1]->close;
    $dollarChange = $currentPrice - $prevPrice;
    $pctChange = $prevPrice > 0 ? (($currentPrice - $prevPrice) / $prevPrice) * 100 : 0;
    
    // Determine status
    $status = '';
    $isCandidate = false;
    
    // Check for large drops with any recovery or stabilization
    $absoluteDrop = abs($dollarChange);
    if ($dollarChange < 0 && $absoluteDrop > 3) {
        $status = '🔴 LARGE DROP';
        $summary['large_drops']++;
        $isCandidate = true;
    } elseif ($dollarChange >= 0 && $pctChange < 1) {
        // Check if there was a recent drop
        if ($prices->count() >= 3) {
            $price2DaysAgo = $prices[2]->close;
            $drop2d = $price2DaysAgo - $currentPrice;
            if ($drop2d > 3) {
                $status = '🟡 MICRO-RECOVERY';
                $summary['micro_recoveries']++;
                $isCandidate = true;
            }
        }
    } elseif ($pctChange > 1) {
        $status = '🟢 BOUNCING';
    } elseif ($pctChange < -1) {
        $status = '🔴 DECLINING';
    } else {
        $status = '⚪ FLAT';
    }
    
    if ($isCandidate) {
        $summary['potential_rebounds']++;
        $reboundCandidates[] = [
            'stock' => $stock,
            'current' => $currentPrice,
            'prev' => $prevPrice,
            'change' => $dollarChange,
            'pct' => $pctChange,
            'status' => $status
        ];
    }
    
    // Print line
    $changeStr = sprintf("%+.2f", $dollarChange);
    $pctStr = sprintf("%+.2f%%", $pctChange);
    echo sprintf("%-6s $%10.2f $%10.2f $%10s %11s %s\n", 
        $stock->symbol, 
        $currentPrice, 
        $prevPrice, 
        $changeStr, 
        $pctStr, 
        $status
    );
}

echo str_repeat('=', 100) . "\n\n";

echo "📈 SUMMARY:\n";
echo str_repeat('-', 50) . "\n";
echo sprintf("%-30s: %d\n", "Total Stocks", $summary['total_stocks']);
echo sprintf("%-30s: %d\n", "With Price Data", $summary['with_data']);
echo sprintf("%-30s: %d\n", "Potential Rebounds", $summary['potential_rebounds']);
echo sprintf("%-30s: %d\n", "  - Large Drops", $summary['large_drops']);
echo sprintf("%-30s: %d\n", "  - Micro-Recoveries", $summary['micro_recoveries']);
echo "\n";

if (count($reboundCandidates) > 0) {
    echo "🎯 REBOUND CANDIDATES:\n";
    echo str_repeat('-', 50) . "\n";
    foreach ($reboundCandidates as $candidate) {
        echo sprintf("  • %-6s: $%7.2f (%+.2f%%) - %s\n", 
            $candidate['stock']->symbol,
            $candidate['current'],
            $candidate['pct'],
            $candidate['status']
        );
    }
    echo "\n";
}

echo "\n🚀 DISPATCHING BATCH REBOUND DETECTION JOB...\n";
echo str_repeat('=', 100) . "\n";

ProcessAllStocksReboundDetectionJob::dispatch();

echo "✅ Job dispatched to queue!\n\n";
echo "📊 The job will:\n";
echo "   1. Process all " . $summary['with_data'] . " stocks with price data\n";
echo "   2. Apply enhanced rebound detection logic:\n";
echo "      • Pattern 8: >$5 drop + >0.1% recovery\n";
echo "      • Pattern 10: >$7 drop + 0-1% stabilization\n";
echo "      • Confidence: 70-150% based on drop size\n";
echo "   3. Regenerate predictions for detected rebounds\n";
echo "   4. Clear prediction cache for updated stocks\n\n";

echo "📝 Monitor progress:\n";
echo "   docker logs market-prediction-queue-worker --tail 100 | grep -i rebound\n\n";

echo "⏱️  Estimated completion: " . ceil($summary['with_data'] / 10) . "-" . ceil($summary['with_data'] / 5) . " minutes\n";
echo "   (depends on queue worker speed)\n\n";

echo "✨ Done!\n";
