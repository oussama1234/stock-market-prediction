<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MarketIndexService;

class UpdateMarketIndices extends Command
{
    protected $signature = 'market:update-indices';
    protected $description = 'Update market indices (S&P 500, NASDAQ, DOW) with latest data';

    public function handle(MarketIndexService $service)
    {
        $this->info('🔄 Updating market indices...');
        
        $results = $service->updateAllIndices();
        
        $this->info("✅ Success: {$results['success']}");
        $this->info("❌ Failed: {$results['failed']}");
        
        if (!empty($results['errors'])) {
            $this->error('Errors:');
            foreach ($results['errors'] as $error) {
                $this->error("  - {$error}");
            }
        }
        
        $this->info('✨ Market indices updated!');
        
        return 0;
    }
}
