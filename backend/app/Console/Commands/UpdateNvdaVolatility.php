<?php

namespace App\Console\Commands;

use App\Models\Stock;
use Illuminate\Console\Command;

class UpdateNvdaVolatility extends Command
{
    protected $signature = 'stocks:update-nvda-volatility {multiplier=1.4 : Volatility multiplier (default: 1.4)}';
    protected $description = 'Update NVIDIA volatility multiplier to reasonable level';

    public function handle()
    {
        $multiplier = (float) $this->argument('multiplier');
        
        $this->info('Updating NVIDIA (NVDA) volatility multiplier...');
        
        $nvda = Stock::where('symbol', 'NVDA')->first();
        
        if (!$nvda) {
            $this->error('NVDA stock not found in database!');
            return 1;
        }
        
        $this->info("Current settings:");
        $this->line("  Volatility Multiplier: " . ($nvda->volatility_multiplier ?? 'NULL'));
        $this->line("  Category: " . ($nvda->category ?? 'NULL'));
        
        // Update with specified volatility
        // NVIDIA: 1.4x (moderate volatility) - produces ~3-4% predictions for 4% actual moves
        // Rationale: Tech mega-cap, volatile but should match actual moves closely
        $nvda->update([
            'volatility_multiplier' => $multiplier,
            'category' => 'Technology - Semiconductors'
        ]);
        
        $this->info("\nUpdated settings:");
        $this->line("  Volatility Multiplier: {$nvda->volatility_multiplier}");
        $this->line("  Category: {$nvda->category}");
        
        $this->info("\n✅ Success! NVDA now has volatility multiplier of {$multiplier}");
        $this->comment("This will produce more balanced predictions (closer to actual moves)");
        
        return 0;
    }
}
