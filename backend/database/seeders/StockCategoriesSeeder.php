<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StockCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Tech Growth',
                'description' => 'High-growth technology companies with aggressive valuation (NVDA, TSLA, PLTR)',
                'volatility_multiplier' => 2.50,
                'typical_daily_range_min' => 2.0,
                'typical_daily_range_max' => 8.0,
                'high_momentum' => true,
            ],
            [
                'name' => 'Tech Blue Chip',
                'description' => 'Established technology companies (AAPL, MSFT, GOOGL)',
                'volatility_multiplier' => 1.50,
                'typical_daily_range_min' => 1.0,
                'typical_daily_range_max' => 4.0,
                'high_momentum' => false,
            ],
            [
                'name' => 'Semiconductor',
                'description' => 'Semiconductor and chip manufacturers (AMD, INTC, AVGO)',
                'volatility_multiplier' => 2.20,
                'typical_daily_range_min' => 1.5,
                'typical_daily_range_max' => 6.0,
                'high_momentum' => true,
            ],
            [
                'name' => 'E-Commerce',
                'description' => 'E-commerce and digital retail platforms (AMZN, SHOP)',
                'volatility_multiplier' => 1.80,
                'typical_daily_range_min' => 1.2,
                'typical_daily_range_max' => 5.0,
                'high_momentum' => true,
            ],
            [
                'name' => 'Financial Services',
                'description' => 'Banks, insurance, and financial institutions (JPM, BAC, GS)',
                'volatility_multiplier' => 1.20,
                'typical_daily_range_min' => 0.8,
                'typical_daily_range_max' => 3.0,
                'high_momentum' => false,
            ],
            [
                'name' => 'Healthcare',
                'description' => 'Pharmaceuticals and healthcare providers (JNJ, UNH, PFE)',
                'volatility_multiplier' => 1.00,
                'typical_daily_range_min' => 0.5,
                'typical_daily_range_max' => 2.5,
                'high_momentum' => false,
            ],
            [
                'name' => 'Biotech',
                'description' => 'Biotechnology and gene therapy companies',
                'volatility_multiplier' => 2.80,
                'typical_daily_range_min' => 2.5,
                'typical_daily_range_max' => 10.0,
                'high_momentum' => true,
            ],
            [
                'name' => 'Consumer Staples',
                'description' => 'Essential consumer goods (PG, KO, WMT)',
                'volatility_multiplier' => 0.70,
                'typical_daily_range_min' => 0.3,
                'typical_daily_range_max' => 1.5,
                'high_momentum' => false,
            ],
            [
                'name' => 'Energy',
                'description' => 'Oil, gas, and renewable energy (XOM, CVX)',
                'volatility_multiplier' => 1.60,
                'typical_daily_range_min' => 1.0,
                'typical_daily_range_max' => 4.5,
                'high_momentum' => false,
            ],
            [
                'name' => 'Utilities',
                'description' => 'Electric, water, and utility providers',
                'volatility_multiplier' => 0.60,
                'typical_daily_range_min' => 0.2,
                'typical_daily_range_max' => 1.2,
                'high_momentum' => false,
            ],
            [
                'name' => 'Industrials',
                'description' => 'Manufacturing and industrial equipment (CAT, BA, GE)',
                'volatility_multiplier' => 1.30,
                'typical_daily_range_min' => 0.8,
                'typical_daily_range_max' => 3.5,
                'high_momentum' => false,
            ],
            [
                'name' => 'Meme Stock',
                'description' => 'High retail interest, social media driven (GME, AMC)',
                'volatility_multiplier' => 3.50,
                'typical_daily_range_min' => 3.0,
                'typical_daily_range_max' => 15.0,
                'high_momentum' => true,
            ],
            [
                'name' => 'Cryptocurrency Related',
                'description' => 'Crypto exchanges and blockchain (COIN, MSTR)',
                'volatility_multiplier' => 3.00,
                'typical_daily_range_min' => 2.5,
                'typical_daily_range_max' => 12.0,
                'high_momentum' => true,
            ],
            [
                'name' => 'Entertainment Media',
                'description' => 'Streaming, entertainment, and media (NFLX, DIS)',
                'volatility_multiplier' => 1.70,
                'typical_daily_range_min' => 1.0,
                'typical_daily_range_max' => 4.5,
                'high_momentum' => false,
            ],
            [
                'name' => 'Real Estate',
                'description' => 'REITs and real estate companies',
                'volatility_multiplier' => 0.90,
                'typical_daily_range_min' => 0.5,
                'typical_daily_range_max' => 2.0,
                'high_momentum' => false,
            ],
        ];

        foreach ($categories as $category) {
            DB::table('stock_categories')->updateOrInsert(
                ['name' => $category['name']],
                array_merge($category, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('Stock categories seeded successfully!');
    }
}
