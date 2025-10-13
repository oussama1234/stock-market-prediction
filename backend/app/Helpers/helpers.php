<?php

use App\Helpers\NumberFormatter;

if (!function_exists('format_volume')) {
    /**
     * Format volume in millions
     */
    function format_volume($volume, int $decimals = 2): string
    {
        return NumberFormatter::formatVolume($volume, $decimals);
    }
}

if (!function_exists('format_large_number')) {
    /**
     * Format large numbers with K, M, B suffixes
     */
    function format_large_number($number, int $decimals = 2): string
    {
        return NumberFormatter::formatLargeNumber($number, $decimals);
    }
}

if (!function_exists('format_percent')) {
    /**
     * Format percentage with sign
     */
    function format_percent(float $percent, int $decimals = 2): string
    {
        return NumberFormatter::formatPercent($percent, $decimals);
    }
}

if (!function_exists('format_price')) {
    /**
     * Format price with currency symbol
     */
    function format_price(float $price, string $currency = '$', int $decimals = 2): string
    {
        return NumberFormatter::formatPrice($price, $currency, $decimals);
    }
}

if (!function_exists('format_market_cap')) {
    /**
     * Format market cap
     */
    function format_market_cap($marketCap, int $decimals = 2): string
    {
        return NumberFormatter::formatMarketCap($marketCap, $decimals);
    }
}

if (!function_exists('format_ratio')) {
    /**
     * Format ratio/multiplier
     */
    function format_ratio(float $ratio, int $decimals = 2): string
    {
        return NumberFormatter::formatRatio($ratio, $decimals);
    }
}
