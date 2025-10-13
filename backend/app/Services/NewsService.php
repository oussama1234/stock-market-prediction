<?php

namespace App\Services;

use App\Services\ApiClients\FinnhubClient;

use App\Models\NewsArticle;
use App\Models\Stock;
use App\Services\ApiClients\NewsApiClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

class NewsService
{
    protected NewsApiClient $newsApi;
    
    public function __construct(NewsApiClient $newsApi)
    {
        $this->newsApi = $newsApi;
    }
    
    /**
     * Get general market news
     */
    public function getMarketNews(int $limit = 20): array
    {
        $cacheKey = "market_news:{$limit}";
        
        // Check cache (1 minute for fresh news)
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        
        // Fetch from API
        $articles = $this->newsApi->getMarketNews($limit);
        
        if ($articles) {
            Cache::put($cacheKey, $articles, 60); // 1 minute cache
        }
        
        return $articles ?? [];
    }
    
    /**
     * Get news for specific stock symbol from multiple sources
     */
    public function getStockNews(string $symbol, int $limit = 10): array
    {
        $symbol = strtoupper($symbol);
        $cacheKey = "stock_news_multi:{$symbol}:{$limit}";
        
        // Check cache (1 minute for real-time updates)
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        
        // Try to get stock name for better search
        $stock = Stock::where('symbol', $symbol)->first();
        $companyName = $stock?->name ?? $symbol;
        
        // Aggregate from multiple sources
        // Priority: NewsAPI first (best images), then Finnhub, Alpha Vantage, Yahoo as last resort
        $allArticles = [];
        
        // Source 1 (HIGHEST PRIORITY): NewsAPI - Best quality images
        try {
            $newsApiArticles = $this->fetchNewsApiNews($symbol, $companyName);
            $allArticles = array_merge($allArticles, $newsApiArticles);
            Log::info("NewsAPI returned " . count($newsApiArticles) . " articles for {$symbol}");
        } catch (\Exception $e) {
            Log::warning("NewsAPI fetch failed: " . $e->getMessage());
        }
        
        // Source 2: Finnhub News - Good images
        try {
            $finnhubNews = $this->fetchFinnhubNews($symbol);
            $allArticles = array_merge($allArticles, $finnhubNews);
            Log::info("Finnhub returned " . count($finnhubNews) . " articles for {$symbol}");
        } catch (\Exception $e) {
            Log::warning("Finnhub news fetch failed: " . $e->getMessage());
        }
        
        // Source 3: Alpha Vantage News - Has images
        try {
            $alphaNews = $this->fetchAlphaVantageNews($symbol);
            $allArticles = array_merge($allArticles, $alphaNews);
            Log::info("Alpha Vantage returned " . count($alphaNews) . " articles for {$symbol}");
        } catch (\Exception $e) {
            Log::warning("Alpha Vantage news fetch failed: " . $e->getMessage());
        }
        
        // Source 4 (LAST RESORT): Yahoo Finance - Often problematic images
        // Only fetch if we still don't have enough articles from other sources
        if (count($allArticles) < $limit * 1.5) {
            try {
                $yahooNews = $this->fetchYahooFinanceNews($symbol);
                $allArticles = array_merge($allArticles, $yahooNews);
                Log::info("Yahoo Finance returned " . count($yahooNews) . " articles for {$symbol}");
            } catch (\Exception $e) {
                Log::warning("Yahoo Finance news fetch failed: " . $e->getMessage());
            }
        }
        
        // Deduplicate by URL and title similarity
        $uniqueArticles = $this->deduplicateNews($allArticles);
        
        // Sort by date (most recent first)
        usort($uniqueArticles, function($a, $b) {
            return strtotime($b['published_at'] ?? '1970-01-01 00:00:00') - strtotime($a['published_at'] ?? '1970-01-01 00:00:00');
        });
        
        // Limit results
        $articles = array_slice($uniqueArticles, 0, $limit);
        
            // Analyze sentiment for each article
            foreach ($articles as &$article) {
                if (!isset($article['sentiment_score'])) {
                    $article['sentiment_score'] = $this->analyzeSentiment($article['title'] ?? '', $article['description'] ?? '');
                }
                // Importance tiers
                $imp = $this->classifyImportance($article);
                $article['importance'] = $imp['importance']; // high|medium|low|none
                $article['importance_keywords'] = $imp['matched_keywords'];
                // Backward compatibility: only flag high as important
                $article['is_important'] = $article['importance'] === 'high';
                
                // NEW: Detect surge-worthy important news
                $surgeDetection = $this->detectImportantNewsWithSurge($article, $symbol);
                if ($surgeDetection['is_important']) {
                    $article['is_important'] = true;
                    $article['expected_surge_percent'] = $surgeDetection['expected_surge_percent'];
                    $article['importance_date'] = $surgeDetection['importance_date'];
                    $article['surge_keywords'] = $surgeDetection['surge_keywords'];
                }
            }
        
        if ($articles) {
            Cache::put($cacheKey, $articles, 60); // 1 minute cache
        }
        
        return $articles;
    }
    
