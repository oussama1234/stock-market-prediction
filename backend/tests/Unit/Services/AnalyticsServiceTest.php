<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\AnalyticsService;
use App\Services\StockDetailsService;
use App\Services\StockService;
use App\Services\NewsService;
use App\Services\FearGreedIndexService;
use App\Services\SentimentService;
use App\Models\Stock;
use App\Models\StockPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AnalyticsService $analyticsService;
    protected $mockDetailsService;
    protected $mockStockService;
    protected $mockNewsService;
    protected $mockFearGreedService;
    protected $mockSentimentService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->mockDetailsService = $this->createMock(StockDetailsService::class);
        $this->mockStockService = $this->createMock(StockService::class);
        $this->mockNewsService = $this->createMock(NewsService::class);
        $this->mockFearGreedService = $this->createMock(FearGreedIndexService::class);
        $this->mockSentimentService = $this->createMock(SentimentService::class);

        $this->analyticsService = new AnalyticsService(
            $this->mockDetailsService,
            $this->mockStockService,
            $this->mockNewsService,
            $this->mockFearGreedService,
            $this->mockSentimentService
        );
    }

    /** @test */
    public function it_normalizes_neutral_prediction_to_bullish_or_bearish()
    {
        $neutralPrediction = [
            'direction' => 'neutral',
            'probability' => 0.6,
        ];

        $reflection = new \ReflectionClass($this->analyticsService);
        $method = $reflection->getMethod('normalizePrediction');
        $method->setAccessible(true);

        $result = $method->invoke($this->analyticsService, $neutralPrediction, null);

        // Should be converted to up (bullish) since probability >= 0.5
        $this->assertEquals('up', $result['direction']);
        $this->assertEquals('BULLISH', $result['label']);
    }

    /** @test */
    public function it_normalizes_neutral_prediction_to_bearish_when_probability_low()
    {
        $neutralPrediction = [
            'direction' => 'neutral',
            'probability' => 0.4,
        ];

        $reflection = new \ReflectionClass($this->analyticsService);
        $method = $reflection->getMethod('normalizePrediction');
        $method->setAccessible(true);

        $result = $method->invoke($this->analyticsService, $neutralPrediction, null);

        // Should be converted to down (bearish) since probability < 0.5
        $this->assertEquals('down', $result['direction']);
        $this->assertEquals('BEARISH', $result['label']);
    }

    /** @test */
    public function it_applies_bearish_override_when_priority_keywords_detected()
    {
        $prediction = [
            'direction' => 'up',
            'probability' => 0.8,
        ];

        $override = [
            'applied' => true,
            'trigger_keywords' => ['tariff', 'ban'],
            'confidence' => 0.9,
        ];

        $reflection = new \ReflectionClass($this->analyticsService);
        $method = $reflection->getMethod('normalizePrediction');
        $method->setAccessible(true);

        $result = $method->invoke($this->analyticsService, $prediction, $override);

        // Override should force bearish
        $this->assertEquals('down', $result['direction']);
        $this->assertEquals('BEARISH', $result['label']);
    }

    /** @test */
    public function it_calculates_support_resistance_levels()
    {
        $prices = $this->generatePriceData(100);
        $currentPrice = 150.0;

        $reflection = new \ReflectionClass($this->analyticsService);
        $method = $reflection->getMethod('calculateSupportResistance');
        $method->setAccessible(true);

        $result = $method->invoke($this->analyticsService, $prices, $currentPrice);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('supports', $result);
        $this->assertArrayHasKey('resistances', $result);
        $this->assertArrayHasKey('strong_support', $result);
        $this->assertArrayHasKey('strong_resistance', $result);
    }

    /** @test */
    public function it_generates_buy_alert_near_strong_support()
    {
        $currentPrice = 100.0;
        $supportResistance = [
            'strong_support' => [
                'price' => 98.5, // 1.5% below current
                'strength' => 'strong',
                'touches' => 3,
            ],
            'supports' => [],
            'resistances' => [],
        ];

        $prediction = ['direction' => 'up'];
        $indicators = ['rsi' => 30]; // Oversold
        $fearGreed = ['value' => 35]; // Fear
        $newsSentiment = ['average_score' => 0.1]; // Slightly positive
        $override = null;

        $reflection = new \ReflectionClass($this->analyticsService);
        $method = $reflection->getMethod('generateAlerts');
        $method->setAccessible(true);

        $alerts = $method->invoke(
            $this->analyticsService,
            $currentPrice,
            $supportResistance,
            $prediction,
            $indicators,
            $fearGreed,
            $newsSentiment,
            $override
        );

        $this->assertIsArray($alerts);
        $this->assertNotEmpty($alerts);
        
        // Should have a BUY alert
        $buyAlert = collect($alerts)->firstWhere('type', 'BUY');
        $this->assertNotNull($buyAlert);
        $this->assertEquals('BUY', $buyAlert['type']);
        $this->assertArrayHasKey('reasons', $buyAlert);
    }

    /** @test */
    public function it_generates_sell_alert_near_strong_resistance()
    {
        $currentPrice = 100.0;
        $supportResistance = [
            'strong_resistance' => [
                'price' => 101.5, // 1.5% above current
                'strength' => 'strong',
                'touches' => 3,
            ],
            'supports' => [],
            'resistances' => [],
        ];

        $prediction = ['direction' => 'down'];
        $indicators = ['rsi' => 75]; // Overbought
        $fearGreed = ['value' => 80]; // Extreme Greed
        $newsSentiment = ['average_score' => -0.1];
        $override = null;

        $reflection = new \ReflectionClass($this->analyticsService);
        $method = $reflection->getMethod('generateAlerts');
        $method->setAccessible(true);

        $alerts = $method->invoke(
            $this->analyticsService,
            $currentPrice,
            $supportResistance,
            $prediction,
            $indicators,
            $fearGreed,
            $newsSentiment,
            $override
        );

        $this->assertIsArray($alerts);
        $this->assertNotEmpty($alerts);
        
        // Should have a SELL alert
        $sellAlert = collect($alerts)->firstWhere('type', 'SELL');
        $this->assertNotNull($sellAlert);
        $this->assertEquals('SELL', $sellAlert['type']);
        $this->assertArrayHasKey('reasons', $sellAlert);
    }

    /** @test */
    public function it_does_not_generate_buy_alert_when_bearish_override_active()
    {
        $currentPrice = 100.0;
        $supportResistance = [
            'strong_support' => [
                'price' => 98.5,
                'strength' => 'strong',
            ],
            'supports' => [],
            'resistances' => [],
        ];

        $prediction = ['direction' => 'down'];
        $indicators = ['rsi' => 30];
        $fearGreed = ['value' => 35];
        $newsSentiment = ['average_score' => 0.1];
        
        // Override is active
        $override = [
            'applied' => true,
            'trigger_keywords' => ['tariff'],
        ];

        $reflection = new \ReflectionClass($this->analyticsService);
        $method = $reflection->getMethod('generateAlerts');
        $method->setAccessible(true);

        $alerts = $method->invoke(
            $this->analyticsService,
            $currentPrice,
            $supportResistance,
            $prediction,
            $indicators,
            $fearGreed,
            $newsSentiment,
            $override
        );

        // Should not have BUY alert due to override
        $buyAlert = collect($alerts)->firstWhere('type', 'BUY');
        $this->assertNull($buyAlert);

        // Should have WARNING alert for override
        $warningAlert = collect($alerts)->firstWhere('type', 'WARNING');
        $this->assertNotNull($warningAlert);
    }

    /** @test */
    public function it_calculates_ema_correctly()
    {
        $data = [100, 102, 101, 103, 105, 104, 106, 108];
        $period = 5;

        $reflection = new \ReflectionClass($this->analyticsService);
        $method = $reflection->getMethod('calculateEMA');
        $method->setAccessible(true);

        $ema = $method->invoke($this->analyticsService, $data, $period);

        $this->assertIsFloat($ema);
        $this->assertGreaterThan(100, $ema);
        $this->assertLessThan(110, $ema);
    }

    /** @test */
    public function it_calculates_rsi_correctly()
    {
        $closes = [44, 44.34, 44.09, 43.61, 44.33, 44.83, 45.10, 45.42, 45.84, 46.08, 45.89, 46.03, 45.61, 46.28, 46.28, 46.00, 46.03, 46.41, 46.22, 45.64];

        $reflection = new \ReflectionClass($this->analyticsService);
        $method = $reflection->getMethod('calculateRSI');
        $method->setAccessible(true);

        $rsi = $method->invoke($this->analyticsService, $closes, 14);

        $this->assertIsFloat($rsi);
        $this->assertGreaterThanOrEqual(0, $rsi);
        $this->assertLessThanOrEqual(100, $rsi);
    }

    /** @test */
    public function it_clusters_nearby_support_resistance_levels()
    {
        $levels = [
            ['price' => 100.0, 'touches' => 1, 'date' => '2025-01-01'],
            ['price' => 100.5, 'touches' => 1, 'date' => '2025-01-02'],
            ['price' => 101.0, 'touches' => 1, 'date' => '2025-01-03'],
            ['price' => 110.0, 'touches' => 1, 'date' => '2025-01-04'],
        ];

        $reflection = new \ReflectionClass($this->analyticsService);
        $method = $reflection->getMethod('clusterLevels');
        $method->setAccessible(true);

        $clustered = $method->invoke($this->analyticsService, $levels, 0.02);

        $this->assertIsArray($clustered);
        // Should cluster first 3 into one, and keep 110 separate
        $this->assertCount(2, $clustered);
        
        // First cluster should be around 100.5
        $this->assertEqualsWithDelta(100.5, $clustered[0]['price'], 1.0);
        $this->assertEquals(3, $clustered[0]['touches']);
    }

    /** @test */
    public function it_determines_market_status_correctly()
    {
        $reflection = new \ReflectionClass($this->analyticsService);
        $method = $reflection->getMethod('getMarketStatus');
        $method->setAccessible(true);

        $status = $method->invoke($this->analyticsService);

        $this->assertIsArray($status);
        $this->assertArrayHasKey('status', $status);
        $this->assertContains($status['status'], ['open', 'closed', 'pre_market', 'after_hours']);
    }

    /** @test */
    public function it_clears_cache_on_regenerate()
    {
        $symbol = 'TEST';
        Cache::put("analytics_new:{$symbol}", ['test' => 'data'], 60);

        $this->assertTrue(Cache::has("analytics_new:{$symbol}"));

        // Mock the detailsService to return valid data
        $this->mockDetailsService
            ->expects($this->once())
            ->method('regenerateToday')
            ->willReturn(['prediction' => ['direction' => 'up']]);

        $this->mockStockService
            ->expects($this->once())
            ->method('getOrCreateStock')
            ->willReturn(Stock::factory()->make(['symbol' => $symbol]));

        $this->analyticsService->regenerateToday($symbol);

        // Cache should be cleared
        $this->assertFalse(Cache::has("analytics_new:{$symbol}"));
    }

    /**
     * Generate mock price data for testing
     */
    private function generatePriceData(int $count): array
    {
        $prices = [];
        $basePrice = 100.0;

        for ($i = 0; $i < $count; $i++) {
            $variation = rand(-5, 5);
            $prices[] = [
                'price_date' => now()->subDays($count - $i)->format('Y-m-d'),
                'open' => $basePrice + $variation,
                'high' => $basePrice + $variation + rand(1, 3),
                'low' => $basePrice + $variation - rand(1, 3),
                'close' => $basePrice + $variation + rand(-2, 2),
                'volume' => rand(1000000, 5000000),
            ];
        }

        return $prices;
    }
}
