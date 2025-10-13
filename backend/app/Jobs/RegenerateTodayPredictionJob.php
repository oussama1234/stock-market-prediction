<?php

namespace App\Jobs;

use App\Services\StockDetailsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RegenerateTodayPredictionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $symbol,
        protected string $horizon = 'today'
    ) {}

    public int $tries = 3;
    public int $backoff = 60;

    public function handle(StockDetailsService $detailsService): void
    {
        try {
            $result = $detailsService->regenerateToday($this->symbol, $this->horizon);
            Cache::put("regen_result:{$this->symbol}:{$this->horizon}", $result, 120);
        } catch (\Throwable $e) {
            Log::error("RegenerateTodayPredictionJob failed for {$this->symbol}: " . $e->getMessage());
            throw $e;
        }
    }
}
