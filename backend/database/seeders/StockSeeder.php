<?php

namespace Database\Seeders;

use App\Models\Stock;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stocks = [
            // Technology
            ['symbol' => 'AAPL', 'name' => 'Apple Inc.', 'sector' => 'Technology', 'industry' => 'Consumer Electronics', 'category' => 'Tech Giants', 'market_cap' => 3000000000000],
            ['symbol' => 'MSFT', 'name' => 'Microsoft Corporation', 'sector' => 'Technology', 'industry' => 'Software Infrastructure', 'category' => 'Tech Giants', 'market_cap' => 2800000000000],
            ['symbol' => 'GOOGL', 'name' => 'Alphabet Inc. (Google)', 'sector' => 'Technology', 'industry' => 'Internet Services', 'category' => 'Tech Giants', 'market_cap' => 1800000000000],
            ['symbol' => 'GOOG', 'name' => 'Alphabet Inc. (Google) Class C', 'sector' => 'Technology', 'industry' => 'Internet Services', 'category' => 'Tech Giants', 'market_cap' => 1800000000000],
            ['symbol' => 'AMZN', 'name' => 'Amazon.com Inc.', 'sector' => 'Consumer Cyclical', 'industry' => 'Internet Retail', 'category' => 'Tech Giants', 'market_cap' => 1700000000000],
            ['symbol' => 'NVDA', 'name' => 'NVIDIA Corporation', 'sector' => 'Technology', 'industry' => 'Semiconductors', 'category' => 'AI/Semiconductors', 'market_cap' => 1200000000000],
            ['symbol' => 'META', 'name' => 'Meta Platforms Inc.', 'sector' => 'Technology', 'industry' => 'Internet Services', 'category' => 'Social Media Tech', 'market_cap' => 900000000000],
            ['symbol' => 'TSLA', 'name' => 'Tesla Inc.', 'sector' => 'Consumer Cyclical', 'industry' => 'Auto Manufacturers', 'category' => 'Electric Vehicles', 'market_cap' => 800000000000],
            ['symbol' => 'AVGO', 'name' => 'Broadcom Inc.', 'sector' => 'Technology', 'industry' => 'Semiconductors', 'category' => 'AI/Semiconductors', 'market_cap' => 600000000000],
            ['symbol' => 'ASML', 'name' => 'ASML Holding N.V.', 'sector' => 'Technology', 'industry' => 'Semiconductors', 'category' => 'AI/Semiconductors', 'market_cap' => 350000000000],
            ['symbol' => 'CRM', 'name' => 'Salesforce Inc.', 'sector' => 'Technology', 'industry' => 'Software Application', 'category' => 'Enterprise Software', 'market_cap' => 250000000000],
            ['symbol' => 'ADBE', 'name' => 'Adobe Inc.', 'sector' => 'Technology', 'industry' => 'Software Application', 'category' => 'Enterprise Software', 'market_cap' => 180000000000],
            ['symbol' => 'INTC', 'name' => 'Intel Corporation', 'sector' => 'Technology', 'industry' => 'Semiconductors', 'category' => 'AI/Semiconductors', 'market_cap' => 200000000000],
            ['symbol' => 'AMD', 'name' => 'Advanced Micro Devices', 'sector' => 'Technology', 'industry' => 'Semiconductors', 'category' => 'AI/Semiconductors', 'market_cap' => 200000000000],
            ['symbol' => 'QCOM', 'name' => 'QUALCOMM Incorporated', 'sector' => 'Technology', 'industry' => 'Semiconductors', 'category' => 'AI/Semiconductors', 'market_cap' => 180000000000],
            ['symbol' => 'NFLX', 'name' => 'Netflix Inc.', 'sector' => 'Communication Services', 'industry' => 'Entertainment', 'category' => 'Media & Entertainment', 'market_cap' => 300000000000],
            ['symbol' => 'IBM', 'name' => 'International Business Machines', 'sector' => 'Technology', 'industry' => 'Information Technology', 'category' => 'Enterprise Software', 'market_cap' => 200000000000],
            ['symbol' => 'ORCL', 'name' => 'Oracle Corporation', 'sector' => 'Technology', 'industry' => 'Software Infrastructure', 'category' => 'Enterprise Software', 'market_cap' => 350000000000],

            // Finance
            ['symbol' => 'BRK.B', 'name' => 'Berkshire Hathaway Inc.', 'sector' => 'Financial', 'industry' => 'Diversified Financial', 'category' => 'Financial', 'market_cap' => 900000000000],
            ['symbol' => 'JPM', 'name' => 'JPMorgan Chase & Co.', 'sector' => 'Financial', 'industry' => 'Banks', 'category' => 'Banking', 'market_cap' => 500000000000],
            ['symbol' => 'BAC', 'name' => 'Bank of America Corp', 'sector' => 'Financial', 'industry' => 'Banks', 'category' => 'Banking', 'market_cap' => 280000000000],
            ['symbol' => 'WFC', 'name' => 'Wells Fargo & Company', 'sector' => 'Financial', 'industry' => 'Banks', 'category' => 'Banking', 'market_cap' => 180000000000],
            ['symbol' => 'GS', 'name' => 'Goldman Sachs Group Inc.', 'sector' => 'Financial', 'industry' => 'Investment Banking', 'category' => 'Financial', 'market_cap' => 120000000000],
            ['symbol' => 'MS', 'name' => 'Morgan Stanley', 'sector' => 'Financial', 'industry' => 'Investment Banking', 'category' => 'Financial', 'market_cap' => 180000000000],
            ['symbol' => 'BLK', 'name' => 'BlackRock Inc.', 'sector' => 'Financial', 'industry' => 'Asset Management', 'category' => 'Financial', 'market_cap' => 140000000000],
            ['symbol' => 'V', 'name' => 'Visa Inc.', 'sector' => 'Financial', 'industry' => 'Financial Data & Stock Exchanges', 'category' => 'Financial', 'market_cap' => 650000000000],
            ['symbol' => 'MA', 'name' => 'Mastercard Incorporated', 'sector' => 'Financial', 'industry' => 'Financial Data & Stock Exchanges', 'category' => 'Financial', 'market_cap' => 480000000000],
            ['symbol' => 'AXP', 'name' => 'American Express Company', 'sector' => 'Financial', 'industry' => 'Financial Data & Stock Exchanges', 'category' => 'Financial', 'market_cap' => 150000000000],

            // Healthcare
            ['symbol' => 'JNJ', 'name' => 'Johnson & Johnson', 'sector' => 'Healthcare', 'industry' => 'Pharmaceuticals', 'category' => 'Healthcare', 'market_cap' => 450000000000],
            ['symbol' => 'UNH', 'name' => 'UnitedHealth Group Inc.', 'sector' => 'Healthcare', 'industry' => 'Health Care Services', 'category' => 'Healthcare', 'market_cap' => 500000000000],
            ['symbol' => 'PFE', 'name' => 'Pfizer Inc.', 'sector' => 'Healthcare', 'industry' => 'Pharmaceuticals', 'category' => 'Healthcare', 'market_cap' => 170000000000],
            ['symbol' => 'ABBV', 'name' => 'AbbVie Inc.', 'sector' => 'Healthcare', 'industry' => 'Pharmaceuticals', 'category' => 'Healthcare', 'market_cap' => 300000000000],
            ['symbol' => 'JNJ', 'name' => 'Johnson & Johnson', 'sector' => 'Healthcare', 'industry' => 'Pharmaceuticals', 'category' => 'Healthcare', 'market_cap' => 450000000000],
            ['symbol' => 'MRK', 'name' => 'Merck & Co., Inc.', 'sector' => 'Healthcare', 'industry' => 'Pharmaceuticals', 'category' => 'Healthcare', 'market_cap' => 280000000000],
            ['symbol' => 'LLY', 'name' => 'Eli Lilly and Company', 'sector' => 'Healthcare', 'industry' => 'Pharmaceuticals', 'category' => 'Healthcare', 'market_cap' => 850000000000],
            ['symbol' => 'AMGN', 'name' => 'Amgen Inc.', 'sector' => 'Healthcare', 'industry' => 'Biotechnology', 'category' => 'Healthcare', 'market_cap' => 140000000000],

            // Industrial & Manufacturing
            ['symbol' => 'BA', 'name' => 'The Boeing Company', 'sector' => 'Industrials', 'industry' => 'Aerospace & Defense', 'category' => 'Industrial', 'market_cap' => 180000000000],
            ['symbol' => 'CAT', 'name' => 'Caterpillar Inc.', 'sector' => 'Industrials', 'industry' => 'Machinery', 'category' => 'Industrial', 'market_cap' => 130000000000],
            ['symbol' => 'GE', 'name' => 'General Electric Company', 'sector' => 'Industrials', 'industry' => 'Industrial Equipment', 'category' => 'Industrial', 'market_cap' => 180000000000],
            ['symbol' => 'MMM', 'name' => '3M Company', 'sector' => 'Industrials', 'industry' => 'Industrial Equipment', 'category' => 'Industrial', 'market_cap' => 60000000000],

            // Energy
            ['symbol' => 'XOM', 'name' => 'Exxon Mobil Corporation', 'sector' => 'Energy', 'industry' => 'Oil & Gas', 'category' => 'Energy', 'market_cap' => 450000000000],
            ['symbol' => 'CVX', 'name' => 'Chevron Corporation', 'sector' => 'Energy', 'industry' => 'Oil & Gas', 'category' => 'Energy', 'market_cap' => 280000000000],
            ['symbol' => 'COP', 'name' => 'ConocoPhillips', 'sector' => 'Energy', 'industry' => 'Oil & Gas', 'category' => 'Energy', 'market_cap' => 150000000000],
            ['symbol' => 'SLB', 'name' => 'Schlumberger Limited', 'sector' => 'Energy', 'industry' => 'Oil & Gas Services', 'category' => 'Energy', 'market_cap' => 80000000000],

            // Consumer
            ['symbol' => 'WMT', 'name' => 'Walmart Inc.', 'sector' => 'Consumer Defensive', 'industry' => 'Retail', 'category' => 'Retail', 'market_cap' => 420000000000],
            ['symbol' => 'MCD', 'name' => "McDonald's Corporation", 'sector' => 'Consumer Cyclical', 'industry' => 'Restaurants', 'category' => 'Consumer', 'market_cap' => 200000000000],
            ['symbol' => 'PG', 'name' => 'Procter & Gamble Company', 'sector' => 'Consumer Defensive', 'industry' => 'Household Products', 'category' => 'Consumer', 'market_cap' => 380000000000],
            ['symbol' => 'KO', 'name' => 'The Coca-Cola Company', 'sector' => 'Consumer Defensive', 'industry' => 'Beverages', 'category' => 'Consumer', 'market_cap' => 280000000000],
            ['symbol' => 'PEP', 'name' => 'PepsiCo Inc.', 'sector' => 'Consumer Defensive', 'industry' => 'Beverages', 'category' => 'Consumer', 'market_cap' => 240000000000],
            ['symbol' => 'NKE', 'name' => 'NIKE Inc.', 'sector' => 'Consumer Cyclical', 'industry' => 'Apparel', 'category' => 'Consumer', 'market_cap' => 100000000000],
            ['symbol' => 'SBUX', 'name' => 'Starbucks Corporation', 'sector' => 'Consumer Cyclical', 'industry' => 'Restaurants', 'category' => 'Consumer', 'market_cap' => 100000000000],
            ['symbol' => 'HD', 'name' => 'The Home Depot Inc.', 'sector' => 'Consumer Cyclical', 'industry' => 'Specialty Retail', 'category' => 'Retail', 'market_cap' => 280000000000],

            // Communications & Utilities
            ['symbol' => 'T', 'name' => 'AT&T Inc.', 'sector' => 'Communication Services', 'industry' => 'Telecom Services', 'category' => 'Telecom', 'market_cap' => 120000000000],
            ['symbol' => 'VZ', 'name' => 'Verizon Communications Inc.', 'sector' => 'Communication Services', 'industry' => 'Telecom Services', 'category' => 'Telecom', 'market_cap' => 250000000000],
            ['symbol' => 'DUK', 'name' => 'Duke Energy Corporation', 'sector' => 'Utilities', 'industry' => 'Utilities', 'category' => 'Utilities', 'market_cap' => 180000000000],
            ['symbol' => 'NEE', 'name' => 'NextEra Energy, Inc.', 'sector' => 'Utilities', 'industry' => 'Utilities', 'category' => 'Utilities', 'market_cap' => 160000000000],

            // Real Estate
            ['symbol' => 'SPG', 'name' => 'Simon Property Group, Inc.', 'sector' => 'Real Estate', 'industry' => 'REIT', 'category' => 'Real Estate', 'market_cap' => 50000000000],
            ['symbol' => 'PLD', 'name' => 'Prologis, Inc.', 'sector' => 'Real Estate', 'industry' => 'REIT', 'category' => 'Real Estate', 'market_cap' => 100000000000],

            // Materials
            ['symbol' => 'NEM', 'name' => 'Newmont Corporation', 'sector' => 'Materials', 'industry' => 'Gold', 'category' => 'Materials', 'market_cap' => 70000000000],
            ['symbol' => 'FCX', 'name' => 'Freeport-McMoran Inc.', 'sector' => 'Materials', 'industry' => 'Copper', 'category' => 'Materials', 'market_cap' => 40000000000],

            // Emerging/Growth
            ['symbol' => 'SQ', 'name' => 'Square Inc. (Block, Inc.)', 'sector' => 'Financial', 'industry' => 'Financial Data & Stock Exchanges', 'category' => 'Fintech', 'market_cap' => 40000000000],
            ['symbol' => 'COIN', 'name' => 'Coinbase Global, Inc.', 'sector' => 'Financial', 'industry' => 'Cryptocurrency', 'category' => 'Fintech', 'market_cap' => 50000000000],
            ['symbol' => 'UBER', 'name' => 'Uber Technologies Inc.', 'sector' => 'Consumer Cyclical', 'industry' => 'Transportation', 'category' => 'Transportation Tech', 'market_cap' => 120000000000],
            ['symbol' => 'LYFT', 'name' => 'Lyft, Inc.', 'sector' => 'Consumer Cyclical', 'industry' => 'Transportation', 'category' => 'Transportation Tech', 'market_cap' => 15000000000],
            ['symbol' => 'RBLX', 'name' => 'Roblox Corporation', 'sector' => 'Technology', 'industry' => 'Entertainment', 'category' => 'Gaming & Metaverse', 'market_cap' => 20000000000],
            ['symbol' => 'SNAP', 'name' => 'Snap Inc.', 'sector' => 'Communication Services', 'industry' => 'Internet Services', 'category' => 'Social Media Tech', 'market_cap' => 25000000000],
            ['symbol' => 'PINS', 'name' => 'Pinterest, Inc.', 'sector' => 'Communication Services', 'industry' => 'Internet Services', 'category' => 'Social Media Tech', 'market_cap' => 15000000000],
            ['symbol' => 'DASH', 'name' => 'DoorDash, Inc.', 'sector' => 'Consumer Cyclical', 'industry' => 'Internet Retail', 'category' => 'Delivery Services', 'market_cap' => 30000000000],
        ];

        foreach ($stocks as $stockData) {
            Stock::firstOrCreate(
                ['symbol' => $stockData['symbol']],
                array_merge($stockData, [
                    'exchange' => 'NASDAQ',
                    'currency' => 'USD',
                    'country' => 'USA',
                    'type' => 'Common Stock',
                    'volatility_multiplier' => 1.0,
                ])
            );
        }

        $this->command->info('✓ ' . count($stocks) . ' stocks seeded successfully with categorization!');
    }
}
