import { useParams, Link } from 'react-router-dom';
import { useState, useEffect, memo, lazy, Suspense } from 'react';
import { useStockDetails } from './hooks/useStockDetails';
import { StockLoader } from '../../components/loaders';
import StockLogo from '../../components/StockLogo';
import AlertBanner from './components/AlertBanner';
import PredictionCardV2 from '../../components/PredictionCardV2';
import AsianMarketWidget from '../../components/AsianMarketWidget';
import EuropeanMarketWidget from '../../components/EuropeanMarketWidget';
import NewsGrid from './components/NewsGrid';
import { formatVolume } from '../../utils/formatters';
import {
  ArrowLeft, RefreshCw, Clock, TrendingUp, TrendingDown, Activity,
  BarChart3, Radio, DollarSign, TrendingUpDown, Globe,
  Building2, Package, Users, AlertTriangle, CheckCircle,
  Sparkles, Brain, Search, Target, Zap
} from 'lucide-react';

/**
 * StockDetails - Complete stock details page with modern design
 * Features:
 * - Auto-regenerating predictions based on news sentiment
 * - Bullish/bearish keyword detection with alerts
 * - Comprehensive stock information
 * - Beautiful animations and hover effects
 * - Performance optimized with hooks and memoization
 */
export default function StockDetails() {
  const { symbol } = useParams();
  const [refreshing, setRefreshing] = useState(false);
  const [currentTime, setCurrentTime] = useState(new Date());
  const {
    data,
    loading,
    error,
    newsSentiment,
    autoRegenerating,
    regenerate,
    refresh,
    isLive,
  } = useStockDetails(symbol);

  // Update current time every second
  useEffect(() => {
    const timer = setInterval(() => setCurrentTime(new Date()), 1000);
    return () => clearInterval(timer);
  }, []);

  const handleRefresh = async () => {
    setRefreshing(true);
    await refresh();
    setTimeout(() => setRefreshing(false), 500);
  };

  // Loading state
  if (loading) {
    return (
      <StockLoader 
        symbol={symbol} 
        message="Analyzing market data and news sentiment" 
      />
    );
  }

  // Error state
  if (error || !data) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 p-4 transition-colors duration-300">
        <div className="bg-white rounded-3xl shadow-2xl p-12 text-center max-w-2xl transition-colors duration-300">
          <AlertTriangle className="w-20 h-20 text-red-500 mx-auto mb-4" />
          <h2 className="text-3xl font-bold text-gray-900 mb-4 transition-colors duration-300">Error Loading Stock</h2>
          <p className="text-gray-600 mb-6 transition-colors duration-300">{error || `Unable to load ${symbol}`}</p>
          <div className="flex gap-4 justify-center">
            <button
              onClick={() => refresh()}
              className="flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white rounded-xl font-semibold hover:bg-indigo-700 transition-all hover:scale-105 shadow-lg"
            >
              <RefreshCw className="w-5 h-5" />
              Try Again
            </button>
            <Link
              to="/"
              className="flex items-center gap-2 px-6 py-3 bg-gray-100 text-gray-800 rounded-xl font-semibold hover:bg-gray-200 transition-all hover:scale-105"
            >
              <ArrowLeft className="w-5 h-5" />
              Back to Home
            </Link>
          </div>
        </div>
      </div>
    );
  }

  const { stock, quote, prediction, news = [] } = data;
  const currentPrice = quote?.current_price || 0;
  const change = quote?.change || 0;
  const changePct = quote?.change_percent || 0;
  const isPositive = change >= 0;

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 transition-colors duration-300">
      <div className="container mx-auto px-4 py-8">
        {/* Navigation Bar */}
        <NavigationBar 
          symbol={symbol} 
          isLive={isLive} 
          refreshing={refreshing} 
          onRefresh={handleRefresh} 
        />

        {/* Alert Banner - Shows when significant news is detected */}
        <AlertBanner newsSentiment={newsSentiment} autoRegenerating={autoRegenerating} />

        {/* Stock Header - Beautiful header with gradient and all price info */}
        <StockHeader 
          stock={stock} 
          quote={quote} 
          symbol={symbol} 
          currentPrice={currentPrice} 
          change={change} 
          changePct={changePct} 
          isPositive={isPositive} 
        />

        {/* Market Influences Section - Full Width */}
        <div className="mb-6">
          <h2 className="text-2xl font-black text-gray-900 mb-4 flex items-center gap-2">
            <Globe className="w-7 h-7 text-indigo-600" />
            Global Market Influences
          </h2>
          <div className="grid lg:grid-cols-2 gap-6">
            {/* European Markets Widget (50% weight) */}
            <EuropeanMarketWidget />
            
            {/* Asian Markets Widget (20% weight) */}
            <AsianMarketWidget />
          </div>
        </div>

        {/* Main Content Grid */}
        <div className="grid lg:grid-cols-3 gap-6">
          {/* Left Column: Prediction */}
          <div className="lg:col-span-2 space-y-6">
            {/* Enhanced V2 Prediction with Market Influences */}
            <PredictionCardV2 symbol={symbol} horizon="today" />
          </div>

          {/* Right Column: Quick Stats */}
          <div className="space-y-6">
            {/* News Sentiment Summary */}
            {newsSentiment && newsSentiment.analysisCount > 0 && (
              <div className="bg-white rounded-2xl shadow-xl p-6 transition-colors duration-300">
                <h3 className="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                  <BarChart3 className="w-6 h-6 text-indigo-600" />
                  News Sentiment
                </h3>
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <span className="text-gray-600">Overall:</span>
                    <span className={`px-3 py-1 rounded-full text-sm font-bold ${
                      newsSentiment.overallSentiment === 'bullish' ? 'bg-green-100 text-green-700' :
                      newsSentiment.overallSentiment === 'bearish' ? 'bg-red-100 text-red-700' :
                      'bg-gray-100 text-gray-700'
                    }`}>
                      {newsSentiment.overallSentiment.toUpperCase()}
                    </span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-gray-600">Confidence:</span>
                    <span className="font-bold text-gray-900">{newsSentiment.confidence}%</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-gray-600">Articles Analyzed:</span>
                    <span className="font-bold text-gray-900">{newsSentiment.analysisCount}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-gray-600">Score:</span>
                    <span className={`font-bold ${
                      newsSentiment.overallScore > 0 ? 'text-green-600' :
                      newsSentiment.overallScore < 0 ? 'text-red-600' :
                      'text-gray-600'
                    }`}>
                      {newsSentiment.overallScore > 0 ? '+' : ''}{newsSentiment.overallScore.toFixed(2)}
                    </span>
                  </div>
                </div>
              </div>
            )}

            {/* Auto-Regeneration Status */}
            <div className="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-2xl shadow-xl p-6 border-2 border-indigo-200 transition-colors duration-300">
              <h3 className="text-lg font-bold text-gray-900 mb-3 flex items-center gap-2">
                <Brain className="w-6 h-6 text-indigo-600 animate-pulse" />
                AI Monitoring
              </h3>
              <div className="space-y-2 text-sm">
                <div className="flex items-start gap-2">
                  <CheckCircle className="w-4 h-4 text-green-500 mt-0.5" />
                  <span className="text-gray-700">Real-time news monitoring</span>
                </div>
                <div className="flex items-start gap-2">
                  <CheckCircle className="w-4 h-4 text-green-500 mt-0.5" />
                  <span className="text-gray-700">Keyword detection active</span>
                </div>
                <div className="flex items-start gap-2">
                  <CheckCircle className="w-4 h-4 text-green-500 mt-0.5" />
                  <span className="text-gray-700">Auto-regeneration enabled</span>
                </div>
                <div className="flex items-start gap-2">
                  {autoRegenerating ? (
                    <>
                      <Zap className="w-4 h-4 text-yellow-500 animate-pulse mt-0.5" />
                      <span className="text-gray-700 font-semibold">Analyzing now...</span>
                    </>
                  ) : (
                    <>
                      <Radio className="w-4 h-4 text-blue-500 mt-0.5" />
                      <span className="text-gray-700">Waiting for signals</span>
                    </>
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* News Section - Full Width Below */}
        <div className="mt-6">
          <NewsGrid news={news} />
        </div>

        {/* How AI Works Section */}
        <HowItWorksSection />
      </div>
    </div>
  );
}

