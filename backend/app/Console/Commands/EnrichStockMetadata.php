<?php

namespace App\Console\Commands;

use App\Models\Stock;
use App\Services\ApiClients\FinnhubClient;
use App\Services\ApiClients\AlphaVantageClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EnrichStockMetadata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stocks:enrich-metadata {symbol?} {--all} {--missing-only} {--logos-only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enrich stock metadata (logos, descriptions, market cap, etc.) from APIs';

    protected FinnhubClient $finnhub;
    protected AlphaVantageClient $alphaVantage;

    public function __construct(FinnhubClient $finnhub, AlphaVantageClient $alphaVantage)
    {
        parent::__construct();
        $this->finnhub = $finnhub;
        $this->alphaVantage = $alphaVantage;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $symbol = $this->argument('symbol');
        $all = $this->option('all');
        $missingOnly = $this->option('missing-only');
        $logosOnly = $this->option('logos-only');

        if ($symbol) {
            // Update specific stock
            $stock = Stock::where('symbol', strtoupper($symbol))->first();
            
            if (!$stock) {
                $this->error("Stock not found: {$symbol}");
                return 1;
            }
            
            $this->info("Enriching metadata for {$stock->symbol}...");
            $this->enrichStock($stock, $logosOnly);
            $this->info("✓ Enriched {$stock->symbol}");
            
        } elseif ($all) {
            // Update all stocks or only those with missing data
            $query = Stock::query();
            
            if ($missingOnly) {
                $query->where(function($q) use ($logosOnly) {
                    if ($logosOnly) {
                        $q->whereNull('logo_url');
                    } else {
                        $q->whereNull('logo_url')
                          ->orWhereNull('description')
                          ->orWhereNull('market_cap')
                          ->orWhereNull('website')
                          ->orWhereNull('industry');
                    }
                });
            }
            
            $stocks = $query->get();
            
            if ($stocks->isEmpty()) {
                $this->info("No stocks need enriching.");
                return 0;
            }
            
            $this->info("Enriching {$stocks->count()} stocks...");
            $progressBar = $this->output->createProgressBar($stocks->count());
            $progressBar->start();
            
            $updated = 0;
            $failed = 0;
            
            foreach ($stocks as $stock) {
                try {
                    if ($this->enrichStock($stock, $logosOnly)) {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $failed++;
                    Log::error("Failed to enrich {$stock->symbol}: " . $e->getMessage());
                }
                
                $progressBar->advance();
                
                // Rate limiting: wait 1 second between requests
                sleep(1);
            }
            
            $progressBar->finish();
            $this->newLine();
            $this->info("✓ Enriched: {$updated}");
            if ($failed > 0) {
                $this->warn("✗ Failed: {$failed}");
            }
            
        } else {
            $this->error('Please specify a symbol or use --all flag');
            $this->info('Examples:');
            $this->info('  php artisan stocks:enrich-metadata AAPL');
            $this->info('  php artisan stocks:enrich-metadata --all --missing-only');
            $this->info('  php artisan stocks:enrich-metadata --all --logos-only --missing-only');
            return 1;
        }

        return 0;
    }

    /**
     * Enrich a single stock's metadata
     */
    protected function enrichStock(Stock $stock, bool $logosOnly = false): bool
    {
        // Try Finnhub first
        $profile = $this->finnhub->getCompanyProfile($stock->symbol);
        
        // Fallback to Alpha Vantage
        if (!$profile) {
            $profile = $this->alphaVantage->getCompanyOverview($stock->symbol);
        }
        
        if (!$profile) {
            Log::warning("Could not fetch profile for {$stock->symbol}");
            return false;
        }
        
        $updateData = ['last_fetched_at' => now()];
        
        if ($logosOnly) {
            // Only update logo
            if (!$stock->logo_url && isset($profile['logo_url'])) {
                $updateData['logo_url'] = $profile['logo_url'];
            }
        } else {
            // Update all missing fields
            if (!$stock->name && isset($profile['name'])) {
                $updateData['name'] = $profile['name'];
            }
            if (!$stock->logo_url && isset($profile['logo_url'])) {
                $updateData['logo_url'] = $profile['logo_url'];
            }
            if (!$stock->description && isset($profile['description'])) {
                $updateData['description'] = $profile['description'];
            }
            if (!$stock->website && isset($profile['website'])) {
                $updateData['website'] = $profile['website'];
            }
            if (!$stock->industry && isset($profile['industry'])) {
                $updateData['industry'] = $profile['industry'];
            }
            if (!$stock->sector && isset($profile['sector'])) {
                $updateData['sector'] = $profile['sector'];
            }
            if (!$stock->market_cap && isset($profile['market_cap'])) {
                $updateData['market_cap'] = $profile['market_cap'];
            }
            if (!$stock->shares_outstanding && isset($profile['shares_outstanding'])) {
                $updateData['shares_outstanding'] = $profile['shares_outstanding'];
            }
            if (!$stock->exchange && isset($profile['exchange'])) {
                $updateData['exchange'] = $profile['exchange'];
            }
            if (!$stock->country && isset($profile['country'])) {
                $updateData['country'] = $profile['country'];
            }
        }
        
        if (count($updateData) > 1) { // More than just last_fetched_at
            $stock->update($updateData);
            Log::info("Enriched {$stock->symbol}", ['fields' => array_keys($updateData)]);
            return true;
        }
        
        return false;
    }
}
