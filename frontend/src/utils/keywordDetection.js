/**
 * Keyword Detection System for News Sentiment Analysis
 * Detects bullish, bearish, and high-impact keywords in news articles
 * Now supports dynamic loading from API with static fallback
 */

import { keywordsAPI } from '../services/api';

// Cache for API keywords
let cachedKeywords = null;
let keywordsFetchPromise = null;

/**
 * Fetch keywords from API with fallback to static
 */
export async function fetchKeywords() {
  if (cachedKeywords) {
    return cachedKeywords;
  }
  
  if (keywordsFetchPromise) {
    return keywordsFetchPromise;
  }
  
  keywordsFetchPromise = keywordsAPI.getAll()
    .then(response => {
      const { data } = response;
      
      // Convert database format to our format
      const bullish = {};
      const bearish = {};
      
      Object.entries(data.bullish || {}).forEach(([keyword, score]) => {
        bullish[keyword] = score;
      });
      
      Object.entries(data.bearish || {}).forEach(([keyword, score]) => {
        bearish[keyword] = score;
      });
      
      cachedKeywords = { bullish, bearish };
      return cachedKeywords;
    })
    .catch(error => {
      console.warn('Failed to fetch keywords from API, using fallback:', error);
      // Use static fallback
      cachedKeywords = {
        bullish: BULLISH_KEYWORDS_FALLBACK,
        bearish: BEARISH_KEYWORDS_FALLBACK
      };
      return cachedKeywords;
    })
    .finally(() => {
      keywordsFetchPromise = null;
    });
  
  return keywordsFetchPromise;
}

/**
 * Get keywords synchronously (uses cache or fallback)
 */
export function getKeywordsSync() {
  if (cachedKeywords) {
    return cachedKeywords;
  }
  
  // If not cached, trigger fetch for next time and return fallback
  fetchKeywords();
  
  return {
    bullish: BULLISH_KEYWORDS_FALLBACK,
    bearish: BEARISH_KEYWORDS_FALLBACK
  };
}

/**
 * Clear keyword cache (call after updating keywords in admin)
 */
export function clearKeywordCache() {
  cachedKeywords = null;
}

// FALLBACK: Static bullish keywords
export const BULLISH_KEYWORDS_FALLBACK = {
  // Score +4: MEGA POSITIVE
  'trump dismisses tariff': 4,
  'trump dismisses': 4,
  'tariff dismisses': 4,
  'tariff dismissed': 4,
  'stock futures rebound': 4,
  'futures rebound': 4,
  'stock market rebound': 4,
  'market rebound': 4,
  'stock rebound': 4,
  'stock rises': 4,
  'stock rise': 4,
  
  // AI & Tech Giants
  'strong ai demand': 3,
  'ai breakthrough': 3,
  'ai leader': 3,
  'mega cap rally': 3,
  'tech giants surge': 3,
  'ai-driven growth': 3,
  'ai revenue': 3,
  
  // High impact bullish (score: 3) - Major positive catalysts
  'record earnings': 3,
  'beat earnings': 3,
  'exceeded earnings': 3,
  'smash earnings': 3,
  'blowout earnings': 3,
  'strong earnings': 3,
  
  // Tariff relief
  'tariff cut': 3,
  'tariff relief': 3,
  'tariff reduction': 3,
  'trade war ends': 3,
  'trade deal': 3,
  
  // AI & other positives
  'strong ai': 3,
  'ai-driven': 3,
  'raised': 3,
  'stock raised': 3,
  'target raised': 3,
  'price target raised': 3,
  'fda approval': 3,
  'major contract': 3,
  'stock buyback': 3,
  'breakthrough': 3,
  
  // Score +2: Strong positive
  'upgrade': 2,
  'outperform': 2,
  'partnership': 2,
  'expansion': 2,
  'rally': 2,
  'surge': 2,
  'soar': 2,
  'jump': 2,
  'ai adoption': 2,
  'nvidia': 2,
  'ai chips': 2,
  'data center': 2,
  'mega cap': 2,
  'tech giant': 2,
  
  // Score +1: Slight positive
  'q1 earnings': 1,
  'q2 earnings': 1,
  'q3 earnings': 1,
  'q4 earnings': 1,
  'earnings report': 1,
  'positive': 1,
  'growth': 1,
  'recovery': 1,
  'rebound': 1,
  'artificial intelligence': 1,
  'ai': 1,
  'buy': 1,
  'profit': 1,
  'gain': 1,
  'rise': 1,
  'increase': 1,
};

