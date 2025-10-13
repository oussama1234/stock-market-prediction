<?php

namespace App\Console\Commands;

use App\Jobs\FetchStockDataJob;
use App\Jobs\FetchNewsArticlesJob;
use App\Jobs\GeneratePredictionsJob;
use App\Models\Stock;
use Illuminate\Console\Command;

class UpdateStockData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stocks:update {--stocks : Update stock prices} {--news : Fetch news} {--predictions : Generate predictions} {--all : Do everything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update stock data, fetch news, and generate predictions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $updateStocks = $this->option('stocks') || $this->option('all');
        $fetchNews = $this->option('news') || $this->option('all');
        $generatePredictions = $this->option('predictions') || $this->option('all');

        // If no options specified, show help
        if (!$updateStocks && !$fetchNews && !$generatePredictions) {
            $this->info('Please specify what to update:');
            $this->info('  --stocks         Update stock prices');
            $this->info('  --news          Fetch news articles');
            $this->info('  --predictions   Generate predictions');
            $this->info('  --all           Do everything');
            return 1;
        }

        // Get all stocks to update
        $stocks = Stock::query()
            ->where('last_fetched_at', '<', now()->subHours(1))
            ->orWhereNull('last_fetched_at')
            ->limit(20)
            ->get();

        if ($stocks->isEmpty()) {
            $this->warn('No stocks found to update');
            return 0;
        }

        $this->info("Processing {$stocks->count()} stocks...");

        $jobsDispatched = 0;

        foreach ($stocks as $stock) {
            if ($updateStocks) {
                FetchStockDataJob::dispatch($stock);
                $jobsDispatched++;
            }

            if ($fetchNews) {
                FetchNewsArticlesJob::dispatch($stock);
                $jobsDispatched++;
            }

            if ($generatePredictions) {
                GeneratePredictionsJob::dispatch($stock);
                $jobsDispatched++;
            }
        }

        // Also fetch market news
        if ($fetchNews) {
            FetchNewsArticlesJob::dispatch(null, true);
            $jobsDispatched++;
        }

        $this->info("Dispatched {$jobsDispatched} jobs to the queue");
        $this->info('Jobs will be processed by queue workers');

        return 0;
    }
}
