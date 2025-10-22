<?php

namespace App\Console\Commands;

use App\Services\MarketIndexService;
use Illuminate\Console\Command;

class UpdateMarketIndices extends Command
{
    protected $signature = 'market:update-indices';
    protected $description = 'Update market indices (S&P 500, NASDAQ, DOW, Russell 2000) with current data';

    public function handle(MarketIndexService $marketIndexService): int
    {
        $this->info('Updating market indices...');
        
        try {
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
            
            // Display current values
            $this->newLine();
            $this->info('Current Market Indices:');
            $indices = $marketIndexService->getAllIndices();
            
            $rows = [];
            foreach ($indices as $key => $data) {
                $chg = $data['change_percent'] ?? 0;
                $sign = $chg >= 0 ? '+' : '';
                $rows[] = [
                    strtoupper($key),
                    '$' . number_format($data['current_price'] ?? 0, 2),
                    $sign . number_format($chg, 2) . '%',
                    $data['last_updated'] ?? 'N/A'
                ];
            }
            
            $this->table(
                ['Index', 'Price', 'Change', 'Updated'],
                $rows
            );
            
            if ($results['failed'] == 0) {
                $this->info('✅ All market indices updated successfully!');
                return Command::SUCCESS;
            } else {
                $this->warn('⚠️  Some indices failed to update.');
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $this->error('Failed to update market indices: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