// Navigation Component
const NavigationBar = memo(({ symbol, isLive, refreshing, onRefresh }) => (
  <div className="flex items-center justify-between mb-6">
    <Link 
      to="/" 
      className="group inline-flex items-center gap-2 px-4 py-2 bg-white/80 backdrop-blur-sm rounded-xl shadow-md hover:shadow-lg text-indigo-600 hover:text-indigo-700 font-semibold transition-all hover:scale-105"
    >
      <ArrowLeft className="w-5 h-5 group-hover:-translate-x-1 transition-transform" />
      Back to Home
    </Link>
    <div className="flex items-center gap-3">
      {isLive && (
        <div className="flex items-center gap-2 px-4 py-2 bg-white/80 backdrop-blur-sm rounded-xl shadow-md">
          <span className="relative flex h-3 w-3">
            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
            <span className="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
          </span>
          <Radio className="w-4 h-4 text-green-600" />
          <span className="text-sm font-semibold text-green-700">Live Data</span>
        </div>
      )}
      <button 
        onClick={onRefresh} 
        disabled={refreshing} 
        className="group inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold rounded-xl hover:from-indigo-700 hover:to-purple-700 transition-all hover:scale-105 disabled:opacity-50 shadow-lg hover:shadow-xl"
      >
        <RefreshCw className={`w-5 h-5 ${refreshing ? 'animate-spin' : 'group-hover:rotate-180'} transition-transform duration-500`} />
        {refreshing ? 'Refreshing...' : 'Refresh'}
      </button>
      <Link
        to={`/stock/${symbol}/analytics`}
        className="group inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-purple-600 to-pink-600 text-white font-semibold rounded-xl hover:from-purple-700 hover:to-pink-700 transition-all hover:scale-105 shadow-lg hover:shadow-xl"
      >
        <BarChart3 className="w-5 h-5 group-hover:scale-110 transition-transform" />
        Analytics
      </Link>
    </div>
  </div>
));

