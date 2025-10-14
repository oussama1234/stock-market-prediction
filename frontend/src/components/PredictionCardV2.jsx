import { memo, useMemo, useCallback, lazy, Suspense } from 'react';
import { useQuery } from '@tanstack/react-query';
import { 
  TrendingUp, TrendingDown, RefreshCw, AlertTriangle, Info, 
  Sparkles, Activity, Target, Brain, DollarSign, Newspaper,
  Globe, BarChart3, Zap, Rocket, Clock
} from 'lucide-react';
import { predictionAPI } from '../services/api';

// Lazy load components
const CorrectionWarningAlert = lazy(() => import('./CorrectionWarningAlert'));

/**
 * Enhanced PredictionCardV2 - Modern, animated, performant
 * 
 * Features:
 * - Stunning gradients and glassmorphism effects
 * - Smooth animations and hover effects
 * - Performance optimized with memoization
 * - Current/target price display with color indicators
 * - News sentiment integration
 * - Organized sub-components
 */
export default function PredictionCardV2Enhanced({ symbol, horizon = 'today' }) {
  const { data, isLoading, error, refetch, isFetching } = useQuery({
    queryKey: ['prediction-v2', symbol, horizon],
    queryFn: async () => await predictionAPI.predict(symbol, horizon),
    refetchInterval: horizon === 'today' ? 60 * 1000 : 5 * 60 * 1000,
    staleTime: horizon === 'today' ? 50 * 1000 : 4 * 60 * 1000,
  });

  const handleRefetch = useCallback(() => {
    refetch();
  }, [refetch]);

  if (isLoading) {
    return <PredictionCardSkeleton />;
  }

  if (error) {
    return <ErrorState error={error} onRetry={handleRefetch} isFetching={isFetching} />;
  }

  const prediction = data?.data || {};
  const meta = data?.meta || {};

  // Debug logging
  console.log('🔍 Prediction Data:', {
    current_price: prediction.current_price,
    expected_pct_move: prediction.expected_pct_move,
    label: prediction.label,
    probability: prediction.probability,
    top_reasons: prediction.top_reasons,
    fullData: prediction
  });
  
  // Debug Key Factors specifically
  if (prediction.top_reasons) {
    console.log('📊 Key Factors from Backend:', prediction.top_reasons);
  }

  return (
    <div className="space-y-6">
      {/* Correction Warning */}
      {prediction.correction_warning?.warning && (
        <Suspense fallback={<div className="h-24 bg-yellow-50 rounded-xl animate-pulse" />}>
          <CorrectionWarningAlert warning={prediction.correction_warning} />
        </Suspense>
      )}

      {/* Main Prediction Card */}
      <MainPredictionCard 
        prediction={prediction} 
        meta={meta} 
        onRefetch={handleRefetch}
        isFetching={isFetching}
      />
    </div>
  );
}

/**
 * Main Prediction Card - Memoized
 */
