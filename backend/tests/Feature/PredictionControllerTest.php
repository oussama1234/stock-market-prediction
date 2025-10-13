<?php

namespace Tests\Feature;

use App\Services\AsianMarketService;
use App\Services\PredictionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PredictionControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure test stock exists
        DB::table('stocks')->insertOrIgnore([
            'symbol' => 'AAPL',
            'name' => 'Apple Inc.',
            'exchange' => 'NASDAQ',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stocks')->insertOrIgnore([
            'symbol' => 'TSLA',
            'name' => 'Tesla Inc.',
            'exchange' => 'NASDAQ',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_predict_today_returns_quick_model_v2_prediction(): void
    {
        // Mock Asian market data
        Http::fake([
            '*yfinance*' => Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'regularMarketPrice' => 38000.0,
                            'previousClose' => 37500.0,
                        ]
                    ]]
                ]
            ], 200),
        ]);

        $response = $this->postJson('/api/predictions/predict', [
            'symbol' => 'AAPL',
            'horizon' => 'today',
        ]);

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Check response structure
        $this->assertArrayHasKey('symbol', $data);
        $this->assertArrayHasKey('horizon', $data);
        $this->assertArrayHasKey('prediction', $data);
        $this->assertArrayHasKey('asian_influence', $data);
        $this->assertArrayHasKey('correction_warning', $data);
        
        // Check prediction structure
        $prediction = $data['prediction'];
        $this->assertArrayHasKey('label', $prediction);
        $this->assertArrayHasKey('probability', $prediction);
        $this->assertArrayHasKey('expected_pct_move', $prediction);
        $this->assertArrayHasKey('top_reasons', $prediction);
        
        // Check Asian influence structure
        $asianInfluence = $data['asian_influence'];
        $this->assertArrayHasKey('score', $asianInfluence);
        $this->assertArrayHasKey('impact_percent', $asianInfluence);
        $this->assertArrayHasKey('markets', $asianInfluence);
        
        // Check correction warning structure
        $correctionWarning = $data['correction_warning'];
        $this->assertArrayHasKey('warning', $correctionWarning);
        $this->assertArrayHasKey('severity', $correctionWarning);
        $this->assertArrayHasKey('reasons', $correctionWarning);
        
        // Validate prediction is binary
        $this->assertContains($prediction['label'], ['BULLISH', 'BEARISH']);
        
        // Validate probability range
        $this->assertGreaterThanOrEqual(0, $prediction['probability']);
        $this->assertLessThanOrEqual(1, $prediction['probability']);
    }

    public function test_predict_week_uses_original_model(): void
    {
        $response = $this->postJson('/api/predictions/predict', [
            'symbol' => 'AAPL',
            'horizon' => 'week',
        ]);

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Week predictions should not have Asian influence or correction warnings
        $this->assertEquals('week', $data['horizon']);
        $this->assertArrayNotHasKey('asian_influence', $data);
        $this->assertArrayNotHasKey('correction_warning', $data);
    }

    public function test_predict_month_uses_original_model(): void
    {
        $response = $this->postJson('/api/predictions/predict', [
            'symbol' => 'AAPL',
            'horizon' => 'month',
        ]);

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Month predictions should not have Asian influence or correction warnings
        $this->assertEquals('month', $data['horizon']);
        $this->assertArrayNotHasKey('asian_influence', $data);
        $this->assertArrayNotHasKey('correction_warning', $data);
    }

    public function test_strong_asian_bearish_influence_on_prediction(): void
    {
        // Mock strong bearish Asian markets
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

        $response = $this->postJson('/api/predictions/predict', [
            'symbol' => 'AAPL',
            'horizon' => 'today',
        ]);

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $asianInfluence = $data['asian_influence'];
        
        // Should have strong negative influence
        $this->assertLessThan(-0.5, $asianInfluence['score']);
        $this->assertGreaterThan(0.3, $asianInfluence['impact_percent']);
        $this->assertEquals('bearish', strtolower($asianInfluence['sentiment']));
    }

    public function test_strong_asian_bullish_influence_on_prediction(): void
    {
        // Mock strong bullish Asian markets
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

        $response = $this->postJson('/api/predictions/predict', [
            'symbol' => 'AAPL',
            'horizon' => 'today',
        ]);

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $asianInfluence = $data['asian_influence'];
        
        // Should have strong positive influence
        $this->assertGreaterThan(0.5, $asianInfluence['score']);
        $this->assertGreaterThan(0.3, $asianInfluence['impact_percent']);
        $this->assertEquals('bullish', strtolower($asianInfluence['sentiment']));
    }

    public function test_invalid_symbol_returns_404(): void
    {
        $response = $this->postJson('/api/predictions/predict', [
            'symbol' => 'INVALID123',
            'horizon' => 'today',
        ]);

        $response->assertStatus(404);
    }

    public function test_missing_symbol_returns_validation_error(): void
    {
        $response = $this->postJson('/api/predictions/predict', [
            'horizon' => 'today',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['symbol']);
    }

    public function test_invalid_horizon_returns_validation_error(): void
    {
        $response = $this->postJson('/api/predictions/predict', [
            'symbol' => 'AAPL',
            'horizon' => 'invalid_horizon',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['horizon']);
    }

    public function test_correction_warning_triggered_for_overbought_conditions(): void
    {
        // This test would require mocking the Python model output
        // For now, we'll test that the structure is correct
        
        Http::fake([
            '*yfinance*' => Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'regularMarketPrice' => 38000.0,
                            'previousClose' => 37500.0,
                        ]
                    ]]
                ]
            ], 200),
        ]);

        $response = $this->postJson('/api/predictions/predict', [
            'symbol' => 'AAPL',
            'horizon' => 'today',
        ]);

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $warning = $data['correction_warning'];
        
        // Check warning structure
        $this->assertIsBool($warning['warning']);
        $this->assertIsString($warning['severity']);
        $this->assertIsArray($warning['reasons']);
        
        // Severity should be one of the valid values
        $this->assertContains($warning['severity'], ['NONE', 'LOW', 'MEDIUM', 'HIGH']);
    }

    public function test_batch_predictions_for_multiple_symbols(): void
    {
        Http::fake([
            '*yfinance*' => Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'regularMarketPrice' => 38000.0,
                            'previousClose' => 37500.0,
                        ]
                    ]]
                ]
            ], 200),
        ]);

        $response = $this->postJson('/api/predictions/batch', [
            'symbols' => ['AAPL', 'TSLA'],
            'horizon' => 'today',
        ]);

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        $this->assertIsArray($data);
        $this->assertCount(2, $data);
        
        // Check each prediction has correct structure
        foreach ($data as $prediction) {
            $this->assertArrayHasKey('symbol', $prediction);
            $this->assertArrayHasKey('prediction', $prediction);
            $this->assertArrayHasKey('asian_influence', $prediction);
        }
    }

    public function test_prediction_includes_top_reasons(): void
    {
        Http::fake([
            '*yfinance*' => Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'regularMarketPrice' => 38000.0,
                            'previousClose' => 37500.0,
                        ]
                    ]]
                ]
            ], 200),
        ]);

        $response = $this->postJson('/api/predictions/predict', [
            'symbol' => 'AAPL',
            'horizon' => 'today',
        ]);

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $prediction = $data['prediction'];
        
        $this->assertArrayHasKey('top_reasons', $prediction);
        $this->assertIsArray($prediction['top_reasons']);
        
        // Should have at least one reason
        $this->assertGreaterThan(0, count($prediction['top_reasons']));
        
        // Each reason should be a string
        foreach ($prediction['top_reasons'] as $reason) {
            $this->assertIsString($reason);
        }
    }

    public function test_expected_move_has_correct_sign(): void
    {
        Http::fake([
            '*yfinance*' => Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'regularMarketPrice' => 38000.0,
                            'previousClose' => 37500.0,
                        ]
                    ]]
                ]
            ], 200),
        ]);

        $response = $this->postJson('/api/predictions/predict', [
            'symbol' => 'AAPL',
            'horizon' => 'today',
        ]);

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $prediction = $data['prediction'];
        
        // Expected move sign should match prediction direction
        if ($prediction['label'] === 'BULLISH') {
            $this->assertGreaterThan(0, $prediction['expected_pct_move']);
        } else {
            $this->assertLessThan(0, $prediction['expected_pct_move']);
        }
    }

    public function test_asian_markets_closed_returns_neutral_influence(): void
    {
        // Mock Asian market API to return market closed status
        Http::fake([
            '*' => Http::response(['error' => 'Market closed'], 404),
        ]);

        $response = $this->postJson('/api/predictions/predict', [
            'symbol' => 'AAPL',
            'horizon' => 'today',
        ]);

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $asianInfluence = $data['asian_influence'];
        
        // Should have neutral influence when markets are closed
        $this->assertEquals(0.0, $asianInfluence['score']);
    }

    public function test_prediction_response_time_is_acceptable(): void
    {
        Http::fake([
            '*yfinance*' => Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'regularMarketPrice' => 38000.0,
                            'previousClose' => 37500.0,
                        ]
                    ]]
                ]
            ], 200),
        ]);

        $startTime = microtime(true);
        
        $response = $this->postJson('/api/predictions/predict', [
            'symbol' => 'AAPL',
            'horizon' => 'today',
        ]);

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        $response->assertStatus(200);
        
        // Prediction should complete within 5 seconds
        $this->assertLessThan(5.0, $duration, 'Prediction took too long');
    }
}
