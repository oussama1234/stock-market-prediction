<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Stock;
use App\Models\NewsArticle;
use App\Services\NewsService;

$stock = Stock::where('symbol', 'TSM')->first();

if (!$stock) {
    echo "TSM stock not found!\n";
    exit(1);
}

echo "Fetching news for TSM...\n";

$newsService = app(NewsService::class);

try {
    // Fetch news articles from APIs
    $articles = $newsService->getStockNews('TSM', 20);
    echo "Fetched " . count($articles) . " news articles for TSM\n\n";
    
    $stored = 0;
    $skipped = 0;
    
    foreach ($articles as $article) {
        echo "  - {$article['title']}\n";
        
        // Store in database
        try {
            $exists = NewsArticle::where('stock_id', $stock->id)
                ->where('url', $article['url'])
                ->exists();
            
            if (!$exists) {
                NewsArticle::create([
                    'stock_id' => $stock->id,
                    'title' => $article['title'],
                    'description' => $article['description'] ?? null,
                    'url' => $article['url'],
                    'image_url' => $article['image_url'] ?? null,
                    'source' => $article['source'] ?? 'Unknown',
                    'published_at' => $article['published_at'] ?? now(),
                    'sentiment_score' => $article['sentiment_score'] ?? null,
                    'is_important' => $article['is_important'] ?? false,
                ]);
                $stored++;
            } else {
                $skipped++;
            }
        } catch (\Exception $e) {
            echo "    [Error storing: {$e->getMessage()}]\n";
        }
    }
    
    echo "\n✅ Results:\n";
    echo "  - Total fetched: " . count($articles) . "\n";
    echo "  - Stored: $stored\n";
    echo "  - Skipped (duplicates): $skipped\n";
    echo "\n✅ Success! Now run:\n";
    echo "   docker exec market-prediction-php-fpm php artisan test:tsm-dispatch --sync --force\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
