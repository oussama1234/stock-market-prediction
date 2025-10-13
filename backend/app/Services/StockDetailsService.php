<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\Prediction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockDetailsService
{
    public function __construct(
        protected StockService $stockService,
        protected NewsService $newsService,
        protected EnhancedPredictionService $enhancedPredictionService,
        protected ScenarioGeneratorService $scenarioGeneratorService
    ) {}

    /**
     * Orchestrate stock details for the UI, including next_open, scenarios, news, and prediction.
     */
    public function getDetails(string $symbol, string $horizon = 'today'): ?array
    {
        $symbol = strtoupper($symbol);
        $stock = $this->stockService->getOrCreateStock($symbol);
        if (!$stock) return null;

        $quote = $this->stockService->getQuote($symbol) ?? [];
        $quote['next_open_estimate'] = $this->computeNextOpen($quote);
        
        // Store price data in database for future reference
        if ($quote && !empty($quote)) {
            try {
                $this->stockService->storePriceData($stock, $quote);
            } catch (\Throwable $e) {
                Log::warning("Failed to store price data for {$symbol}: " . $e->getMessage());
            }
        }

        // News for today, include matched priority keywords and sentiment
        $news = $this->newsService->getStockNews($symbol, 30);
        $todayNews = $this->filterTodayNews($news);
        $keywordsInfo = $this->detectPriorityKeywords($todayNews);

        // Base scenarios from indicators (two groups for UI)
        $scenarios = $this->buildScenarioGroups($stock, $horizon);

        // Generate a quick today-only model prediction (7–14 day features)
        $quickPred = $this->generateQuickPrediction($stock, $quote, $todayNews);

        // Persist today's prediction (idempotent for today)
        if ($horizon === 'today') {
            $this->storeTodayPrediction($stock, $quickPred, null);
        }

        return [
            'stock' => $stock->toArray(),
            'quote' => $quote,
            'news' => $news,
            'scenarios' => $scenarios,
            'prediction' => $quickPred,
        ];
    }

    public function regenerateToday(string $symbol, string $horizon = 'today'): array
    {
        $details = $this->getDetails($symbol, $horizon);
        return [
            'prediction' => $details['prediction'] ?? null,
            'scenarios' => $details['scenarios'] ?? [],
            'override' => $details['override'] ?? null,
        ];
    }

    /** Compute next open per rules. Prefer premarket; else previous_close (or microspread). */
    public function computeNextOpen(array $quote): ?float
    {
        $prev = isset($quote['previous_close']) ? (float)$quote['previous_close'] : null;
        $market = $quote['market_status'] ?? 'closed';
        $curr = isset($quote['current_price']) ? (float)$quote['current_price'] : null;
        $open = isset($quote['open']) ? (float)$quote['open'] : null;

        // If we are in pre-market, use the most recent trading price (current or pre-market open if provided)
        if ($market === 'pre_market') {
            $val = $curr ?? $open ?? $prev;
            return $val !== null ? round($val, 2) : null;
        }
        // Default to previous close (do not reuse stale cached open)
        if ($prev !== null) return round($prev, 2);
        return $curr !== null ? round($curr, 2) : ($open !== null ? round($open, 2) : null);
    }

    protected function filterTodayNews(array $articles): array
    {
        $today = now('UTC')->format('Y-m-d');
        return array_values(array_filter($articles, function ($a) use ($today) {
            $d = isset($a['published_at']) ? substr((string)$a['published_at'], 0, 10) : null;
            return $d === $today;
        }));
    }

    /**
     * Load priority keywords from centralized KeywordService
     * Returns: ['bearish' => ['keyword' => score, ...], 'bullish' => ['keyword' => score, ...]]
     */
    protected function getPriorityKeywords(): array
    {
        $keywordService = app(KeywordService::class);
        return $keywordService->getKeywords();
    }

    /** 
     * Detect priority keywords in today's news with scores and sentiment.
     * Returns both matched keywords and calculated sentiment score.
     */
    protected function detectPriorityKeywords(array $articles): array
    {
        $prio = $this->getPriorityKeywords();
        $matched = [];
        $totalScore = 0;
        
        foreach ($articles as $a) {
            $text = strtolower(trim(($a['title'] ?? '') . ' ' . ($a['description'] ?? '')));
            
            // Check bearish keywords
            foreach ($prio['bearish'] ?? [] as $kw => $score) {
                if ($kw !== '' && str_contains($text, strtolower($kw))) {
                    $matched[$kw] = $matched[$kw] ?? [
                        'count' => 0, 
                        'score' => $score,
                        'sentiment' => 'bearish',
                        'articles' => []
                    ];
                    $matched[$kw]['count']++;
                    $totalScore += $score;
                    $matched[$kw]['articles'][] = [
                        'title' => $a['title'] ?? '',
                        'url' => $a['url'] ?? null,
                        'source' => $a['source'] ?? null,
                    ];
                }
            }
            
            // Check bullish keywords
            foreach ($prio['bullish'] ?? [] as $kw => $score) {
                if ($kw !== '' && str_contains($text, strtolower($kw))) {
                    $matched[$kw] = $matched[$kw] ?? [
                        'count' => 0,
                        'score' => $score,
                        'sentiment' => 'bullish',
                        'articles' => []
                    ];
                    $matched[$kw]['count']++;
                    $totalScore += $score;
                    $matched[$kw]['articles'][] = [
                        'title' => $a['title'] ?? '',
                        'url' => $a['url'] ?? null,
                        'source' => $a['source'] ?? null,
                    ];
                }
            }
        }
        
        // Flatten for UI
        $flat = [];
        foreach ($matched as $kw => $data) {
            $flat[] = [
                'keyword' => $kw,
                'count' => $data['count'],
                'score' => $data['score'],
                'sentiment' => $data['sentiment'],
                'articles' => $data['articles'],
            ];
        }
        
        // Sort by absolute score (most impactful first)
        usort($flat, fn($a, $b) => abs($b['score']) <=> abs($a['score']));
        
        // Determine overall sentiment
        $overallSentiment = 'neutral';
        if ($totalScore < -2) {
            $overallSentiment = 'bearish';
        } elseif ($totalScore > 2) {
            $overallSentiment = 'bullish';
        }
        
        return [
            'matched' => $flat,
            'total_hits' => array_sum(array_map(fn($x) => $x['count'], $flat)),
            'total_score' => $totalScore,
            'overall_sentiment' => $overallSentiment,
            'bearish_count' => count(array_filter($flat, fn($x) => $x['sentiment'] === 'bearish')),
            'bullish_count' => count(array_filter($flat, fn($x) => $x['sentiment'] === 'bullish')),
        ];
    }

    /** Build two scenario groups for UI using indicators. */
    protected function buildScenarioGroups(Stock $stock, string $horizon): array
    {
        try {
            $generated = $this->scenarioGeneratorService->generateScenarios($stock, $horizon, force: true);
            // If service returns domain objects, map to lightweight cards
            $cards = $generated->map(function ($s) {
                return [
                    'name' => $s->name ?? ($s['name'] ?? 'Scenario'),
                    'predicted_range_percent' => $s->predicted_range_percent ?? ($s['predicted_range_percent'] ?? null),
                    'confidence' => $s->confidence ?? ($s['confidence'] ?? null),
                    'key_indicators' => $s->key_indicators ?? ($s['key_indicators'] ?? []),
                    'rationale' => $s->rationale ?? ($s['rationale'] ?? ''),
                    'cta' => [ 'bookmark' => true, 'vote' => true ],
                    'group' => $s->group ?? ($s['group'] ?? 'momentum'),
                ];
            })->all();
            // Partition by group (default to momentum)
            $momentum = array_values(array_filter($cards, fn($c) => ($c['group'] ?? 'momentum') === 'momentum'));
            $vv = array_values(array_filter($cards, fn($c) => ($c['group'] ?? '') === 'volume_volatility'));
            return [
                'momentum' => $momentum,
                'volume_volatility' => $vv,
            ];
        } catch (\Throwable $e) {
            Log::warning('Scenario generation failed: ' . $e->getMessage());
            // Fallback simple scenarios
            return [
                'momentum' => [
                    [
                        'name' => 'Momentum Baseline',
                        'predicted_range_percent' => [ -1.5, 1.5 ],
                        'confidence' => 0.55,
                        'key_indicators' => ['RSI', 'MACD'],
                        'rationale' => 'Baseline momentum signal with moderate confidence.',
                        'cta' => [ 'bookmark' => true, 'vote' => true ],
                    ]
                ],
                'volume_volatility' => [
                    [
                        'name' => 'Volume/ATR Watch',
                        'predicted_range_percent' => [ -2.2, 2.2 ],
                        'confidence' => 0.52,
                        'key_indicators' => ['ATR', 'Volume Ratio'],
                        'rationale' => 'ATR and volume ratio indicate moderate intraday swing.',
                        'cta' => [ 'bookmark' => true, 'vote' => true ],
                    ]
                ],
            ];
        }
    }

    /** Today-only quick model using last 7–14 days features. */
    protected function generateQuickPrediction(Stock $stock, array $quote, array $todayNews): array
    {
        // Gather last 14 closes and volumes
        $rows = \App\Models\StockPrice::where('stock_id', $stock->id)
            ->where('interval', '1day')
            ->orderBy('price_date', 'desc')
            ->limit(14)
            ->get();
        $closes = $rows->pluck('close')->reverse()->values()->toArray();
        $volumes = $rows->pluck('volume')->reverse()->values()->toArray();

        // Features
        $current = (float)($quote['current_price'] ?? $rows->last()->close ?? 0);
        $past7 = count($closes) >= 8 ? $closes[count($closes)-8] : ($closes[0] ?? $current);
        $ret7 = $past7 ? ($current - $past7) / $past7 : 0.0;
        $avgVol = !empty($volumes) ? array_sum($volumes) / max(1, count($volumes)) : 0.0;
        $lastVol = !empty($volumes) ? end($volumes) : 0.0;
        $volRatio = $avgVol ? ($lastVol / $avgVol) : 1.0;

        // Heuristic probabilities
        $score = (0.7 * $ret7) + (0.3 * (min(max($volRatio - 1, -1), 1)) * 0.02);
        $probUp = 1 / (1 + exp(-50 * $score)); // squashed
        $probUp = max(0.05, min(0.95, $probUp));

        $direction = $probUp > 0.55 ? 'up' : ($probUp < 0.45 ? 'down' : 'neutral');
        $label = $direction === 'up' ? 'Likely Up' : ($direction === 'down' ? 'Likely Down' : 'Neutral');
        $predChangePct = ($probUp - 0.5) * 4.0; // +/- up to ~2%
        $predPrice = $current * (1 + $predChangePct / 100);

        // Explainability top 3 features
        $features = [
            'volume_ratio' => round($volRatio, 2),
            'ret7' => round($ret7 * 100, 2),
            'news_count_today' => count($todayNews),
        ];

        return [
            'direction' => $direction,
            'label' => $label,
            'probability' => $direction === 'neutral' ? 0.5 : ($direction === 'up' ? $probUp : 1 - $probUp),
            'predicted_change_percent' => round($predChangePct, 2),
            'predicted_price' => round($predPrice, 2),
            'current_price' => round($current, 2),
            'rationale' => 'Quick model using recent returns and volume dynamics.',
            'indicators_snapshot' => $features,
            'trigger_keywords' => [],
            'horizon' => 'today',
            'model_version' => 'quick_1.0',
        ];
    }

    protected function storeTodayPrediction(Stock $stock, array $pred, ?array $override): void
    {
        try {
            // Deactivate prior today predictions
            Prediction::where('stock_id', $stock->id)
                ->whereDate('prediction_date', now()->toDateString())
                ->where('timeframe', 'today')
                ->update(['is_active' => false]);

            Prediction::create([
                'stock_id' => $stock->id,
                'direction' => $pred['direction'] ?? 'neutral',
                'confidence_score' => isset($pred['probability']) ? (int) round(($pred['probability']) * 100) : 50,
                'predicted_change_percent' => $pred['predicted_change_percent'] ?? 0,
                'current_price' => $pred['current_price'] ?? null,
                'predicted_price' => $pred['predicted_price'] ?? null,
                'reasoning' => $pred['rationale'] ?? null,
                'indicators' => $pred['indicators_snapshot'] ?? null,
                'news_count' => $pred['indicators_snapshot']['news_count_today'] ?? 0,
                'model_version' => $pred['model_version'] ?? 'quick_1.0',
                'prediction_date' => now(),
                'target_date' => now()->endOfDay(),
                'is_active' => true,
                // new fields (if migration present)
                'timeframe' => 'today',
                'label' => $pred['label'] ?? null,
                'probability' => $pred['probability'] ?? null,
                'scenario' => 'today_quick',
                'indicators_snapshot' => $pred['indicators_snapshot'] ?? null,
                'trigger_keywords' => $pred['trigger_keywords'] ?? [],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed storing today prediction: ' . $e->getMessage());
        }
    }
}
