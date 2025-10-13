<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Services\NewsService;

echo "=== TSM News Analysis ===" . PHP_EOL . PHP_EOL;

$newsService = app(NewsService::class);

// Get TSM news
$news = $newsService->getStockNews('TSM', 10);

echo "Found " . count($news) . " news articles for TSM" . PHP_EOL . PHP_EOL;

// Show recent articles
foreach (array_slice($news, 0, 5) as $i => $article) {
    echo "Article " . ($i + 1) . ":" . PHP_EOL;
    echo "  Title: " . ($article['title'] ?? 'N/A') . PHP_EOL;
    echo "  Published: " . ($article['published_at'] ?? 'N/A') . PHP_EOL;
    echo "  Source: " . ($article['source'] ?? 'N/A') . PHP_EOL;
    
    // Check for keywords
    $text = strtolower(($article['title'] ?? '') . ' ' . ($article['description'] ?? ''));
    $foundKeywords = [];
    
    $checkKeywords = [
        'rally', 'surge', 'recovery', 'investment', 'jump', 'soar',
        'plunge', 'crash', 'drop', 'fall', 'decline', 'down'
    ];
    
    foreach ($checkKeywords as $kw) {
        if (str_contains($text, $kw)) {
            $foundKeywords[] = $kw;
        }
    }
    
    if (!empty($foundKeywords)) {
        echo "  Keywords: " . implode(', ', $foundKeywords) . PHP_EOL;
    }
    
    echo PHP_EOL;
}

echo "=== Analysis ===" . PHP_EOL;
echo "The news override might be triggered by old or misleading headlines." . PHP_EOL;
echo "TSM is currently DOWN -6.41% but news contains bullish keywords." . PHP_EOL;
echo "This suggests the articles are talking about past rallies or future expectations," . PHP_EOL;
echo "not the current price action." . PHP_EOL;

echo PHP_EOL;
echo "=== Complete ===" . PHP_EOL;
