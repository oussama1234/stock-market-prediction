<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed essential data for production
        $this->call([
            StockCategoriesSeeder::class,      // Stock categories (required)
            PriorityKeywordsSeeder::class,     // Sentiment keywords (required)
            PopularStocksSeeder::class,        // Popular stocks (required)
            // ImportantNewsSeeder::class,     // Uncomment for testing only
        ]);

        $this->command->info('✅ All essential seeders completed!');
        
        // Optional: Create test user (only for development)
        if (app()->environment('local', 'development')) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
            $this->command->info('✅ Test user created for development');
        }
    }
}
