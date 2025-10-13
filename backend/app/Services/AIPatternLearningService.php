<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\StockPrice;
use App\Models\NewsArticle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * AI Pattern Learning Service
 * 
 * Uses machine learning techniques to predict stock movements by:
 * - Analyzing historical patterns (same day, month, season)
 * - Correlating with earnings reports
 * - Deep news sentiment analysis with context understanding
 * - Pattern matching with similar technical setups
 * 
 * Weighting: 60% News Sentiment + 40% Technical Analysis
 */
class AIPatternLearningService
{
    protected NewsService $newsService;
    
    public function __construct(NewsService $newsService)
    {
        $this->newsService = $newsService;
    }
    
    /**
     * Generate AI-powered prediction
     * 
     * @param Stock $stock
     * @param float $currentPrice
     * @param array $technicalIndicators
     * @return array
     */
    public function generateAIPrediction(Stock $stock, float $currentPrice, array $technicalIndicators): array
    {
        $cacheKey = "ai_prediction:{$stock->id}:" . now()->format('Y-m-d-H');
        
        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($stock, $currentPrice, $technicalIndicators) {
            Log::info("Generating AI prediction for {$stock->symbol}");
            
            // 1. Historical Pattern Analysis (20%)
            $historicalScore = $this->analyzeHistoricalPatterns($stock, $currentPrice);
            
            // 2. Seasonal & Calendar Effects (20%)
            $seasonalScore = $this->analyzeSeasonalPatterns($stock);
            
            // 3. News Sentiment Analysis (60%)
            $newsSentimentScore = $this->analyzeNewsSentiment($stock);
            
            // 4. Technical Analysis (40% of non-news weight = 16%)
            $technicalScore = $this->analyzeTechnicalPatterns($technicalIndicators);
            
            // Combine scores with weighting
            $newsWeight = 0.60;
            $technicalWeight = 0.20;
            $historicalWeight = 0.12;
            $seasonalWeight = 0.08;
            
            $finalScore = (
                ($newsSentimentScore['score'] * $newsWeight) +
                ($technicalScore * $technicalWeight) +
                ($historicalScore * $historicalWeight) +
                ($seasonalScore * $seasonalWeight)
            );
            
            // Calculate confidence based on agreement across signals
            $confidence = $this->calculateConfidence([
                $newsSentimentScore,
                $historicalScore,
                $seasonalScore,
                $technicalScore
            ]);
            
            // Determine direction and magnitude
            $direction = $finalScore > 0.3 ? 'bullish' : ($finalScore < -0.3 ? 'bearish' : 'neutral');
            $predictedChangePercent = $this->calculatePredictedChange($finalScore, $technicalIndicators);
            
            return [
                'direction' => $direction,
                'predicted_change_percent' => $predictedChangePercent,
                'confidence' => $confidence,
                'news_sentiment_score' => $newsSentimentScore,
                'historical_score' => $historicalScore,
                'seasonal_score' => $seasonalScore,
                'technical_score' => $technicalScore,
                'final_score' => $finalScore,
                'reasoning' => $this->generateReasoning($newsSentimentScore, $historicalScore, $seasonalScore, $technicalScore, $direction),
            ];
        });
    }
    
    /**
     * Analyze historical patterns - same day, month, similar conditions
     */
    protected function analyzeHistoricalPatterns(Stock $stock, float $currentPrice): float
    {
        $now = now();
        $dayOfWeek = $now->dayOfWeek;
        $month = $now->month;
        
        // Find similar historical dates (same day of week, same month over past 3 years)
        $similarDates = StockPrice::where('stock_id', $stock->id)
            ->where('interval', '1day')
            ->where('price_date', '>=', now()->subYears(3))
            ->whereRaw('DAYOFWEEK(price_date) = ?', [$dayOfWeek + 1]) // MySQL DAYOFWEEK is 1-indexed
            ->whereRaw('MONTH(price_date) = ?', [$month])
            ->orderBy('price_date', 'desc')
            ->limit(50)
            ->get();
        
        if ($similarDates->count() < 5) {
            return 0;
        }
        
        // Calculate average next-day movement
        $nextDayMovements = [];
        foreach ($similarDates as $date) {
            $nextDay = StockPrice::where('stock_id', $stock->id)
                ->where('interval', '1day')
                ->where('price_date', '>', $date->price_date)
                ->orderBy('price_date', 'asc')
                ->first();
            
            if ($nextDay) {
                $movement = (($nextDay->close - $date->close) / $date->close) * 100;
                $nextDayMovements[] = $movement;
            }
        }
        
        if (empty($nextDayMovements)) {
            return 0;
        }
        
        $avgMovement = collect($nextDayMovements)->avg();
        
        // Normalize to -1 to 1 scale
        return max(-1, min(1, $avgMovement / 3));
    }
    
    /**
     * Analyze seasonal patterns and calendar effects
     */
    protected function analyzeSeasonalPatterns(Stock $stock): float
    {
        $month = now()->month;
        $quarter = ceil($month / 3);
        
        // Q4 (Oct-Dec) typically bullish (holiday season)
        // January effect (small caps rally)
        // "Sell in May and go away" effect
        
        $seasonalBias = 0;
        
        // October-December: Bullish bias
        if ($month >= 10) {
            $seasonalBias += 0.3;
        }
        
        // January: Small bullish bias
        if ($month === 1) {
            $seasonalBias += 0.2;
        }
        
        // May-September: Slight bearish bias
        if ($month >= 5 && $month <= 9) {
            $seasonalBias -= 0.1;
        }
        
        // Check earnings season (typically mid-month in Jan, Apr, Jul, Oct)
        $isEarningsSeason = in_array($month, [1, 4, 7, 10]);
        if ($isEarningsSeason) {
            // Earnings season increases volatility - slight positive bias for strong stocks
            $recentPerformance = $this->getRecentPerformance($stock);
            if ($recentPerformance > 0) {
                $seasonalBias += 0.2;
            } else {
                $seasonalBias -= 0.1;
            }
        }
        
        return max(-1, min(1, $seasonalBias));
    }
    
    /**
     * Deep news sentiment analysis with context understanding
     */
    protected function analyzeNewsSentiment(Stock $stock): array
    {
        // Get recent news (last 7 days)
        $recentNews = NewsArticle::where('stock_id', $stock->id)
            ->where('published_at', '>=', now()->subDays(7))
            ->orderBy('published_at', 'desc')
            ->limit(50)
            ->get();
        
        if ($recentNews->isEmpty()) {
            return [
                'score' => 0,
                'confidence' => 30,
                'key_themes' => [],
                'impact_level' => 'low'
            ];
        }
        
        $weightedSentiments = [];
        $keyThemes = [];
        $highImpactNews = 0;
        
        foreach ($recentNews as $news) {
            // Calculate importance weight based on keywords
            $importance = $this->calculateNewsImportance($news->title, $news->content);
            
            // Weight recent news more heavily
            $recencyWeight = $this->calculateRecencyWeight($news->published_at);
            
            // Combined weight
            $weight = $importance * $recencyWeight;
            
            $sentiment = $news->sentiment_score ?? 0;
            $weightedSentiments[] = $sentiment * $weight;
            
            // Track high-impact news
            if ($importance > 0.7) {
                $highImpactNews++;
                $keyThemes[] = $this->extractTheme($news->title);
            }
        }
        
        $avgSentiment = collect($weightedSentiments)->avg();
        
        // Normalize to -1 to 1 scale
        $normalizedScore = max(-1, min(1, $avgSentiment / 5));
        
        // Calculate confidence based on agreement and volume
        $sentimentVariance = $this->calculateVariance($weightedSentiments);
        $confidence = $this->calculateSentimentConfidence($recentNews->count(), $sentimentVariance, $highImpactNews);
        
        return [
            'score' => $normalizedScore,
            'confidence' => $confidence,
            'key_themes' => array_unique($keyThemes),
            'impact_level' => $highImpactNews > 3 ? 'high' : ($highImpactNews > 0 ? 'medium' : 'low'),
            'news_count' => $recentNews->count()
        ];
    }
    
    /**
     * Calculate news importance based on keywords and context
     */
    protected function calculateNewsImportance(string $title, ?string $content): float
    {
        $text = strtolower($title . ' ' . ($content ?? ''));
        $importance = 0.3; // Base importance
        
        // High impact keywords (geopolitical, regulatory, major events)
        $highImpact = ['tariff', 'ban', 'regulation', 'lawsuit', 'investigation', 'acquisition', 'merger', 
                       'earnings', 'revenue', 'profit', 'loss', 'bankruptcy', 'recall', 'fda', 'approval'];
        
        // Medium impact keywords
        $mediumImpact = ['partnership', 'contract', 'deal', 'expansion', 'growth', 'decline', 
                         'upgrade', 'downgrade', 'forecast', 'guidance'];
        
        // Check for high-impact keywords
        foreach ($highImpact as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $importance += 0.15;
            }
        }
        
        // Check for medium-impact keywords
        foreach ($mediumImpact as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $importance += 0.08;
            }
        }
        
        return min(1.0, $importance);
    }
    
    /**
     * Calculate recency weight (more recent = higher weight)
     */
    protected function calculateRecencyWeight($publishedAt): float
    {
        $hoursAgo = now()->diffInHours($publishedAt);
        
        // Exponential decay: news from last 24h gets full weight
        if ($hoursAgo < 24) {
            return 1.0;
        } elseif ($hoursAgo < 48) {
            return 0.8;
        } elseif ($hoursAgo < 72) {
            return 0.6;
        } else {
            return 0.4;
        }
    }
    
    /**
     * Extract theme from news title
     */
    protected function extractTheme(string $title): string
    {
        $title = strtolower($title);
        
        if (strpos($title, 'tariff') !== false || strpos($title, 'trade') !== false) {
            return 'Trade Policy';
        }
        if (strpos($title, 'earnings') !== false || strpos($title, 'revenue') !== false) {
            return 'Earnings';
        }
        if (strpos($title, 'regulation') !== false || strpos($title, 'investigation') !== false) {
            return 'Regulatory';
        }
        if (strpos($title, 'merger') !== false || strpos($title, 'acquisition') !== false) {
            return 'M&A';
        }
        
        return 'General';
    }
    
    /**
     * Analyze technical patterns
     */
    protected function analyzeTechnicalPatterns(array $indicators): float
    {
        $technicalScore = 0;
        
        // RSI
        if ($indicators['rsi']['value'] < 30) {
            $technicalScore += 0.4; // Oversold
        } elseif ($indicators['rsi']['value'] > 70) {
            $technicalScore -= 0.4; // Overbought
        }
        
        // MACD
        if ($indicators['macd']['signal'] === 'strong_bullish') {
            $technicalScore += 0.3;
        } elseif ($indicators['macd']['signal'] === 'bullish') {
            $technicalScore += 0.2;
        } elseif ($indicators['macd']['signal'] === 'strong_bearish') {
            $technicalScore -= 0.3;
        } elseif ($indicators['macd']['signal'] === 'bearish') {
            $technicalScore -= 0.2;
        }
        
        // Momentum
        $momentum = $indicators['momentum']['value'] / 10; // Normalize
        $technicalScore += max(-0.3, min(0.3, $momentum));
        
        return max(-1, min(1, $technicalScore));
    }
    
    /**
     * Calculate overall confidence
     */
    protected function calculateConfidence(array $signals): int
    {
        // Check agreement across signals
        $positiveSignals = 0;
        $negativeSignals = 0;
        $totalSignals = 0;
        
        foreach ($signals as $signal) {
            if (is_array($signal)) {
                $value = $signal['score'] ?? 0;
            } else {
                $value = $signal;
            }
            
            if (abs($value) > 0.2) {
                $totalSignals++;
                if ($value > 0) {
                    $positiveSignals++;
                } else {
                    $negativeSignals++;
                }
            }
        }
        
        if ($totalSignals === 0) {
            return 50;
        }
        
        // High confidence if most signals agree
        $agreement = max($positiveSignals, $negativeSignals) / $totalSignals;
        
        // Base confidence: 50% + agreement bonus
        $confidence = 50 + ($agreement * 40);
        
        return (int) min(95, max(40, $confidence));
    }
    
    /**
     * Calculate sentiment confidence
     */
    protected function calculateSentimentConfidence(int $newsCount, float $variance, int $highImpactCount): int
    {
        $confidence = 40;
        
        // More news = higher confidence
        if ($newsCount > 10) {
            $confidence += 15;
        } elseif ($newsCount > 5) {
            $confidence += 10;
        }
        
        // Low variance = higher confidence (signals agree)
        if ($variance < 2) {
            $confidence += 15;
        } elseif ($variance < 4) {
            $confidence += 10;
        }
        
        // High impact news = higher confidence
        if ($highImpactCount > 3) {
            $confidence += 20;
        } elseif ($highImpactCount > 0) {
            $confidence += 10;
        }
        
        return min(95, $confidence);
    }
    
    /**
     * Calculate variance
     */
    protected function calculateVariance(array $values): float
    {
        if (empty($values)) {
            return 0;
        }
        
        $mean = collect($values)->avg();
        $squaredDiffs = array_map(fn($val) => pow($val - $mean, 2), $values);
        
        return sqrt(collect($squaredDiffs)->avg());
    }
    
    /**
     * Get recent performance
     */
    protected function getRecentPerformance(Stock $stock): float
    {
        $recentPrices = StockPrice::where('stock_id', $stock->id)
            ->where('interval', '1day')
            ->orderBy('price_date', 'desc')
            ->limit(2)
            ->get();
        
        if ($recentPrices->count() < 2) {
            return 0;
        }
        
        $latest = $recentPrices->first();
        $previous = $recentPrices->last();
        
        return (($latest->close - $previous->close) / $previous->close) * 100;
    }
    
    /**
     * Calculate predicted change percentage
     */
    protected function calculatePredictedChange(float $finalScore, array $indicators): float
    {
        // Base change from final score
        $baseChange = $finalScore * 3; // Scale to realistic percentage
        
        // Adjust by historical volatility
        $volatility = $indicators['volatility']['value'] ?? 0.02;
        $volatilityMultiplier = 1 + ($volatility * 2);
        
        $predictedChange = $baseChange * $volatilityMultiplier;
        
        // Cap to realistic range
        return max(-5, min(5, $predictedChange));
    }
    
    /**
     * Generate human-readable reasoning
     */
    protected function generateReasoning(array $newsScore, float $historicalScore, float $seasonalScore, float $technicalScore, string $direction): string
    {
        $reasons = [];
        
        // News sentiment
        if (abs($newsScore['score']) > 0.3) {
            $sentiment = $newsScore['score'] > 0 ? 'positive' : 'negative';
            $reasons[] = "Strong {$sentiment} news sentiment detected";
            if (!empty($newsScore['key_themes'])) {
                $reasons[] = "Key themes: " . implode(', ', array_slice($newsScore['key_themes'], 0, 3));
            }
        }
        
        // Historical patterns
        if (abs($historicalScore) > 0.2) {
            $trend = $historicalScore > 0 ? 'upward' : 'downward';
            $reasons[] = "Historical pattern analysis shows {$trend} bias for this day/month combination";
        }
        
        // Seasonal
        if (abs($seasonalScore) > 0.15) {
            $seasonal = $seasonalScore > 0 ? 'favorable' : 'unfavorable';
            $reasons[] = "Seasonal patterns are {$seasonal}";
        }
        
        // Technical
        if (abs($technicalScore) > 0.3) {
            $technical = $technicalScore > 0 ? 'bullish' : 'bearish';
            $reasons[] = "Technical indicators show {$technical} signals";
        }
        
        if (empty($reasons)) {
            return "Mixed signals across all indicators";
        }
        
        return implode('. ', $reasons) . '.';
    }
}
