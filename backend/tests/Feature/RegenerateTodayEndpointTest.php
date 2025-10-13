<?php

namespace Tests\Feature;

use App\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegenerateTodayEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_regenerate_only_updates_today_prediction(): void
    {
        // Create a stock (minimal fields)
        \Illuminate\Support\Facades\DB::table('stocks')->insert([
            'symbol' => 'AVGO',
            'name' => 'Broadcom Inc.',
            'exchange' => 'NASDAQ',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $stock = \App\Models\Stock::where('symbol', 'AVGO')->first();

        // Disable auth middleware for test
        $this->withoutMiddleware();

        // Seed keywords table to ensure override works
        if (DB::getSchemaBuilder()->hasTable('priority_keywords')) {
            DB::table('priority_keywords')->insertOrIgnore([
                ['keyword' => 'tariff', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // Prime cooldown empty
        Cache::forget('regen_cooldown:AVGO');

        $res = $this->postJson('/api/stocks/AVGO/regenerate-today', ['horizon' => 'today']);
        $res->assertStatus(200)->assertJson(['success' => true]);

        // Verify prediction date is today
        $row = DB::table('predictions')->where('stock_id', $stock->id)->orderByDesc('id')->first();
        $this->assertNotNull($row);
        $this->assertEquals(now()->toDateString(), substr($row->prediction_date, 0, 10));
    }
}
