<?php

namespace App\Services;

use App\Models\NewsArticle;
use Illuminate\Support\Facades\Log;

class SentimentService
{
    protected ?KeywordService $keywordService = null;
    
    /**
     * Get positive keywords from KeywordService with fallback
     */
    protected function getPositiveKeywords(): array
    {
        if ($this->keywordService === null) {
            $this->keywordService = app(KeywordService::class);
        }
        
        return $this->keywordService->getBullishKeywordsWeighted();
    }
    
    /**
     * Get negative keywords from KeywordService with fallback  
     */
    protected function getNegativeKeywords(): array
    {
        if ($this->keywordService === null) {
            $this->keywordService = app(KeywordService::class);
        }
        
        // Get with negative weights
        $keywords = $this->keywordService->getBearishKeywordsWeighted();
        $negative = [];
        
        foreach ($keywords as $kw => $weight) {
            $negative[$kw] = -$weight; // Make negative for sentiment scoring
        }
        
        return $negative;
    }
    
    /**
     * FALLBACK: Positive keywords (only used if KeywordService fails)
     */
    protected array $positiveKeywords = [
        // Mega positive (Trump tariff & major rebounds)
        'trump dismisses tariff' => 4.0,
        'trump dismisses' => 3.5,
        'tariff dismisses' => 3.5,
        'tariff dismissed' => 3.5,
        'stock futures rebound' => 3.5,
        'futures rebound' => 3.0,
        'stock market rebound' => 3.5,
        'market rebound' => 3.0,
        'stock rebound' => 2.5,
        'stock rises' => 2.5,
        'stock rise' => 2.5,
        
        // Strong AI & Tech
        'strong ai demand' => 3.0,
        'ai breakthrough' => 3.0,
        'ai leader' => 2.5,
        'mega cap rally' => 3.0,
        'tech giants surge' => 3.0,
        'ai-driven growth' => 2.5,
        'ai revenue' => 2.5,
        'ai adoption' => 2.0,
        'ai chips' => 2.0,
        'data center' => 2.0,
        'mega cap' => 2.0,
        'tech giant' => 2.0,
        'strong ai' => 2.0,
        'strong AI' => 2.0,
        'ai-driven' => 2.0,
        'AI-driven' => 2.0,
        'artificial intelligence' => 1.5,
        
        // Existing strong positives
        'surge' => 2.0,
        'soar' => 2.0,
        'rally' => 2.0,
        'breakthrough' => 2.0,
        'record high' => 2.5,
        'all-time high' => 2.5,
        'record earnings' => 2.5,
        'beat earnings' => 2.5,
        'earnings beat' => 2.5,
        'bullish' => 2.0,
        'growth' => 1.5,
        'profit' => 1.5,
        'gain' => 1.5,
        'rise' => 1.5,
        'increase' => 1.0,
        'positive' => 1.0,
        'strong' => 1.0,
        'outperform' => 1.5,
        'beat' => 1.5,
        'exceed' => 1.5,
        'success' => 1.0,
        'innovation' => 1.0,
        'opportunity' => 1.0,
        'optimistic' => 1.5,
        'upgrade' => 1.5,
        'buy' => 1.0,
        'recommend' => 1.0,
        'raised' => 2.0,
        'stock raised' => 2.5,
        'target raised' => 2.5,
        'price target raised' => 2.5,
    ];
    
    /**
     * Negative keywords and their weights
     */
    protected array $negativeKeywords = [
        'crash' => -2.5,
        'plunge' => -2.0,
        'plummet' => -2.0,
        'collapse' => -2.5,
        'bearish' => -2.0,
        'decline' => -1.5,
        'fall' => -1.5,
        'drop' => -1.5,
        'loss' => -1.5,
        'decrease' => -1.0,
        'negative' => -1.0,
        'weak' => -1.0,
        'underperform' => -1.5,
        'miss' => -1.5,
        'fail' => -1.5,
        'concern' => -1.0,
        'risk' => -1.0,
        'warning' => -1.5,
        'downgrade' => -1.5,
        'sell' => -1.0,
        'lawsuit' => -1.5,
        'investigation' => -1.5,
        'scandal' => -2.0,
        'fraud' => -2.5,
    ];
    
    /**
     * Analyze sentiment of text
     * Returns a score between -10 (very negative) and +10 (very positive)
     */
    public function analyzeSentiment(string $text): float
    {
        $text = strtolower($text);
        $score = 0.0;
        $matchCount = 0;
        
        // Get keywords from KeywordService
        $positiveKeywords = $this->getPositiveKeywords();
        $negativeKeywords = $this->getNegativeKeywords();
        
        // Check positive keywords
        foreach ($positiveKeywords as $keyword => $weight) {
            $count = substr_count($text, $keyword);
            if ($count > 0) {
                $score += ($weight * $count);
                $matchCount += $count;
            }
        }
        
        // Check negative keywords
        foreach ($negativeKeywords as $keyword => $weight) {
            $count = substr_count($text, $keyword);
            if ($count > 0) {
                $score += ($weight * $count);
                $matchCount += $count;
            }
        }
        
        // Normalize score to -10 to +10 range
        if ($matchCount > 0) {
            // Average the score and cap at -10 to +10
            $score = $score / max(1, $matchCount / 2);
            $score = max(-10, min(10, $score));
        }
        
        return round($score, 2);
    }
    
    /**
     * Analyze sentiment for a news article
     */
    public function analyzeArticle(NewsArticle $article): float
    {
        $text = implode(' ', array_filter([
            $article->title,
            $article->description,
            $article->content,
        ]));
        
        $score = $this->analyzeSentiment($text);
        
        // Update article with sentiment score
        $article->update(['sentiment_score' => $score]);
        
        Log::info("Analyzed sentiment for article {$article->id}: {$score}");
        
        return $score;
    }
    
    /**
     * Bulk analyze articles
     */
    public function bulkAnalyze(iterable $articles): array
    {
        $results = [];
        
        foreach ($articles as $article) {
            try {
                $score = $this->analyzeArticle($article);
                $results[] = [
                    'article_id' => $article->id,
                    'score' => $score,
                    'success' => true,
                ];
            } catch (\Exception $e) {
                Log::error("Failed to analyze article {$article->id}: " . $e->getMessage());
                $results[] = [
                    'article_id' => $article->id,
                    'score' => null,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Get sentiment label from score
     */
    public function getSentimentLabel(float $score): string
    {
        if ($score >= 3) return 'very positive';
        if ($score >= 1) return 'positive';
        if ($score > -1) return 'neutral';
        if ($score > -3) return 'negative';
        return 'very negative';
    }
    
    /**
     * Get sentiment color for UI
     */
    public function getSentimentColor(float $score): string
    {
        if ($score >= 3) return 'green';
        if ($score >= 1) return 'lightgreen';
        if ($score > -1) return 'gray';
        if ($score > -3) return 'orange';
        return 'red';
    }
    
    /**
     * Analyze quick sentiment from string (for API use)
     */
    public function quickAnalyze(string $text): array
    {
        $score = $this->analyzeSentiment($text);
        
        return [
            'score' => $score,
            'label' => $this->getSentimentLabel($score),
            'color' => $this->getSentimentColor($score),
        ];
    }
}
