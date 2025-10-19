<?php

namespace App\Console\Commands;

use App\Models\Stock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignStockCategories extends Command
{
    protected $signature = 'stocks:assign-categories';
    protected $description = 'Assign categories to stocks based on their sector, industry, and symbol';

    public function handle()
    {
        $this->info('Assigning categories to stocks...');
        
        // Get all categories
        $categories = DB::table('stock_categories')->get()->keyBy('name');
        
        if ($categories->isEmpty()) {
            $this->error('No stock categories found! Please run: php artisan db:seed --class=StockCategoriesSeeder');
            return 1;
        }
        
        $stocks = Stock::all();
        $updated = 0;
        $skipped = 0;
        
        $bar = $this->output->createProgressBar($stocks->count());
        $bar->start();
        
        foreach ($stocks as $stock) {
            $categoryId = $this->determineCategoryForStock($stock, $categories);
            
            if ($categoryId) {
                $stock->update(['category_id' => $categoryId]);
                $updated++;
            } else {
                $skipped++;
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        $this->info("✓ Assigned categories to {$updated} stocks");
        if ($skipped > 0) {
            $this->warn("⚠ Skipped {$skipped} stocks (couldn't determine category)");
        }
        
        return 0;
    }
    
    protected function determineCategoryForStock($stock, $categories)
    {
        $symbol = strtoupper($stock->symbol);
        $sector = strtolower($stock->sector ?? '');
        $industry = strtolower($stock->industry ?? '');
        
        // Specific symbol mappings (high priority)
        $symbolMap = [
            // Meme stocks
            'GME' => 'Meme Stock',
            'AMC' => 'Meme Stock',
            'BB' => 'Meme Stock',
            'BBBY' => 'Meme Stock',
            
            // Crypto-related
            'COIN' => 'Cryptocurrency Related',
            'MSTR' => 'Cryptocurrency Related',
            'RIOT' => 'Cryptocurrency Related',
            'MARA' => 'Cryptocurrency Related',
            
            // Tech Growth
            'NVDA' => 'Tech Growth',
            'TSLA' => 'Tech Growth',
            'PLTR' => 'Tech Growth',
            'SNOW' => 'Tech Growth',
            'CRWD' => 'Tech Growth',
            'NET' => 'Tech Growth',
            'DDOG' => 'Tech Growth',
            'ZS' => 'Tech Growth',
            
            // Tech Blue Chip
            'AAPL' => 'Tech Blue Chip',
            'MSFT' => 'Tech Blue Chip',
            'GOOGL' => 'Tech Blue Chip',
            'GOOG' => 'Tech Blue Chip',
            'META' => 'Tech Blue Chip',
            'AMZN' => 'E-Commerce',
            'NFLX' => 'Entertainment Media',
            'DIS' => 'Entertainment Media',
            
            // Semiconductors
            'AMD' => 'Semiconductor',
            'INTC' => 'Semiconductor',
            'AVGO' => 'Semiconductor',
            'TSM' => 'Semiconductor',
            'QCOM' => 'Semiconductor',
            'MU' => 'Semiconductor',
            'AMAT' => 'Semiconductor',
            'LRCX' => 'Semiconductor',
            
            // Financial
            'JPM' => 'Financial Services',
            'BAC' => 'Financial Services',
            'WFC' => 'Financial Services',
            'GS' => 'Financial Services',
            'MS' => 'Financial Services',
            'C' => 'Financial Services',
            
            // Healthcare
            'JNJ' => 'Healthcare',
            'UNH' => 'Healthcare',
            'PFE' => 'Healthcare',
            'ABBV' => 'Healthcare',
            'TMO' => 'Healthcare',
            
            // Consumer Staples
            'PG' => 'Consumer Staples',
            'KO' => 'Consumer Staples',
            'PEP' => 'Consumer Staples',
            'WMT' => 'Consumer Staples',
            'COST' => 'Consumer Staples',
            
            // Energy
            'XOM' => 'Energy',
            'CVX' => 'Energy',
            'COP' => 'Energy',
            
            // Industrials
            'CAT' => 'Industrials',
            'BA' => 'Industrials',
            'GE' => 'Industrials',
            'HON' => 'Industrials',
        ];
        
        if (isset($symbolMap[$symbol])) {
            return $categories[$symbolMap[$symbol]]->id ?? null;
        }
        
        // Industry-based mappings
        if (str_contains($industry, 'semiconductor') || str_contains($industry, 'chip')) {
            return $categories['Semiconductor']->id ?? null;
        }
        
        if (str_contains($industry, 'biotech') || str_contains($industry, 'biotechnology')) {
            return $categories['Biotech']->id ?? null;
        }
        
        if (str_contains($industry, 'crypto') || str_contains($industry, 'blockchain')) {
            return $categories['Cryptocurrency Related']->id ?? null;
        }
        
        if (str_contains($industry, 'e-commerce') || str_contains($industry, 'online retail')) {
            return $categories['E-Commerce']->id ?? null;
        }
        
        if (str_contains($industry, 'streaming') || str_contains($industry, 'entertainment') || 
            str_contains($industry, 'media')) {
            return $categories['Entertainment Media']->id ?? null;
        }
        
        // Sector-based mappings (fallback)
        $sectorMap = [
            'technology' => 'Tech Blue Chip',
            'information technology' => 'Tech Blue Chip',
            'software' => 'Tech Blue Chip',
            'financial' => 'Financial Services',
            'financials' => 'Financial Services',
            'health care' => 'Healthcare',
            'healthcare' => 'Healthcare',
            'consumer staples' => 'Consumer Staples',
            'consumer defensive' => 'Consumer Staples',
            'energy' => 'Energy',
            'utilities' => 'Utilities',
            'utility' => 'Utilities',
            'industrial' => 'Industrials',
            'industrials' => 'Industrials',
            'real estate' => 'Real Estate',
        ];
        
        foreach ($sectorMap as $key => $categoryName) {
            if (str_contains($sector, $key)) {
                return $categories[$categoryName]->id ?? null;
            }
        }
        
        // Default to Tech Blue Chip if nothing matches
        return $categories['Tech Blue Chip']->id ?? null;
    }
}
