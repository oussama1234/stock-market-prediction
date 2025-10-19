<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Stock;
use Illuminate\Support\Facades\DB;

echo "=== TESTING STOCK MODEL ===\n\n";

// Test 1: Raw database query
echo "Test 1: Raw DB Query\n";
$raw = DB::table('stocks')->where('symbol', 'AVGO')->first();
echo "Category ID: {$raw->category_id} (type: " . gettype($raw->category_id) . ")\n\n";

// Test 2: Eloquent without relationship
echo "Test 2: Eloquent (no relationship)\n";
$stock = Stock::where('symbol', 'AVGO')->first();
echo "Category ID: {$stock->category_id} (type: " . gettype($stock->category_id) . ")\n";
echo "Category attribute: " . json_encode($stock->category) . "\n\n";

// Test 3: Fresh load with relationship
echo "Test 3: Fresh load with relationship\n";
$stock2 = Stock::with('category')->where('symbol', 'AVGO')->first();
echo "Category loaded: " . ($stock2->relationLoaded('category') ? 'Yes' : 'No') . "\n";
echo "Category type: " . gettype($stock2->category) . "\n";
echo "Category class: " . (is_object($stock2->category) ? get_class($stock2->category) : 'not an object') . "\n";

if (is_object($stock2->category)) {
    echo "Category name: {$stock2->category->name}\n";
} else {
    echo "Category value: " . var_export($stock2->category, true) . "\n";
}
