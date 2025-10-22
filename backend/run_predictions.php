<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Stock;
use App\Services\PredictionService;

$symbols = ['AVGO','NVDA','MSFT','TSLA','AMD'];
$svc = app(PredictionService::class);

function println($s=''){ echo $s.PHP_EOL; }

println("Batch Prediction (v6) - Today");
println(str_repeat('=', 60));
foreach ($symbols as $sym) {
    try {
        $stock = Stock::where('symbol', $sym)->first();
        if (!$stock) { println("$sym: not found"); continue; }
        $pred = $svc->getPredictionForHorizon($stock, 'today', 'v6');

        $label = $pred['label'] ?? 'N/A';
        $move = $pred['expected_pct_move'] ?? null;
        $prob = isset($pred['probability']) ? round($pred['probability']*100,1) : null;
        $us = $pred['us_factors'] ?? [];
        $gm = $pred['global_markets'] ?? [];
        $sent = $pred['scores']['sentiment'] ?? null;
        $tags = isset($pred['tags']) ? implode(', ', $pred['tags']) : '';

        println("Symbol: $sym");
        println("  Label         : $label");
        println("  Expected Move : " . ($move !== null ? sprintf('%+.2f%%', $move) : 'N/A'));
        println("  Confidence    : " . ($prob !== null ? $prob.'%' : 'N/A'));
        println("  US Factors    : SPX=".($us['sp500_change']??'N/A')."% NDX=".($us['nasdaq_change']??'N/A')."% R2K=".($us['russell_2000_change']??'N/A')."% TY10=".($us['treasury_yield_10y']??'N/A'));
        println("  Global Mkts   : EU=".($gm['european_influence_score']??'N/A')." AS=".($gm['asian_influence_score']??'N/A'));
        println("  Sentiment S   : ".($sent!==null?number_format($sent,3):'N/A'));
        if ($tags) println("  Tags          : $tags");
        
        // Show contributions breakdown
        if (isset($pred['contributions'])) {
            println("  Contributions:");
            foreach ($pred['contributions'] as $factor => $val) {
                if ($factor !== 'composite') {
                    $w = ($pred['weights'][$factor] ?? 0) * 100;
                    println("    ".str_pad($factor, 14).sprintf(": %+.4f (weight %.0f%%)", $val, $w));
                }
            }
            println("    Composite     : ".sprintf("%+.4f", $pred['contributions']['composite']??0));
        }
        println();
    } catch (Throwable $e) {
        println("$sym: ERROR - ".$e->getMessage());
    }
}
