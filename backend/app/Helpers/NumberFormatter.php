<?php

namespace App\Helpers;

class NumberFormatter
{
    /**
     * Format volume in millions with 2 decimal places
     * 
     * @param int|float $volume
     * @param int $decimals
     * @return string
     */
    public static function formatVolume($volume, int $decimals = 2): string
    {
        if ($volume === null || $volume === 0) {
            return '0.00M';
        }
        
        $volumeInMillions = $volume / 1000000;
        return number_format($volumeInMillions, $decimals) . 'M';
    }
    
    /**
     * Format large numbers with K, M, B suffixes
     * 
     * @param int|float $number
     * @param int $decimals
     * @return string
     */
    public static function formatLargeNumber($number, int $decimals = 2): string
    {
        if ($number === null) {
            return '0';
        }
        
        $abs = abs($number);
        
        if ($abs >= 1000000000) {
            // Billions
            return number_format($number / 1000000000, $decimals) . 'B';
        } elseif ($abs >= 1000000) {
            // Millions
            return number_format($number / 1000000, $decimals) . 'M';
        } elseif ($abs >= 1000) {
            // Thousands
            return number_format($number / 1000, $decimals) . 'K';
        }
        
        return number_format($number, $decimals);
    }
    
    /**
     * Format percentage with sign
     * 
     * @param float $percent
     * @param int $decimals
     * @return string
     */
    public static function formatPercent(float $percent, int $decimals = 2): string
    {
        $sign = $percent > 0 ? '+' : '';
        return $sign . number_format($percent, $decimals) . '%';
    }
    
    /**
     * Format price with currency symbol
     * 
     * @param float $price
     * @param string $currency
     * @param int $decimals
     * @return string
     */
    public static function formatPrice(float $price, string $currency = '$', int $decimals = 2): string
    {
        return $currency . number_format($price, $decimals);
    }
    
    /**
     * Format market cap
     * 
     * @param int|float $marketCap (in millions)
     * @param int $decimals
     * @return string
     */
    public static function formatMarketCap($marketCap, int $decimals = 2): string
    {
        if ($marketCap === null || $marketCap === 0) {
            return 'N/A';
        }
        
        // Market cap is stored in millions, so convert to appropriate unit
        if ($marketCap >= 1000000) {
            // Trillions
            return number_format($marketCap / 1000000, $decimals) . 'T';
        } elseif ($marketCap >= 1000) {
            // Billions
            return number_format($marketCap / 1000, $decimals) . 'B';
        }
        
        // Millions
        return number_format($marketCap, $decimals) . 'M';
    }
    
    /**
     * Format ratio/multiplier
     * 
     * @param float $ratio
     * @param int $decimals
     * @return string
     */
    public static function formatRatio(float $ratio, int $decimals = 2): string
    {
        return number_format($ratio, $decimals) . 'x';
    }
}
