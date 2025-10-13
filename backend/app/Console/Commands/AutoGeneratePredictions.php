<?php

namespace App\Console\Commands;

use App\Jobs\AutoGeneratePredictionsJob;
use Illuminate\Console\Command;

class AutoGeneratePredictions extends Command
{
    protected $signature = 'predictions:auto-generate {--sync : Run synchronously instead of dispatching to queue}';
    protected $description = 'Automatically generate predictions for all stocks that need updates';

    public function handle(): int
    {
        $this->info('🚀 Starting automatic prediction generation...');
        
        if ($this->option('sync')) {
            $this->warn('⚡ Running synchronously (not using queue)');
            $this->newLine();
            
            try {
                $job = new AutoGeneratePredictionsJob();
                $job->handle(app(\App\Services\EnhancedPredictionService::class));
                
                $this->newLine();
                $this->info('✅ Automatic prediction generation completed!');
                $this->info('📋 Check logs for detailed results');
                
                return Command::SUCCESS;
            } catch (\Exception $e) {
                $this->error('❌ Failed: ' . $e->getMessage());
                return Command::FAILURE;
            }
        }
        
        // Dispatch to queue
        AutoGeneratePredictionsJob::dispatch();
        
        $this->newLine();
        $this->info('✅ Job dispatched to queue successfully!');
        $this->info('📋 Monitor with: php artisan queue:work');
        $this->info('📝 Check logs for progress and results');
        
        return Command::SUCCESS;
    }
}