// FALLBACK: Static bearish keywords
export const BEARISH_KEYWORDS_FALLBACK = {
  // Score -3: Critical negative
  'tariff': -3,
  'tariffs': -3,
  'new tariff': -3,
  'trade war': -3,
  'ban': -3,
  'banned': -3,
  'shutdown': -3,
  'shut down': -3,
  'bankruptcy': -3,
  'crash': -3,
  'fraud': -3,
  'scandal': -3,
  'earnings miss': -3,
  'revenue miss': -3,
  'plunge': -3,
  'collapse': -3,
  
  // Score -2: Medium negative
  'lawsuit': -2,
  'layoff': -2,
  'layoffs': -2,
  'restructuring': -2,
  'investigation': -2,
  'disappointing earnings': -2,
  'miss earnings': -2,
  'slowdown': -2,
  'weakness': -2,
  'downgrade': -2,
  'underperform': -2,
  
  // Score -1: Slight negative
  'concern': -1,
  'risk': -1,
  'warning': -1,
  'decline': -1,
  'fall': -1,
  'drop': -1,
  'loss': -1,
  'weak': -1,
  'negative': -1,
};

// Backward compatibility exports - for code that still uses the old names
export const BULLISH_KEYWORDS = BULLISH_KEYWORDS_FALLBACK;
export const BEARISH_KEYWORDS = BEARISH_KEYWORDS_FALLBACK;

// Special context modifiers
export const CONTEXT_MODIFIERS = {
  'trump': 0.5, // Multiplier for political news
  'china': 0.5,
  'fed': 0.5,
  'ceo': 0.3,
  'analyst': 0.2,
};

