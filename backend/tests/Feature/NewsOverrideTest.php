<?php

namespace Tests\Feature;

use App\Services\NewsService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NewsOverrideTest extends TestCase
{
    public function test_keyword_override_triggers_bearish_label(): void
    {
        // Ensure stock exists
        DB::table('stocks')->insertOrIgnore([
            'symbol' => 'TEST', 'name' => 'Test Corp', 'exchange' => 'NYSE', 'currency' => 'USD', 'created_at' => now(), 'updated_at' => now()
        ]);

        // Mock NewsService to return an article with a priority keyword
        $this->mock(NewsService::class, function ($mock) {
            $mock->shouldReceive('getStockNews')->andReturn([
                [
                    'title' => 'New tariff imposed on imports',
                    'description' => 'Government announces new tariffs affecting sector.',
                    'url' => 'https://example.com/news1',
                    'source' => 'Reuters',
                    'published_at' => now()->toDateTimeString(),
                    'sentiment_score' => -0.2,
                ],
            ]);
        });

        // Call API show endpoint which now uses StockDetailsService
        $this->withoutExceptionHandling();
        $res = $this->getJson('/api/stocks/TEST');
        $res->assertStatus(200);
        $data = $res->json('data');
        $this->assertNotEmpty($data['prediction']);
        $this->assertEquals('down', $data['prediction']['direction']);
        $this->assertStringContainsString('Override', $data['prediction']['label']);
    }
}