const MainPredictionCard = memo(({ prediction, meta, onRefetch, isFetching }) => {
  const isBullish = prediction.label === 'BULLISH';
  const expectedMove = prediction.expected_pct_move || 0;
  const probability = (prediction.probability || 0) * 100;

  // Calculate target price
  const currentPrice = prediction.current_price || 0;
  const targetPrice = currentPrice * (1 + expectedMove / 100);

  // Memoize colors and styles
  const theme = useMemo(() => ({
    gradient: isBullish
      ? 'from-emerald-500 via-green-500 to-teal-500'
      : 'from-rose-500 via-red-500 to-pink-500',
    bgGradient: isBullish
      ? 'from-emerald-50 via-green-50 to-teal-50'
      : 'from-rose-50 via-red-50 to-pink-50',
    textColor: isBullish ? 'text-green-600' : 'text-red-600',
    borderColor: isBullish ? 'border-green-200' : 'border-red-200',
    lightBg: isBullish ? 'bg-green-50' : 'bg-red-50',
    IconComponent: isBullish ? TrendingUp : TrendingDown,
  }), [isBullish]);

  return (
    <div className="relative group">
      {/* Animated Gradient Glow */}
      <div 
        className={`absolute -inset-1 bg-gradient-to-r ${theme.gradient} rounded-3xl opacity-20 blur-xl group-hover:opacity-30 transition-all duration-500 animate-pulse`}
      />
      
      {/* Card Container with Glassmorphism */}
      <div className="relative bg-white/90 backdrop-blur-xl rounded-3xl shadow-2xl border border-gray-200/50 overflow-hidden">
        {/* Animated Background Pattern */}
        <div className="absolute inset-0 opacity-5">
          <div className={`absolute inset-0 bg-gradient-to-br ${theme.bgGradient}`} />
          <div className="absolute inset-0" style={{
            backgroundImage: `repeating-linear-gradient(45deg, transparent, transparent 35px, rgba(0,0,0,.03) 35px, rgba(0,0,0,.03) 70px)`
          }} />
        </div>

        <div className="relative p-8">
          {/* Header Section */}
          <HeaderSection 
            modelVersion={prediction.model_version}
            onRefetch={onRefetch}
            isFetching={isFetching}
          />

          {/* Main Prediction Display */}
          <MainPredictionDisplay
            isBullish={isBullish}
            label={prediction.label}
            expectedMove={expectedMove}
            probability={probability}
            theme={theme}
            timestamp={meta.timestamp}
            currentPrice={currentPrice}
            dbChange={prediction.db_change}
            dbChangePercent={prediction.db_change_percent}
            dbPreviousClose={prediction.previous_close || prediction.db_previous_close}
            dbLastCheckDate={prediction.db_last_check_date}
          />


          {/* Market Influences - European, Asian, Local */}
          {(prediction.european_influence_score !== undefined || 
            prediction.asian_influence_score !== undefined) && (
            <MarketInfluencesSection
              europeanScore={prediction.european_influence_score}
              europeanImpact={prediction.european_impact_percent}
              europeanContribution={prediction.european_contribution}
              asianScore={prediction.asian_influence_score}
              asianImpact={prediction.asian_impact_percent}
              asianContribution={prediction.asian_contribution}
              localScore={prediction.local_score}
              localImpact={prediction.local_impact_percent}
              localContribution={prediction.local_contribution}
              europeanMarkets={prediction.european_markets}
              asianMarkets={prediction.asian_markets}
            />
          )}

          {/* Key Factors Grid */}
          <KeyFactorsSection 
            reasons={prediction.top_reasons}
            theme={theme}
          />

          {/* Technical Details (Collapsible) */}
          <TechnicalDetailsSection 
            baseScore={prediction.base_score}
            finalScore={prediction.final_score}
          />
        </div>
      </div>
    </div>
  );
});

MainPredictionCard.displayName = 'MainPredictionCard';

/**
 * Header Section - Memoized
 */
const HeaderSection = memo(({ modelVersion, onRefetch, isFetching }) => (
  <div className="flex items-center justify-between mb-8">
    <div className="flex items-center gap-3">
      <div className="relative">
        <Sparkles className="w-8 h-8 text-indigo-600 animate-pulse" />
        <div className="absolute inset-0 blur-lg bg-indigo-400 animate-ping" />
      </div>
      <div>
        <h2 className="text-3xl font-black bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
          AI Prediction
        </h2>
        {modelVersion && (
          <span className="text-xs px-3 py-1 bg-gradient-to-r from-indigo-500 to-purple-500 text-white rounded-full font-semibold shadow-lg">
            {modelVersion}
          </span>
        )}
      </div>
    </div>
    <button
      onClick={onRefetch}
      disabled={isFetching}
      className="group p-3 hover:bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl transition-all duration-300 hover:scale-110 active:scale-95"
      title="Refresh prediction"
    >
      <RefreshCw className={`w-6 h-6 text-indigo-600 group-hover:text-purple-600 transition-colors ${isFetching ? 'animate-spin' : ''}`} />
    </button>
  </div>
));

HeaderSection.displayName = 'HeaderSection';

/**
 * Main Prediction Display - Memoized
 * Compact card with left border and price info on the right
 */
