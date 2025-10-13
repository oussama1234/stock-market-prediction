<?php

namespace Tests\Unit;

use App\Services\StockDetailsService;
use Tests\TestCase;

class StockDetailsServiceTest extends TestCase
{
    public function test_compute_next_open_uses_previous_close_when_no_premarket(): void
    {
        $svc = new StockDetailsService(app('App\Services\StockService'), app('App\Services\NewsService'), app('App\Services\EnhancedPredictionService'), app('App\Services\ScenarioGeneratorService'));
        $quote = [
            'market_status' => 'closed',
            'previous_close' => 324.65,
            'open' => 345.00, // stale
            'current_price' => 325.10,
        ];
        $next = $svc->computeNextOpen($quote);
        $this->assertEquals(324.65, $next);
    }
}