// Stock Header Component - Matches Analytics style with gradient
const StockHeader = memo(({ stock, quote, symbol, currentPrice, change, changePct, isPositive }) => {
  if (!stock || !quote) return null;

  const open = Number(quote.open || 0).toFixed(2);
  const high = Number(quote.high || 0).toFixed(2);
  const low = Number(quote.low || 0).toFixed(2);
  const prevClose = Number(quote.previous_close || 0).toFixed(2);
  const volume = formatVolume(quote.volume || 0);
  const marketStatus = quote.market_status;

  return (
    <div className="group relative bg-white rounded-3xl shadow-2xl hover:shadow-3xl p-8 mb-6 overflow-hidden transition-all duration-500">
      {/* Gradient Background */}
      <div className="absolute inset-0 bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 opacity-70"></div>
      <div className="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
        <div className="absolute top-0 left-0 w-96 h-96 bg-indigo-200 rounded-full blur-3xl animate-pulse"></div>
        <div className="absolute bottom-0 right-0 w-96 h-96 bg-purple-200 rounded-full blur-3xl animate-pulse"></div>
      </div>
      
      <div className="relative z-10">
        <div className="flex items-start justify-between">
          <div className="flex items-center gap-6">
            <div className="transform hover:scale-110 hover:rotate-3 transition-all duration-300">
              <StockLogo symbol={stock?.symbol} logoUrl={stock?.logo_url} size="xl" />
            </div>
            <div>
              <div className="flex items-center gap-3 mb-2">
                <TrendingUpDown className="w-8 h-8 text-indigo-600" />
                <h1 className="text-5xl font-black bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 bg-clip-text text-transparent">
                  {symbol}
                </h1>
              </div>
              <p className="text-2xl text-gray-700 font-semibold mb-3">{stock?.name}</p>
              <div className="flex items-center gap-2 flex-wrap">
                <Badge icon={<Building2 className="w-3.5 h-3.5" />} text={stock?.exchange || 'N/A'} color="indigo" />
                {stock?.industry && (
                  <Badge icon={<Package className="w-3.5 h-3.5" />} text={stock.industry} color="purple" />
                )}
                <Badge icon={<DollarSign className="w-3.5 h-3.5" />} text={stock?.currency || 'USD'} color="pink" />
                <Badge 
                  icon={<Radio className="w-3.5 h-3.5" />} 
                  text={marketStatus === 'open' ? 'Market Open' : 'Market Closed'} 
                  color={marketStatus === 'open' ? 'green' : 'gray'} 
                  pulse={marketStatus === 'open'} 
                />
              </div>
            </div>
          </div>
          
          <div className="text-right">
            <div className="text-6xl font-black text-gray-900 mb-3 tracking-tight">
              ${currentPrice.toFixed(2)}
            </div>
            <div className={`inline-flex items-center gap-2 px-5 py-3 rounded-2xl text-xl font-bold shadow-lg backdrop-blur-md transition-all hover:scale-105 ${
              isPositive 
                ? 'bg-gradient-to-r from-green-500 to-emerald-500 text-white' 
                : 'bg-gradient-to-r from-red-500 to-rose-500 text-white'
            }`}>
              {isPositive ? <TrendingUp className="w-6 h-6" /> : <TrendingDown className="w-6 h-6" />}
              <span>{isPositive ? '+' : ''}{changePct.toFixed(2)}%</span>
            </div>
            <div className="mt-2 text-sm text-gray-600 font-medium">
              {isPositive ? '+' : ''}{change.toFixed(2)} today
            </div>
          </div>
        </div>

        {/* Price Stats Grid */}
        <div className="grid grid-cols-2 md:grid-cols-5 gap-4 mt-6">
          <StatCard icon={<TrendingUp className="w-5 h-5" />} label="Open" value={`$${open}`} color="blue" />
          <StatCard icon={<Activity className="w-5 h-5" />} label="High" value={`$${high}`} color="green" />
          <StatCard icon={<TrendingDown className="w-5 h-5" />} label="Low" value={`$${low}`} color="red" />
          <StatCard icon={<Clock className="w-5 h-5" />} label="Prev Close" value={`$${prevClose}`} color="gray" />
          <StatCard icon={<BarChart3 className="w-5 h-5" />} label="Volume" value={volume} color="purple" />
        </div>

        {/* Additional Info */}
        {(stock.market_cap || stock.website) && (
          <div className="mt-6 pt-6 border-t border-gray-200 flex flex-wrap items-center gap-4">
            {stock.market_cap && (
              <InfoBadge icon={<DollarSign />} label="Market Cap" value={`$${(stock.market_cap / 1000).toFixed(1)}B`} />
            )}
            {stock.shares_outstanding && (
              <InfoBadge icon={<Users />} label="Shares" value={`${stock.shares_outstanding.toLocaleString()}M`} />
            )}
            {stock.website && (
              <a
                href={stock.website}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center gap-2 px-4 py-2 bg-white/50 hover:bg-white rounded-xl backdrop-blur-sm border border-gray-200 hover:border-indigo-300 transition-all hover:scale-105 shadow-sm hover:shadow-md"
              >
                <Globe className="w-4 h-4 text-indigo-600" />
                <span className="text-sm font-semibold text-gray-700">Visit Website</span>
              </a>
            )}
          </div>
        )}
      </div>
    </div>
  );
});

