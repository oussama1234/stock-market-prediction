<?php

namespace App\Console\Commands;

use App\Models\Stock;
use App\Models\StockPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SyncPreviousCloses Command
 * 
 * DEV MODE ONLY: Updates previous_close to match current close price
 * Useful for testing when you want to simulate end-of-day state
 * where previous_close equals today's close.
 * 
 * Usage:
 * php artisan stocks:sync-previous-closes
 * php artisan stocks:sync-previous-closes --date=2025-10-14
 */
class SyncPreviousCloses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stocks:sync-previous-closes
                          {--date= : Specific date to sync (YYYY-MM-DD format, defaults to today)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '[DEV MODE] Sync previous_close to match current close price for testing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->warn('⚠️  DEV MODE: Syncing previous_close with current close prices');
        $this->info('🕐 Starting at ' . now()->timezone('America/New_York')->format('Y-m-d H:i:s T'));
        
        // Get the target date (today by default, or specified date)
        $targetDate = $this->option('date') ? $this->option('date') : now()->toDateString();
        
        $this->info("📅 Target date: {$targetDate}");
        $this->newLine();
        
        try {
            // Update all stock prices for the target date
            // Set previous_close = close
            $affectedRows = DB::table('stock_prices')
                ->where(DB::raw('DATE(price_date)'), $targetDate)
                ->whereNotNull('close')
                ->update([
                    'previous_close' => DB::raw('close'),
                    'updated_at' => now(),
                ]);
            
            $this->newLine();
            $this->info("✅ Successfully synced {$affectedRows} stock price records");
            
            // Show sample of updated records
            $this->newLine();
            $this->info('📊 Sample of updated records:');
            
            $samples = DB::table('stock_prices as sp')
                ->join('stocks as s', 'sp.stock_id', '=', 's.id')
                ->select('s.symbol', 's.name', 'sp.previous_close', 'sp.close')
                ->where(DB::raw('DATE(sp.price_date)'), $targetDate)
                ->orderBy('s.symbol')
                ->limit(5)
                ->get();
            
            if ($samples->count() > 0) {
                $this->table(
                    ['Symbol', 'Name', 'Previous Close', 'Close'],
                    $samples->map(function ($item) {
                        return [
                            $item->symbol,
                            $item->name,
                            '$' . number_format($item->previous_close, 2),
                            '$' . number_format($item->close, 2),
                        ];
                    })
                );
            }
            
            Log::info('Previous close sync completed (DEV MODE)', [
                'target_date' => $targetDate,
                'affected_rows' => $affectedRows,
            ]);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            Log::error('Previous close sync failed', [
                'target_date' => $targetDate,
                'error' => $e->getMessage(),
            ]);
            
            return Command::FAILURE;
        }
    }
}
