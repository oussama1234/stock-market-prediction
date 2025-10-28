<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Stock;
use App\Models\StockCategory;
use Illuminate\Support\Facades\DB;

class PopularStocksSeeder extends Seeder
{
    /**
     * Seed popular stocks for production.
     * These are the most traded and searched stocks.
     */
    public function run(): void
    {
        $this->command->info('🚀 Seeding popular stocks...');

        // Get categories
        $techBlueChip = StockCategory::where('name', 'Tech Blue Chip')->first();
        $techGrowth = StockCategory::where('name', 'Tech Growth')->first();
        $semiconductor = StockCategory::where('name', 'Semiconductor')->first();
        $ecommerce = StockCategory::where('name', 'E-Commerce')->first();
        $financial = StockCategory::where('name', 'Financial Services')->first();
        $healthcare = StockCategory::where('name', 'Healthcare')->first();
        $energy = StockCategory::where('name', 'Energy')->first();
        $entertainment = StockCategory::where('name', 'Entertainment Media')->first();
        $crypto = StockCategory::where('name', 'Cryptocurrency Related')->first();
        $industrials = StockCategory::where('name', 'Industrials')->first();
        $consumerStaples = StockCategory::where('name', 'Consumer Staples')->first();

        $stocks = [
            // Tech Blue Chips (Magnificent 7 + Microsoft)
            [
                'symbol' => 'AAPL',
                'name' => 'Apple Inc.',
                'sector' => 'Technology',
                'industry' => 'Consumer Electronics',
                'category_id' => $techBlueChip?->id,
                'is_popular' => true,
                'market_cap' => 3500000000000, // $3.5T
            ],
            [
                'symbol' => 'MSFT',
                'name' => 'Microsoft Corporation',
                'sector' => 'Technology',
                'industry' => 'Software',
                'category_id' => $techBlueChip?->id,
                'is_popular' => true,
                'market_cap' => 3200000000000, // $3.2T
            ],
            [
                'symbol' => 'GOOGL',
                'name' => 'Alphabet Inc. Class A',
                'sector' => 'Technology',
                'industry' => 'Internet Services',
                'category_id' => $techBlueChip?->id,
                'is_popular' => true,
                'market_cap' => 2100000000000, // $2.1T
            ],
            [
                'symbol' => 'GOOG',
                'name' => 'Alphabet Inc. Class C',
                'sector' => 'Technology',
                'industry' => 'Internet Services',
                'category_id' => $techBlueChip?->id,
                'is_popular' => true,
                'market_cap' => 2100000000000,
            ],
            [
                'symbol' => 'AMZN',
                'name' => 'Amazon.com Inc.',
                'sector' => 'Consumer Cyclical',
                'industry' => 'E-Commerce',
                'category_id' => $ecommerce?->id,
                'is_popular' => true,
                'market_cap' => 2000000000000, // $2T
            ],
            [
                'symbol' => 'META',
                'name' => 'Meta Platforms Inc.',
                'sector' => 'Technology',
                'industry' => 'Social Media',
                'category_id' => $techBlueChip?->id,
                'is_popular' => true,
                'market_cap' => 1400000000000, // $1.4T
            ],

            // Tech Growth & AI
            [
                'symbol' => 'NVDA',
                'name' => 'NVIDIA Corporation',
                'sector' => 'Technology',
                'industry' => 'Semiconductors',
                'category_id' => $techGrowth?->id,
                'is_popular' => true,
                'market_cap' => 3400000000000, // $3.4T
            ],
            [
                'symbol' => 'TSLA',
                'name' => 'Tesla Inc.',
                'sector' => 'Consumer Cyclical',
                'industry' => 'Auto Manufacturers',
                'category_id' => $techGrowth?->id,
                'is_popular' => true,
                'market_cap' => 800000000000, // $800B
            ],
            [
                'symbol' => 'PLTR',
                'name' => 'Palantir Technologies Inc.',
                'sector' => 'Technology',
                'industry' => 'Software',
                'category_id' => $techGrowth?->id,
                'is_popular' => true,
                'market_cap' => 80000000000, // $80B
            ],

            // Semiconductors
            [
                'symbol' => 'AMD',
                'name' => 'Advanced Micro Devices Inc.',
                'sector' => 'Technology',
                'industry' => 'Semiconductors',
                'category_id' => $semiconductor?->id,
                'is_popular' => true,
                'market_cap' => 220000000000, // $220B
            ],
            [
                'symbol' => 'INTC',
                'name' => 'Intel Corporation',
                'sector' => 'Technology',
                'industry' => 'Semiconductors',
                'category_id' => $semiconductor?->id,
                'is_popular' => true,
                'market_cap' => 180000000000, // $180B
            ],
            [
                'symbol' => 'AVGO',
                'name' => 'Broadcom Inc.',
                'sector' => 'Technology',
                'industry' => 'Semiconductors',
                'category_id' => $semiconductor?->id,
                'is_popular' => true,
                'market_cap' => 700000000000, // $700B
            ],
            [
                'symbol' => 'TSM',
                'name' => 'Taiwan Semiconductor Manufacturing',
                'sector' => 'Technology',
                'industry' => 'Semiconductors',
                'category_id' => $semiconductor?->id,
                'is_popular' => true,
                'market_cap' => 900000000000, // $900B
            ],

            // Financial Services
            [
                'symbol' => 'JPM',
                'name' => 'JPMorgan Chase & Co.',
                'sector' => 'Financial Services',
                'industry' => 'Banks',
                'category_id' => $financial?->id,
                'is_popular' => true,
                'market_cap' => 600000000000, // $600B
            ],
            [
                'symbol' => 'BAC',
                'name' => 'Bank of America Corporation',
                'sector' => 'Financial Services',
                'industry' => 'Banks',
                'category_id' => $financial?->id,
                'is_popular' => true,
                'market_cap' => 320000000000, // $320B
            ],
            [
                'symbol' => 'GS',
                'name' => 'The Goldman Sachs Group Inc.',
                'sector' => 'Financial Services',
                'industry' => 'Investment Banking',
                'category_id' => $financial?->id,
                'is_popular' => true,
                'market_cap' => 160000000000, // $160B
            ],
            [
                'symbol' => 'V',
                'name' => 'Visa Inc.',
                'sector' => 'Financial Services',
                'industry' => 'Payment Processing',
                'category_id' => $financial?->id,
                'is_popular' => true,
                'market_cap' => 600000000000, // $600B
            ],
            [
                'symbol' => 'MA',
                'name' => 'Mastercard Incorporated',
                'sector' => 'Financial Services',
                'industry' => 'Payment Processing',
                'category_id' => $financial?->id,
                'is_popular' => true,
                'market_cap' => 450000000000, // $450B
            ],

            // Healthcare
            [
                'symbol' => 'JNJ',
                'name' => 'Johnson & Johnson',
                'sector' => 'Healthcare',
                'industry' => 'Pharmaceuticals',
                'category_id' => $healthcare?->id,
                'is_popular' => true,
                'market_cap' => 380000000000, // $380B
            ],
            [
                'symbol' => 'UNH',
                'name' => 'UnitedHealth Group Inc.',
                'sector' => 'Healthcare',
                'industry' => 'Health Insurance',
                'category_id' => $healthcare?->id,
                'is_popular' => true,
                'market_cap' => 480000000000, // $480B
            ],
            [
                'symbol' => 'PFE',
                'name' => 'Pfizer Inc.',
                'sector' => 'Healthcare',
                'industry' => 'Pharmaceuticals',
                'category_id' => $healthcare?->id,
                'is_popular' => true,
                'market_cap' => 150000000000, // $150B
            ],

            // Energy
            [
                'symbol' => 'XOM',
                'name' => 'Exxon Mobil Corporation',
                'sector' => 'Energy',
                'industry' => 'Oil & Gas',
                'category_id' => $energy?->id,
                'is_popular' => true,
                'market_cap' => 500000000000, // $500B
            ],
            [
                'symbol' => 'CVX',
                'name' => 'Chevron Corporation',
                'sector' => 'Energy',
                'industry' => 'Oil & Gas',
                'category_id' => $energy?->id,
                'is_popular' => true,
                'market_cap' => 280000000000, // $280B
            ],

            // Entertainment & Media
            [
                'symbol' => 'NFLX',
                'name' => 'Netflix Inc.',
                'sector' => 'Communication Services',
                'industry' => 'Streaming',
                'category_id' => $entertainment?->id,
                'is_popular' => true,
                'market_cap' => 350000000000, // $350B
            ],
            [
                'symbol' => 'DIS',
                'name' => 'The Walt Disney Company',
                'sector' => 'Communication Services',
                'industry' => 'Entertainment',
                'category_id' => $entertainment?->id,
                'is_popular' => true,
                'market_cap' => 180000000000, // $180B
            ],

            // Cryptocurrency Related
            [
                'symbol' => 'COIN',
                'name' => 'Coinbase Global Inc.',
                'sector' => 'Financial Services',
                'industry' => 'Cryptocurrency Exchange',
                'category_id' => $crypto?->id,
                'is_popular' => true,
                'market_cap' => 70000000000, // $70B
            ],
            [
                'symbol' => 'MSTR',
                'name' => 'MicroStrategy Incorporated',
                'sector' => 'Technology',
                'industry' => 'Software/Bitcoin Holdings',
                'category_id' => $crypto?->id,
                'is_popular' => true,
                'market_cap' => 100000000000, // $100B
            ],

            // Consumer Staples
            [
                'symbol' => 'WMT',
                'name' => 'Walmart Inc.',
                'sector' => 'Consumer Defensive',
                'industry' => 'Retail',
                'category_id' => $consumerStaples?->id,
                'is_popular' => true,
                'market_cap' => 650000000000, // $650B
            ],
            [
                'symbol' => 'PG',
                'name' => 'The Procter & Gamble Company',
                'sector' => 'Consumer Defensive',
                'industry' => 'Consumer Goods',
                'category_id' => $consumerStaples?->id,
                'is_popular' => true,
                'market_cap' => 400000000000, // $400B
            ],
            [
                'symbol' => 'KO',
                'name' => 'The Coca-Cola Company',
                'sector' => 'Consumer Defensive',
                'industry' => 'Beverages',
                'category_id' => $consumerStaples?->id,
                'is_popular' => true,
                'market_cap' => 280000000000, // $280B
            ],

            // Industrials
            [
                'symbol' => 'BA',
                'name' => 'The Boeing Company',
                'sector' => 'Industrials',
                'industry' => 'Aerospace & Defense',
                'category_id' => $industrials?->id,
                'is_popular' => true,
                'market_cap' => 120000000000, // $120B
            ],
            [
                'symbol' => 'CAT',
                'name' => 'Caterpillar Inc.',
                'sector' => 'Industrials',
                'industry' => 'Construction Equipment',
                'category_id' => $industrials?->id,
                'is_popular' => true,
                'market_cap' => 180000000000, // $180B
            ],
            [
                'symbol' => 'GE',
                'name' => 'General Electric Company',
                'sector' => 'Industrials',
                'industry' => 'Industrial Conglomerate',
                'category_id' => $industrials?->id,
                'is_popular' => true,
                'market_cap' => 200000000000, // $200B
            ],

            // Other Popular Stocks
            [
                'symbol' => 'SHOP',
                'name' => 'Shopify Inc.',
                'sector' => 'Technology',
                'industry' => 'E-Commerce Platform',
                'category_id' => $ecommerce?->id,
                'is_popular' => true,
                'market_cap' => 140000000000, // $140B
            ],
            [
                'symbol' => 'UBER',
                'name' => 'Uber Technologies Inc.',
                'sector' => 'Technology',
                'industry' => 'Ride Sharing',
                'category_id' => $techGrowth?->id,
                'is_popular' => true,
                'market_cap' => 150000000000, // $150B
            ],
            [
                'symbol' => 'ABNB',
                'name' => 'Airbnb Inc.',
                'sector' => 'Consumer Cyclical',
                'industry' => 'Travel Services',
                'category_id' => $techGrowth?->id,
                'is_popular' => true,
                'market_cap' => 90000000000, // $90B
            ],
            [
                'symbol' => 'SQ',
                'name' => 'Block Inc.',
                'sector' => 'Financial Services',
                'industry' => 'FinTech',
                'category_id' => $financial?->id,
                'is_popular' => true,
                'market_cap' => 50000000000, // $50B
            ],
            [
                'symbol' => 'PYPL',
                'name' => 'PayPal Holdings Inc.',
                'sector' => 'Financial Services',
                'industry' => 'Payment Processing',
                'category_id' => $financial?->id,
                'is_popular' => true,
                'market_cap' => 80000000000, // $80B
            ],
        ];

        $count = 0;
        foreach ($stocks as $stockData) {
            Stock::updateOrInsert(
                ['symbol' => $stockData['symbol']],
                array_merge($stockData, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
            $count++;
            $this->command->info("  ✅ {$stockData['symbol']} - {$stockData['name']}");
        }

        $this->command->info("\n🎉 Successfully seeded {$count} popular stocks!");
    }
}