// Badge Component
const Badge = ({ icon, text, color, pulse }) => (
  <div className={`flex items-center gap-1.5 px-3 py-1.5 bg-${color}-100 text-${color}-700 rounded-full backdrop-blur-sm shadow-sm hover:shadow-md transition-shadow ${pulse ? 'animate-pulse' : ''}`}>
    {icon}
    <span className="text-xs font-semibold">{text}</span>
  </div>
);

// Stat Card Component
const StatCard = ({ icon, label, value, color }) => (
  <div className="group bg-white/50 backdrop-blur-sm rounded-xl p-4 border border-gray-200 hover:border-indigo-300 hover:bg-white transition-all hover:scale-105 cursor-pointer shadow-sm hover:shadow-md">
    <div className="flex items-center gap-2 mb-2">
      <div className={`text-${color}-600 group-hover:scale-110 transition-transform`}>{icon}</div>
      <div className="text-xs font-semibold text-gray-600">{label}</div>
    </div>
    <div className="text-xl font-bold text-gray-900">{value}</div>
  </div>
);

// Info Badge Component
const InfoBadge = ({ icon, label, value }) => (
  <div className="flex items-center gap-2 px-4 py-2 bg-white/50 rounded-xl backdrop-blur-sm border border-gray-200 shadow-sm">
    <div className="text-gray-600">{icon}</div>
    <span className="text-sm text-gray-600">{label}:</span>
    <span className="text-sm font-bold text-gray-900">{value}</span>
  </div>
);

