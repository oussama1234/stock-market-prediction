<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Stock;
use App\Models\NewsArticle;
use Carbon\Carbon;

class ImportantNewsSeeder extends Seeder
{
    /**
     * Seed the database with test important news for AVGO (Broadcom)
     * Testing the OpenAI chip deal pattern
     */
    public function run(): void
    {
        $this->command->info('Seeding important news for testing...');
        
        // Create or get AVGO stock
        $avgo = Stock::firstOrCreate(
            ['symbol' => 'AVGO'],
            [
                'name' => 'Broadcom Inc.',
                'sector' => 'Technology',
                'industry' => 'Semiconductors',
                'is_popular' => true,
            ]
        );
        
        $this->command->info("Stock AVGO created/found: {$avgo->id}");
        
        // Test Case 1: "Broadcom Stock Surges on OpenAI Chip Deal" - TODAY
        $news1 = NewsArticle::updateOrCreate(
            [
                'url' => 'https://example.com/broadcom-openai-chip-deal-surge'
            ],
            [
                'stock_id' => $avgo->id,
                'title' => 'Broadcom Stock Surges on OpenAI Chip Deal',
                'description' => 'Broadcom announces major partnership with OpenAI to supply custom AI chips, expected to boost revenue by billions.',
                'source' => 'TechNews',
                'published_at' => now(),
                'sentiment_score' => 0.85,
                'is_important' => true,
                'importance_date' => now()->toDateString(),
                'expected_surge_percent' => 10.0,
                'surge_keywords' => json_encode(['openai chip deal', 'stock surges on', 'major partnership']),
            ]
        );
        
        $this->command->info("✅ Created: {$news1->title} (Expected surge: {$news1->expected_surge_percent}%)");
        
        // Test Case 2: "AVGO Jumps on AI Partnership Announcement" - TODAY
        $news2 = NewsArticle::updateOrCreate(
            [
                'url' => 'https://example.com/avgo-ai-partnership-jump'
            ],
            [
                'stock_id' => $avgo->id,
                'title' => 'AVGO Jumps on Major AI Partnership Announcement',
                'description' => 'Broadcom secures exclusive AI chip supply deal, analysts raise price targets significantly.',
                'source' => 'MarketWatch',
                'published_at' => now()->subHours(2),
                'sentiment_score' => 0.78,
                'is_important' => true,
                'importance_date' => now()->toDateString(),
                'expected_surge_percent' => 8.5,
                'surge_keywords' => json_encode(['ai partnership', 'jumps on', 'exclusive ai deal']),
            ]
        );
        
        $this->command->info("✅ Created: {$news2->title} (Expected surge: {$news2->expected_surge_percent}%)");
        
        // Test Case 3: Regular news (NOT important) - for comparison
        $news3 = NewsArticle::updateOrCreate(
            [
                'url' => 'https://example.com/broadcom-quarterly-report'
            ],
            [
                'stock_id' => $avgo->id,
                'title' => 'Broadcom Reports Quarterly Earnings',
                'description' => 'The company reported solid quarterly results in line with expectations.',
                'source' => 'Reuters',
                'published_at' => now()->subHours(5),
                'sentiment_score' => 0.25,
                'is_important' => false,
                'importance_date' => null,
                'expected_surge_percent' => null,
                'surge_keywords' => null,
            ]
        );
        
        $this->command->info("✅ Created regular news: {$news3->title}");
        
        // Test Case 4: Important news but for TOMORROW (after market close)
        $news4 = NewsArticle::updateOrCreate(
            [
                'url' => 'https://example.com/avgo-breakthrough-announced'
            ],
            [
                'stock_id' => $avgo->id,
                'title' => 'Broadcom Announces Breakthrough in AI Chip Technology',
                'description' => 'Revolutionary new chip design promises 10x performance improvement for AI workloads.',
                'source' => 'TechCrunch',
                'published_at' => now()->subHours(1),
                'sentiment_score' => 0.92,
                'is_important' => true,
                'importance_date' => now()->addDay()->toDateString(), // Tomorrow
                'expected_surge_percent' => 7.5,
                'surge_keywords' => json_encode(['breakthrough in ai', 'revolutionary', 'announces breakthrough']),
            ]
        );
        
        $this->command->info("✅ Created: {$news4->title} (Expected surge: {$news4->expected_surge_percent}%, Date: TOMORROW)");
        
        // Also create test news for other mega-cap stocks
        $stocks = [
            ['symbol' => 'NVDA', 'name' => 'NVIDIA Corporation', 'title' => 'NVIDIA Stock Soars on AI Chip Demand'],
            ['symbol' => 'MSFT', 'name' => 'Microsoft Corporation', 'title' => 'Microsoft Surges on OpenAI Deal Expansion'],
        ];
        
        foreach ($stocks as $stockData) {
            $stock = Stock::firstOrCreate(
                ['symbol' => $stockData['symbol']],
                [
                    'name' => $stockData['name'],
                    'sector' => 'Technology',
                    'is_popular' => true,
                ]
            );
            
            NewsArticle::updateOrCreate(
                [
                    'url' => "https://example.com/{$stockData['symbol']}-surge-test"
                ],
                [
                    'stock_id' => $stock->id,
                    'title' => $stockData['title'],
                    'description' => "Major news for {$stockData['name']} driving significant price movement.",
                    'source' => 'Bloomberg',
                    'published_at' => now(),
                    'sentiment_score' => 0.80,
                    'is_important' => true,
                    'importance_date' => now()->toDateString(),
                    'expected_surge_percent' => 9.0,
                    'surge_keywords' => json_encode(['stock soars on', 'surges on', 'ai deal']),
                ]
            );
            
            $this->command->info("✅ Created test news for {$stockData['symbol']}");
        }
        
        $this->command->info("\n🎉 Important news seeding completed!");
        $this->command->info("\n📊 Summary:");
        $this->command->info("   - AVGO has " . $avgo->newsArticles()->count() . " news articles");
        $this->command->info("   - " . $avgo->newsArticles()->important()->count() . " are marked as important");
        $this->command->info("   - " . $avgo->newsArticles()->importantToday()->count() . " are important for TODAY");
        $this->command->info("\n🧪 Test URLs:");
        $this->command->info("   - Frontend: http://localhost:5173/stocks/AVGO");
        $this->command->info("   - API: http://localhost:8000/api/stocks/AVGO");
    }
}
