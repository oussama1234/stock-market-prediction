<?php

namespace Tests\Feature;

use App\Services\AsianMarketService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AsianMarketServiceTest extends TestCase
{
    protected AsianMarketService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AsianMarketService();
    }

    public function test_get_market_data_returns_proper_structure(): void
    {
        // Mock HTTP responses for Asian market APIs
        Http::fake([
            '*yfinance*^N225*' => Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'regularMarketPrice' => 38000.0,
                            'previousClose' => 37500.0,
                        ]
                    ]]
                ]
            ], 200),
            '*yfinance*000001.SS*' => Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'regularMarketPrice' => 3200.0,
                            'previousClose' => 3150.0,
                        ]
                    ]]
                ]
            ], 200),
            '*yfinance*^HSI*' => Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'regularMarketPrice' => 19000.0,
                            'previousClose' => 18800.0,
                        ]
                    ]]
                ]
            ], 200),
        ]);

        $result = $this->service->getMarketData();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('markets', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('influence_score', $result);
        $this->assertArrayHasKey('timestamp', $result);
    }

    public function test_influence_score_is_normalized(): void
    {
        Http::fake([
            '*yfinance*' => Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'regularMarketPrice' => 10000.0,
                            'previousClose' => 9900.0,
                        ]
                    ]]
                ]
            ], 200),
        ]);

        $result = $this->service->getMarketData();

        // Influence score should be between -1 and 1
        $this->assertGreaterThanOrEqual(-1.0, $result['influence_score']);
        $this->assertLessThanOrEqual(1.0, $result['influence_score']);
    }

    public function test_bullish_markets_produce_positive_influence(): void
    {
        // All markets up significantly
        Http::fake([
            '*yfinance*^N225*' => Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'regularMarketPrice' => 40000.0,
                            'previousClose' => 38000.0,  // +5.26%
                        ]
                    ]]
                ]
            ], 200),
            '*yfinance*000001.SS*' => Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'regularMarketPrice' => 3400.0,
                            'previousClose' => 3200.0,  // +6.25%
                        ]
                    ]]
                ]
            ], 200),
            '*yfinance*^HSI*' => Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'regularMarketPrice' => 20000.0,
                            'previousClose' => 19000.0,  // +5.26%
                        ]
                    ]]
                ]
            ], 200),
        ]);

        $result = $this->service->getMarketData();

        $this->assertGreaterThan(0.5, $result['influence_score'], 'Strong bullish markets should produce positive influence > 0.5');
        $this->assertEquals('bullish', strtolower($result['summary']['sentiment']));
    }

    public function test_bearish_markets_produce_negative_influence(): void
    {
        // All markets down significantly
        Http::fake([
            '*yfinance*^N225*' => Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'regularMarketPrice' => 36000.0,
                            'previousClose' => 38000.0,  // -5.26%
                        ]
                    ]]
                ]
            ], 200),
            '*yfinance*000001.SS*' => Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'regularMarketPrice' => 3000.0,
                            'previousClose' => 3200.0,  // -6.25%
                        ]
                    ]]
                ]
            ], 200),
            '*yfinance*^HSI*' => Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'regularMarketPrice' => 18000.0,
                            'previousClose' => 19000.0,  // -5.26%
                        ]
                    ]]
                ]
            ], 200),
        ]);

        $result = $this->service->getMarketData();

        $this->assertLessThan(-0.5, $result['influence_score'], 'Strong bearish markets should produce negative influence < -0.5');
        $this->assertEquals('bearish', strtolower($result['summary']['sentiment']));
    }

    public function test_handles_api_failures_gracefully(): void
    {
        // Simulate API failure
        Http::fake([
            '*' => Http::response(null, 500),
        ]);

        $result = $this->service->getMarketData();

        // Should return neutral influence when API fails
        $this->assertArrayHasKey('influence_score', $result);
        $this->assertEquals(0.0, $result['influence_score']);
        $this->assertArrayHasKey('markets', $result);
    }

    public function test_mixed_markets_produce_moderate_influence(): void
    {
        // Some markets up, some down
        Http::fake([
            '*yfinance*^N225*' => Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'regularMarketPrice' => 39000.0,
                            'previousClose' => 38000.0,  // +2.63%
                        ]
                    ]]
                ]
            ], 200),
            '*yfinance*000001.SS*' => Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'regularMarketPrice' => 3100.0,
                            'previousClose' => 3200.0,  // -3.13%
                        ]
                    ]]
                ]
            ], 200),
            '*yfinance*^HSI*' => Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'regularMarketPrice' => 19100.0,
                            'previousClose' => 19000.0,  // +0.53%
                        ]
                    ]]
                ]
            ], 200),
        ]);

        $result = $this->service->getMarketData();

        // Mixed markets should produce moderate influence
        $this->assertLessThan(0.5, abs($result['influence_score']), 'Mixed markets should produce moderate influence');
    }

    public function test_caching_works(): void
    {
        Http::fake([
            '*yfinance*' => Http::sequence()
                ->push([
                    'chart' => [
                        'result' => [[
                            'meta' => [
                                'regularMarketPrice' => 38000.0,
                                'previousClose' => 37500.0,
                            ]
                        ]]
                    ]
                ], 200)
                ->push(['error' => 'Should not be called'], 500),
        ]);

        // First call - should hit API
        $result1 = $this->service->getMarketData();
        
        // Second call - should use cache
        $result2 = $this->service->getMarketData();

        $this->assertEquals($result1['influence_score'], $result2['influence_score']);
        $this->assertEquals($result1['timestamp'], $result2['timestamp']);
    }

    public function test_summary_stats_are_correct(): void
    {
        Http::fake([
            '*yfinance*^N225*' => Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'regularMarketPrice' => 39000.0,
                            'previousClose' => 38000.0,
                        ]
                    ]]
                ]
            ], 200),
            '*yfinance*000001.SS*' => Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'regularMarketPrice' => 3300.0,
                            'previousClose' => 3200.0,
                        ]
                    ]]
                ]
            ], 200),
            '*yfinance*^HSI*' => Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'regularMarketPrice' => 19500.0,
                            'previousClose' => 19000.0,
                        ]
                    ]]
                ]
            ], 200),
        ]);

        $result = $this->service->getMarketData();

        $this->assertArrayHasKey('avg_change_pct', $result['summary']);
        $this->assertArrayHasKey('markets_up', $result['summary']);
        $this->assertArrayHasKey('markets_down', $result['summary']);
        $this->assertArrayHasKey('sentiment', $result['summary']);

        // All markets are up
        $this->assertEquals(3, $result['summary']['markets_up']);
        $this->assertEquals(0, $result['summary']['markets_down']);
        $this->assertGreaterThan(0, $result['summary']['avg_change_pct']);
    }
}
