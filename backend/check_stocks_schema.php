<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "=== STOCKS TABLE SCHEMA ===\n\n";

$columns = Schema::getColumnListing('stocks');
echo "Columns: " . implode(', ', $columns) . "\n\n";

$avgo = DB::table('stocks')->where('symbol', 'AVGO')->first();
echo "AVGO fields:\n";
foreach ($avgo as $key => $value) {
    if (in_array($key, ['category', 'category_id', 'sector', 'industry'])) {
        echo "  $key: " . var_export($value, true) . "\n";
    }
}
