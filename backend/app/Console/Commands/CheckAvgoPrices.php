<?php

namespace App\Console\Commands;

use App\Models\Stock;
use App\Models\StockPrice;
use Illuminate\Console\Command;

class CheckAvgoPrices extends Command
{
    protected $signature = 'stocks:check-avgo';
    protected $description = 'Check AVGO price history';

    public function handle()
    {
        $stock = Stock::where('symbol', 'AVGO')->first();
        
        if (!$stock) {
            $this->error('AVGO not found!');
            return Command::FAILURE;
        }
        
        $prices = StockPrice::where('stock_id', $stock->id)
            ->where('interval', '1day')
            ->orderBy('price_date', 'desc')
            ->limit(5)
            ->get();
        
        $this->info('AVGO Price History:');
        $this->newLine();
        
        foreach ($prices as $price) {
            $this->info("Date: {$price->price_date}");
            $this->info("  Close: {$price->close}");
            $this->info("  Prev Close: {$price->previous_close}");
            $this->info("  Open: {$price->open}");
            $this->newLine();
        }
        
        return Command::SUCCESS;
    }
}
