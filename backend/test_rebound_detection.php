<?php

require __DIR__ . '/vendor/autoload.php';

use App\Models\Stock;
use App\Models\StockPrice;
use App\Jobs\DetectReboundAndRegenerateJob;
use App\Jobs\ProcessAllStocksReboundDetectionJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=====================================\n";
echo "ENHANCED REBOUND DETECTION TEST\n";
echo "Testing Absolute Price Drop Detection\n";
echo "=====================================\n\n";

// Test Configuration
$testSymbols = ['NVDA', 'AAPL', 'MSFT', 'TSLA', 'GOOGL'];
$results = [];

echo "📊 Testing Rebound Detection for Multiple Stocks\n";
echo "==================================================\n\n";

foreach ($testSymbols as $symbol) {
    echo "\n🔍 Analyzing: $symbol\n";
    echo str_repeat('-', 60) . "\n";
    
    try {
        // Get stock
        $stock = Stock::where('symbol', $symbol)->first();
        
        if (!$stock) {
            echo "   ❌ Stock not found in database\n";
            continue;
        }
        
        // Get recent prices
        $recentPrices = StockPrice::where('stock_id', $stock->id)
            ->where('interval', '1day')
            ->where('price_date', '>=', now()->subDays(10))
            ->orderBy('price_date', 'desc')
            ->get();
        
        if ($recentPrices->count() < 3) {
            echo "   ⚠️  Insufficient price data (need at least 3 days)\n";
            continue;
        }
        
        // Calculate metrics
        $prices = $recentPrices->pluck('close')->reverse()->values();
        $currentPrice = $prices->last();
        $price1DayAgo = $prices->count() > 1 ? $prices[$prices->count() - 2] : $currentPrice;
        $price3DayAgo = $prices->count() > 3 ? $prices[$prices->count() - 4] : $currentPrice;
        $price7DayAgo = $prices->count() > 7 ? $prices[$prices->count() - 8] : $currentPrice;
        
        // Absolute drops
        $absoluteDrop1d = $price1DayAgo - $currentPrice;
        $absoluteDrop3d = $price3DayAgo - $currentPrice;
        
        // Percentage changes
        $priceChange1d = $price1DayAgo > 0 ? (($currentPrice - $price1DayAgo) / $price1DayAgo) * 100 : 0;
        $priceChange3d = $price3DayAgo > 0 ? (($currentPrice - $price3DayAgo) / $price3DayAgo) * 100 : 0;
        $priceChange7d = $price7DayAgo > 0 ? (($currentPrice - $price7DayAgo) / $price7DayAgo) * 100 : 0;
        
        // Get sentiment
        $sentiment = ($stock->getAverageSentiment() ?? 0.0) / 10.0;
        $recentNews = $stock->newsArticles()
            ->where('published_at', '>=', now()->subHours(48))
            ->whereNotNull('sentiment_score')
            ->count();
        
        // Display metrics
        echo "   💰 Current Price: $" . number_format($currentPrice, 2) . "\n";
        echo "\n   📉 Price Changes:\n";
        echo "      1-day: " . sprintf("%+.2f%%", $priceChange1d) . " ($$" . sprintf("%+.2f", -$absoluteDrop1d) . ")\n";
        echo "      3-day: " . sprintf("%+.2f%%", $priceChange3d) . " ($$" . sprintf("%+.2f", -$absoluteDrop3d) . ")\n";
        echo "      7-day: " . sprintf("%+.2f%%", $priceChange7d) . "\n";
        
        echo "\n   📰 Sentiment:\n";
        echo "      Score: " . number_format($sentiment, 3) . "\n";
        echo "      Recent News: $recentNews articles\n";
        
        // Calculate absolute drop severity (same logic as job)
        $absoluteDropSeverity = 0;
        if ($currentPrice > 0) {
            if ($currentPrice > 100 && $absoluteDrop1d > 5) {
                $absoluteDropSeverity = min(25, ($absoluteDrop1d / 5) * 5);
            } elseif ($currentPrice > 50 && $absoluteDrop1d > 3) {
                $absoluteDropSeverity = min(20, ($absoluteDrop1d / 3) * 5);
            } elseif ($currentPrice > 20 && $absoluteDrop1d > 1) {
                $absoluteDropSeverity = min(15, ($absoluteDrop1d / 1) * 3);
            }
            
            if ($currentPrice > 100 && $absoluteDrop3d > 10) {
                $absoluteDropSeverity = max($absoluteDropSeverity, min(30, ($absoluteDrop3d / 10) * 10));
            }
        }
        
        echo "\n   🎯 Absolute Drop Analysis:\n";
        echo "      1-day absolute drop: $" . number_format($absoluteDrop1d, 2) . "\n";
        echo "      3-day absolute drop: $" . number_format($absoluteDrop3d, 2) . "\n";
        echo "      Severity Score: " . number_format($absoluteDropSeverity, 2) . " points\n";
        
        // Check for large dollar drop patterns
        $willTriggerPattern8 = $absoluteDrop1d > 5 && $priceChange1d > 0.5 && $sentiment >= 0;
        $willTriggerPattern9 = $absoluteDrop3d > 10 && $priceChange1d > 0;
        
        if ($willTriggerPattern8) {
            echo "      ✅ WILL TRIGGER: Large Dollar Drop Recovery (Pattern 8)\n";
            $confidence = min(90, 65 + ($absoluteDrop1d * 2));
            if ($sentiment > 0.3) {
                $confidence = min(95, $confidence + 10);
            }
            echo "         Expected Confidence: " . number_format($confidence, 1) . "%\n";
        } elseif ($willTriggerPattern9) {
            echo "      ✅ WILL TRIGGER: Multi-day Dollar Drop Recovery (Pattern 9)\n";
            $confidence = min(88, 60 + ($absoluteDrop3d * 1.5));
            echo "         Expected Confidence: " . number_format($confidence, 1) . "%\n";
        } else {
            echo "      ℹ️  Large drop patterns not triggered\n";
            if ($absoluteDrop1d > 5 || $absoluteDrop3d > 10) {
                echo "         (Price needs recovery signal: 1d > 0.5% and sentiment >= 0)\n";
            }
        }
        
        // Dispatch job
        echo "\n   🚀 Dispatching Rebound Detection Job...\n";
        DetectReboundAndRegenerateJob::dispatch($stock);
        
        $results[$symbol] = [
            'price' => $currentPrice,
            'drop_1d' => $absoluteDrop1d,
            'drop_3d' => $absoluteDrop3d,
            'severity' => $absoluteDropSeverity,
            'pattern_8' => $willTriggerPattern8,
            'pattern_9' => $willTriggerPattern9
        ];
        
        echo "   ✅ Job queued successfully\n";
        
    } catch (Exception $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n\n=====================================\n";
echo "SUMMARY OF ABSOLUTE DROP ANALYSIS\n";
echo "=====================================\n\n";

echo sprintf("%-10s %12s %15s %15s %15s %10s\n", 
    "Symbol", "Price", "1d Drop ($)", "3d Drop ($)", "Severity", "Patterns");
echo str_repeat('-', 85) . "\n";

foreach ($results as $symbol => $data) {
    $patterns = [];
    if ($data['pattern_8']) $patterns[] = 'P8';
    if ($data['pattern_9']) $patterns[] = 'P9';
    $patternStr = !empty($patterns) ? implode(',', $patterns) : 'None';
    
    echo sprintf("%-10s $%10.2f $%13.2f $%13.2f %14.1f %10s\n",
        $symbol,
        $data['price'],
        $data['drop_1d'],
        $data['drop_3d'],
        $data['severity'],
        $patternStr
    );
}

echo "\n\n📋 Pattern Legend:\n";
echo "   P8 = Large Dollar Drop Recovery (1-day drop > $5)\n";
echo "   P9 = Multi-day Dollar Drop Recovery (3-day drop > $10)\n";

echo "\n\n🔔 Testing Batch Process for All Stocks\n";
echo "=========================================\n";

try {
    echo "Dispatching ProcessAllStocksReboundDetectionJob...\n";
    ProcessAllStocksReboundDetectionJob::dispatch();
    echo "✅ Batch job dispatched successfully!\n";
    echo "   All active stocks will be analyzed for rebound patterns.\n";
} catch (Exception $e) {
    echo "❌ Error dispatching batch job: " . $e->getMessage() . "\n";
}

echo "\n\n📝 Monitoring Instructions:\n";
echo "===========================\n";
echo "1. Check Laravel logs:\n";
echo "   tail -f storage/logs/laravel.log | grep -i rebound\n\n";
echo "2. Run queue worker (if not already running):\n";
echo "   php artisan queue:work --tries=3 --timeout=60\n\n";
echo "3. Check rebound events in cache (Tinker):\n";
echo "   php artisan tinker\n";
echo "   use Illuminate\\Support\\Facades\\Cache;\n";
echo "   \$events = Cache::get('rebound_events_NVDA_' . now()->format('Y-m-d'), []);\n";
echo "   dd(\$events);\n\n";

echo "\n✨ Test Complete!\n";
echo "Jobs have been dispatched to the queue.\n";
echo "Run the queue worker to process them.\n\n";
