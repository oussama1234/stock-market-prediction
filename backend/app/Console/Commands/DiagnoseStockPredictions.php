<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ApiClients\FinnhubClient;
use App\Services\ApiClients\AlphaVantageClient;
use App\Services\ApiClients\NewsAPIClient;
use App\Services\StockService;
use App\Models\Stock;
use App\Models\StockPrice;
use App\Models\Prediction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DiagnoseStockPredictions extends Command
{
    protected $signature = 'stocks:diagnose {symbol?} {--fix : Automatically fix issues} {--fetch-history : Fetch historical data for all stocks}';
    protected $description = 'Diagnose stock prediction system - check API connections, historical data, and predictions';

    protected FinnhubClient $finnhub;
    protected AlphaVantageClient $alphaVantage;
    protected NewsAPIClient $newsApi;
    protected StockService $stockService;

    public function __construct(
        FinnhubClient $finnhub,
        AlphaVantageClient $alphaVantage,
        NewsAPIClient $newsApi,
        StockService $stockService
    ) {
        parent::__construct();
        $this->finnhub = $finnhub;
        $this->alphaVantage = $alphaVantage;
        $this->newsApi = $newsApi;
        $this->stockService = $stockService;
    }

    public function handle()
    {
        $this->info('========================================');
        $this->info('STOCK PREDICTION SYSTEM DIAGNOSTICS');
        $this->info('========================================');
        $this->newLine();

        // Step 1: Test API Connections
        $this->testApiConnections();
        $this->newLine();

        // Step 2: Check Database
        $this->checkDatabase();
        $this->newLine();

        // Step 3: Audit Historical Data
        if ($symbol = $this->argument('symbol')) {
            $this->auditSingleStock($symbol);
        } else {
            $this->auditAllStocks();
        }
        $this->newLine();

        // Step 4: Test Prediction Generation
        if ($symbol = $this->argument('symbol')) {
            $this->testPrediction($symbol);
        }

        $this->newLine();
        $this->info('========================================');
        $this->info('DIAGNOSTICS COMPLETE');
        $this->info('========================================');
    }

    protected function testApiConnections()
    {
        $this->info('📡 Testing API Connections...');
        $this->newLine();

        // Test Finnhub
        $this->info('1. Testing Finnhub API...');
        try {
            $quote = $this->finnhub->getQuote('AAPL');
            if ($quote && isset($quote['current_price'])) {
                $this->info("   ✓ Finnhub API working (AAPL: $" . $quote['current_price'] . ")");
            } else {
                $this->error('   ✗ Finnhub API returned invalid data');
            }
        } catch (\Exception $e) {
            $this->error("   ✗ Finnhub API failed: {$e->getMessage()}");
        }

        // Test Alpha Vantage
        $this->info('2. Testing Alpha Vantage API...');
        try {
            $quote = $this->alphaVantage->getGlobalQuote('AAPL');
            if ($quote && isset($quote['current_price'])) {
                $this->info("   ✓ Alpha Vantage API working (AAPL: $" . $quote['current_price'] . ")");
            } else {
                $this->error('   ✗ Alpha Vantage API returned invalid data');
            }
        } catch (\Exception $e) {
            $this->error("   ✗ Alpha Vantage API failed: {$e->getMessage()}");
        }

        // Test NewsAPI
        $this->info('3. Testing NewsAPI...');
        try {
            $news = $this->newsApi->getMarketNews();
            if (is_array($news) && count($news) > 0) {
                $this->info("   ✓ NewsAPI working (" . count($news) . " articles fetched)");
            } else {
                $this->error('   ✗ NewsAPI returned no data');
            }
        } catch (\Exception $e) {
            $this->error("   ✗ NewsAPI failed: {$e->getMessage()}");
        }
    }

    protected function checkDatabase()
    {
        $this->info('💾 Checking Database...');
        $this->newLine();

        $stockCount = Stock::count();
        $priceCount = StockPrice::count();
        $predictionCount = Prediction::count();

        $this->info("   • Stocks tracked: {$stockCount}");
        $this->info("   • Historical prices stored: {$priceCount}");
        $this->info("   • Predictions generated: {$predictionCount}");

        if ($stockCount > 0) {
            $stocksWithPrices = Stock::has('prices')->count();
            $stocksWithPredictions = Stock::has('predictions')->count();
            
            $this->newLine();
            $this->info("   • Stocks with historical data: {$stocksWithPrices}/{$stockCount}");
            $this->info("   • Stocks with predictions: {$stocksWithPredictions}/{$stockCount}");

            if ($stocksWithPrices < $stockCount) {
                $this->warn("   ⚠ " . ($stockCount - $stocksWithPrices) . " stocks missing historical data!");
            }
        }
    }

    protected function auditAllStocks()
    {
        $this->info('🔍 Auditing All Stocks...');
        $this->newLine();

        $stocks = Stock::all();

        if ($stocks->isEmpty()) {
            $this->warn('   No stocks found in database');
            return;
        }

        $table = [];
        $needsHistoricalData = [];

        foreach ($stocks as $stock) {
            $priceCount = StockPrice::where('stock_id', $stock->id)
                ->where('interval', '1day')
                ->count();

            $latestPrediction = $stock->activePrediction;
            $predictionAge = $latestPrediction ? 
                $latestPrediction->created_at->diffForHumans() : 'Never';

            $status = 'OK';
            if ($priceCount < 20) {
                $status = 'INSUFFICIENT DATA';
                $needsHistoricalData[] = $stock;
            } elseif ($priceCount < 50) {
                $status = 'LIMITED DATA';
            }

            $table[] = [
                $stock->symbol,
                $stock->name ?? 'N/A',
                $priceCount,
                $predictionAge,
                $status
            ];
        }

        $this->table(
            ['Symbol', 'Name', 'Historical Days', 'Last Prediction', 'Status'],
            $table
        );

        if (!empty($needsHistoricalData)) {
            $this->newLine();
            $this->warn('⚠ ' . count($needsHistoricalData) . ' stock(s) need historical data:');
            
            foreach ($needsHistoricalData as $stock) {
                $this->line("   • {$stock->symbol}");
            }

            if ($this->option('fetch-history') || $this->option('fix')) {
                $this->newLine();
                $this->info('📥 Fetching historical data...');
                
                foreach ($needsHistoricalData as $stock) {
                    $this->info("   Fetching data for {$stock->symbol}...");
                    $stored = $this->stockService->fetchHistoricalData($stock, 90);
                    
                    if ($stored > 0) {
                        $this->info("   ✓ Stored {$stored} days for {$stock->symbol}");
                    } else {
                        $this->error("   ✗ Failed to fetch data for {$stock->symbol}");
                    }
                }
            } else {
                $this->newLine();
                $this->comment('💡 Run with --fetch-history to automatically fetch missing data');
            }
        }
    }

    protected function auditSingleStock(string $symbol)
    {
        $this->info("🔍 Detailed Audit for {$symbol}...");
        $this->newLine();

        $stock = Stock::where('symbol', $symbol)->first();

        if (!$stock) {
            $this->warn("   Stock {$symbol} not found in database");
            
            if ($this->option('fix')) {
                $this->info("   Creating stock entry...");
                $stock = $this->stockService->getOrCreateStock($symbol);
                
                if ($stock) {
                    $this->info("   ✓ Stock created: {$stock->name}");
                } else {
                    $this->error("   ✗ Failed to create stock");
                    return;
                }
            } else {
                $this->comment('💡 Run with --fix to create the stock entry');
                return;
            }
        }

        // Show stock info
        $this->info("📊 Stock Information:");
        $this->line("   Symbol: {$stock->symbol}");
        $this->line("   Name: " . ($stock->name ?? 'N/A'));
        $this->line("   Exchange: " . ($stock->exchange ?? 'N/A'));
        $this->newLine();

        // Check historical data
        $prices = StockPrice::where('stock_id', $stock->id)
            ->where('interval', '1day')
            ->orderBy('price_date', 'desc')
            ->get();

        $this->info("📈 Historical Data:");
        $this->line("   Total days: " . $prices->count());
        
        if ($prices->isNotEmpty()) {
            $oldest = $prices->last()->price_date;
            $newest = $prices->first()->price_date;
            $this->line("   Date range: {$oldest} to {$newest}");
        }

        if ($prices->count() < 20) {
            $this->newLine();
            $this->error("   ✗ INSUFFICIENT DATA (need at least 20 days)");
            
            if ($this->option('fix') || $this->option('fetch-history')) {
                $this->info("   📥 Fetching historical data...");
                $stored = $this->stockService->fetchHistoricalData($stock, 90);
                $this->info("   ✓ Stored {$stored} days");
            } else {
                $this->comment('💡 Run with --fix to fetch historical data');
            }
        } else if ($prices->count() < 50) {
            $this->warn("   ⚠ Limited data (recommended: 90 days)");
        } else {
            $this->info("   ✓ Sufficient data");
        }

        $this->newLine();

        // Check predictions
        $predictions = Prediction::where('stock_id', $stock->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $this->info("🔮 Predictions:");
        $this->line("   Total predictions: " . Prediction::where('stock_id', $stock->id)->count());
        
        if ($predictions->isNotEmpty()) {
            $this->newLine();
            foreach ($predictions as $pred) {
                $age = $pred->created_at->diffForHumans();
                $this->line("   • {$age}: {$pred->direction} ({$pred->confidence_score}% confidence)");
                $this->line("     Price: \${$pred->current_price} → \${$pred->predicted_price} ({$pred->predicted_change_percent}%)");
            }
        } else {
            $this->warn("   No predictions found");
        }
    }

    protected function testPrediction(string $symbol)
    {
        $this->newLine();
        $this->info("🧪 Testing Prediction Generation for {$symbol}...");
        $this->newLine();

        try {
            $this->info("   Generating prediction...");
            $prediction = $this->stockService->regeneratePrediction($symbol);

            if ($prediction) {
                $this->info("   ✓ Prediction generated successfully!");
                $this->newLine();
                $this->info("   Results:");
                $this->line("   • Direction: {$prediction['direction']}");
                $this->line("   • Confidence: {$prediction['confidence_score']}%");
                $this->line("   • Current: \${$prediction['current_price']}");
                $this->line("   • Predicted: \${$prediction['predicted_price']} ({$prediction['predicted_change_percent']}%)");
                $this->line("   • Range: \${$prediction['predicted_low']} - \${$prediction['predicted_high']}");
                
                if (isset($prediction['reasoning'])) {
                    $this->newLine();
                    $this->line("   Reasoning: {$prediction['reasoning']}");
                }

                if (isset($prediction['indicators'])) {
                    $this->newLine();
                    $this->info("   Technical Indicators:");
                    $indicators = $prediction['indicators'];
                    
                    if (isset($indicators['technical']['rsi'])) {
                        $this->line("   • RSI: {$indicators['technical']['rsi']['value']} ({$indicators['technical']['rsi']['signal']})");
                    }
                    
                    if (isset($indicators['sentiment']['score'])) {
                        $this->line("   • Sentiment: {$indicators['sentiment']['score']} (from {$indicators['sentiment']['count']} articles)");
                    }
                }
            } else {
                $this->error("   ✗ Failed to generate prediction");
            }
        } catch (\Exception $e) {
            $this->error("   ✗ Error: {$e->getMessage()}");
            $this->line("   " . $e->getTraceAsString());
        }
    }
}
