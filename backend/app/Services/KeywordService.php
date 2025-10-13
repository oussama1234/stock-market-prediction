<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Centralized Keyword Service
 * 
 * Provides database-driven keywords with fallback for all services
 * Cached for performance
 */
class KeywordService
{
    protected const CACHE_KEY = 'priority_keywords';
    protected const CACHE_TTL = 3600; // 1 hour

    /**
     * Get all keywords organized by sentiment
     * Returns: ['bearish' => ['keyword' => score, ...], 'bullish' => ['keyword' => score, ...]]
     */
    public function getKeywords(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            try {
                if (DB::getSchemaBuilder()->hasTable('priority_keywords')) {
                    $rows = DB::table('priority_keywords')
                        ->where('active', true)
                        ->select('keyword', 'sentiment', 'score')
                        ->get();
                    
                    if ($rows->count() > 0) {
                        $keywords = ['bearish' => [], 'bullish' => []];
                        foreach ($rows as $row) {
                            $sentiment = strtolower($row->sentiment ?? 'bearish');
                            $keyword = strtolower($row->keyword);
                            $score = (int)($row->score ?? ($sentiment === 'bullish' ? 1 : -1));
                            
                            if (!isset($keywords[$sentiment])) {
                                $keywords[$sentiment] = [];
                            }
                            $keywords[$sentiment][$keyword] = $score;
                        }
                        
                        Log::info('Loaded keywords from database', [
                            'bearish_count' => count($keywords['bearish']),
                            'bullish_count' => count($keywords['bullish'])
                        ]);
                        
                        return $keywords;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to load keywords from database: ' . $e->getMessage());
            }
            
            // Fallback to static keywords
            return $this->getFallbackKeywords();
        });
    }

    /**
     * Get bullish keywords only
     */
    public function getBullishKeywords(): array
    {
        return $this->getKeywords()['bullish'] ?? [];
    }

    /**
     * Get bearish keywords only
     */
    public function getBearishKeywords(): array
    {
        return $this->getKeywords()['bearish'] ?? [];
    }
    
    /**
     * Get bullish keywords with weights (for sentiment analysis)
     * Converts scores to sentiment weights
     */
    public function getBullishKeywordsWeighted(): array
    {
        $keywords = $this->getBullishKeywords();
        $weighted = [];
        
        foreach ($keywords as $kw => $score) {
            // Convert score (1-4) to weight (1.0-4.0)
            $weighted[$kw] = (float) $score;
        }
        
        return $weighted;
    }
    
    /**
     * Get bearish keywords with weights (for sentiment analysis)
     * Returns positive weights (absolute values)
     */
    public function getBearishKeywordsWeighted(): array
    {
        $keywords = $this->getBearishKeywords();
        $weighted = [];
        
        foreach ($keywords as $kw => $score) {
            // Convert negative score to positive weight
            $weighted[$kw] = (float) abs($score);
        }
        
        return $weighted;
    }

    /**
     * Get high-impact keywords (score >= 3 or <= -3)
     */
    public function getHighImpactKeywords(): array
    {
        $all = $this->getKeywords();
        $high = ['bearish' => [], 'bullish' => []];
        
        foreach ($all['bearish'] as $kw => $score) {
            if ($score <= -3) {
                $high['bearish'][$kw] = $score;
            }
        }
        
        foreach ($all['bullish'] as $kw => $score) {
            if ($score >= 3) {
                $high['bullish'][$kw] = $score;
            }
        }
        
        return $high;
    }

    /**
     * Get all keywords as flat list (for simple matching)
     */
    public function getAllKeywordsList(): array
    {
        $all = $this->getKeywords();
        return array_merge(
            array_keys($all['bearish']),
            array_keys($all['bullish'])
        );
    }

    /**
     * Check if text contains any high-impact keywords
     */
    public function containsHighImpact(string $text): bool
    {
        $text = strtolower($text);
        $highImpact = $this->getHighImpactKeywords();
        
        foreach ($highImpact['bearish'] as $kw => $score) {
            if (str_contains($text, $kw)) {
                return true;
            }
        }
        
        foreach ($highImpact['bullish'] as $kw => $score) {
            if (str_contains($text, $kw)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Clear keyword cache
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Log::info('Keyword cache cleared');
    }

    /**
     * Fallback keywords (matches StockDetailsService)
     */
    protected function getFallbackKeywords(): array
    {
        Log::info('Using fallback keywords');
        
        return [
            'bearish' => [
                // Score -3: Critical negative
                'tariff' => -3, 'tariffs' => -3, 'new tariff' => -3,
                'trade war' => -3, 'ban' => -3, 'banned' => -3,
                'shutdown' => -3, 'shut down' => -3, 'bankruptcy' => -3,
                'crash' => -3, 'fraud' => -3, 'scandal' => -3,
                'earnings miss' => -3, 'revenue miss' => -3,
                
                // Score -2: Medium negative
                'lawsuit' => -2, 'layoff' => -2, 'layoffs' => -2,
                'restructuring' => -2, 'investigation' => -2,
                'disappointing earnings' => -2, 'miss earnings' => -2,
                'slowdown' => -2, 'weakness' => -2,
            ],
            'bullish' => [
                // Score +4: Mega positive (Trump tariff dismissal & major rebounds)
                'trump dismisses tariff' => 4, 'trump dismisses' => 4,
                'tariff dismisses' => 4, 'tariff dismissed' => 4,
                'stock futures rebound' => 4, 'futures rebound' => 4,
                'stock market rebound' => 4, 'market rebound' => 4,
                
                // Score +3: Critical positive
                'stock rebound' => 3, 'stock rises' => 3, 'stock rise' => 3,
                'tariff cut' => 3, 'tariff relief' => 3, 'stop tariff' => 3,
                'trade war ends' => 3, 'trade deal' => 3,
                'beat earnings' => 3, 'earnings beat' => 3, 'record earnings' => 3,
                'breakthrough' => 3, 'fda approval' => 3,
                'major contract' => 3, 'stock buyback' => 3,
                'strong ai demand' => 3, 'ai breakthrough' => 3, 'ai leader' => 3,
                'mega cap rally' => 3, 'tech giants surge' => 3,
                
                // Score +2: Strong positive (AI & Tech specific)
                'strong earnings' => 2, 'exceeded expectations' => 2,
                'upgrade' => 2, 'outperform' => 2, 'partnership' => 2,
                'expansion' => 2, 'rally' => 2, 'surge' => 2,
                'ai-driven growth' => 2, 'ai revenue' => 2, 'ai adoption' => 2,
                'nvidia' => 2, 'ai chips' => 2, 'data center' => 2,
                'mega cap' => 2, 'tech giant' => 2,
                
                // Score +1: Slight positive
                'q1 earnings' => 1, 'q2 earnings' => 1,
                'q3 earnings' => 1, 'q4 earnings' => 1,
                'earnings report' => 1, 'positive' => 1,
                'growth' => 1, 'recovery' => 1,
                'artificial intelligence' => 1, 'ai' => 1,
            ]
        ];
    }
}
