<?php
/**
 * Test Yahoo Finance API directly
 * Run: docker exec market-prediction-php-fpm php test-yahoo-api.php AAPL
 */

require __DIR__ . '/vendor/autoload.php';

$symbol = $argv[1] ?? 'AAPL';

echo "🔍 Testing Yahoo Finance API for {$symbol}...\n\n";

// Test 1: Quote API
echo "📊 Test 1: Quote API\n";
echo "URL: https://query1.finance.yahoo.com/v7/finance/quote?symbols={$symbol}\n";
$quoteUrl = "https://query1.finance.yahoo.com/v7/finance/quote?symbols={$symbol}";
$quoteResponse = file_get_contents($quoteUrl);
$quoteData = json_decode($quoteResponse, true);

if (isset($quoteData['quoteResponse']['result'][0])) {
    echo "✅ Quote API working!\n";
    $quote = $quoteData['quoteResponse']['result'][0];
    echo "   Price: " . ($quote['regularMarketPrice'] ?? 'N/A') . "\n";
    echo "   Market State: " . ($quote['marketState'] ?? 'N/A') . "\n\n";
} else {
    echo "❌ Quote API failed!\n";
    echo "Response: " . substr($quoteResponse, 0, 500) . "\n\n";
}

// Test 2: Fundamentals API
echo "💰 Test 2: Fundamentals API\n";
echo "URL: https://query2.finance.yahoo.com/v10/finance/quoteSummary/{$symbol}?modules=defaultKeyStatistics,financialData,summaryDetail\n";
$fundUrl = "https://query2.finance.yahoo.com/v10/finance/quoteSummary/{$symbol}?modules=defaultKeyStatistics,financialData,summaryDetail";

$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]
]);

$fundResponse = @file_get_contents($fundUrl, false, $context);

if ($fundResponse === false) {
    echo "❌ Fundamentals API failed - Connection error!\n";
    echo "Error: " . error_get_last()['message'] . "\n\n";
    
    echo "💡 Possible solutions:\n";
    echo "1. Yahoo Finance may be blocking requests from your IP\n";
    echo "2. Try adding User-Agent header\n";
    echo "3. Consider using a proxy or alternative API\n";
    echo "4. Check if docker container has internet access\n\n";
    
} else {
    $fundData = json_decode($fundResponse, true);
    
    if (isset($fundData['quoteSummary']['result'][0])) {
        echo "✅ Fundamentals API working!\n\n";
        
        $result = $fundData['quoteSummary']['result'][0];
        $stats = $result['defaultKeyStatistics'] ?? [];
        $financial = $result['financialData'] ?? [];
        $summary = $result['summaryDetail'] ?? [];
        
        echo "📈 Retrieved Data:\n";
        echo "   P/E Ratio: " . ($stats['trailingPE']['raw'] ?? 'N/A') . "\n";
        echo "   P/B Ratio: " . ($stats['priceToBook']['raw'] ?? 'N/A') . "\n";
        echo "   EPS Growth: " . (($stats['earningsQuarterlyGrowth']['raw'] ?? 0) * 100) . "%\n";
        echo "   Revenue Growth: " . (($financial['revenueGrowth']['raw'] ?? 0) * 100) . "%\n";
        echo "   ROE: " . (($financial['returnOnEquity']['raw'] ?? 0) * 100) . "%\n";
        echo "   Profit Margin: " . (($financial['profitMargins']['raw'] ?? 0) * 100) . "%\n";
        echo "   Debt/Equity: " . ($financial['debtToEquity']['raw'] ?? 'N/A') . "\n";
        echo "   Dividend Yield: " . (($summary['dividendYield']['raw'] ?? 0) * 100) . "%\n\n";
        
        echo "✅ All data looks good!\n\n";
        
    } else {
        echo "❌ Fundamentals API returned empty result!\n";
        echo "Response (first 500 chars): " . substr($fundResponse, 0, 500) . "\n\n";
        
        if (isset($fundData['quoteSummary']['error'])) {
            echo "API Error: " . json_encode($fundData['quoteSummary']['error'], JSON_PRETTY_PRINT) . "\n\n";
        }
    }
}

// Test 3: Network connectivity
echo "🌐 Test 3: Network Connectivity\n";
$testUrls = [
    'https://www.google.com',
    'https://finance.yahoo.com',
];

foreach ($testUrls as $url) {
    $headers = @get_headers($url);
    $status = $headers ? strstr($headers[0], '200') !== false : false;
    echo "   {$url}: " . ($status ? "✅ Reachable" : "❌ Not reachable") . "\n";
}

echo "\n";