// How It Works Section
const HowItWorksSection = memo(() => (
  <div className="mt-8 bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 rounded-2xl shadow-xl p-8 border-2 border-indigo-200">
    <div className="flex items-center gap-3 mb-6">
      <Brain className="w-10 h-10 text-indigo-600 animate-pulse" />
      <h3 className="text-2xl font-bold text-gray-900">How Our AI Works</h3>
    </div>
    
    <div className="grid md:grid-cols-3 gap-6">
      <AIStepCard 
        icon={<Search className="w-8 h-8" />}
        title="1. Monitor News"
        color="indigo"
        description='Our AI continuously scans news sources in real-time, detecting keywords like "tariff", "earnings", "contract", and more.'
      />
      <AIStepCard 
        icon={<Activity className="w-8 h-8" />}
        title="2. Analyze Sentiment"
        color="purple"
        description="Advanced NLP algorithms analyze news sentiment, categorizing articles as bullish, bearish, or neutral with confidence scores."
      />
      <AIStepCard 
        icon={<Zap className="w-8 h-8" />}
        title="3. Auto-Regenerate"
        color="pink"
        description="When significant news is detected, predictions are automatically regenerated to reflect the latest market sentiment."
      />
    </div>

    <div className="mt-6 pt-6 border-t border-indigo-200">
      <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
        <FeatureBadge icon={<CheckCircle />} text="Real-time monitoring" />
        <FeatureBadge icon={<Target />} text="Keyword detection" />
        <FeatureBadge icon={<Brain />} text="Sentiment analysis" />
        <FeatureBadge icon={<Sparkles />} text="Auto regeneration" />
      </div>
    </div>
  </div>
));

// AI Step Card
const AIStepCard = ({ icon, title, color, description }) => (
  <div className="bg-white rounded-xl p-6 shadow-lg hover:shadow-2xl transition-all hover:scale-105 group">
    <div className="flex items-center gap-3 mb-4">
      <div className={`text-${color}-600 group-hover:scale-110 transition-transform`}>{icon}</div>
      <h4 className={`text-lg font-bold text-${color}-600`}>{title}</h4>
    </div>
    <p className="text-gray-700 text-sm leading-relaxed">{description}</p>
  </div>
);

// Feature Badge
const FeatureBadge = ({ icon, text }) => (
  <div className="flex items-center gap-2 text-sm">
    <div className="text-green-600">{icon}</div>
    <span className="text-gray-700"><strong>{text.split(' ')[0]}</strong> {text.split(' ').slice(1).join(' ')}</span>
  </div>
);
