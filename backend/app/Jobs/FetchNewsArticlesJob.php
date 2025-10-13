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
            } elseif ($this->stock) {
                Log::info("Fetching news for {$this->stock->symbol}");
                $news = $newsService->getStockNews($this->stock->symbol, 10);
                Log::info("Successfully fetched " . count($news) . " news articles for {$this->stock->symbol}");
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
