<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GlobalMarketSentimentService
{
    /**
     * Analyze global market sentiment from keywords and recent events
     * 
     * This detects market-wide events like:
     * - Tariff implementation/relief
     * - Fed rate decisions
     * - Major economic announcements
     * - Tech sector news (AI, chips)
     * 
     * @return array Global sentiment data
     */
    public function getGlobalMarketSentiment(): array
    {
        $cacheKey = 'global_market_sentiment';
        
        return Cache::remember($cacheKey, now()->addMinutes(15), function () {
            return $this->analyzeGlobalSentiment();
        });
    }
    
    /**
     * Analyze global sentiment from various sources
     */
    protected function analyzeGlobalSentiment(): array
    {
        $sentiment = [
            'overall_score' => 0.0,
            'tariff_sentiment' => 0.0,
            'tech_sector_sentiment' => 0.0,
            'market_sentiment' => 0.0,
            'events' => [],
            'applies_to_sectors' => ['all'],
        ];
        
        // Analyze recent news articles for global keywords
        $recentNews = DB::table('news_articles')
            ->where('published_at', '>=', now()->subDays(3))
            ->get(['title', 'description', 'content', 'sentiment_score', 'published_at']);
        
        if ($recentNews->isEmpty()) {
            return $sentiment;
        }
        
        // Get priority keywords
        $keywords = DB::table('priority_keywords')
            ->where('active', 1)
            ->get()
            ->keyBy('keyword');
        
        $tariffScore = 0;
        $tariffCount = 0;
        $techScore = 0;
        $techCount = 0;
        $detectedEvents = [];
        
        foreach ($recentNews as $article) {
            $text = strtolower($article->title . ' ' . ($article->description ?? '') . ' ' . ($article->content ?? ''));
            
            // Detect tariff-related events
            $tariffEvent = $this->detectTariffEvent($text, $keywords);
            if ($tariffEvent) {
                $tariffScore += $tariffEvent['score'];
                $tariffCount++;
                $detectedEvents[] = $tariffEvent;
                
                Log::info("Global tariff event detected", [
                    'event' => $tariffEvent['type'],
                    'score' => $tariffEvent['score'],
                    'article' => substr($article->title, 0, 100)
                ]);
            }
            
            // Detect tech sector events
            $techEvent = $this->detectTechSectorEvent($text, $keywords);
            if ($techEvent) {
                $techScore += $techEvent['score'];
                $techCount++;
                $detectedEvents[] = $techEvent;
            }
        }
        
        // Calculate averages
        if ($tariffCount > 0) {
            $sentiment['tariff_sentiment'] = $tariffScore / $tariffCount;
        }
        
        if ($techCount > 0) {
            $sentiment['tech_sector_sentiment'] = $techScore / $techCount;
        }
        
        // Overall sentiment is weighted average
        $sentiment['overall_score'] = ($sentiment['tariff_sentiment'] * 0.6) + 
                                      ($sentiment['tech_sector_sentiment'] * 0.4);
        
        $sentiment['events'] = $detectedEvents;
        
        // Determine which sectors this affects most
        if (abs($sentiment['tariff_sentiment']) > 0.3) {
            $sentiment['applies_to_sectors'][] = 'tech';
            $sentiment['applies_to_sectors'][] = 'manufacturing';
        }
        
        Log::info("Global market sentiment analyzed", [
            'overall' => round($sentiment['overall_score'], 3),
            'tariff' => round($sentiment['tariff_sentiment'], 3),
            'tech' => round($sentiment['tech_sector_sentiment'], 3),
            'events_detected' => count($detectedEvents)
        ]);
        
        return $sentiment;
    }
    
    /**
     * Detect tariff-related events and their sentiment
     */
    protected function detectTariffEvent(string $text, $keywords): ?array
    {
        $tariffPatterns = [
            // BULLISH - Tariff relief/removal
            'tariff relief' => 'tariff_relief',
            'tariff removed' => 'tariff_removal',
            'tariff cut' => 'tariff_reduction',
            'tariff pause' => 'tariff_pause',
            'suspend tariff' => 'tariff_suspension',
            'trade deal' => 'trade_agreement',
            'trade agreement' => 'trade_agreement',
            'trade war ends' => 'trade_war_end',
            'trump removes tariff' => 'tariff_removal',
            'china tariff removed' => 'tariff_removal',
            
            // BEARISH - Tariff implementation/increase
            'tariff implementation' => 'tariff_implementation',
            'new tariff' => 'tariff_implementation',
            'tariff increase' => 'tariff_increase',
            'tariff hike' => 'tariff_increase',
            'implement tariff' => 'tariff_implementation',
            'trump tariff' => 'tariff_announcement',
            'trade war escalates' => 'trade_war_escalation',
        ];
        
        foreach ($tariffPatterns as $pattern => $eventType) {
            if (str_contains($text, $pattern)) {
                // Get keyword score from database
                $keyword = $keywords->get($pattern);
                $score = $keyword ? (float)$keyword->score / 10.0 : 0.0; // Normalize to -1 to +1
                
                return [
                    'type' => $eventType,
                    'pattern' => $pattern,
                    'score' => $score,
                    'sentiment' => $score > 0 ? 'bullish' : 'bearish',
                    'detected_at' => now()->toIso8601String()
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Detect tech sector specific events
     */
    protected function detectTechSectorEvent(string $text, $keywords): ?array
    {
        $techPatterns = [
            // BULLISH tech
            'ai boom' => 0.5,
            'ai growth' => 0.4,
            'chip demand' => 0.4,
            'semiconductor growth' => 0.5,
            'data center' => 0.3,
            'cloud computing growth' => 0.4,
            
            // BEARISH tech
            'chip shortage' => -0.4,
            'semiconductor ban' => -0.5,
            'export restriction' => -0.4,
        ];
        
        foreach ($techPatterns as $pattern => $score) {
            if (str_contains($text, $pattern)) {
                return [
                    'type' => 'tech_sector_' . ($score > 0 ? 'positive' : 'negative'),
                    'pattern' => $pattern,
                    'score' => $score,
                    'sentiment' => $score > 0 ? 'bullish' : 'bearish',
                    'detected_at' => now()->toIso8601String()
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Apply global sentiment to a stock based on its characteristics
     * 
     * @param \App\Models\Stock $stock
     * @param float $stockSentiment Individual stock sentiment
     * @return array Blended sentiment with global influence
     */
    public function applyGlobalSentimentToStock($stock, float $stockSentiment): array
    {
        $globalSentiment = $this->getGlobalMarketSentiment();
        
        // Determine how much global sentiment affects this stock
        $globalWeight = $this->calculateGlobalWeight($stock, $globalSentiment);
        
        // Blend individual and global sentiment
        $blendedSentiment = ($stockSentiment * (1 - $globalWeight)) + 
                           ($globalSentiment['overall_score'] * $globalWeight);
        
        return [
            'original_sentiment' => $stockSentiment,
            'global_sentiment' => $globalSentiment['overall_score'],
            'blended_sentiment' => $blendedSentiment,
            'global_weight' => $globalWeight,
            'global_events' => $globalSentiment['events'],
            'reason' => $this->getGlobalSentimentReason($globalSentiment, $globalWeight)
        ];
    }
    
    /**
     * Calculate how much global sentiment should affect this stock
     */
    protected function calculateGlobalWeight($stock, array $globalSentiment): float
    {
        $baseWeight = 0.4; // 40% global influence by default (increased from 30%)
        
        // Tech stocks are MORE affected by tariffs (especially AVGO, NVDA)
        $techSymbols = ['NVDA', 'AVGO', 'AMD', 'INTC', 'QCOM', 'TSM', 'GOOGL', 'META', 'MSFT'];
        if (in_array($stock->symbol, $techSymbols)) {
            $baseWeight += 0.15; // 55% total for tech stocks
        }
        
        // Financial stocks also affected by tariff/trade news
        $financialSymbols = ['V', 'MA', 'JPM', 'BRK.A'];
        if (in_array($stock->symbol, $financialSymbols)) {
            $baseWeight += 0.1; // 50% for financials
        }
        
        // If global tariff sentiment is STRONG, increase weight significantly
        if (abs($globalSentiment['tariff_sentiment']) > 0.5) {
            $baseWeight += 0.20; // Strong global events dominate
        } elseif (abs($globalSentiment['tariff_sentiment']) > 0.4) {
            $baseWeight += 0.15;
        }
        
        // Cap at 75% (allow strong global influence)
        return min(0.75, $baseWeight);
    }
    
    /**
     * Get human-readable reason for global sentiment influence
     */
    protected function getGlobalSentimentReason(array $globalSentiment, float $weight): string
    {
        if ($weight < 0.2) {
            return '';
        }
        
        if (abs($globalSentiment['tariff_sentiment']) > 0.4) {
            if ($globalSentiment['tariff_sentiment'] > 0) {
                return 'Global tariff relief boosting tech sector';
            } else {
                return 'Global tariff concerns weighing on markets';
            }
        }
        
        if (abs($globalSentiment['tech_sector_sentiment']) > 0.3) {
            if ($globalSentiment['tech_sector_sentiment'] > 0) {
                return 'Positive tech sector trends';
            } else {
                return 'Tech sector headwinds';
            }
        }
        
        return 'General market sentiment';
    }
    
    /**
     * Clear the global sentiment cache
     */
    public function clearCache(): void
    {
        Cache::forget('global_market_sentiment');
        Log::info("Global market sentiment cache cleared");
    }
}
