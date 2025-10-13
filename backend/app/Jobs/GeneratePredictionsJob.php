<?php

namespace App\Jobs;

use App\Models\Stock;
use App\Services\PredictionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GeneratePredictionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Stock $stock;

    /**
     * Create a new job instance.
     */
    public function __construct(Stock $stock)
    {
        $this->stock = $stock;
    }

    /**
     * Execute the job.
     */
    public function handle(PredictionService $predictionService): void
    {
        try {
            Log::info("Generating prediction for {$this->stock->symbol}");
            
            $prediction = $predictionService->generatePrediction($this->stock);
            
            if ($prediction) {
                Log::info("Successfully generated prediction for {$this->stock->symbol}: {$prediction->direction}");
            } else {
                Log::warning("Could not generate prediction for {$this->stock->symbol}");
            }
            
        } catch (\Exception $e) {
            Log::error("Failed to generate prediction for {$this->stock->symbol}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 180;
}