// OLD STATIC DEFINITIONS (replaced by dynamic API loading - kept for reference)
/*
export const OLD_BULLISH_KEYWORDS = {
  'beat earnings': 3,
  'exceeded earnings': 3,
  'smash earnings': 3,
  'blowout earnings': 3,
  'strong earnings': 3,
  'Q1 earnings report': 3,
  'Q2 earnings report': 3,
  'Q3 earnings report': 3,
  'Q4 earnings report': 3,
  'quarterly earnings': 3,
  'annual earnings': 3,
  'earnings guidance': 3,
  'guidance raised': 3,
  
  // Trade and tariff relief
  'tariff cut': 3,
  'tariff relief': 3,
  'tariff reduction': 3,
  'cut tariff': 3,
  'reduce tariff': 3,
  'tariffs eased': 3,
  'tariffs lifted': 3,
  'trade war ends': 3,
  'trade war resolved': 3,
  'trade deal': 3,
  'trade agreement': 3,
  'export approval': 3,
  'export license': 3,
  'tariffs removed': 3,
  
  // Business deals and partnerships
  'major contract': 3,
  'partnership': 3,
  'strategic partnership': 3,
  'acquisition': 3,
  'merger': 3,
  'deal signed': 3,
  'deal closed': 3,
  'contract win': 3,
  'partnership announced': 3,
  
  // Innovation and breakthroughs
  'breakthrough': 3,
  'breakthrough technology': 3,
  'patent approval': 3,
  'fda approval': 3,
  'FDA approval': 3,
  'regulatory approval': 3,
  'drug approval': 3,
  'product launch': 3,
  'new product': 3,
  
  // Analyst upgrades and ratings
  'raised': 3,
  'stock raised': 3,
  'target raised': 3,
  'price target raised': 3,
  'upgraded to buy': 3,
  'buy recommendation': 3,
  'strong buy': 3,
  'outperform rating': 3,
  
  // AI and technology
  'strong ai': 3,
  'strong AI': 3,
  'ai-driven': 3,
  'AI-driven': 3,
  'AI breakthrough': 3,
  'ai breakthrough': 3,
  'AI leadership': 3,
  'ai leadership': 3,
  
  // Financial strength
  'record revenue': 3,
  'record profit': 3,
  'record sales': 3,
  'cash flow surge': 3,
  'dividend increase': 3,
  'dividend raised': 3,
  'share buyback': 3,
  'buyback program': 3,
  'stock split': 3,
  
  // Market position
  'market leader': 3,
  'market dominance': 3,
  'market share gain': 3,
  'competitive advantage': 3,
  
  // Medium impact bullish (score: 2) - Positive signals
  // Outlook and sentiment
  'positive outlook': 2,
  'strong outlook': 2,
  'optimistic outlook': 2,
  'bullish': 2,
  'bullish outlook': 2,
  
  // Growth indicators
  'growth': 2,
  'revenue growth': 2,
  'sales growth': 2,
  'profit growth': 2,
  'expansion': 2,
  'expanding': 2,
  'scale up': 2,
  'scaling': 2,
  
  // Analyst actions
  'upgrade': 2,
  'upgraded': 2,
  'buy rating': 2,
  'overweight rating': 2,
  'positive rating': 2,
  
  // Market movement
  'surge': 2,
  'rally': 2,
  'rallies': 2,
  'jump': 2,
  'jumps': 2,
  'soar': 2,
  'soars': 2,
  'climb': 2,
  'climbs': 2,
  
  // Performance
  'outperform': 2,
  'outperforming': 2,
  'beat expectations': 2,
  'exceed expectations': 2,
  'strong performance': 2,
  'stellar performance': 2,
  
  // Business momentum
  'momentum': 2,
  'acceleration': 2,
  'accelerating': 2,
  'traction': 2,
  'demand surge': 2,
  'strong demand': 2,
  
  // Low impact bullish (score: 1) - Mild positive indicators
  // Basic positive terms
  'buy': 1,
  'increase': 1,
  'gain': 1,
  'gains': 1,
  'profit': 1,
  'profitable': 1,
  'success': 1,
  'successful': 1,
  'innovation': 1,
  'innovative': 1,
  'strong': 1,
  'optimistic': 1,
  'confidence': 1,
  'confident': 1,
  'invest': 1,
  'investment': 1,
  'positive': 1,
  'improve': 1,
  'improvement': 1,
  'improved': 1,
  'recovery': 1,
  'rebound': 1,
  'upturn': 1,
  'advance': 1,
  'advances': 1,
  'rise': 1,
  'rises': 1,
  'up': 1,
  'higher': 1,
  'boost': 1,
  'boosted': 1,
  'benefit': 1,
  'benefits': 1,
  'opportunity': 1,
  'opportunities': 1,
};

export const BEARISH_KEYWORDS = {
  // High impact bearish (score: -3) - Critical negative events
  // Earnings failures
  'miss earnings': -3,
  'missed earnings': -3,
  'earnings miss': -3,
  'revenue miss': -3,
  'earnings warning': -3,
  'profit warning': -3,
  'guidance cut': -3,
  'missed guidance': -3,
  'disappointing results': -3,
  'disappointing earnings': -3,
  
  // Trade and regulatory
  'tariff': -3,
  'tariffs': -3,
  'ban': -3,
  'banned': -3,
  'embargo': -3,
  'sanction': -3,
  'sanctions': -3,
  'trade war': -3,
  'export restriction': -3,
  'export ban': -3,
  
  // Legal and compliance
  'lawsuit': -3,
  'investigation': -3,
  'scandal': -3,
  'fraud': -3,
  'fraudulent': -3,
  'violation': -3,
  'violations': -3,
  
  // Financial crisis
  'bankruptcy': -3,
  'bankrupt': -3,
  'massive loss': -3,
  'massive losses': -3,
  'debt crisis': -3,
  
  // Operations disruption
  'shutdown': -3,
  'shut down': -3,
  'recall': -3,
  'delisting': -3,
  'delisted': -3,
  'plant closure': -3,
  'factory closure': -3,
  'production halt': -3,
  'supply chain disruption': -3,
  
  // Market crashes
  'plunge': -3,
  'plunges': -3,
  'plunged': -3,
  'crash': -3,
  'crashed': -3,
  'collapse': -3,
  'collapsed': -3,
  
  // Security breaches
  'cyberattack': -3,
  'cyber attack': -3,
  'major cyberattack': -3,
  'data breach': -3,
  'security breach': -3,
  'hack': -3,
  'hacked': -3,
  
  // Labor issues
  'strike': -3,
  'major strike': -3,
  'strike action': -3,
  
  // Performance
  'worst performance': -3,
  
  // Medium impact bearish (score: -2) - Significant concerns
  // Analyst actions
  'downgrade': -2,
  'downgraded': -2,
  'sell rating': -2,
  'underperform': -2,
  'underperforming': -2,
  
  // Performance issues
  'poor performance': -2,
  'disappointing': -2,
  'disappoints': -2,
  'disappointed': -2,
  
  // Company actions
  'layoff': -2,
  'layoffs': -2,
  'restructuring': -2,
  'cost cutting': -2,
  
  // Market movement
  'decline': -2,
  'declining': -2,
  'slump': -2,
  'slumps': -2,
  'tumble': -2,
  'tumbles': -2,
  'slides': -2,
  
  // Weakness indicators
  'weakness': -2,
  'weaken': -2,
  'bearish': -2,
  
  // Concerns and warnings
  'concern': -2,
  'concerns': -2,
  'warning': -2,
  'warns': -2,
  'warned': -2,
  
  // Business challenges
  'headwinds': -2,
  'challenges': -2,
  'struggling': -2,
  'struggles': -2,
  'slowdown': -2,
  'slow down': -2,
  
  // Legal and regulatory
  'regulatory scrutiny': -2,
  'antitrust': -2,
  'legal trouble': -2,
  'regulation': -2,
  'fined': -2,
  'fine': -2,
  'penalty': -2,
  'penalties': -2,
  
  // Financial issues
  'bad debt': -2,
  'write-down': -2,
  'writedown': -2,
  'impairment': -2,
  
  // Low impact bearish (score: -1)
  'loss': -1,
  'losses': -1,
  'drop': -1,
  'drops': -1,
  'dropped': -1,
  'fall': -1,
  'falls': -1,
  'fell': -1,
  'weak': -1,
  'weaker': -1,
  'risk': -1,
  'risks': -1,
  'risky': -1,
  'uncertainty': -1,
  'volatile': -1,
  'volatility': -1,
  'pressure': -1,
  'pressured': -1,
  'negative': -1,
  'trouble': -1,
  'troubled': -1,
  'difficult': -1,
  'difficulties': -1,
  'problem': -1,
  'problems': -1,
  'issue': -1,
  'issues': -1,
};

/**
 * Analyze news article for keywords and sentiment
 * Now uses dynamic keywords from API/cache
 */