    /**
     * Fetch news from Finnhub API
     */
    protected function fetchFinnhubNews(string $symbol): array
    {
        $apiKey = config('services.finnhub.key');
        if (!$apiKey) return [];
        
        try {
            $response = Http::timeout(5)
                ->get("https://finnhub.io/api/v1/company-news", [
                    'symbol' => $symbol,
                    'from' => now()->subDays(7)->format('Y-m-d'),
                    'to' => now()->format('Y-m-d'),
                    'token' => $apiKey,
                ]);
            
            if (!$response->successful()) return [];
            
            $news = $response->json();
            $articles = [];
            
            foreach ($news as $item) {
                $source = $item['source'] ?? 'Finnhub';
                
                // Skip Yahoo articles from Finnhub since they often have placeholder images
                // NewsAPI and direct Yahoo RSS will provide better versions
                if (stripos($source, 'yahoo') !== false) {
                    continue;
                }
                
                $articles[] = [
                    'title' => $item['headline'] ?? '',
                    'description' => $item['summary'] ?? '',
                    'url' => $item['url'] ?? '',
                    'image_url' => $item['image'] ?? null,
                    'source' => $source,
                    'published_at' => isset($item['datetime']) ? date('Y-m-d H:i:s', $item['datetime']) : now()->toDateTimeString(),
                    'sentiment_score' => null,
                ];
            }
            
            return $articles;
        } catch (\Exception $e) {
            Log::error("Finnhub API error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Fetch news from Alpha Vantage API
     */
    protected function fetchAlphaVantageNews(string $symbol): array
    {
        $apiKey = config('services.alpha_vantage.key');
        if (!$apiKey) return [];
        
        try {
            $response = Http::timeout(5)
                ->get("https://www.alphavantage.co/query", [
                    'function' => 'NEWS_SENTIMENT',
                    'tickers' => $symbol,
                    'limit' => 50,
                    'apikey' => $apiKey,
                ]);
            
            if (!$response->successful()) return [];
            
            $data = $response->json();
            $articles = [];
            
            if (isset($data['feed'])) {
                foreach ($data['feed'] as $item) {
                    // Calculate overall sentiment
                    $sentiment = 0;
                    if (isset($item['ticker_sentiment'])) {
                        foreach ($item['ticker_sentiment'] as $ts) {
                            if ($ts['ticker'] === $symbol) {
                                $sentiment = floatval($ts['ticker_sentiment_score'] ?? 0);
                                break;
                            }
                        }
                    }
                    
                    $articles[] = [
                        'title' => $item['title'] ?? '',
                        'description' => $item['summary'] ?? '',
                        'url' => $item['url'] ?? '',
                        'image_url' => $item['banner_image'] ?? null,
                        'source' => $item['source'] ?? 'Alpha Vantage',
                        'published_at' => (function() use ($item) {
                            if (!empty($item['time_published'])) {
                                // Alpha Vantage uses YmdTHis (UTC)
                                $dt = \DateTime::createFromFormat('Ymd\\THis', $item['time_published'], new \DateTimeZone('UTC'));
                                if ($dt) return $dt->format('Y-m-d H:i:s');
                                $ts = strtotime($item['time_published']);
                                if ($ts) return date('Y-m-d H:i:s', $ts);
                            }
                            return now()->toDateTimeString();
                        })(),
                        'sentiment_score' => $sentiment,
                    ];
                }
            }
            
            return $articles;
        } catch (\Exception $e) {
            Log::error("Alpha Vantage API error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Fetch news from NewsAPI (HIGHEST PRIORITY)
     */
    protected function fetchNewsApiNews(string $symbol, string $companyName): array
    {
        $apiKey = config('services.newsapi.key');
        if (!$apiKey) {
            Log::warning("NewsAPI key not configured");
            return [];
        }
        
        try {
            // Search for company name or symbol
            $query = strlen($companyName) > 3 ? $companyName : $symbol;
            
            $response = Http::timeout(5)
                ->get("https://newsapi.org/v2/everything", [
                    'q' => $query,
                    'language' => 'en',
                    'sortBy' => 'publishedAt',
                    'pageSize' => 100, // Increased to 100 (NewsAPI max)
                    'apiKey' => $apiKey,
                ]);
            
            if (!$response->successful()) {
                Log::warning("NewsAPI request failed: " . $response->status());
                return [];
            }
            
            $data = $response->json();
            $articles = [];
            
            if (isset($data['articles'])) {
                foreach ($data['articles'] as $item) {
                    // Skip articles without images
                    if (empty($item['urlToImage'])) continue;
                    
                    // Skip removed/deleted articles
                    if (isset($item['title']) && str_contains(strtolower($item['title']), '[removed]')) continue;
                    
                    $articles[] = [
                        'title' => $item['title'] ?? '',
                        'description' => $item['description'] ?? '',
                        'url' => $item['url'] ?? '',
                        'image_url' => $item['urlToImage'] ?? null,
                        'source' => $item['source']['name'] ?? 'NewsAPI',
                        'published_at' => isset($item['publishedAt']) ? 
                            date('Y-m-d H:i:s', strtotime($item['publishedAt'])) : 
                            now()->toDateTimeString(),
                        'sentiment_score' => null,
                    ];
                }
            }
            
            Log::info("NewsAPI processed " . count($articles) . " articles with images for {$symbol}");
            return $articles;
        } catch (\Exception $e) {
            Log::error("NewsAPI error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Fetch news from Yahoo Finance RSS/API for a specific symbol
     */
    protected function fetchYahooFinanceNews(string $symbol): array
    {
        try {
            // Yahoo Finance RSS feed
            $url = "https://finance.yahoo.com/rss/headline?s={$symbol}";
            
            $response = Http::timeout(5)->get($url);
            if (!$response->successful()) return [];
            
            $xml = @simplexml_load_string($response->body());
            if (!$xml) return [];
            
            // Register namespaces for media content
            $namespaces = $xml->getNamespaces(true);
            
            $articles = [];
            
            foreach ($xml->channel->item as $item) {
                $imageUrl = null;
                
                // Try to extract image from media:content namespace
                if (isset($namespaces['media'])) {
                    $media = $item->children($namespaces['media']);
                    if (isset($media->content)) {
                        $imageUrl = (string) $media->content->attributes()->url;
                    } elseif (isset($media->thumbnail)) {
                        $imageUrl = (string) $media->thumbnail->attributes()->url;
                    }
                }
                
                // Try enclosure tag (common in RSS feeds)
                if (!$imageUrl && isset($item->enclosure)) {
                    $enclosure = $item->enclosure->attributes();
                    if (isset($enclosure->type) && str_contains((string) $enclosure->type, 'image')) {
                        $imageUrl = (string) $enclosure->url;
                    }
                }
                
                // Try to extract image from description HTML
                if (!$imageUrl) {
                    $description = (string) $item->description;
                    preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $description, $matches);
                    if (isset($matches[1])) {
                        $imageUrl = $matches[1];
                    }
                }
                
                $articles[] = [
                    'title' => (string) $item->title,
                    'description' => strip_tags((string) $item->description),
                    'url' => (string) $item->link,
                    'image_url' => $imageUrl,
                    'source' => 'Yahoo Finance',
                    'published_at' => isset($item->pubDate) ? 
                        date('Y-m-d H:i:s', strtotime((string) $item->pubDate)) : 
                        now()->toDateTimeString(),
                    'sentiment_score' => null,
                ];
            }
            
            return $articles;
        } catch (\Exception $e) {
            Log::error("Yahoo Finance RSS error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch general market news from Yahoo Finance RSS
     */
    protected function fetchYahooMarketNews(): array
    {
        try {
            $url = "https://finance.yahoo.com/news/rss"; // General Yahoo Finance news RSS
            $response = Http::timeout(5)->get($url);
            if (!$response->successful()) return [];

            $xml = @simplexml_load_string($response->body());
            if (!$xml) return [];

            $namespaces = $xml->getNamespaces(true);
            $articles = [];

            foreach ($xml->channel->item as $item) {
                $imageUrl = null;
                if (isset($namespaces['media'])) {
                    $media = $item->children($namespaces['media']);
                    if (isset($media->content)) {
                        $imageUrl = (string) $media->content->attributes()->url;
                    } elseif (isset($media->thumbnail)) {
                        $imageUrl = (string) $media->thumbnail->attributes()->url;
                    }
                }
                if (!$imageUrl && isset($item->enclosure)) {
                    $enclosure = $item->enclosure->attributes();
                    if (isset($enclosure->type) && str_contains((string) $enclosure->type, 'image')) {
                        $imageUrl = (string) $enclosure->url;
                    }
                }
                if (!$imageUrl) {
                    $description = (string) $item->description;
                    preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $description, $matches);
                    if (isset($matches[1])) {
                        $imageUrl = $matches[1];
                    }
                }

                $articles[] = [
                    'title' => (string) $item->title,
                    'description' => strip_tags((string) $item->description),
                    'url' => (string) $item->link,
                    'image_url' => $imageUrl,
                    'source' => 'Yahoo Finance',
                    'published_at' => isset($item->pubDate) ? date('Y-m-d H:i:s', strtotime((string) $item->pubDate)) : now()->toDateTimeString(),
                    'sentiment_score' => null,
                ];
            }

            return $articles;
        } catch (\Exception $e) {
            Log::error("Yahoo Finance market RSS error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Deduplicate news articles
     */
    protected function deduplicateNews(array $articles): array
    {
        $unique = [];
        $seen = [];
        
        foreach ($articles as $article) {
            $url = $article['url'] ?? '';
            $title = strtolower($article['title'] ?? '');
            
            // Skip if URL already seen
            if (in_array($url, $seen)) continue;
            
            // Skip if very similar title exists
            $isDuplicate = false;
            foreach ($unique as $existing) {
                $existingTitle = strtolower($existing['title'] ?? '');
                similar_text($title, $existingTitle, $percent);
                if ($percent > 92) {
                    $isDuplicate = true;
                    break;
                }
            }
            
            if (!$isDuplicate && $url) {
                $unique[] = $article;
                $seen[] = $url;
            }
        }
        
        return $unique;
    }
    
    /**
     * Detect important news with surge expectations for mega-cap tech stocks
     * Returns array with is_important, expected_surge_percent, importance_date, surge_keywords
     */
    public function detectImportantNewsWithSurge(array $article, ?string $stockSymbol = null): array
    {
        $title = strtolower($article['title'] ?? '');
        $description = strtolower($article['description'] ?? '');
        $text = trim($title . ' ' . $description);
        
        // Mega-cap tech stocks that respond strongly to AI/chip news
        $megaCapTech = ['nvda', 'nvidia', 'msft', 'microsoft', 'aapl', 'apple', 'googl', 'google', 'amzn', 'amazon', 'meta', 'tsla', 'tesla', 'avgo', 'broadcom'];
        
        // High-impact surge keywords (6%+ expected surge)
        $surgeKeywords = [
            // AI Deal / Partnership patterns (MEGA SURGE)
            'openai chip deal' => 10.0,
            'openai deal' => 9.0,
            'openai partnership' => 9.0,
            'ai chip deal' => 9.0,
            'major ai deal' => 8.5,
            'ai partnership' => 8.0,
            'chip supply deal' => 8.0,
            'exclusive ai deal' => 9.5,
            'signs ai deal' => 8.5,
            
            // Stock Surge patterns (STRONG)
            'stock surges on' => 7.5,
            'surges on' => 7.0,
            'stock soars on' => 7.5,
            'soars on' => 7.0,
            'stock jumps on' => 7.0,
            'jumps on' => 6.5,
            'rallies on' => 6.5,
            'stock rallies' => 6.5,
            
            // Earnings / Guidance beats (STRONG)
            'crushes earnings' => 7.5,
            'beats earnings expectations' => 7.0,
            'smashes expectations' => 7.5,
            'blows past estimates' => 7.0,
            'raises guidance significantly' => 7.5,
            'massive earnings beat' => 8.0,
            
            // Major announcements (MEDIUM-HIGH)
            'announces breakthrough' => 7.0,
            'breakthrough in ai' => 7.5,
            'revolutionary' => 6.5,
            'game-changing' => 7.0,
            'major breakthrough' => 7.5,
            'unveils new ai' => 6.5,
            'launches revolutionary' => 7.0,
        ];
        
        // Check for mega-cap tech stock mention
        $isMegaCap = false;
        $mentionedStock = null;
        foreach ($megaCapTech as $stock) {
            if (str_contains($text, $stock)) {
                $isMegaCap = true;
                $mentionedStock = strtoupper($stock);
                break;
            }
        }
        
        // If stock symbol provided, check if it's mega-cap
        if ($stockSymbol) {
            $stockLower = strtolower($stockSymbol);
            if (in_array($stockLower, $megaCapTech)) {
                $isMegaCap = true;
                $mentionedStock = $stockSymbol;
            }
        }
        
        $matchedKeywords = [];
        $maxSurge = 0.0;
        
        // Detect surge keywords
        foreach ($surgeKeywords as $keyword => $expectedSurge) {
            if (str_contains($text, $keyword)) {
                $matchedKeywords[] = $keyword;
                $maxSurge = max($maxSurge, $expectedSurge);
            }
        }
        
        // Determine if this is important news
        $isImportant = false;
        $expectedSurgePercent = 0.0;
        
        // RULE 1: Mega-cap stock + surge keywords = IMPORTANT
        if ($isMegaCap && !empty($matchedKeywords) && $maxSurge >= 6.0) {
            $isImportant = true;
            $expectedSurgePercent = $maxSurge;
        }
        
        // RULE 2: Even without mega-cap mention, very strong keywords = IMPORTANT
        if (!$isImportant && $maxSurge >= 8.0) {
            $isImportant = true;
            $expectedSurgePercent = $maxSurge;
        }
        
        // Determine importance_date (today or tomorrow based on market timing)
        $importanceDate = null;
        if ($isImportant) {
            $now = now();
            $publishedAt = isset($article['published_at']) ? \Carbon\Carbon::parse($article['published_at']) : $now;
            
            // US Market hours: 9:30 AM - 4:00 PM ET
            // If news comes after market close (4 PM ET), impact is for tomorrow
            // If news comes before/during market, impact is today
            $marketCloseET = 16; // 4 PM ET
            
            // Convert to ET for comparison
            $publishedET = $publishedAt->setTimezone('America/New_York');
            $currentET = $now->setTimezone('America/New_York');
            
            // If published after market close, impact is next trading day
            if ($publishedET->hour >= $marketCloseET) {
                // Impact tomorrow (or Monday if Friday evening)
                $importanceDate = $currentET->addDay()->toDateString();
            } else {
                // Impact today
                $importanceDate = $currentET->toDateString();
            }
        }
        
        return [
            'is_important' => $isImportant,
            'expected_surge_percent' => $expectedSurgePercent,
            'importance_date' => $importanceDate,
            'surge_keywords' => $matchedKeywords,
            'is_mega_cap' => $isMegaCap,
            'mentioned_stock' => $mentionedStock,
        ];
    }
    
    /**
     * Classify importance based on keywords and sentiment
     * Returns: high | medium | low | none
     */
    protected function classifyImportance(array $article): array
    {
        $title = strtolower($article['title'] ?? '');
        $description = strtolower($article['description'] ?? '');
        $text = trim($title . ' ' . $description);
        $sent = isset($article['sentiment_score']) ? (float) $article['sentiment_score'] : 0.0;

        $highKeywords = [
            'tariff','tariffs','tarif','ban','banned','sanction','sanctions','embargo','blacklist','antitrust',
            'regulator','regulation','lawsuit','bankruptcy','recall','cease','cease-and-desist'
        ];
        $mediumKeywords = [
            'earnings','guidance','forecast','dividend','split','downgrade','upgrade','rating',
            'acquisition','merger','ipo','approval','fda','ceo','investigation','settlement','strike','layoff','probe'
        ];

        $matchedHigh = [];
        foreach ($highKeywords as $kw) {
            if ($kw && str_contains($text, $kw)) $matchedHigh[] = $kw;
        }
        if (!empty($matchedHigh)) {
            return ['importance' => 'high', 'matched_keywords' => $matchedHigh];
        }

        $matchedMedium = [];
        foreach ($mediumKeywords as $kw) {
            if ($kw && str_contains($text, $kw)) $matchedMedium[] = $kw;
        }
        if (!empty($matchedMedium)) {
            return ['importance' => 'medium', 'matched_keywords' => $matchedMedium];
        }

        // Sentiment-based fallback
        if (abs($sent) >= 0.85) {
            return ['importance' => 'high', 'matched_keywords' => []];
        }
        if (abs($sent) >= 0.4) {
            return ['importance' => 'medium', 'matched_keywords' => []];
        }

        return ['importance' => 'low', 'matched_keywords' => []];
    }

    /**
     * Simple sentiment analysis
     */
    protected function analyzeSentiment(string $title, string $description): float
    {
        $text = strtolower($title . ' ' . $description);
        
        $positiveWords = [
            // Mega positive
            'trump dismisses tariff', 'trump dismisses', 'tariff dismisses', 'tariff dismissed',
            'stock futures rebound', 'futures rebound', 'stock market rebound', 'market rebound',
            'stock rebound', 'stock rises', 'stock rise',
            // AI & Tech
            'strong ai demand', 'ai breakthrough', 'ai leader', 'mega cap rally', 'tech giants surge',
            'ai-driven growth', 'ai revenue', 'ai adoption', 'ai chips', 'data center', 'mega cap', 'tech giant',
            'strong ai', 'ai-driven', 'AI-driven', 'artificial intelligence',
            // Standard positive
            'surge', 'soar', 'jump', 'gain', 'rise', 'up', 'bull', 'profit', 'beat', 'growth', 'strong', 'positive', 'rally', 'high', 'record',
            'raised', 'stock raised', 'target raised', 'price target raised', 'beat earnings', 'record earnings'
        ];
        $negativeWords = ['fall', 'drop', 'plunge', 'decline', 'loss', 'down', 'bear', 'miss', 'weak', 'negative', 'crash', 'low', 'concern', 'warning', 'risk'];
        
        $score = 0;
        foreach ($positiveWords as $word) {
            $score += substr_count($text, $word) * 0.1;
        }
        foreach ($negativeWords as $word) {
            $score -= substr_count($text, $word) * 0.1;
        }
        
        // Normalize between -1 and 1
        return max(-1, min(1, $score));
    }
    
    /**
     * Determine if news is important
     */
    protected function isImportantNews(array $article): bool
    {
        $title = strtolower($article['title'] ?? '');
        $description = strtolower($article['description'] ?? '');
        $text = trim($title . ' ' . $description);

        // Expanded set of high-impact keywords
        $importantKeywords = [
            'earnings','acquisition','merger','ceo','lawsuit','fda','approval','recall','bankruptcy','ipo','split','dividend','guidance','forecast',
            'tariff','tariffs','tarif','ban','banned','sanction','sanctions','embargo','restriction','blacklist','antitrust','regulator','regulation',
            'downgrade','upgrade','rating','fine','penalty','investigation','sec','settlement','strike','layoff','recall','probe'
        ];
        foreach ($importantKeywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        // Lowered threshold so fresh (today) items surface as important more often
        if (isset($article['sentiment_score']) && abs((float)$article['sentiment_score']) > 0.3) {
            return true;
        }

        return false;
    }
    
    /**
     * Store news article in database
     */
    public function storeArticle(array $articleData, ?Stock $stock = null): ?NewsArticle
    {
        try {
            // Check if article already exists by URL
            $existing = NewsArticle::where('url', $articleData['url'])->first();
            
            if ($existing) {
                // If stock provided and article doesn't have this stock_id, update it
                if ($stock && $existing->stock_id !== $stock->id) {
                    $existing->update(['stock_id' => $stock->id]);
                }
                return $existing;
            }
            
            // Create new article
            $article = NewsArticle::create([
                'stock_id' => $stock?->id,
                'title' => $articleData['title'],
                'description' => $articleData['description'] ?? null,
                'content' => $articleData['content'] ?? null,
                'url' => $articleData['url'],
                'image_url' => $articleData['image_url'] ?? null,
                'source' => $articleData['source'] ?? 'newsapi',
                'author' => $articleData['author'] ?? null,
                'published_at' => $articleData['published_at'] ?? now(),
                'sentiment_score' => $articleData['sentiment_score'] ?? $this->analyzeSentiment($articleData['title'] ?? '', $articleData['description'] ?? ''),
            ]);
            
            Log::info("Stored news article: {$article->title} for {$stock->symbol}");
            return $article;
            
        } catch (\Exception $e) {
            Log::error("Failed to store news article: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Bulk store articles for a stock
     */
    public function bulkStoreForStock(Stock $stock, array $articles): int
    {
        $stored = 0;
        
        foreach ($articles as $articleData) {
            if ($this->storeArticle($articleData, $stock)) {
                $stored++;
            }
        }
        
        Log::info("Stored {$stored} articles for {$stock->symbol}");
        return $stored;
    }
    
    /**
     * Get recent news from database for a stock
     */
    public function getRecentNewsForStock(Stock $stock, int $limit = 10): Collection
    {
        return $stock->newsArticles()
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get recent market news from database
     */
    public function getRecentMarketNews(int $limit = 20): Collection
    {
        return NewsArticle::whereNull('sentiment_score')
            ->orWhereNotNull('sentiment_score')
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Search news articles in database
     */
    public function searchInDatabase(string $query, int $limit = 20): Collection
    {
        return NewsArticle::where('title', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get news articles that need sentiment analysis
     */
    public function getArticlesNeedingSentiment(int $limit = 50): Collection
    {
        return NewsArticle::whereNull('sentiment_score')
            ->where('published_at', '>=', now()->subWeek())
            ->limit($limit)
            ->get();
    }

    /**
     * Aggregated market news with filters and pagination
     */
    public function getAggregatedMarketNews(?string $query, ?string $from, ?string $to, int $limit = 20, int $offset = 0, bool $importantFirst = true): array
    {
        $query = $query ? trim($query) : null;
        $fromTs = $from ? strtotime($from) : null;
        $toTs = $to ? strtotime($to) : null;
        // Treat small windows (<= 36h) as "fresh" for broader NewsAPI fetching even if they cross day boundaries
        $isFreshWindow = $fromTs && $toTs && ($toTs - $fromTs) <= (36 * 3600);

        $cacheKey = 'market_news:aggregated:' . md5(json_encode([
            'q' => $query,
            'from' => $fromTs,
            'to' => $toTs,
            'important' => $importantFirst,
        ]));

        $allArticles = Cache::remember($cacheKey, 60, function () use ($query, $fromTs, $toTs, $importantFirst, $isFreshWindow, $limit) {
            $articles = [];

            // 1) NewsAPI - top headlines (business)
            try {
                $newsApiArticles = $this->newsApi->getMarketNews(100);
                $articles = array_merge($articles, $newsApiArticles);
            } catch (\Exception $e) {
                Log::warning('NewsAPI market headlines failed: ' . $e->getMessage());
            }

            // 1a) If TODAY, pull NewsAPI Everything constrained by time window to boost fresh diversity
            if ($isFreshWindow) {
                try {
                    $generalQuery = $query ?: 'stocks OR markets OR economy';
                    $fromIso = $fromTs ? gmdate('Y-m-d\TH:i:s\Z', $fromTs) : null;
                    $toIso = $toTs ? gmdate('Y-m-d\TH:i:s\Z', $toTs) : null;
                    $freshArticles1 = $this->newsApi->searchEverything($generalQuery, $fromIso, $toIso, 100, 1);
                    $freshArticles2 = $this->newsApi->searchEverything($generalQuery, $fromIso, $toIso, 100, 2);
                    $articles = array_merge($articles, $freshArticles1, $freshArticles2);
                } catch (\Exception $e) {
                    Log::warning('NewsAPI everything (today) failed: ' . $e->getMessage());
                }

                // 1b) Curated finance sources for broader coverage
                try {
                    $curatedSources = [
                        'cnbc','business-insider','financial-times','fortune','the-economist','reuters'
                    ];
                    $sourceArticles1 = $this->newsApi->getFromSources($curatedSources, 100, 1);
                    $sourceArticles2 = $this->newsApi->getFromSources($curatedSources, 100, 2);
                    $articles = array_merge($articles, $sourceArticles1, $sourceArticles2);
                } catch (\Exception $e) {
                    Log::warning('NewsAPI sources fetch failed: ' . $e->getMessage());
                }
            } else if ($query) {
                // 1c) Non-today: query-driven boost
                try {
                    $searchResults = $this->newsApi->searchNews($query, 100);
                    $articles = array_merge($articles, $searchResults);
                } catch (\Exception $e) {
                    Log::warning('NewsAPI search failed: ' . $e->getMessage());
                }
            }

            // 2) Finnhub - general market news
            try {
                $finnhub = new FinnhubClient();
                $finnhubNews = $finnhub->getMarketNews('general');
                $articles = array_merge($articles, $finnhubNews);
            } catch (\Exception $e) {
                Log::warning('Finnhub market news failed: ' . $e->getMessage());
            }

            // 3) Alpha Vantage - market topics news
            try {
                $alphaNews = $this->fetchAlphaVantageMarketNews();
                $articles = array_merge($articles, $alphaNews);
            } catch (\Exception $e) {
                Log::warning('Alpha Vantage market news failed: ' . $e->getMessage());
            }

            // 4) Always include Yahoo Finance RSS after other sources (dedup will remove overlaps)
            try {
                $yahooNews = $this->fetchYahooMarketNews();
                $articles = array_merge($articles, $yahooNews);
            } catch (\Exception $e) {
                Log::warning('Yahoo Finance market RSS failed: ' . $e->getMessage());
            }

            // Normalize published_at across sources to 'Y-m-d H:i:s' (UTC)
            $normalized = [];
            foreach ($articles as $a) {
                $published = $a['published_at'] ?? null;
                $ts = null;
                if ($published) {
                    $ts = strtotime($published);
                    if ($ts === false) {
                        // Attempt to parse Alpha Vantage format Ymd\THis
                        if (preg_match('/^\d{8}T\d{6}$/', $published)) {
                            $dt = \DateTime::createFromFormat('Ymd\\THis', $published, new \DateTimeZone('UTC'));
                            if ($dt) $ts = $dt->getTimestamp();
                        }
                    }
                }
                if (!$ts) {
                    // Default to now to avoid dropping the item; date filter will handle it
                    $ts = time();
                }
                $a['published_at'] = gmdate('Y-m-d H:i:s', $ts);
                $normalized[] = $a;
            }

            // Deduplicate
            $unique = $this->deduplicateNews($normalized);

            // Filter by query
            if ($query) {
                $q = strtolower($query);
                $unique = array_values(array_filter($unique, function ($a) use ($q) {
                    $hay = strtolower(($a['title'] ?? '') . ' ' . ($a['description'] ?? ''));
                    return strpos($hay, $q) !== false;
                }));
            }

            // Filter by date range
            if ($fromTs || $toTs) {
                $unique = array_values(array_filter($unique, function ($a) use ($fromTs, $toTs) {
                    $ts = isset($a['published_at']) ? strtotime($a['published_at']) : null;
                    if (!$ts) return false;
                    if ($fromTs && $ts < $fromTs) return false;
                    if ($toTs && $ts > $toTs) return false;
                    return true;
                }));
            }

            // Enrich: sentiment and importance
            foreach ($unique as &$a) {
                if (!isset($a['sentiment_score']) || $a['sentiment_score'] === null) {
                    $a['sentiment_score'] = $this->analyzeSentiment($a['title'] ?? '', $a['description'] ?? '');
                }
                $imp = $this->classifyImportance($a);
                $a['importance'] = $imp['importance'];
                $a['importance_keywords'] = $imp['matched_keywords'];
                $a['is_important'] = $a['importance'] === 'high';
            }
            unset($a);

            // Sort
            usort($unique, function ($a, $b) use ($importantFirst) {
                if ($importantFirst) {
                    $impA = !empty($a['is_important']);
                    $impB = !empty($b['is_important']);
                    if ($impA !== $impB) {
                        return $impA ? -1 : 1; // important first
                    }
                }
                $ta = strtotime($a['published_at'] ?? '');
                $tb = strtotime($b['published_at'] ?? '');
                return $tb <=> $ta; // recent first
            });

            return $unique;
        });

        $total = count($allArticles);
        $items = array_slice($allArticles, $offset, $limit);

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * Fetch general market news from Alpha Vantage NEWS_SENTIMENT (topics)
     */
    protected function fetchAlphaVantageMarketNews(): array
    {
        $apiKey = config('services.alpha_vantage.key');
        if (!$apiKey) return [];

        try {
            $response = Http::timeout(5)
                ->get('https://www.alphavantage.co/query', [
                    'function' => 'NEWS_SENTIMENT',
                    'topics' => 'financial_markets,technology',
                    'sort' => 'LATEST',
                    'limit' => 50,
                    'apikey' => $apiKey,
                ]);
            if (!$response->successful()) return [];

            $data = $response->json();
            $articles = [];
            if (isset($data['feed']) && is_array($data['feed'])) {
                foreach ($data['feed'] as $item) {
                    $overall = isset($item['overall_sentiment_score']) ? (float) $item['overall_sentiment_score'] : null;
                    $articles[] = [
                        'title' => $item['title'] ?? '',
                        'description' => $item['summary'] ?? '',
                        'url' => $item['url'] ?? '',
                        'image_url' => $item['banner_image'] ?? null,
                        'source' => $item['source'] ?? 'Alpha Vantage',
                        'published_at' => (function() use ($item) {
                            if (!empty($item['time_published'])) {
                                $dt = \DateTime::createFromFormat('Ymd\\THis', $item['time_published'], new \DateTimeZone('UTC'));
                                if ($dt) return $dt->format('Y-m-d H:i:s');
                                $ts = strtotime($item['time_published']);
                                if ($ts) return date('Y-m-d H:i:s', $ts);
                            }
                            return now()->toDateTimeString();
                        })(),
                        'sentiment_score' => $overall,
                    ];
                }
            }
            return $articles;
        } catch (\Exception $e) {
            Log::error('Alpha Vantage market API error: ' . $e->getMessage());
            return [];
        }
    }
}
