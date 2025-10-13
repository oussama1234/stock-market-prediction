<?php

namespace App\Console\Commands;

use App\Models\Stock;
use App\Services\StockService;
use Illuminate\Console\Command;

class FetchHistoricalStockData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stocks:fetch-historical {symbol?} {--days=90} {--all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch historical price data for stocks';

    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        parent::__construct();
        $this->stockService = $stockService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $symbol = $this->argument('symbol');
        $days = (int) $this->option('days');
        $all = $this->option('all');

        if ($all) {
            // Fetch for all stocks
            $stocks = Stock::all();
            $this->info("Fetching historical data for {$stocks->count()} stocks...");
            
            $progressBar = $this->output->createProgressBar($stocks->count());
            $progressBar->start();
            
            $totalStored = 0;
            
            foreach ($stocks as $stock) {
                $stored = $this->stockService->fetchHistoricalData($stock, $days);
                $totalStored += $stored;
                $progressBar->advance();
                
                // Rate limiting: wait 1 second between requests
                sleep(1);
            }
            
            $progressBar->finish();
            $this->newLine();
            $this->info("Successfully fetched {$totalStored} total price records for {$stocks->count()} stocks.");
            
        } elseif ($symbol) {
            // Fetch for specific symbol
            $stock = Stock::where('symbol', strtoupper($symbol))->first();
            
            if (!$stock) {
                $this->error("Stock not found: {$symbol}");
                return 1;
            }
            
            $this->info("Fetching {$days} days of historical data for {$stock->symbol}...");
            
            $stored = $this->stockService->fetchHistoricalData($stock, $days);
            
            if ($stored > 0) {
                $this->info("Successfully fetched {$stored} price records for {$stock->symbol}");
            } else {
                $this->warn("No historical data was fetched for {$stock->symbol}");
            }
            
        } else {
            $this->error('Please specify a symbol or use --all flag');
            $this->info('Examples:');
            $this->info('  php artisan stocks:fetch-historical AAPL');
            $this->info('  php artisan stocks:fetch-historical AAPL --days=180');
            $this->info('  php artisan stocks:fetch-historical --all');
            return 1;
        }

        return 0;
    }
}
