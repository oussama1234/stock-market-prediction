<?php

namespace App\Jobs;

use App\Models\Stock;
use App\Services\NewsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchNewsArticlesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ?Stock $stock;
    protected bool $isMarketNews;

    /**
     * Create a new job instance.
     */
    public function __construct(?Stock $stock = null, bool $isMarketNews = false)
    {
        $this->stock = $stock;
        $this->isMarketNews = $isMarketNews;
    }

    /**
     * Execute the job.
     */
    public function handle(NewsService $newsService): void
    {
        try {
            if ($this->isMarketNews) {
                Log::info("Fetching market news");
                $news = $newsService->getMarketNews(20);
                Log::info("Successfully fetched " . count($news) . " market news articles");
                
                // Market news articles don't have a specific stock_id
                // Store them with null stock_id for general market news
                foreach ($news as $article) {
                    $newsService->storeArticle($article, null);
                }
                
            } elseif ($this->stock) {
                Log::info("Fetching news for {$this->stock->symbol}");
                $news = $newsService->getStockNews($this->stock->symbol, 20); // Increased to 20
                Log::info("Successfully fetched " . count($news) . " news articles for {$this->stock->symbol}");
                
                // CRITICAL: Store articles in database with stock_id and sentiment
                $stored = $newsService->bulkStoreForStock($this->stock, $news);
                Log::info("Stored {$stored} articles in database for {$this->stock->symbol}");
                
                // Dispatch sentiment analysis job for new articles
                if ($stored > 0) {
                    \App\Jobs\AnalyzeNewsSentimentJob::dispatch($this->stock)
                        ->delay(now()->addSeconds(10));
                    Log::info("Dispatched sentiment analysis job for {$this->stock->symbol}");
                }
                
            } else {
                Log::warning("No stock specified and not market news");
                return;
            }
            
        } catch (\Exception $e) {
            $context = $this->isMarketNews ? 'market news' : "news for {$this->stock->symbol}";
            Log::error("Failed to fetch {$context}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 120;
}
