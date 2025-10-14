<?php

namespace App\Console\Commands;

use App\Models\Stock;
use App\Models\StockPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PersistPreviousCloses Command
 * 
 * Runs at 2 AM ET to persist yesterday's closing prices as the previous_close
 * for today's trading session. This ensures accurate price change calculations.
 * 
 * Usage:
 * php artisan stocks:persist-previous-closes
 */
class PersistPreviousCloses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stocks:persist-previous-closes
                          {--date= : Specific date to process (YYYY-MM-DD format, defaults to today)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Persist previous day closing prices as previous_close for the new trading day';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🕐 Starting previous close persistence at ' . now()->timezone('America/New_York')->format('Y-m-d H:i:s T'));
        
        // Get the target date (today by default, or specified date)
        $targetDate = $this->option('date') ? $this->option('date') : now()->toDateString();
        $targetDateObj = \Carbon\Carbon::parse($targetDate);
        
        $this->info("📅 Target date: {$targetDate}");
        
        // Get all stocks
        $stocks = Stock::all();
        $totalStocks = $stocks->count();
        
        if ($totalStocks === 0) {
            $this->warn('⚠️  No stocks found in database.');
            return Command::SUCCESS;
        }
        
        $this->info("📊 Processing {$totalStocks} stocks...");
        $this->newLine();
        
        $bar = $this->output->createProgressBar($totalStocks);
        $bar->setFormat('verbose');
        $bar->start();
        
        $successCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $errors = [];
        
        foreach ($stocks as $stock) {
            try {
                // Find the most recent close price BEFORE target date
                $previousPrice = StockPrice::where('stock_id', $stock->id)
                    ->where('interval', '1day')
                    ->where('price_date', '<', $targetDate)
                    ->whereNotNull('close')
                    ->where('close', '>', 0)
                    ->orderBy('price_date', 'desc')
                    ->first();
                
                if (!$previousPrice) {
                    $skippedCount++;
                    $bar->advance();
                    continue;
                }
                
                $previousClose = (float) $previousPrice->close;
                
                // Check if record already exists for today
                $existingRecord = StockPrice::where('stock_id', $stock->id)
                    ->where('price_date', $targetDate)
                    ->where('interval', '1day')
                    ->first();
                
                if ($existingRecord) {
                    // Update only previous_close if record exists
                    $existingRecord->update([
                        'previous_close' => $previousClose,
                    ]);
                } else {
                    // Create new record with previous_close and use it as placeholder for close
                    // (close field is NOT NULL in DB, so we need a placeholder value)
                    // The actual close will be updated during market hours
                    StockPrice::create([
                        'stock_id' => $stock->id,
                        'price_date' => $targetDate,
                        'interval' => '1day',
                        'previous_close' => $previousClose,
                        'close' => $previousClose, // Placeholder - will be updated during market hours
                        'source' => 'previous_close_persist',
                    ]);
                }
                
                $successCount++;
                
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = "{$stock->symbol}: {$e->getMessage()}";
                Log::error("Failed to persist previous close for {$stock->symbol}: {$e->getMessage()}");
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        // Display summary
        $this->info('✅ Previous close persistence completed!');
        $this->newLine();
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Stocks', $totalStocks],
                ['Successfully Persisted', $successCount],
                ['Skipped (No Previous Data)', $skippedCount],
                ['Errors', $errorCount],
            ]
        );
        
        if (!empty($errors)) {
            $this->newLine();
            $this->warn('⚠️  Errors encountered:');
            foreach (array_slice($errors, 0, 10) as $error) {
                $this->line("  • {$error}");
            }
            if (count($errors) > 10) {
                $this->line('  ... and ' . (count($errors) - 10) . ' more');
            }
        }
        
        Log::info('Previous close persistence completed', [
            'target_date' => $targetDate,
            'total' => $totalStocks,
            'success' => $successCount,
            'skipped' => $skippedCount,
            'errors' => $errorCount,
        ]);
        
        return Command::SUCCESS;
    }
}
