<?php

namespace App\Console\Commands;

use App\Services\MarketIndexService;
use Illuminate\Console\Command;

class InitializeMarketIndices extends Command
{
    protected $signature = 'market:init-indices';
    protected $description = 'Initialize market indices (S&P 500, NASDAQ, DOW) in the database';

    public function handle(MarketIndexService $marketIndexService): int
    {
        $this->info('Initializing market indices...');
        
        try {
            $marketIndexService->initializeIndices();
            $this->info('✅ Market indices initialized successfully!');
            
            // Also update them with current data
            $this->info('Fetching current market data...');
            $results = $marketIndexService->updateAllIndices();
            
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Success', $results['success']],
                    ['Failed', $results['failed']],
                ]
            );
            
            if (!empty($results['errors'])) {
                $this->warn('Errors encountered:');
                foreach ($results['errors'] as $error) {
                    $this->line("  • {$error}");
                }
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to initialize market indices: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
