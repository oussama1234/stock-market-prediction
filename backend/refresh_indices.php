<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\MarketIndexService;

$svc = app(MarketIndexService::class);

echo "Refreshing Market Indices...\n";
echo str_repeat('=', 60) . "\n";

$result = $svc->updateAllIndices();
echo "Success: " . $result['success'] . "\n";
echo "Failed: " . $result['failed'] . "\n";

if (!empty($result['errors'])) {
    echo "\nErrors:\n";
    foreach ($result['errors'] as $error) {
        echo "  - $error\n";
    }
}

echo "\n\nCurrent Market Indices:\n";
echo str_repeat('=', 60) . "\n";

$indices = $svc->getAllIndices();
foreach ($indices as $key => $data) {
    $chg = $data['change_percent'] ?? 0;
    $sign = $chg >= 0 ? '+' : '';
    echo sprintf("%-15s: $%.2f (%s%.2f%%) - Updated: %s\n", 
        strtoupper($key),
        $data['current_price'] ?? 0,
        $sign,
        $chg,
        $data['last_updated'] ?? 'N/A'
    );
}