export function analyzeNewsKeywords(title, description = '') {
  const text = `${title} ${description}`.toLowerCase();
  let score = 0;
  const matchedKeywords = [];
  let contextMultiplier = 1;
  
  // Get keywords (uses cache or fallback)
  const keywords = getKeywordsSync();
  const bullishKeywords = keywords.bullish;
  const bearishKeywords = keywords.bearish;
  
  // Check for context modifiers
  Object.entries(CONTEXT_MODIFIERS).forEach(([keyword, multiplier]) => {
    if (text.includes(keyword)) {
      contextMultiplier = Math.max(contextMultiplier, 1 + multiplier);
    }
  });
  
  // Check bullish keywords (from API/database)
  Object.entries(bullishKeywords).forEach(([keyword, weight]) => {
    if (text.includes(keyword.toLowerCase())) {
      score += weight;
      matchedKeywords.push({ keyword, impact: 'bullish', weight });
    }
  });
  
  // Check bearish keywords (from API/database)
  Object.entries(bearishKeywords).forEach(([keyword, weight]) => {
    const absWeight = Math.abs(weight);
    if (text.includes(keyword.toLowerCase())) {
      score += weight; // weight is already negative
      matchedKeywords.push({ keyword, impact: 'bearish', weight: absWeight });
    }
  });
  
  // Apply context multiplier
  score *= contextMultiplier;
  
  // Determine sentiment
  let sentiment = 'neutral';
  let confidence = 0;
  
  if (score > 2) {
    sentiment = 'bullish';
    confidence = Math.min(95, 50 + (score * 10));
  } else if (score < -2) {
    sentiment = 'bearish';
    confidence = Math.min(95, 50 + (Math.abs(score) * 10));
  } else if (score !== 0) {
    sentiment = score > 0 ? 'slightly bullish' : 'slightly bearish';
    confidence = 30 + (Math.abs(score) * 10);
  }
  
  return {
    score,
    sentiment,
    confidence: Math.round(confidence),
    matchedKeywords,
    contextMultiplier,
    needsAlert: Math.abs(score) >= 2, // Alert for significant news
  };
}

/**
 * Analyze multiple news articles and aggregate sentiment
 */
export function aggregateNewsSentiment(newsArticles) {
  if (!newsArticles || newsArticles.length === 0) {
    return {
      overallScore: 0,
      overallSentiment: 'neutral',
      confidence: 0,
      analysisCount: 0,
      majorAlerts: [],
    };
  }
  
  const analyses = newsArticles.map(article => ({
    ...analyzeNewsKeywords(article.title, article.description),
    article,
  }));
  
  const totalScore = analyses.reduce((sum, a) => sum + a.score, 0);
  const avgScore = totalScore / analyses.length;
  
  // Find major alerts (high-impact news)
  const majorAlerts = analyses
    .filter(a => a.needsAlert)
    .sort((a, b) => Math.abs(b.score) - Math.abs(a.score))
    .slice(0, 3); // Top 3 alerts
  
  let overallSentiment = 'neutral';
  let confidence = 0;
  
  if (avgScore > 1.5) {
    overallSentiment = 'bullish';
    confidence = Math.min(90, 50 + (avgScore * 15));
  } else if (avgScore < -1.5) {
    overallSentiment = 'bearish';
    confidence = Math.min(90, 50 + (Math.abs(avgScore) * 15));
  } else if (avgScore !== 0) {
    overallSentiment = avgScore > 0 ? 'slightly bullish' : 'slightly bearish';
    confidence = 30 + (Math.abs(avgScore) * 10);
  }
  
  return {
    overallScore: avgScore,
    overallSentiment,
    confidence: Math.round(confidence),
    analysisCount: analyses.length,
    majorAlerts,
    allAnalyses: analyses,
  };
}
