<?php

namespace App\Jobs;

use App\Services\StockDetailsService;
use App\Services\NewsService;
use App\Models\Stock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * NewsTriggeredPredictionJob
 * 
 * Automatically triggered when significant news is detected for a stock
 * Checks for priority keywords (tariff, earnings, contract, etc.) and regenerates prediction
 */
class NewsTriggeredPredictionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $symbol,
        protected array $newsData = [],
        protected string $trigger = 'keyword_detection'
    ) {}

    public int $tries = 2;
    public int $backoff = 30;

    public function handle(StockDetailsService $detailsService, NewsService $newsService): void
    {
        try {
            Log::info("NewsTriggeredPredictionJob started for {$this->symbol}", [
                'trigger' => $this->trigger,
                'news_count' => count($this->newsData),
            ]);

            // Check if we should actually regenerate (cooldown check)
            $cooldownKey = "news_pred_cooldown:{$this->symbol}";
            if (Cache::has($cooldownKey)) {
                Log::info("Skipping news-triggered prediction for {$this->symbol} - cooldown active");
                return;
            }

            // Analyze news sentiment and keywords
            $stock = Stock::where('symbol', $this->symbol)->first();
            if (!$stock) {
                Log::warning("Stock not found for news-triggered prediction: {$this->symbol}");
                return;
            }

            // Get today's news
            $news = $newsService->getStockNews($this->symbol, 30);
            $todayNews = $this->filterTodayNews($news);

            // CRITICAL: Check for important news with surge expectations
            $hasImportantNewsSurge = $this->detectImportantNewsSurge($stock, $todayNews);
            
            // Detect priority keywords
            $hasHighImpactKeywords = $this->detectHighImpactKeywords($todayNews);

            if ($hasImportantNewsSurge || $hasHighImpactKeywords) {
                $reason = $hasImportantNewsSurge ? 'important news surge detected' : 'high-impact keywords detected';
                Log::info("{$reason} for {$this->symbol}, regenerating prediction");
                
                // Regenerate prediction with news override
                $result = $detailsService->regenerateToday($this->symbol, 'today');
                
                // Cache result
                Cache::put("news_pred_result:{$this->symbol}", $result, 300); // 5 minutes
                
                // Set cooldown (5 minutes to avoid spam)
                Cache::put($cooldownKey, true, 300);
                
                Log::info("News-triggered prediction completed for {$this->symbol}", [
                    'prediction' => $result['prediction']['direction'] ?? 'unknown',
                    'override' => $result['override']['applied'] ?? false,
                    'has_surge_news' => $hasImportantNewsSurge,
                ]);
            } else {
                Log::info("No high-impact keywords or surge news found for {$this->symbol}, skipping regeneration");
            }

        } catch (\Throwable $e) {
            Log::error("NewsTriggeredPredictionJob failed for {$this->symbol}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    protected function filterTodayNews(array $articles): array
    {
        $today = now('UTC')->format('Y-m-d');
        return array_values(array_filter($articles, function ($a) use ($today) {
            $d = isset($a['published_at']) ? substr((string)$a['published_at'], 0, 10) : null;
            return $d === $today;
        }));
    }

    protected function detectImportantNewsSurge(Stock $stock, array $articles): bool
    {
        // Check if stock has important news with surge expectation for today
        $importantNewsToday = $stock->newsArticles()
            ->importantToday()
            ->where('expected_surge_percent', '>=', 6.0)
            ->exists();
        
        if ($importantNewsToday) {
            Log::info("Important news surge detected for {$stock->symbol} - regeneration required");
            return true;
        }
        
        return false;
    }
    
    protected function detectHighImpactKeywords(array $articles): bool
    {
        $highImpactKeywords = [
            // Bearish high-impact
            'tariff', 'tariffs', 'ban', 'banned', 'embargo', 'sanction', 'sanctions',
            'lawsuit', 'investigation', 'scandal', 'bankruptcy', 'miss earnings',
            'earnings warning', 'downgrade', 'recall', 'shutdown',
            
            // Bullish high-impact
            'beat earnings', 'record earnings', 'major contract', 'cut tariff',
            'reduce tariff', 'acquisition', 'partnership', 'breakthrough',
            'upgrade', 'buy rating', 'positive outlook',
        ];

        foreach ($articles as $article) {
            $text = strtolower(trim(($article['title'] ?? '') . ' ' . ($article['description'] ?? '')));
            foreach ($highImpactKeywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    Log::info("High-impact keyword detected: {$keyword} in article: {$article['title']}");
                    return true;
                }
            }
        }

        return false;
    }
}
