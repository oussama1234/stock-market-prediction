<?php

require 'bootstrap/app.php';

$stock = \App\Models\Stock::where('symbol', 'AVGO')->first();

if ($stock) {
    $service = app(\App\Services\PredictionService::class);
    
    // Use reflection to access private methods
    $reflection = new ReflectionClass($service);
    
    $methods = ['estimateRevenueGrowth', 'estimateEarningsGrowth', 'estimateMarginChange', 'getAnalystAction', 'getInsiderActivity', 'getPEPercentile'];
    
    echo "=== FEATURE VALUES FOR AVGO ===\n\n";
    
    foreach ($methods as $methodName) {
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        $value = $method->invoke($service, $stock);
        echo ucfirst(str_replace(['estimate', 'get'], '', $methodName)) . ": ";
        if (is_bool($value)) {
            echo ($value ? 'true' : 'false');
        } else {
            echo $value;
        }
        echo "\n";
    }
    
    echo "\n=== VOLUME RATIO TEST ===\n";
    $prices = \App\Models\StockPrice::where('stock_id', $stock->id)
        ->where('interval', '1day')
        ->orderBy('price_date', 'desc')
        ->limit(50)
        ->get();
    
    if ($prices->count() >= 20) {
        $volumes = $prices->pluck('volume')->toArray();
        $volumeSMA = array_sum(array_slice($volumes, -20)) / 20;
        $latestVolume = $volumes[0] ?? 0;
        $ratio = $volumeSMA > 0 ? $latestVolume / $volumeSMA : 1.0;
        echo "Latest Volume: $latestVolume\n";
        echo "20-day Volume SMA: $volumeSMA\n";
        echo "Volume Ratio: $ratio\n";
    }
}
?>