const MainPredictionDisplay = memo(({ isBullish, label, expectedMove, probability, theme, timestamp, currentPrice, dbChange, dbChangePercent, dbPreviousClose, dbLastCheckDate }) => {
  // Ensure values are valid numbers
  const validCurrentPrice = currentPrice && !isNaN(currentPrice) ? Number(currentPrice) : 0;
  const validExpectedMove = expectedMove && !isNaN(expectedMove) ? Number(expectedMove) : 0;
  const validPrevClose = dbPreviousClose && !isNaN(dbPreviousClose) ? Number(dbPreviousClose) : validCurrentPrice;
  
  // CRITICAL: Calculate target price from PREVIOUS CLOSE, not current price
  // The prediction is: Previous Close -> Target Price (expected move from previous close)
  // This way the prediction is consistent: "From yesterday's close of $X, we expect it to reach $Y (Z% move)"
  const targetPrice = validPrevClose > 0 ? validPrevClose * (1 + validExpectedMove / 100) : 0;
  
  // Calculate the actual expected change: from Previous Close to Target
  const expectedDollarChange = targetPrice - validPrevClose;
  
  // Calculate current change from previous close (for display)
  const currentChange = validCurrentPrice - validPrevClose;
  const currentChangePercent = validPrevClose > 0 ? ((currentChange / validPrevClose) * 100) : 0;
  
  // Check if we have database-based change values
  const hasDbChange = dbChange !== undefined && dbChangePercent !== undefined;

  return (
    <div className="mb-8">
      {/* Main Prediction Card with subtle tint */}
      <div className={`relative rounded-2xl border-l-8 ${theme.borderColor} shadow-xl overflow-hidden`}>
        {/* Subtle background tint */}
        <div className={`absolute inset-0 ${theme.bgGradient} opacity-30`} />
        
        <div className="relative bg-white p-6">
          <div className="flex items-center justify-between gap-6">
            {/* Left: Prediction Label */}
            <div className="flex items-center gap-4">
              <div className={`flex items-center justify-center w-16 h-16 rounded-2xl ${theme.lightBg} shadow-lg`}>
                <theme.IconComponent className={`w-8 h-8 ${theme.textColor}`} strokeWidth={2.5} />
              </div>
              <div>
                <div className={`text-3xl font-black ${theme.textColor} mb-1`}>
                  {label}
                </div>
                <div className="flex items-center gap-2 text-sm text-gray-600">
                  <Target className="w-4 h-4" />
                  <span>Confidence: <span className="font-bold">{probability.toFixed(1)}%</span></span>
                </div>
              </div>
            </div>

            {/* Right: Price Information */}
            <div className="grid grid-cols-3 gap-4 flex-1 max-w-xl">
              {/* Previous Close */}
              <div className="text-center p-4 bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl border-2 border-gray-300">
                <div className="text-xs text-gray-600 font-semibold mb-1">Prev Close</div>
                <div className="text-2xl font-black text-gray-700">
                  ${validPrevClose > 0 ? validPrevClose.toFixed(2) : '0.00'}
                </div>
              </div>

              {/* Current Price */}
              <div className="text-center p-4 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl border-2 border-blue-200">
                <div className="text-xs text-gray-600 font-semibold mb-1">Current</div>
                <div className="text-2xl font-black text-blue-600">
                  ${validCurrentPrice > 0 ? validCurrentPrice.toFixed(2) : '0.00'}
                </div>
                {hasDbChange && currentChangePercent !== 0 && (
                  <div className={`text-xs font-bold mt-1 ${
                    currentChangePercent > 0 ? 'text-green-600' : 'text-red-600'
                  }`}>
                    {currentChangePercent > 0 ? '+' : ''}{currentChangePercent.toFixed(2)}%
                  </div>
                )}
              </div>

              {/* Target Price */}
              <div className={`text-center p-4 bg-gradient-to-br ${theme.bgGradient} rounded-xl border-2 ${theme.borderColor}`}>
                <div className="text-xs text-gray-600 font-semibold mb-1">Target</div>
                <div className={`text-2xl font-black ${theme.textColor}`}>
                  ${targetPrice > 0 ? targetPrice.toFixed(2) : '0.00'}
                </div>
                <div className={`text-xs font-bold mt-1 ${theme.textColor}`}>
                  {validExpectedMove > 0 ? '+' : ''}{validExpectedMove.toFixed(2)}%
                </div>
              </div>
            </div>
          </div>

          {/* Timestamp and Current Price Change */}
          {timestamp && (
            <div className="mt-4 pt-4 border-t border-gray-200 text-xs">
              {/* Timestamp row */}
              <div className="flex items-center justify-between text-gray-500 mb-2">
                <div className="flex items-center gap-1">
                  <Activity className="w-3 h-3" />
                  <span>Updated: {new Date(timestamp).toLocaleTimeString()}</span>
                </div>
                <div className="flex items-center gap-2">
                  <div className={`w-2 h-2 rounded-full ${isBullish ? 'bg-green-500' : 'bg-red-500'} animate-pulse`} />
                  <span className="font-semibold">Expected: {expectedDollarChange > 0 ? '+' : ''}${Math.abs(expectedDollarChange).toFixed(2)} ({validExpectedMove > 0 ? '+' : ''}{validExpectedMove.toFixed(2)}%)</span>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Single Confidence Progress Bar */}
      <div className="mt-4">
        <div className="relative h-3 bg-gradient-to-r from-gray-200 to-gray-300 rounded-full overflow-hidden shadow-inner">
          <div
            className={`h-full bg-gradient-to-r ${theme.gradient} transition-all duration-1000 ease-out relative overflow-hidden`}
            style={{ width: `${probability}%` }}
          >
            <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-30 animate-shimmer" />
          </div>
        </div>
        <div className="mt-1 text-xs text-gray-500 text-right">Confidence: {probability.toFixed(1)}%</div>
      </div>
    </div>
  );
});

MainPredictionDisplay.displayName = 'MainPredictionDisplay';



/**
 * Important News Surge Alert - Memoized
 */
const ImportantNewsSurgeAlert = memo(({ surgePct }) => (
  <div className="relative overflow-hidden rounded-2xl shadow-2xl">
    {/* Animated Background */}
    <div className="absolute inset-0 bg-gradient-to-r from-yellow-400 via-amber-500 to-orange-500 animate-pulse" />
    <div className="absolute inset-0 opacity-30" style={{
      backgroundImage: `repeating-linear-gradient(45deg, transparent, transparent 35px, rgba(255,255,255,.2) 35px, rgba(255,255,255,.2) 70px)`
    }} />
    
    <div className="relative p-6">
      <div className="flex items-center gap-4">
        {/* Rocket Icon */}
        <div className="flex-shrink-0">
          <div className="relative">
            <div className="w-16 h-16 bg-white rounded-2xl flex items-center justify-center shadow-xl">
              <Rocket className="w-10 h-10 text-orange-500 animate-bounce" />
            </div>
            <div className="absolute inset-0 bg-yellow-300 rounded-2xl blur-xl animate-ping" />
          </div>
        </div>
        
        {/* Content */}
        <div className="flex-1">
          <div className="flex items-center gap-2 mb-2">
            <Zap className="w-6 h-6 text-white animate-pulse" />
            <h3 className="text-2xl font-black text-white uppercase tracking-wide">
              Important News Surge Detected!
            </h3>
          </div>
          <p className="text-white/90 font-semibold text-lg">
            Expected surge: <span className="text-white font-black text-2xl">+{surgePct.toFixed(1)}%</span>
          </p>
          <div className="flex items-center gap-1 text-white/80 text-sm mt-1 font-medium">
            <Clock className="w-4 h-4" />
            <span>This surge expectation is for TODAY ONLY based on breaking news</span>
          </div>
        </div>
        
        {/* Badge */}
        <div className="flex-shrink-0">
          <div className="bg-white/20 backdrop-blur-sm px-4 py-2 rounded-xl border-2 border-white/40">
            <div className="text-xs text-white/90 font-semibold">MEGA CAP</div>
            <div className="text-2xl font-black text-white">+{surgePct.toFixed(0)}%</div>
          </div>
        </div>
      </div>
    </div>
  </div>
));

ImportantNewsSurgeAlert.displayName = 'ImportantNewsSurgeAlert';

/**
 * Key Factors Section - Memoized
 */
const KeyFactorsSection = memo(({ reasons, theme }) => {
  if (!reasons || reasons.length === 0) return null;

  const icons = [Brain, Activity, Sparkles, Target, TrendingUp, TrendingDown];

  // Function to format reason text - replace placeholders with proper values
  const formatReason = (reason) => {
    if (!reason) return reason;
    
    // Replace common placeholder patterns
    let formatted = reason
      .replace(/\b0 rsi\b/gi, 'low RSI')
      .replace(/\b0\s+(?=rsi|macd|volume|momentum)/gi, 'low ')
      .replace(/oversold with 0/gi, 'oversold condition')
      .replace(/overbought with 0/gi, 'overbought condition')
      .replace(/\s+0(?=\s*$)/g, ''); // Remove trailing 0s
    
    return formatted;
  };

  return (
    <div className="mb-8">
      <h3 className="text-xl font-black text-gray-900 mb-4 flex items-center gap-2">
        <Brain className="w-6 h-6 text-indigo-600" />
        Key Factors
      </h3>
      <div className="grid gap-3">
        {reasons.map((reason, index) => {
          const Icon = icons[index % icons.length];
          const formattedReason = formatReason(reason);
          
          return (
            <div 
              key={index} 
              className="group flex items-start gap-4 p-4 bg-gradient-to-r from-white to-gray-50 rounded-xl border-2 border-gray-200 hover:border-indigo-300 hover:shadow-lg transform transition-all duration-300"
            >
              <div className={`flex-shrink-0 w-10 h-10 rounded-xl ${theme.lightBg} flex items-center justify-center transform group-hover:scale-110 group-hover:rotate-12 transition-all duration-300`}>
                <Icon className={`w-5 h-5 ${theme.textColor}`} />
              </div>
              <div className="flex-1 flex items-center justify-between gap-3">
                <span className="text-sm text-gray-700 font-medium leading-relaxed">{formattedReason}</span>
                <span className={`flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-xs font-black ${theme.lightBg} ${theme.textColor}`}>
                  {index + 1}
                </span>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
});

KeyFactorsSection.displayName = 'KeyFactorsSection';

/**
 * News Sentiment Section - NEW
 */
const NewsSentimentSection = memo(({ score, theme }) => {
  const sentimentLabel = score > 3 ? 'Very Positive' : score > 0 ? 'Positive' : score < -3 ? 'Very Negative' : score < 0 ? 'Negative' : 'Neutral';
  const sentimentColor = score > 0 ? 'from-green-500 to-emerald-500' : score < 0 ? 'from-red-500 to-rose-500' : 'from-gray-400 to-gray-500';

  return (
    <div className="mb-8 p-6 bg-gradient-to-br from-purple-50 via-pink-50 to-orange-50 rounded-2xl border-2 border-purple-200">
      <div className="flex items-center gap-2 mb-4">
        <Newspaper className="w-6 h-6 text-purple-600" />
        <h3 className="text-lg font-black text-gray-900">News Sentiment</h3>
      </div>
      
      <div className="flex items-center justify-between">
        <div>
          <div className="text-3xl font-black text-purple-600 mb-1">
            {score > 0 ? '+' : ''}{score.toFixed(1)}
          </div>
          <div className={`inline-block px-3 py-1 rounded-full text-sm font-bold text-white bg-gradient-to-r ${sentimentColor}`}>
            {sentimentLabel}
          </div>
        </div>
        <div className="relative w-24 h-24">
          <svg className="transform -rotate-90 w-24 h-24">
            <circle
              cx="48"
              cy="48"
              r="40"
              stroke="currentColor"
              strokeWidth="8"
              fill="transparent"
              className="text-gray-200"
            />
            <circle
              cx="48"
              cy="48"
              r="40"
              stroke="url(#sentiment-gradient)"
              strokeWidth="8"
              fill="transparent"
              strokeDasharray={`${(Math.abs(score) / 10) * 251} 251`}
              strokeLinecap="round"
              className="transition-all duration-1000"
            />
          </svg>
          <defs>
            <linearGradient id="sentiment-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stopColor={score > 0 ? '#10b981' : '#ef4444'} />
              <stop offset="100%" stopColor={score > 0 ? '#059669' : '#dc2626'} />
            </linearGradient>
          </defs>
        </div>
      </div>
    </div>
  );
});

NewsSentimentSection.displayName = 'NewsSentimentSection';

/**
 * Market Influences Section - Shows all three markets with weights
 */
const MarketInfluencesSection = memo(({ 
  europeanScore, europeanImpact, europeanContribution,
  asianScore, asianImpact, asianContribution,
  localScore, localImpact, localContribution,
  europeanMarkets, asianMarkets 
}) => {
  const markets = [
    {
      name: 'European Markets',
      icon: Globe,
      score: europeanScore || 0,
      impact: (europeanImpact || 0) * 100,
      contribution: europeanContribution || 0,
      gradient: 'from-blue-500 via-indigo-500 to-violet-500',
      bgGradient: 'from-blue-50 via-indigo-50 to-violet-50',
      textColor: (europeanScore || 0) > 0 ? 'text-green-600' : 'text-red-600',
      markets: europeanMarkets,
    },
    {
      name: 'Asian Markets',
      icon: Globe,
      score: asianScore || 0,
      impact: (asianImpact || 0) * 100,
      contribution: asianContribution || 0,
      gradient: 'from-indigo-500 via-purple-500 to-pink-500',
      bgGradient: 'from-indigo-50 via-purple-50 to-pink-50',
      textColor: (asianScore || 0) > 0 ? 'text-green-600' : 'text-red-600',
      markets: asianMarkets,
    },
    {
      name: 'Local US Factors',
      icon: BarChart3,
      score: localScore || 0,
      impact: (localImpact || 0) * 100,
      contribution: localContribution || 0,
      gradient: 'from-purple-500 via-pink-500 to-rose-500',
      bgGradient: 'from-purple-50 via-pink-50 to-rose-50',
      textColor: (localScore || 0) > 0 ? 'text-green-600' : 'text-red-600',
      markets: null,
    },
  ];

  return (
    <div className="mb-8">
      <h3 className="text-xl font-black text-gray-900 mb-4 flex items-center gap-2">
        <Zap className="w-6 h-6 text-indigo-600" />
        Market Influences
      </h3>
      
      <div className="grid gap-4">
        {markets.map((market, index) => {
          const MarketIcon = market.icon;
          const isBullish = market.score > 0;
          const isSignificant = Math.abs(market.score) > 0.1;
          
          return (
            <div 
              key={index}
              className="group relative p-5 bg-white rounded-2xl border-2 border-gray-200 hover:border-indigo-300 hover:shadow-lg transform transition-all duration-300"
            >
              {/* Background gradient */}
              <div className={`absolute inset-0 bg-gradient-to-br ${market.bgGradient} opacity-10 rounded-2xl`} />
              
              <div className="relative">
                <div className="flex items-center justify-between mb-4">
                  <div className="flex items-center gap-3">
                    <div className={`p-3 bg-gradient-to-r ${market.gradient} rounded-xl shadow-lg transform group-hover:scale-110 transition-transform`}>
                      <MarketIcon className="w-5 h-5 text-white" />
                    </div>
                    <div>
                      <h4 className="text-lg font-black text-gray-900">{market.name}</h4>
                      <div className="flex items-center gap-2 mt-1">
                        <span className={`px-2 py-0.5 rounded-full text-xs font-bold bg-gradient-to-r ${market.gradient} text-white shadow`}>
                          {market.impact.toFixed(0)}% Weight
                        </span>
                        {isSignificant && (
                          <span className={`px-2 py-0.5 rounded-full text-xs font-bold ${
                            isBullish ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                          }`}>
                            {isBullish ? 'Bullish' : 'Bearish'}
                          </span>
                        )}
                      </div>
                    </div>
                  </div>
                  
                  <div className="text-right">
                    <div className="text-sm text-gray-600 font-semibold mb-1">Influence Score</div>
                    <div className={`text-3xl font-black ${market.textColor}`}>
                      {market.score > 0 ? '+' : ''}{market.score.toFixed(2)}
                    </div>
                  </div>
                </div>
                
                {/* Progress bar */}
                <div className="relative h-2 bg-gray-200 rounded-full overflow-hidden mb-3">
                  <div
                    className={`h-full bg-gradient-to-r ${market.gradient} transition-all duration-1000`}
                    style={{ width: `${market.impact}%` }}
                  />
                </div>
                
                <div className="flex items-center justify-between text-xs text-gray-600">
                  <span className="font-semibold">Contribution to prediction: <span className={market.textColor}>{market.contribution > 0 ? '+' : ''}{market.contribution.toFixed(3)}</span></span>
                  <span className="font-bold">{market.impact.toFixed(0)}% of total weight</span>
                </div>
              </div>
            </div>
          );
        })}
      </div>
      
    </div>
  );
});

MarketInfluencesSection.displayName = 'MarketInfluencesSection';

/**
 * Asian Influence Section - Memoized (Legacy - kept for backward compatibility)
 */
const AsianInfluenceSection = memo(({ score, impactPercent, markets }) => {
  const isBullish = score > 0;
  const isSignificant = Math.abs(score) > 0.3;

  if (!isSignificant) return null;

  return (
    <div className="mb-8 p-6 bg-gradient-to-br from-indigo-50 via-blue-50 to-cyan-50 rounded-2xl border-2 border-indigo-200 transform hover:scale-[1.02] transition-all duration-300">
      <div className="flex items-center gap-2 mb-4">
        <Globe className="w-7 h-7 text-indigo-600" />
        <h3 className="text-lg font-black text-gray-900">Asian Market Influence</h3>
      </div>

      <div className="grid grid-cols-2 gap-4 mb-4">
        <div className="text-center p-4 bg-white rounded-xl shadow-md">
          <div className="text-xs text-gray-600 mb-1 font-semibold">Influence Score</div>
          <div className={`text-3xl font-black ${isBullish ? 'text-green-600' : 'text-red-600'}`}>
            {score > 0 ? '+' : ''}{score.toFixed(2)}
          </div>
        </div>
        <div className="text-center p-4 bg-white rounded-xl shadow-md">
          <div className="text-xs text-gray-600 mb-1 font-semibold">Impact Weight</div>
          <div className="text-3xl font-black text-indigo-600">
            {(impactPercent * 100).toFixed(0)}%
          </div>
        </div>
      </div>

      {markets && Object.keys(markets).length > 0 && (
        <div className="grid grid-cols-2 gap-2">
          {Object.entries(markets).slice(0, 4).map(([key, market]) => (
            <div key={key} className="flex items-center justify-between p-3 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow">
              <span className="text-xs font-semibold text-gray-700 truncate">{market.name}</span>
              <span className={`font-bold text-sm ${
                market.change_percent > 0 ? 'text-green-600' : 'text-red-600'
              }`}>
                {market.change_percent > 0 ? '+' : ''}{market.change_percent?.toFixed(1)}%
              </span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
});

AsianInfluenceSection.displayName = 'AsianInfluenceSection';

/**
 * Technical Details Section - Memoized
 */
const TechnicalDetailsSection = memo(({ baseScore, finalScore }) => {
  if (baseScore === undefined && finalScore === undefined) return null;

  return (
    <details className="mt-6">
      <summary className="text-xs text-gray-500 cursor-pointer hover:text-gray-700 font-semibold flex items-center gap-2">
        <Info className="w-4 h-4" />
        Show technical details
      </summary>
      <div className="mt-3 grid grid-cols-2 gap-3">
        {baseScore !== undefined && (
          <div className="p-3 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl border border-blue-200">
            <div className="text-xs text-gray-600 font-semibold">Base Score</div>
            <div className="text-xl font-black text-blue-600">{baseScore.toFixed(3)}</div>
          </div>
        )}
        {finalScore !== undefined && (
          <div className="p-3 bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl border border-purple-200">
            <div className="text-xs text-gray-600 font-semibold">Final Score</div>
            <div className="text-xl font-black text-purple-600">{finalScore.toFixed(3)}</div>
          </div>
        )}
      </div>
    </details>
  );
});

TechnicalDetailsSection.displayName = 'TechnicalDetailsSection';

/**
 * Error State Component - Memoized
 */
const ErrorState = memo(({ error, onRetry, isFetching }) => (
  <div className="bg-gradient-to-br from-red-50 to-pink-50 rounded-3xl shadow-2xl p-8 border-2 border-red-200">
    <div className="text-center mb-6">
      <div className="inline-flex items-center justify-center w-20 h-20 bg-red-100 rounded-full mb-4 animate-bounce">
        <AlertTriangle className="w-10 h-10 text-red-600" />
      </div>
      <h3 className="text-2xl font-black text-gray-900 mb-2">Prediction Unavailable</h3>
      <p className="text-gray-600 mb-1">Unable to load AI prediction</p>
      <p className="text-sm text-red-600 font-semibold mt-2">{error.message || 'Server Error'}</p>
    </div>
    
    <div className="bg-white rounded-2xl p-6 mb-6 shadow-lg">
      <h4 className="text-sm font-black text-gray-900 mb-3 flex items-center gap-2">
        <Info className="w-5 h-5 text-gray-600" />
        Possible Causes:
      </h4>
      <ul className="text-sm text-gray-700 space-y-2">
        <li className="flex items-start gap-2">
          <span className="text-red-500">•</span>
          <span>Python prediction service may not be running</span>
        </li>
        <li className="flex items-start gap-2">
          <span className="text-red-500">•</span>
          <span>Stock data is being refreshed</span>
        </li>
        <li className="flex items-start gap-2">
          <span className="text-red-500">•</span>
          <span>Temporary network connectivity issue</span>
        </li>
      </ul>
    </div>

    <div className="flex gap-3">
      <button
        onClick={onRetry}
        disabled={isFetching}
        className="flex-1 px-6 py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl hover:from-indigo-700 hover:to-purple-700 transition-all disabled:opacity-50 font-bold flex items-center justify-center gap-2 shadow-lg transform hover:scale-105"
      >
        <RefreshCw className={`w-5 h-5 ${isFetching ? 'animate-spin' : ''}`} />
        {isFetching ? 'Retrying...' : 'Try Again'}
      </button>
      <button
        onClick={() => window.location.reload()}
        className="px-6 py-4 bg-gray-200 text-gray-800 rounded-xl hover:bg-gray-300 transition-all font-bold shadow-lg transform hover:scale-105"
      >
        Reload Page
      </button>
    </div>
  </div>
));

ErrorState.displayName = 'ErrorState';

/**
 * Loading Skeleton - Memoized
 */
const PredictionCardSkeleton = memo(() => (
  <div className="bg-gradient-to-br from-gray-50 to-gray-100 rounded-3xl shadow-2xl p-8 border border-gray-200">
    <div className="animate-pulse space-y-6">
      <div className="h-10 bg-gradient-to-r from-gray-200 to-gray-300 rounded-xl w-1/2" />
      <div className="h-24 bg-gradient-to-r from-gray-200 to-gray-300 rounded-2xl w-3/4 mx-auto" />
      <div className="h-16 bg-gradient-to-r from-gray-200 to-gray-300 rounded-xl w-1/2 mx-auto" />
      <div className="h-4 bg-gradient-to-r from-gray-200 to-gray-300 rounded-full" />
      <div className="space-y-3">
        {[1, 2, 3].map((i) => (
          <div key={i} className="h-16 bg-gradient-to-r from-gray-200 to-gray-300 rounded-xl" />
        ))}
      </div>
    </div>
  </div>
));

PredictionCardSkeleton.displayName = 'PredictionCardSkeleton';

// Add shimmer animation to index.css
const shimmerKeyframes = `
@keyframes shimmer {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}
.animate-shimmer {
  animation: shimmer 2s infinite;
}
`;
