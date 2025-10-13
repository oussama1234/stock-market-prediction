import { useState, useEffect, useCallback, useRef, useMemo, lazy, Suspense } from 'react';
import { useNavigate } from 'react-router-dom';
import { useStocks } from '../hooks/useStocks';
import { newsAPI, stockAPI, marketAPI } from '../services/api';
import { GenericLoader } from '../components/loaders';
import StockLogo from '../components/StockLogo';
import { 
  TrendingUp, TrendingDown, Activity, BarChart3, 
  Newspaper, Target, Shield, Zap, Brain, Award, Flame, FileText
} from 'lucide-react';

// Lazy load components for performance
const StockCardModern = lazy(() => import('../components/StockCardModern'));
const NewsCard = lazy(() => import('../components/NewsCard'));
const SectionHeader = lazy(() => import('../components/SectionHeader'));

function HomePageNew() {
  const navigate = useNavigate();
  const [searchQuery, setSearchQuery] = useState('');
  const [activeTab, setActiveTab] = useState('popular');
  const [trendingStocks, setTrendingStocks] = useState([]);
  const [isLoadingTrending, setIsLoadingTrending] = useState(false);
  // Latest market news (today) using advanced endpoint
  const [latestMarketNews, setLatestMarketNews] = useState([]);
  const [latestNewsTotal, setLatestNewsTotal] = useState(0); // Total count of all news
  const [latestLimit, setLatestLimit] = useState(9);
  const [latestLoading, setLatestLoading] = useState(true);
  const [latestHasMore, setLatestHasMore] = useState(true);
  const [latestLoadingMore, setLatestLoadingMore] = useState(false);
  
  // Market indices state
  const [marketIndices, setMarketIndices] = useState({
    sp500: null,
    nasdaq: null,
    dow: null,
  });
  const [indicesLoading, setIndicesLoading] = useState(true);
  
  // Fear & Greed Index state
  const [fearGreedIndex, setFearGreedIndex] = useState(null);
  const [fearGreedLoading, setFearGreedLoading] = useState(true);
  
  const { stocks: searchResults, popularStocks, loading: searchLoading, searchStocks, getPopularStocks, clearSearch } = useStocks();

  // Refs for GSAP animations and search container
  const heroRef = useRef(null);
  const featuresRef = useRef(null);
  const statsRef = useRef(null);
  const searchContainerRef = useRef(null);

  // Fetch latest market news (today)
  const fetchLatestMarketNews = useCallback(async (limit = latestLimit, { silent = false } = {}) => {
    try {
      if (!silent && latestMarketNews.length === 0) {
        setLatestLoading(true);
      }
      const res = await newsAPI.getMarketAdvanced({ date: 'today', limit });
      if (res?.success) {
        const items = res.data || [];
        // Sort desc by published_at to be safe
        const sorted = [...items].sort((a, b) => new Date(b.published_at || 0) - new Date(a.published_at || 0));
        setLatestMarketNews(sorted);
        setLatestNewsTotal(res.total || items.length); // Set total count
        setLatestHasMore(!!res?.has_more);
      } else {
        setLatestMarketNews([]);
        setLatestNewsTotal(0);
        setLatestHasMore(false);
      }
    } catch (e) {
      console.error('Failed to fetch latest market news:', e);
      setLatestMarketNews([]);
      setLatestNewsTotal(0);
      setLatestHasMore(false);
    } finally {
      if (!silent) {
        setLatestLoading(false);
      }
    }
  }, [latestLimit, latestMarketNews.length]);

  const loadMoreLatestMarketNews = useCallback(async () => {
    try {
      setLatestLoadingMore(true);
      const newLimit = latestLimit + 9;
      setLatestLimit(newLimit);
      const res = await newsAPI.getMarketAdvanced({ date: 'today', limit: newLimit });
      if (res?.success) {
        const items = res.data || [];
        // Dedup by URL then sort desc
        const map = new Map();
        for (const it of latestMarketNews) if (it?.url) map.set(it.url, it);
        for (const it of items) if (it?.url && !map.has(it.url)) map.set(it.url, it);
        const merged = Array.from(map.values()).sort((a, b) => new Date(b.published_at || 0) - new Date(a.published_at || 0));
        setLatestMarketNews(merged);
        setLatestNewsTotal(res.total || merged.length); // Update total count
        setLatestHasMore(!!res?.has_more);
      }
    } finally {
      setLatestLoadingMore(false);
    }
  }, [latestLimit, latestMarketNews]);

  // Fetch major market indices from database
  const fetchMarketIndices = useCallback(async () => {
    try {
      setIndicesLoading(true);
      const response = await marketAPI.getIndices();
      
      if (response.success && response.data) {
        // The API returns string values, convert to numbers where needed
        const formatIndex = (index) => {
          if (!index) return null;
          return {
            ...index,
            current_price: index.current_price ? parseFloat(index.current_price) : null,
            change: index.change ? parseFloat(index.change) : null,
            change_percent: index.change_percent ? parseFloat(index.change_percent) : null,
            momentum_score: index.momentum_score ? parseFloat(index.momentum_score) : null,
            day_high: index.day_high ? parseFloat(index.day_high) : null,
            day_low: index.day_low ? parseFloat(index.day_low) : null,
          };
        };

        setMarketIndices({
          sp500: formatIndex(response.data.sp500),
          nasdaq: formatIndex(response.data.nasdaq),
          dow: formatIndex(response.data.dow),
        });
      } else {
        console.warn('Failed to fetch market indices:', response);
      }
    } catch (error) {
      console.error('Failed to fetch market indices:', error);
    } finally {
      setIndicesLoading(false);
    }
  }, []);
  
  // Fetch trending stocks (based on price change %)
  const fetchTrendingStocks = useCallback(async () => {
    try {
      setIsLoadingTrending(true);
      const response = await stockAPI.getPopular();
      if (response.success && response.data) {
        // Sort by absolute price change percentage (highest movers)
        const trending = [...response.data]
          .filter(stock => {
            const changePercent = stock.quote?.change_percent || stock.latest_price?.change_percent;
            return changePercent !== null && changePercent !== undefined;
          })
          .sort((a, b) => {
            const changeA = Math.abs(a.quote?.change_percent || a.latest_price?.change_percent || 0);
            const changeB = Math.abs(b.quote?.change_percent || b.latest_price?.change_percent || 0);
            return changeB - changeA;
          })
          .slice(0, 12); // Top 12 movers
        
        setTrendingStocks(trending);
      }
    } catch (error) {
      console.error('Failed to fetch trending stocks:', error);
      setTrendingStocks([]);
    } finally {
      setIsLoadingTrending(false);
    }
  }, []);

  // Fetch Fear & Greed Index
  const fetchFearGreedIndex = useCallback(async () => {
    try {
      setFearGreedLoading(true);
      const response = await marketAPI.getFearGreedIndex();
      if (response.success && response.data) {
        setFearGreedIndex(response.data);
      }
    } catch (error) {
      console.error('Failed to fetch Fear & Greed Index:', error);
    } finally {
      setFearGreedLoading(false);
    }
  }, []);
  
  // Fetch initial data
  useEffect(() => {
    fetchLatestMarketNews(undefined, { silent: false });
    getPopularStocks();
    fetchTrendingStocks();
    fetchMarketIndices();
    fetchFearGreedIndex();
  }, [fetchLatestMarketNews, getPopularStocks, fetchTrendingStocks, fetchMarketIndices, fetchFearGreedIndex]);

  // Auto-refresh latest market news every 1 minute
  useEffect(() => {
    const newsInterval = setInterval(() => {
      fetchLatestMarketNews(undefined, { silent: true });
    }, 60000); // 60 seconds

    return () => clearInterval(newsInterval);
  }, [fetchLatestMarketNews]);

  // Click outside to close search results
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (searchContainerRef.current && !searchContainerRef.current.contains(event.target)) {
        // Clicked outside search container - close results
        if (searchResults.length > 0) {
          clearSearch();
        }
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [searchResults, clearSearch]);
  
  // GSAP Animations removed to prevent opacity issues
  // All content now displays immediately for better user experience

  const handleSearch = useCallback(async (e) => {
    e.preventDefault();
    if (!searchQuery.trim()) return;
    await searchStocks(searchQuery);
  }, [searchQuery, searchStocks]);

  const handleStockClick = useCallback((symbol, event) => {
    // Prevent event from bubbling up to the document click handler
    if (event) {
      event.stopPropagation();
    }
    // Navigate and clear search
    navigate(`/stock/${symbol}`);
    setSearchQuery('');
    clearSearch();
  }, [navigate, clearSearch]);
  
  const handleDeleteStock = useCallback(async (symbol) => {
    const confirmed = window.confirm(
      `Are you sure you want to delete ${symbol}? This will remove all associated data including predictions and price history.`
    );
    
    if (!confirmed) return;
    
    try {
      await stockAPI.delete(symbol);
      // Refresh the stock list
      await getPopularStocks();
    } catch (error) {
      console.error('Failed to delete stock:', error);
      alert(`Failed to delete ${symbol}. Please try again.`);
    }
  }, [getPopularStocks]);

  // Memoized current display stocks based on active tab
  const displayStocks = useMemo(() => {
    return activeTab === 'popular' ? popularStocks : trendingStocks;
  }, [activeTab, popularStocks, trendingStocks]);

  // Memoized computed values
  const totalStocks = useMemo(() => popularStocks.length, [popularStocks.length]);
  
  const predictionStats = useMemo(() => {
    const bullish = popularStocks.filter(s => s.active_prediction?.direction === 'up').length;
    const bearish = popularStocks.filter(s => s.active_prediction?.direction === 'down').length;
    return { bullish, bearish };
  }, [popularStocks]);
  
  const marketSentiment = useMemo(() => {
    // Multi-factor sentiment analysis
    let sentimentScore = 0;
    let factorCount = 0;
    
    // 1. Fear & Greed Index (40% weight)
    if (fearGreedIndex && fearGreedIndex.value) {
      // Convert 0-100 scale to -1 to +1 scale
      const fgScore = (fearGreedIndex.value - 50) / 50; // -1 to +1
      sentimentScore += fgScore * 0.4;
      factorCount++;
    }
    
    // 2. Market Indices Performance (30% weight)
    if (marketIndices.sp500 || marketIndices.nasdaq || marketIndices.dow) {
      let indicesScore = 0;
      let indicesCount = 0;
      
      if (marketIndices.sp500?.change_percent !== null && marketIndices.sp500?.change_percent !== undefined) {
        indicesScore += marketIndices.sp500.change_percent;
        indicesCount++;
      }
      if (marketIndices.nasdaq?.change_percent !== null && marketIndices.nasdaq?.change_percent !== undefined) {
        indicesScore += marketIndices.nasdaq.change_percent;
        indicesCount++;
      }
      if (marketIndices.dow?.change_percent !== null && marketIndices.dow?.change_percent !== undefined) {
        indicesScore += marketIndices.dow.change_percent;
        indicesCount++;
      }
      
      if (indicesCount > 0) {
        const avgChange = indicesScore / indicesCount;
        // Normalize to -1 to +1 (assuming ±5% is extreme)
        const normalizedScore = Math.max(-1, Math.min(1, avgChange / 5));
        sentimentScore += normalizedScore * 0.3;
        factorCount++;
      }
    }
    
    // 3. Stock Predictions (20% weight)
    const bullish = popularStocks.filter(s => s.active_prediction?.direction === 'up').length;
    const bearish = popularStocks.filter(s => s.active_prediction?.direction === 'down').length;
    if (bullish + bearish > 0) {
      const ratio = (bullish - bearish) / (bullish + bearish);
      sentimentScore += ratio * 0.2;
      factorCount++;
    }
    
    // 4. News Sentiment (10% weight)
    const predictions = popularStocks
      .filter(s => s.active_prediction?.sentiment_score !== null && s.active_prediction?.sentiment_score !== undefined)
      .map(s => ({
        score: parseFloat(s.active_prediction.sentiment_score),
        count: s.active_prediction.news_count || 0
      }));
    const withNews = predictions.filter(p => p.count > 0);
    if (withNews.length > 0) {
      const avg = withNews.reduce((sum, p) => sum + p.score, 0) / withNews.length;
      sentimentScore += avg * 0.1;
      factorCount++;
    }
    
    // Calculate final sentiment
    if (factorCount === 0) {
      return { value: '0.0', label: 'Neutral', hasData: false, icon: 'neutral' };
    }
    
    // Determine label and icon with more sensitive thresholds
    let label, icon;
    if (sentimentScore > 0.15) {
      label = 'Bullish';
      icon = 'bullish';
    } else if (sentimentScore < -0.15) {
      label = 'Bearish';
      icon = 'bearish';
    } else {
      label = 'Neutral';
      icon = 'neutral';
    }
    
    return { 
      value: (sentimentScore * 10).toFixed(1), 
      label,
      icon,
      hasData: true,
      fearGreed: fearGreedIndex?.classification || null
    };
  }, [popularStocks, fearGreedIndex, marketIndices]);

  // Handle tab change with smooth transition
  const handleTabChange = useCallback((tab) => {
    setActiveTab(tab);
    // Fetch trending if switching to it for the first time
    if (tab === 'trending' && trendingStocks.length === 0 && !isLoadingTrending) {
      fetchTrendingStocks();
    }
  }, [trendingStocks.length, isLoadingTrending, fetchTrendingStocks]);

  // Check if currently loading stocks
  const isLoadingStocks = useMemo(() => {
    return activeTab === 'popular' ? searchLoading : isLoadingTrending;
  }, [activeTab, searchLoading, isLoadingTrending]);

  // Note: Removed initial loading screen for faster page load
  // Individual sections will show their own loading states

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 transition-colors duration-300">
      
      {/* Modern Hero Section with Enhanced Glassmorphism and Colorful Gradients */}
      <div className="relative overflow-visible bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-600 dark:from-gray-900 dark:via-purple-900 dark:to-indigo-900 -mt-20 md:-mt-24 pt-32 md:pt-40">
        {/* Animated Background Shapes with More Colors */}
        <div className="absolute inset-0 opacity-30 dark:opacity-20">
          <div className="absolute top-10 left-10 w-96 h-96 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-full mix-blend-multiply filter blur-2xl animate-blob"></div>
          <div className="absolute top-0 right-10 w-96 h-96 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full mix-blend-multiply filter blur-2xl animate-blob animation-delay-2000"></div>
          <div className="absolute -bottom-8 left-20 w-96 h-96 bg-gradient-to-br from-pink-400 to-rose-500 rounded-full mix-blend-multiply filter blur-2xl animate-blob animation-delay-4000"></div>
          <div className="absolute bottom-20 right-20 w-80 h-80 bg-gradient-to-br from-purple-400 to-indigo-500 rounded-full mix-blend-multiply filter blur-2xl animate-blob animation-delay-3000"></div>
        </div>

        <div ref={heroRef} className="relative container mx-auto px-4 py-24 md:py-32">
          <div className="text-center text-white">
            {/* Animated Badge with Gradient Border */}
            <div className="group inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-white/10 backdrop-blur-xl border-2 border-white/30 mb-8 animate-fade-in-up hover:scale-105 transition-all duration-500 hover:shadow-2xl hover:shadow-emerald-500/50">
              <span className="relative flex h-3 w-3">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-gradient-to-r from-emerald-400 to-cyan-400 opacity-75"></span>
                <span className="relative inline-flex rounded-full h-3 w-3 bg-gradient-to-r from-emerald-500 to-cyan-500"></span>
              </span>
              <span className="text-sm font-bold text-white drop-shadow-lg group-hover:tracking-wide transition-all duration-300">Live Market Data</span>
            </div>

            {/* Main Heading with Enhanced Gradient Text and Animations */}
            <h1 className="text-5xl md:text-7xl lg:text-8xl font-black mb-6 leading-tight">
              <span className="inline-block animate-fade-in-up hover:scale-105 transition-transform duration-500" style={{animationDelay: '0.1s'}}>
                <span className="bg-clip-text text-transparent bg-gradient-to-r from-white via-cyan-100 to-blue-100 drop-shadow-2xl animate-text-shimmer">
                  Smart Trading
                </span>
              </span>
              <br />
              <span className="inline-block animate-fade-in-up hover:scale-105 transition-transform duration-500" style={{animationDelay: '0.2s'}}>
                <span className="bg-clip-text text-transparent bg-gradient-to-r from-yellow-200 via-pink-200 to-purple-200 drop-shadow-2xl animate-text-shimmer" style={{animationDelay: '0.5s'}}>
                  Powered by AI
                </span>
              </span>
            </h1>
            
            {/* Subtitle */}
            <p className="text-xl md:text-2xl font-light mb-4 text-white/80 max-w-3xl mx-auto animate-fade-in-up" style={{animationDelay: '0.3s'}}>
              Advanced stock market predictions using machine learning, sentiment analysis, and real-time data
            </p>
            
            {/* Feature Pills with Colorful Hover Effects */}
            <div className="flex flex-wrap justify-center gap-3 mb-12 animate-fade-in-up" style={{animationDelay: '0.4s'}}>
              <span className="group px-5 py-2.5 rounded-full bg-white/10 backdrop-blur-xl border-2 border-white/30 text-sm font-bold text-white hover:bg-gradient-to-r hover:from-cyan-500/20 hover:to-blue-500/20 hover:border-cyan-300/50 hover:scale-110 hover:shadow-2xl hover:shadow-cyan-500/50 transition-all duration-500 cursor-pointer">
                <Zap className="w-4 h-4 inline-block group-hover:scale-125 transition-transform duration-300" /> Real-time Analysis
              </span>
              <span className="group px-5 py-2.5 rounded-full bg-white/10 backdrop-blur-xl border-2 border-white/30 text-sm font-bold text-white hover:bg-gradient-to-r hover:from-purple-500/20 hover:to-pink-500/20 hover:border-purple-300/50 hover:scale-110 hover:shadow-2xl hover:shadow-purple-500/50 transition-all duration-500 cursor-pointer">
                <Brain className="w-4 h-4 inline-block group-hover:scale-125 transition-transform duration-300" /> AI Predictions
              </span>
              <span className="group px-5 py-2.5 rounded-full bg-white/10 backdrop-blur-xl border-2 border-white/30 text-sm font-bold text-white hover:bg-gradient-to-r hover:from-green-500/20 hover:to-emerald-500/20 hover:border-green-300/50 hover:scale-110 hover:shadow-2xl hover:shadow-green-500/50 transition-all duration-500 cursor-pointer">
                <TrendingUp className="w-4 h-4 inline-block group-hover:scale-125 transition-transform duration-300" /> Market Insights
              </span>
            </div>

            {/* Enhanced Search Bar with Colorful Effects */}
            <div ref={searchContainerRef} className="max-w-3xl mx-auto animate-fade-in-up" style={{animationDelay: '0.5s'}}>
              <form onSubmit={handleSearch} className="group relative">
                {/* Multi-colored glowing background effect */}
                <div className="absolute -inset-1 bg-gradient-to-r from-cyan-500 via-purple-600 to-pink-500 rounded-2xl blur-xl opacity-40 group-hover:opacity-100 group-focus-within:opacity-100 transition-all duration-1000"></div>
                <div className="absolute -inset-0.5 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 rounded-2xl opacity-50 group-hover:opacity-0 group-focus-within:opacity-0 transition-opacity duration-500"></div>
                
                <div className="relative">
                  <div className="flex items-stretch bg-white/98 dark:bg-gray-800/98 backdrop-blur-xl rounded-2xl shadow-2xl overflow-hidden border-2 border-white/50 dark:border-gray-700/50 group-hover:border-purple-300/50 dark:group-hover:border-purple-600/50 group-focus-within:border-purple-400 dark:group-focus-within:border-purple-500 transition-all duration-500">
                    {/* Search Icon with Gradient */}
                    <div className="flex items-center justify-center px-5 bg-gradient-to-br from-cyan-500 via-indigo-600 to-purple-600 group-hover:from-indigo-600 group-hover:via-purple-600 group-hover:to-pink-600 transition-all duration-700">
                      <svg className="w-6 h-6 text-white group-hover:scale-110 group-hover:rotate-12 transition-all duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                      </svg>
                    </div>
                    
                    {/* Input Field with Enhanced Styling */}
                    <input
                      type="text"
                      value={searchQuery}
                      onChange={(e) => setSearchQuery(e.target.value)}
                      placeholder="Search stocks (AAPL, TSLA, MSFT, GOOGL...)"
                      className="flex-1 px-6 py-5 text-lg font-bold text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 bg-transparent focus:outline-none transition-all duration-300"
                    />
                    
                    {/* Search Button with Enhanced Gradients */}
                    <button
                      type="submit"
                      disabled={searchLoading}
                      className="group relative px-8 py-5 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 text-white font-bold hover:from-cyan-600 hover:via-indigo-600 hover:to-purple-600 hover:shadow-2xl hover:shadow-purple-500/50 transition-all duration-700 disabled:opacity-50 disabled:cursor-not-allowed overflow-hidden"
                    >
                      {/* Animated shimmer effect */}
                      <div className="absolute inset-0 -translate-x-full group-hover:translate-x-full transition-transform duration-1000 bg-gradient-to-r from-transparent via-white/30 to-transparent"></div>
                      <div className="absolute inset-0 bg-gradient-to-r from-transparent via-cyan-400/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                      
                      {searchLoading ? (
                        <div className="flex items-center gap-2">
                          <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none"></circle>
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                          </svg>
                          <span>Searching</span>
                        </div>
                      ) : (
                        <div className="flex items-center gap-2">
                          <span>Search</span>
                          <svg className="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                          </svg>
                        </div>
                      )}
                    </button>
                  </div>
                </div>
              </form>

              {/* Modern Search Results Dropdown - Clean & Colorful */}
              {searchResults.length > 0 && (
                <div className="mt-6 animate-slide-down">
                  <div className="relative">
                    {/* Colorful glow background */}
                    <div className="absolute -inset-1 bg-gradient-to-r from-cyan-500 via-purple-500 to-pink-500 rounded-2xl blur-lg opacity-50"></div>
                    
                    <div className="relative bg-white/98 dark:bg-gray-900/98 backdrop-blur-2xl rounded-2xl shadow-2xl border-2 border-white/50 dark:border-gray-700/50 overflow-hidden">
                      {/* Header with gradient border */}
                      <div className="px-6 py-4 bg-gradient-to-r from-indigo-50 via-purple-50 to-pink-50 dark:from-gray-800 dark:via-gray-800 dark:to-gray-800 border-b-2 border-gradient">
                        <div className="flex items-center justify-between">
                          <div className="flex items-center gap-3">
                            <div className="w-2 h-2 rounded-full bg-gradient-to-r from-green-500 to-emerald-500 animate-pulse"></div>
                            <h3 className="text-base font-black text-gray-900 dark:text-white">
                              {searchResults.length} {searchResults.length === 1 ? 'Result' : 'Results'}
                            </h3>
                          </div>
                          <button 
                            onClick={() => clearSearch()}
                            className="group p-2 rounded-lg hover:bg-gradient-to-r hover:from-red-500 hover:to-pink-500 transition-all duration-300 hover:scale-110"
                          >
                            <svg className="w-5 h-5 text-gray-600 dark:text-gray-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                          </button>
                        </div>
                      </div>
                      
                      {/* Results list */}
                      <div className="max-h-[400px] overflow-y-auto custom-scrollbar">
                        {searchResults.map((stock, index) => {
                          const price = stock.quote?.current_price || stock.latest_price?.close;
                          const change = stock.quote?.change_percent || stock.latest_price?.change_percent;
                          const isPositive = change >= 0;
                          
                          return (
                            <div
                              key={index}
                              onClick={(e) => handleStockClick(stock.symbol, e)}
                              className="group relative px-6 py-4 cursor-pointer transition-all duration-300 bg-white dark:bg-gray-900 hover:bg-gradient-to-r hover:from-red-50 hover:via-pink-50 hover:to-purple-50 dark:hover:from-gray-800 dark:hover:via-gray-800 dark:hover:to-gray-800 border-b border-gray-100 dark:border-gray-800 last:border-b-0"
                              style={{
                                animationDelay: `${index * 30}ms`,
                                animation: 'fadeInUp 0.4s ease-out forwards',
                                opacity: 0,
                              }}
                            >
                              {/* Animated gradient border at bottom - always visible on hover */}
                              <div className="absolute inset-x-0 bottom-0 h-1 bg-gradient-to-r from-red-500 via-pink-500 to-purple-500 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left shadow-lg shadow-pink-500/50"></div>
                              
                              <div className="flex items-center justify-between">
                                <div className="flex items-center gap-4 flex-1 min-w-0">
                                  {/* Logo */}
                                  <div className="relative flex-shrink-0">
                                    <div className="absolute -inset-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 rounded-xl opacity-0 group-hover:opacity-100 blur transition-all duration-300"></div>
                                    <div className="relative transform group-hover:scale-110 transition-transform duration-300">
                                      <StockLogo symbol={stock.symbol} name={stock.name} logoUrl={stock.logo_url} size="md" />
                                    </div>
                                  </div>
                                  
                                  {/* Stock Info */}
                                  <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 mb-0.5">
                                      <span className="font-black text-lg text-gray-900 dark:text-white group-hover:text-transparent group-hover:bg-clip-text group-hover:bg-gradient-to-r group-hover:from-indigo-600 group-hover:via-purple-600 group-hover:to-pink-600 transition-all duration-300">
                                        {stock.symbol}
                                      </span>
                                    </div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400 truncate group-hover:text-gray-900 dark:group-hover:text-white transition-colors">
                                      {stock.name}
                                    </p>
                                  </div>
                                </div>
                                
                                {/* Price & Change */}
                                <div className="flex items-center gap-4 flex-shrink-0">
                                  {price && (
                                    <div className="text-right">
                                      <div className="text-lg font-black text-gray-900 dark:text-white group-hover:text-transparent group-hover:bg-clip-text group-hover:bg-gradient-to-r group-hover:from-indigo-600 group-hover:to-purple-600 transition-all">
                                        ${typeof price === 'number' ? price.toFixed(2) : parseFloat(price).toFixed(2)}
                                      </div>
                                      {change !== null && change !== undefined && (
                                        <div className={`text-xs font-bold px-2 py-0.5 rounded-full mt-1 ${
                                          isPositive 
                                            ? 'bg-gradient-to-r from-green-500 to-emerald-500 text-white' 
                                            : 'bg-gradient-to-r from-red-500 to-rose-500 text-white'
                                        }`}>
                                          {isPositive ? '+' : ''}{typeof change === 'number' ? change.toFixed(2) : parseFloat(change).toFixed(2)}%
                                        </div>
                                      )}
                                    </div>
                                  )}
                                  
                                  {/* Arrow Icon */}
                                  <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-600 flex items-center justify-center opacity-0 group-hover:opacity-100 transform translate-x-2 group-hover:translate-x-0 transition-all duration-300 shadow-lg">
                                    <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M9 5l7 7-7 7" />
                                    </svg>
                                  </div>
                                </div>
                              </div>
                            </div>
                          );
                        })}
                      </div>
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Modern Market Overview Section */}
      <div ref={statsRef} className="relative bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 dark:from-gray-900 dark:via-gray-850 dark:to-gray-900 transition-colors duration-300">
        <div className="container mx-auto px-4 py-16">
          {/* Section Header */}
          <div className="text-center mb-12">
            <h2 className="text-4xl md:text-5xl font-black mb-3 bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600">
              Market Overview
            </h2>
            <p className="text-gray-600 dark:text-gray-400 text-lg">Real-time market data and sentiment analysis</p>
          </div>

          {/* Major Indices Cards */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            {/* S&P 500 Card */}
            <div className="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-500 to-cyan-500 p-6 shadow-xl hover:shadow-2xl transition-all duration-500 hover:scale-105">
              <div className="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 group-hover:scale-150 transition-transform duration-500"></div>
              <div className="relative z-10">
                <div className="flex items-center justify-between mb-4">
                  <div className="flex items-center gap-2">
                    <BarChart3 className="w-6 h-6 text-white" />
                    <h3 className="text-xl font-bold text-white">S&P 500</h3>
                  </div>
                  {marketIndices.sp500 && (
                    <div className={`px-3 py-1 rounded-full text-xs font-bold text-white shadow-lg ${
                      marketIndices.sp500.change_percent >= 0 
                        ? 'bg-green-500/70' 
                        : 'bg-red-500/70'
                    }`}>
                      {marketIndices.sp500.change_percent >= 0 ? <TrendingUp className="inline w-3 h-3" /> : <TrendingDown className="inline w-3 h-3" />}
                      {' '}{marketIndices.sp500.change_percent >= 0 ? '+' : ''}{marketIndices.sp500.change_percent?.toFixed(2)}%
                    </div>
                  )}
                </div>
                {!indicesLoading && marketIndices.sp500 ? (
                  <>
                    <div className="text-3xl font-black text-white mb-2">
                      ${marketIndices.sp500.current_price?.toFixed(2)}
                    </div>
                    <div className="flex items-center gap-2 text-blue-100">
                      <Activity className="w-4 h-4" />
                      <span className="text-sm font-medium">
                        {marketIndices.sp500.change_percent >= 0 ? 'Bullish' : 'Bearish'} Trend
                      </span>
                    </div>
                  </>
                ) : (
                  <div className="animate-pulse">
                    <div className="h-8 bg-white/20 rounded mb-2"></div>
                    <div className="h-4 bg-white/20 rounded w-24"></div>
                  </div>
                )}
              </div>
            </div>

            {/* NASDAQ Card */}
            <div className="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-purple-500 to-pink-500 p-6 shadow-xl hover:shadow-2xl transition-all duration-500 hover:scale-105">
              <div className="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 group-hover:scale-150 transition-transform duration-500"></div>
              <div className="relative z-10">
                <div className="flex items-center justify-between mb-4">
                  <div className="flex items-center gap-2">
                    <Zap className="w-6 h-6 text-white" />
                    <h3 className="text-xl font-bold text-white">NASDAQ</h3>
                  </div>
                  {marketIndices.nasdaq && (
                    <div className={`px-3 py-1 rounded-full text-xs font-bold text-white shadow-lg ${
                      marketIndices.nasdaq.change_percent >= 0 
                        ? 'bg-green-500/70' 
                        : 'bg-red-500/70'
                    }`}>
                      {marketIndices.nasdaq.change_percent >= 0 ? <TrendingUp className="inline w-3 h-3" /> : <TrendingDown className="inline w-3 h-3" />}
                      {' '}{marketIndices.nasdaq.change_percent >= 0 ? '+' : ''}{marketIndices.nasdaq.change_percent?.toFixed(2)}%
                    </div>
                  )}
                </div>
                {!indicesLoading && marketIndices.nasdaq ? (
                  <>
                    <div className="text-3xl font-black text-white mb-2">
                      ${marketIndices.nasdaq.current_price?.toFixed(2)}
                    </div>
                    <div className="flex items-center gap-2 text-purple-100">
                      <Activity className="w-4 h-4" />
                      <span className="text-sm font-medium">
                        {marketIndices.nasdaq.change_percent >= 0 ? 'Bullish' : 'Bearish'} Trend
                      </span>
                    </div>
                  </>
                ) : (
                  <div className="animate-pulse">
                    <div className="h-8 bg-white/20 rounded mb-2"></div>
                    <div className="h-4 bg-white/20 rounded w-24"></div>
                  </div>
                )}
              </div>
            </div>

            {/* DOW JONES Card */}
            <div className="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-500 p-6 shadow-xl hover:shadow-2xl transition-all duration-500 hover:scale-105">
              <div className="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 group-hover:scale-150 transition-transform duration-500"></div>
              <div className="relative z-10">
                <div className="flex items-center justify-between mb-4">
                  <div className="flex items-center gap-2">
                    <Target className="w-6 h-6 text-white" />
                    <h3 className="text-xl font-bold text-white">DOW JONES</h3>
                  </div>
                  {marketIndices.dow && (
                    <div className={`px-3 py-1 rounded-full text-xs font-bold text-white shadow-lg ${
                      marketIndices.dow.change_percent >= 0 
                        ? 'bg-green-500/70' 
                        : 'bg-red-500/70'
                    }`}>
                      {marketIndices.dow.change_percent >= 0 ? <TrendingUp className="inline w-3 h-3" /> : <TrendingDown className="inline w-3 h-3" />}
                      {' '}{marketIndices.dow.change_percent >= 0 ? '+' : ''}{marketIndices.dow.change_percent?.toFixed(2)}%
                    </div>
                  )}
                </div>
                {!indicesLoading && marketIndices.dow ? (
                  <>
                    <div className="text-3xl font-black text-white mb-2">
                      ${marketIndices.dow.current_price?.toFixed(2)}
                    </div>
                    <div className="flex items-center gap-2 text-emerald-100">
                      <Activity className="w-4 h-4" />
                      <span className="text-sm font-medium">
                        {marketIndices.dow.change_percent >= 0 ? 'Bullish' : 'Bearish'} Trend
                      </span>
                    </div>
                  </>
                ) : (
                  <div className="animate-pulse">
                    <div className="h-8 bg-white/20 rounded mb-2"></div>
                    <div className="h-4 bg-white/20 rounded w-24"></div>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Quick Stats Grid */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {/* Stocks Tracked */}
            <div className="group relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 p-6 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1">
              <div className="absolute inset-0 bg-gradient-to-br from-indigo-500/10 to-purple-500/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
              <div className="relative z-10">
                <div className="flex items-center justify-between mb-3">
                  <div className="p-2 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-500">
                    <Shield className="w-5 h-5 text-white" />
                  </div>
                  <div className="text-2xl font-black text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-purple-600">
                    {totalStocks}
                  </div>
                </div>
                <p className="text-sm font-bold text-gray-600 dark:text-gray-400">Stocks Tracked</p>
              </div>
            </div>

            {/* Market Signals - Bullish vs Bearish */}
            <div className="group relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 p-6 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1">
              <div className={`absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity ${
                predictionStats.bullish + predictionStats.bearish === 0
                  ? 'bg-gradient-to-br from-gray-500/10 to-slate-500/10'
                  : predictionStats.bullish > predictionStats.bearish
                  ? 'bg-gradient-to-br from-green-500/10 to-emerald-500/10'
                  : 'bg-gradient-to-br from-red-500/10 to-rose-500/10'
              }`}></div>
              <div className="relative z-10">
                <div className="flex items-center justify-between mb-3">
                  <div className={`p-2 rounded-lg ${
                    predictionStats.bullish + predictionStats.bearish === 0
                      ? 'bg-gradient-to-br from-gray-500 to-slate-500'
                      : predictionStats.bullish > predictionStats.bearish
                      ? 'bg-gradient-to-br from-green-500 to-emerald-500'
                      : predictionStats.bearish > predictionStats.bullish
                      ? 'bg-gradient-to-br from-red-500 to-rose-500'
                      : 'bg-gradient-to-br from-gray-500 to-slate-500'
                  }`}>
                    {predictionStats.bullish + predictionStats.bearish === 0 ? (
                      <Activity className="w-5 h-5 text-white" />
                    ) : predictionStats.bullish > predictionStats.bearish ? (
                      <TrendingUp className="w-5 h-5 text-white" />
                    ) : predictionStats.bearish > predictionStats.bullish ? (
                      <TrendingDown className="w-5 h-5 text-white" />
                    ) : (
                      <Activity className="w-5 h-5 text-white" />
                    )}
                  </div>
                  <div className="flex items-center gap-2">
                    <div className="text-right">
                      <div className="text-lg font-black text-transparent bg-clip-text bg-gradient-to-r from-green-600 to-emerald-600">
                        {predictionStats.bullish}↑
                      </div>
                      <div className="text-lg font-black text-transparent bg-clip-text bg-gradient-to-r from-red-600 to-rose-600">
                        {predictionStats.bearish}↓
                      </div>
                    </div>
                  </div>
                </div>
                <p className="text-sm font-bold text-gray-600 dark:text-gray-400">Market Signals</p>
              </div>
            </div>

            {/* Today's News */}
            <div className="group relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 p-6 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1">
              <div className="absolute inset-0 bg-gradient-to-br from-blue-500/10 to-cyan-500/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
              <div className="relative z-10">
                <div className="flex items-center justify-between mb-3">
                  <div className="p-2 rounded-lg bg-gradient-to-br from-blue-500 to-cyan-500">
                    <Newspaper className="w-5 h-5 text-white" />
                  </div>
                  <div className="text-2xl font-black text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-cyan-600">
                    {latestNewsTotal || latestMarketNews.length}
                  </div>
                </div>
                <p className="text-sm font-bold text-gray-600 dark:text-gray-400">Today's News</p>
              </div>
            </div>

            {/* Market Sentiment */}
            <div className="group relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 p-6 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1">
              <div className={`absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity ${
                parseFloat(marketSentiment.value) > 0
                  ? 'bg-gradient-to-br from-green-500/10 to-emerald-500/10'
                  : parseFloat(marketSentiment.value) < 0
                  ? 'bg-gradient-to-br from-red-500/10 to-rose-500/10'
                  : 'bg-gradient-to-br from-gray-500/10 to-slate-500/10'
              }`}></div>
              <div className="relative z-10">
                <div className="flex items-center justify-between mb-3">
                  <div className={`p-2 rounded-lg ${
                    parseFloat(marketSentiment.value) > 0
                      ? 'bg-gradient-to-br from-green-500 to-emerald-500'
                      : parseFloat(marketSentiment.value) < 0
                      ? 'bg-gradient-to-br from-red-500 to-rose-500'
                      : 'bg-gradient-to-br from-gray-500 to-slate-500'
                  }`}>
                    {parseFloat(marketSentiment.value) > 0 ? (
                      <TrendingUp className="w-5 h-5 text-white" />
                    ) : parseFloat(marketSentiment.value) < 0 ? (
                      <TrendingDown className="w-5 h-5 text-white" />
                    ) : (
                      <Activity className="w-5 h-5 text-white" />
                    )}
                  </div>
                  <div className="text-right">
                    <div className={`text-2xl font-black text-transparent bg-clip-text bg-gradient-to-r ${
                      parseFloat(marketSentiment.value) > 0 
                        ? 'from-green-600 to-emerald-600' 
                        : parseFloat(marketSentiment.value) < 0
                        ? 'from-red-600 to-rose-600'
                        : 'from-gray-600 to-slate-600'
                    }`}>
                      {marketSentiment.label}
                    </div>
                    {marketSentiment.hasData && (
                      <div className="text-xs text-gray-500 dark:text-gray-400">
                        {parseFloat(marketSentiment.value) > 0 ? '+' : ''}{marketSentiment.value}
                      </div>
                    )}
                  </div>
                </div>
                <p className="text-sm font-bold text-gray-600 dark:text-gray-400">Market Sentiment</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="container mx-auto px-4 py-12">
        
        {/* Stocks Section with Tabs */}
        <div className="mb-12">
          <Suspense fallback={<div className="mb-6"><h2 className="text-3xl font-bold">Stock Analysis</h2></div>}>
            <SectionHeader
              icon={<TrendingUp className="w-6 h-6" />}
              title="Stock Analysis"
              subtitle={activeTab === 'popular' ? 'Top stocks by market cap' : 'Biggest price movers today'}
              badge={`${displayStocks.length} stocks`}
            >
              <div className="flex items-center gap-3">
                <div className="flex gap-2 bg-white rounded-xl p-1 shadow-lg">
                  <button
                    onClick={() => handleTabChange('popular')}
                    className={`px-6 py-2 rounded-lg font-bold transition-all duration-300 ${
                      activeTab === 'popular'
                        ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-lg scale-105'
                        : 'text-gray-600 hover:bg-gray-100'
                    }`}
                  >
                    <span className="flex items-center gap-2">
                      <Award className="w-4 h-4" /> Popular
                    </span>
                  </button>
                  <button
                    onClick={() => handleTabChange('trending')}
                    className={`px-6 py-2 rounded-lg font-bold transition-all duration-300 ${
                      activeTab === 'trending'
                        ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-lg scale-105'
                        : 'text-gray-600 hover:bg-gray-100'
                    }`}
                  >
                    <span className="flex items-center gap-2">
                      <Flame className="w-4 h-4" /> Trending
                    </span>
                  </button>
                </div>
                <button
                  onClick={() => activeTab === 'popular' ? getPopularStocks() : fetchTrendingStocks()}
                  disabled={isLoadingStocks}
                  className="px-4 py-2 bg-white rounded-lg font-bold text-indigo-600 hover:bg-indigo-50 transition-all hover:scale-105 disabled:opacity-50 shadow-lg"
                  title="Refresh stocks"
                >
                  <svg className={`w-5 h-5 ${isLoadingStocks ? 'animate-spin' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                  </svg>
                </button>
              </div>
            </SectionHeader>
          </Suspense>

          {isLoadingStocks ? (
            <div className="text-center py-12">
              <GenericLoader message={`Loading ${activeTab} stocks`} size="medium" fullScreen={false} />
            </div>
          ) : displayStocks.length === 0 ? (
            <div className="text-center py-12 bg-white dark:bg-gray-800 rounded-2xl shadow-md transition-colors duration-300">
              <div className="mb-4">
                {activeTab === 'popular' ? (
                  <BarChart3 className="w-16 h-16 mx-auto text-gray-400" />
                ) : (
                  <Flame className="w-16 h-16 mx-auto text-gray-400" />
                )}
              </div>
              <p className="text-gray-600 dark:text-gray-300 transition-colors duration-300">
                {activeTab === 'popular' 
                  ? 'No stocks available yet. Try searching above!' 
                  : 'No trending stocks available at the moment.'}
              </p>
            </div>
          ) : (
            <Suspense fallback={<GenericLoader message="Loading stocks" size="small" fullScreen={false} />}>
              <div className="grid sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 transition-all duration-500">
                {displayStocks.map((stock, index) => (
                  <div
                    key={stock.id}
                    className="animate-fade-in"
                    style={{ animationDelay: `${index * 50}ms` }}
                  >
                    <StockCardModern stock={stock} onDelete={handleDeleteStock} />
                  </div>
                ))}
              </div>
            </Suspense>
          )}
        </div>

        {/* Latest Market News (Today) */}
        <div className="mb-12">
          <Suspense fallback={<div className="mb-6"><h2 className="text-3xl font-bold">Latest Market News</h2></div>}>
            <SectionHeader
              icon={<FileText className="w-6 h-6" />}
              title="Latest Market News"
              subtitle="Today's breaking stories and market updates"
            >
              <div className="flex items-center gap-2 px-4 py-2 bg-green-50 border border-green-200 rounded-lg">
                <span className="relative flex h-3 w-3">
                  <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                  <span className="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                </span>
                <span className="text-sm font-bold text-green-700">Live Updates</span>
              </div>
            </SectionHeader>
          </Suspense>
          {latestLoading ? (
            <GenericLoader message="Loading latest market news" size="medium" fullScreen={false} />
          ) : latestMarketNews.length === 0 ? (
            <div className="text-center py-12 bg-white rounded-2xl shadow-md">
              <Newspaper className="w-16 h-16 mx-auto mb-4 text-gray-400" />
              <p className="text-gray-600">No market news found for today</p>
            </div>
          ) : (
            <>
              <Suspense fallback={<GenericLoader message="Loading news" size="small" fullScreen={false} />}>
                <div className="grid sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                  {latestMarketNews.map((article, idx) => (
                    <NewsCard key={article.url || idx} article={article} />
                  ))}
                </div>
              </Suspense>
              {latestHasMore && latestMarketNews.length >= 9 && (
                <div className="flex justify-center mt-8">
                  <button
                    onClick={loadMoreLatestMarketNews}
                    disabled={latestLoadingMore}
                    className="group relative inline-flex items-center gap-3 px-8 py-4 bg-white text-gray-900 font-semibold rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 disabled:opacity-50 disabled:cursor-not-allowed overflow-hidden"
                  >
                    <div className="absolute inset-0 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 opacity-0 group-hover:opacity-10 transition-opacity duration-300"></div>
                    <div className="absolute inset-0 rounded-2xl bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 opacity-20 group-hover:opacity-100 transition-opacity duration-300" style={{padding: '2px'}}>
                      <div className="h-full w-full bg-white rounded-2xl"></div>
                    </div>
                    <span className="relative z-10 flex items-center gap-3">
                      {latestLoadingMore ? (
                        <>
                          <svg className="animate-spin h-5 w-5 text-indigo-600" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none"></circle>
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                          </svg>
                          <span className="text-indigo-600">Loading More News...</span>
                        </>
                      ) : (
                        <>
                          <span className="text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 group-hover:from-indigo-700 group-hover:via-purple-700 group-hover:to-pink-700 transition-all">
                            Load More
                          </span>
                          <svg className="w-5 h-5 text-indigo-600 group-hover:translate-y-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                          </svg>
                        </>
                      )}
                    </span>
                  </button>
                </div>
              )}
            </>
          )}
        </div>
      </div>

      {/* Custom styles for animations */}
      <style>{`
        @keyframes blob {
          0%, 100% { transform: translate(0, 0) scale(1); }
          25% { transform: translate(20px, -50px) scale(1.1); }
          50% { transform: translate(-20px, 20px) scale(0.9); }
          75% { transform: translate(50px, 50px) scale(1.05); }
        }
        
        @keyframes fadeInUp {
          from {
            opacity: 0;
            transform: translateY(30px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }
        
        @keyframes fadeIn {
          from {
            opacity: 0;
            transform: scale(0.95);
          }
          to {
            opacity: 1;
            transform: scale(1);
          }
        }
        
        .animate-fade-in {
          animation: fadeIn 0.5s ease-out forwards;
          opacity: 0;
        }
        
        @keyframes slideDown {
          from {
            opacity: 0;
            transform: translateY(-20px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }
        
        .animate-blob {
          animation: blob 7s infinite;
        }
        
        .animate-fade-in-up {
          animation: fadeInUp 0.8s ease-out forwards;
          opacity: 0;
        }
        
        .animate-slide-down {
          animation: slideDown 0.4s ease-out forwards;
        }
        
        .animation-delay-2000 {
          animation-delay: 2s;
        }
        
        .animation-delay-3000 {
          animation-delay: 3s;
        }
        
        .animation-delay-4000 {
          animation-delay: 4s;
        }
        
        /* Custom Scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
          width: 8px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
          background: #f1f1f1;
          border-radius: 10px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
          background: linear-gradient(to bottom, #3b82f6, #8b5cf6, #ec4899);
          border-radius: 10px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
          background: linear-gradient(to bottom, #2563eb, #7c3aed, #db2777);
        }
        
        @keyframes text-shimmer {
          0%, 100% {
            background-position: 0% 50%;
          }
          50% {
            background-position: 100% 50%;
          }
        }
        
        .animate-text-shimmer {
          background-size: 200% auto;
          animation: text-shimmer 3s linear infinite;
        }
        
        @keyframes gradient-slow {
          0%, 100% {
            transform: rotate(0deg) scale(1);
            opacity: 0.4;
          }
          50% {
            transform: rotate(180deg) scale(1.1);
            opacity: 1;
          }
        }
        
        .animate-gradient-slow {
          animation: gradient-slow 8s ease-in-out infinite;
        }
        
        .animation-delay-3000 {
          animation-delay: 3s;
        }
      `}</style>
    </div>
  );
}

export default HomePageNew;
