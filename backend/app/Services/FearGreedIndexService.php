<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FearGreedIndexService
{
    /**
     * Get Fear & Greed Index from Alternative.me API
     * Returns value 0-100: 0-24=Extreme Fear, 25-44=Fear, 45-55=Neutral, 56-75=Greed, 76-100=Extreme Greed
     */
    public function getFearGreedIndex(): array
    {
        $cacheKey = 'fear_greed_index';
        
        // Cache for 1 hour (Fear & Greed updates once per day)
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        
        try {
            // Alternative.me Crypto Fear & Greed Index (free, no API key)
            // Note: This is crypto-focused but correlates well with stock market sentiment
            $response = Http::timeout(10)->get('https://api.alternative.me/fng/');
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['data'][0])) {
                    $fngData = $data['data'][0];
                    $value = (int) $fngData['value'];
                    $classification = $fngData['value_classification'];
                    
                    $result = [
                        'value' => $value,
                        'classification' => $classification,
                        'timestamp' => $fngData['timestamp'] ?? time(),
                        'updated_at' => now()->toDateTimeString(),
                        'description' => $this->getDescription($value),
                        'market_impact' => $this->getMarketImpact($value),
                    ];
                    
                    Cache::put($cacheKey, $result, 3600); // 1 hour
                    return $result;
                }
            }
        } catch (\Exception $e) {
            Log::warning("Failed to fetch Fear & Greed Index: " . $e->getMessage());
        }
        
        // Return default neutral value if API fails
        return $this->getDefaultIndex();
    }
    
    /**
     * Get default neutral index
     */
    protected function getDefaultIndex(): array
    {
        return [
            'value' => 50,
            'classification' => 'Neutral',
            'timestamp' => time(),
            'updated_at' => now()->toDateTimeString(),
            'description' => 'Market sentiment data unavailable',
            'market_impact' => [
                'multiplier' => 1.0,
                'bias' => 0,
                'risk_level' => 'medium',
            ],
        ];
    }
    
    /**
     * Get human-readable description
     */
    protected function getDescription(int $value): string
    {
        if ($value <= 24) {
            return 'Extreme Fear: Market is oversold, potentially good buying opportunity. Investors are very worried.';
        } elseif ($value <= 44) {
            return 'Fear: Market sentiment is negative. Investors are concerned but not panicking.';
        } elseif ($value <= 55) {
            return 'Neutral: Market is balanced. No strong emotions driving decisions.';
        } elseif ($value <= 75) {
            return 'Greed: Market sentiment is positive. Investors are confident, risk of overvaluation.';
        } else {
            return 'Extreme Greed: Market may be overvalued. High risk of correction. Be cautious with new positions.';
        }
    }
    
    /**
     * Get market impact for predictions
     */
    protected function getMarketImpact(int $value): array
    {
        // Calculate multiplier for prediction ranges
        // Extreme fear/greed = higher volatility = wider prediction ranges
        $deviationFromNeutral = abs($value - 50) / 50; // 0 to 1
        $multiplier = 1.0 + ($deviationFromNeutral * 0.5); // 1.0 to 1.5
        
        // Calculate bias (-1 to +1)
        $bias = ($value - 50) / 50; // -1 (extreme fear) to +1 (extreme greed)
        
        // Risk level
        if ($value <= 20 || $value >= 80) {
            $riskLevel = 'very_high';
        } elseif ($value <= 30 || $value >= 70) {
            $riskLevel = 'high';
        } elseif ($value <= 40 || $value >= 60) {
            $riskLevel = 'medium';
        } else {
            $riskLevel = 'low';
        }
        
        return [
            'multiplier' => round($multiplier, 2),
            'bias' => round($bias, 2),
            'risk_level' => $riskLevel,
        ];
    }
    
    /**
     * Adjust prediction confidence based on Fear & Greed
     */
    public function adjustConfidence(int $baseConfidence, int $fearGreedValue): int
    {
        // Extreme values reduce confidence (market is unpredictable)
        $deviationFromNeutral = abs($fearGreedValue - 50);
        
        if ($deviationFromNeutral > 40) {
            // Extreme fear or greed: reduce confidence by 15-20%
            $confidenceReduction = 15 + ($deviationFromNeutral - 40) / 10;
            $baseConfidence -= (int) $confidenceReduction;
        } elseif ($deviationFromNeutral > 25) {
            // Strong fear or greed: reduce confidence by 5-10%
            $confidenceReduction = 5 + ($deviationFromNeutral - 25) / 3;
            $baseConfidence -= (int) $confidenceReduction;
        }
        
        return max(30, min(95, $baseConfidence));
    }
}
