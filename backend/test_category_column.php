<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$stock = DB::table('stocks')->where('symbol', 'AVGO')->first();

echo "AVGO Database Record:\n";
echo "ID: {$stock->id}\n";
echo "Symbol: {$stock->symbol}\n";
echo "Category ID: " . ($stock->category_id ?? 'NULL') . "\n";
echo "Category ID Type: " . gettype($stock->category_id ?? 'NULL') . "\n";

echo "\n";

if ($stock->category_id) {
    $category = DB::table('stock_categories')->where('id', $stock->category_id)->first();
    if ($category) {
        echo "Category Found:\n";
        echo "  Name: {$category->name}\n";
        echo "  Volatility Multiplier: {$category->volatility_multiplier}\n";
        echo "  Range: {$category->typical_daily_range_min}% to {$category->typical_daily_range_max}%\n";
    } else {
        echo "❌ Category ID {$stock->category_id} not found in stock_categories table!\n";
    }
}
