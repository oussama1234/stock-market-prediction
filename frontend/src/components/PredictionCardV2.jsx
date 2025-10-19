import { memo, useMemo, useCallback, lazy, Suspense, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { 
  TrendingUp, TrendingDown, RefreshCw, AlertTriangle, AlertCircle, Info, 
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
queryFn: async () => await predictionAPI.predict(symbol, horizon, 'v6'),
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

  const apiData = data?.data || {};
  // Check if v6 by model_version OR by structure (v6 has label/probability at top level)
  const isV6 = apiData.model_version === 'quick_model_v6';
  const meta = data?.meta || {};

  // Handle v6 vs v4 structure differences
  // v6 returns: { label, probability, expected_pct_move, scores, weights, ... }
  // v4 returns: { prediction: { direction, confidence, target_change_percent }, ... }
  const isV6Structure = apiData.model_version === 'quick_model_v6' || (!!apiData.label && !!apiData.scores && !!apiData.weights);
  
  const prediction = {
    // v6 top-level fields OR v4 nested prediction fields
    label: isV6Structure 
      ? apiData.label 
      : (apiData.prediction?.direction === 'up' ? 'BULLISH' : apiData.prediction?.direction === 'down' ? 'BEARISH' : 'NEUTRAL'),
    probability: isV6Structure
      ? (apiData.probability || 0.5)
      : (apiData.prediction?.confidence || 0.5),
    expected_pct_move: isV6Structure
      ? (apiData.expected_pct_move || 0)
      : (apiData.prediction?.target_change_percent || 0),
    current_price: apiData.current_price || apiData.prediction?.current_price,
    model_version: apiData.model_version || 'quick_model_v4',
    // Market influence data
    // weight = configured weight (50, 30, 20)
    // impact_percent = actual measured impact (0.12 = 12%)
    european_influence_score: apiData.market_influences?.european?.impact_percent || 0,
    european_impact_percent: (apiData.market_influences?.european?.weight || 30) / 100, // Convert 30 to 0.3
    european_contribution: apiData.market_influences?.european?.impact_percent || 0,
    asian_influence_score: apiData.market_influences?.asian?.impact_percent || 0,
    asian_impact_percent: (apiData.market_influences?.asian?.weight || 20) / 100, // Convert 20 to 0.2
    asian_contribution: apiData.market_influences?.asian?.impact_percent || 0,
    local_score: apiData.market_influences?.local?.impact_percent || 0,
    local_impact_percent: (apiData.market_influences?.local?.weight || 50) / 100, // Convert 50 to 0.5
    local_contribution: apiData.market_influences?.local?.impact_percent || 0,
    // Market data
    european_markets: apiData.european_markets,
    asian_markets: apiData.asian_markets,
    // Other fields
    top_reasons: apiData.top_reasons || prediction.top_reasons || apiData.signals || [],
    base_score: apiData.scores?.composite,
    final_score: apiData.scores?.composite,
    db_previous_close: apiData.db_previous_close || apiData.api_previous_close,
    db_change: apiData.db_change || apiData.api_change,
    db_change_percent: apiData.db_change_percent || apiData.api_change_percent,
    db_last_check_date: apiData.db_last_check_date,
  };

  // Debug logging (only when data first loads)
  if (data && !window._debugLogged) {
    window._debugLogged = true;
    console.log('🔍 Fundamentals Score:', apiData.scores?.fundamentals);
    console.log('🔍 Fundamentals Data:', apiData.factors?.fundamentals);
    setTimeout(() => { window._debugLogged = false; }, 1000);
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
        apiData={apiData}
        isV6={isV6}
        onRefetch={handleRefetch}
        isFetching={isFetching}
      />
    </div>
  );
}

/**
 * Main Prediction Card - Memoized
 */
const MainPredictionCard = memo(({ prediction, meta, apiData, isV6, onRefetch, isFetching }) => {
  const label = prediction.label || 'NEUTRAL';
  const isBullish = label === 'BULLISH';
  const isBearish = label === 'BEARISH';
  const isNeutral = label === 'NEUTRAL';
  const expectedMove = prediction.expected_pct_move || 0;
  const probability = (prediction.probability || 0) * 100;

  // Calculate target price
  const currentPrice = prediction.current_price || 0;
  const targetPrice = currentPrice * (1 + expectedMove / 100);

  // Memoize colors and styles based on label
  const theme = useMemo(() => {
    if (isBullish) {
      return {
        gradient: 'from-emerald-500 via-green-500 to-teal-500',
        bgGradient: 'from-emerald-50 via-green-50 to-teal-50',
        textColor: 'text-green-600',
        borderColor: 'border-green-200',
        lightBg: 'bg-green-50',
        IconComponent: TrendingUp,
      };
    } else if (isBearish) {
      return {
        gradient: 'from-rose-500 via-red-500 to-pink-500',
        bgGradient: 'from-rose-50 via-red-50 to-pink-50',
        textColor: 'text-red-600',
        borderColor: 'border-red-200',
        lightBg: 'bg-red-50',
        IconComponent: TrendingDown,
      };
    } else {
      // NEUTRAL - color by expected move direction (green if +, red if -, blue if flat)
      return {
        gradient: expectedMove > 0
          ? 'from-emerald-500 via-green-500 to-teal-500'
          : expectedMove < 0
          ? 'from-rose-500 via-red-500 to-pink-500'
          : 'from-blue-500 via-indigo-500 to-purple-500',
        bgGradient: expectedMove > 0
          ? 'from-emerald-50 via-green-50 to-teal-50'
          : expectedMove < 0
          ? 'from-rose-50 via-red-50 to-pink-50'
          : 'from-blue-50 via-indigo-50 to-purple-50',
        textColor: expectedMove > 0 ? 'text-green-600' : expectedMove < 0 ? 'text-red-600' : 'text-blue-600',
        borderColor: expectedMove !== 0 ? (expectedMove > 0 ? 'border-green-200' : 'border-red-200') : 'border-blue-200',
        lightBg: expectedMove !== 0 ? (expectedMove > 0 ? 'bg-green-50' : 'bg-red-50') : 'bg-blue-50',
        IconComponent: expectedMove > 0 ? TrendingUp : expectedMove < 0 ? TrendingDown : Activity,
      };
    }
  }, [isBullish, isBearish, isNeutral, expectedMove]);

  return (
    <div className="relative group">
      {/* Animated Gradient Glow */}
      <div 
        className={`absolute -inset-1 bg-gradient-to-r ${theme.gradient} rounded-3xl opacity-20 blur-xl group-hover:opacity-30 transition-all duration-500 animate-pulse`}
      />
      
      {/* Card Container with Glassmorphism */}
      <div className="relative bg-white/90 dark:bg-gray-800/90 backdrop-blur-xl rounded-3xl shadow-2xl border border-gray-200/50 dark:border-gray-600/50 overflow-hidden">
        {/* Animated Background Pattern */}
        <div className="absolute inset-0 opacity-5">
          <div className={`absolute inset-0 bg-gradient-to-br ${theme.bgGradient}`} />
          <div className="absolute inset-0" style={{
            backgroundImage: `repeating-linear-gradient(45deg, transparent, transparent 35px, rgba(0,0,0,.03) 35px, rgba(0,0,0,.03) 70px)`
          }} />
        </div>

        <div className="relative p-4 sm:p-6 md:p-8">
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
            dbPreviousClose={prediction.db_previous_close}
            dbLastCheckDate={prediction.db_last_check_date}
            apiChange={apiData.api_change}
            apiChangePercent={apiData.api_change_percent}
            apiPreviousClose={apiData.api_previous_close}
          />


          {/* Market Influences (v4) or Factor Summary (v6) */}
          {isV6 ? (
            <FactorSummarySection
              scores={apiData.scores}
              weights={apiData.weights}
              contributions={apiData.contributions}
            />
          ) : (
            (prediction.european_influence_score !== undefined || 
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
            )
          )}

          {/* US Factors and Global Markets (v6) */}
          {isV6 && (
            <div className="grid md:grid-cols-2 gap-6 mt-6">
              <USFactorsWidget us={apiData.us_factors} />
              <GlobalMarketsWidget global={apiData.global_markets} asianMarkets={prediction.asian_markets} europeanMarkets={prediction.european_markets} />
            </div>
          )}

          {/* Detailed Factor Widgets (v6) */}
          {isV6 && (
            <div className="grid lg:grid-cols-3 gap-6 mt-6">
              <TechnicalsWidget tech={apiData.factors?.technical} />
              <FundamentalsWidget fund={apiData.factors?.fundamentals} />
              <VolumeWidget tech={apiData.factors?.technical} liq={apiData.factors?.liquidity} />
            </div>
          )}

          {/* Tags and Outlook (v6) */}
          {isV6 && (
            <div className="mt-6 grid md:grid-cols-2 gap-6">
              <TagsSection tags={apiData.tags} />
              <OutlookSection outlook={apiData.outlook} />
            </div>
          )}

          {/* Key Factors Grid / Reasons */}
          <KeyFactorsSection 
            reasons={apiData.top_reasons || prediction.top_reasons}
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

// Factor Summary (v6) - Enhanced with colorful gradients and 3-per-row layout
const FactorSummarySection = memo(({ scores = {}, weights = {}, contributions = {} }) => {
  const items = [
    { 
      key: 'technical', 
      label: 'Technical Analysis', 
      icon: Activity, 
      gradient: 'from-blue-500 via-cyan-500 to-teal-500',
      bgGradient: 'from-blue-50 via-cyan-50 to-teal-50',
      iconColor: 'text-blue-600',
      iconBg: 'bg-gradient-to-br from-blue-100 to-cyan-100'
    },
    { 
      key: 'fundamentals', 
      label: 'Fundamentals', 
      icon: BarChart3, 
      gradient: 'from-emerald-500 via-green-500 to-lime-500',
      bgGradient: 'from-emerald-50 via-green-50 to-lime-50',
      iconColor: 'text-emerald-600',
      iconBg: 'bg-gradient-to-br from-emerald-100 to-green-100'
    },
    { 
      key: 'sentiment', 
      label: 'Market Sentiment', 
      icon: Newspaper, 
      gradient: 'from-purple-500 via-fuchsia-500 to-pink-500',
      bgGradient: 'from-purple-50 via-fuchsia-50 to-pink-50',
      iconColor: 'text-purple-600',
      iconBg: 'bg-gradient-to-br from-purple-100 to-fuchsia-100'
    },
    { 
      key: 'regional', 
      label: 'Global Markets', 
      icon: Globe, 
      gradient: 'from-indigo-500 via-violet-500 to-purple-500',
      bgGradient: 'from-indigo-50 via-violet-50 to-purple-50',
      iconColor: 'text-indigo-600',
      iconBg: 'bg-gradient-to-br from-indigo-100 to-violet-100'
    },
    { 
      key: 'liquidity', 
      label: 'Volume & Liquidity', 
      icon: DollarSign, 
      gradient: 'from-orange-500 via-amber-500 to-yellow-500',
      bgGradient: 'from-orange-50 via-amber-50 to-yellow-50',
      iconColor: 'text-orange-600',
      iconBg: 'bg-gradient-to-br from-orange-100 to-amber-100'
    },
    { 
      key: 'fear_index', 
      label: 'Fear & Greed Index', 
      icon: AlertCircle, 
      gradient: 'from-red-500 via-rose-500 to-pink-500',
      bgGradient: 'from-red-50 via-rose-50 to-pink-50',
      iconColor: 'text-red-600',
      iconBg: 'bg-gradient-to-br from-red-100 to-rose-100'
    },
  ];
  
  return (
    <div className="mt-8 mb-8">
      <h3 className="text-xl font-black text-gray-900 dark:text-white mb-5 flex items-center gap-2">
        <Sparkles className="w-6 h-6 text-indigo-600" />
        Factor Breakdown
      </h3>
      <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
        {items.map(({ key, label, icon: Icon, gradient, bgGradient, iconColor, iconBg }) => {
          const s = Number(scores?.[key] ?? 0);
          const w = Number(weights?.[key] ?? 0);
          const c = Number(contributions?.[key] ?? 0);
          const isPositive = c >= 0;
          
          return (
            <div key={key} className="group relative rounded-2xl border-2 border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500 shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden bg-white dark:bg-gray-700">
              {/* Animated gradient background */}
              <div className={`absolute inset-0 bg-gradient-to-br ${bgGradient} opacity-40 group-hover:opacity-60 transition-opacity`} />
              
              {/* Top gradient bar */}
              <div className={`absolute top-0 left-0 right-0 h-1 bg-gradient-to-r ${gradient}`} />
              
              <div className="relative p-5">
                {/* Header with icon */}
                <div className="flex items-center gap-3 mb-4">
                  <div className={`${iconBg} p-3 rounded-xl shadow-md group-hover:scale-110 transition-transform`}>
                    <Icon className={`w-6 h-6 ${iconColor}`} strokeWidth={2.5} />
                  </div>
                  <div className="font-black text-gray-900 dark:text-white text-base leading-tight">{label}</div>
                </div>
                
                {/* Metrics grid */}
                <div className="space-y-3">
                  {/* Score */}
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-semibold text-gray-600 dark:text-gray-300">Score</span>
                    <span className={`text-2xl font-black ${isPositive ? 'text-green-600' : s < 0 ? 'text-red-600' : 'text-gray-700'}`}>
                      {s > 0 ? '+' : ''}{s.toFixed(2)}
                    </span>
                  </div>
                  
                  {/* Weight with progress bar */}
                  <div>
                    <div className="flex items-center justify-between mb-1">
                      <span className="text-sm font-semibold text-gray-600 dark:text-gray-300">Weight</span>
                      <span className="text-lg font-black text-indigo-600 dark:text-indigo-400">{(w * 100).toFixed(0)}%</span>
                    </div>
                    <div className="h-2 bg-gray-200 dark:bg-gray-600 rounded-full overflow-hidden">
                      <div className={`h-full bg-gradient-to-r ${gradient} transition-all duration-500`} style={{ width: `${w * 100}%` }} />
                    </div>
                  </div>
                  
                  {/* Contribution */}
                  <div className="flex items-center justify-between pt-2 border-t border-gray-300 dark:border-gray-600">
                    <span className="text-sm font-semibold text-gray-600 dark:text-gray-300">Impact</span>
                    <div className="flex items-center gap-2">
                      {isPositive ? (
                        <TrendingUp className="w-4 h-4 text-green-600" strokeWidth={3} />
                      ) : (
                        <TrendingDown className="w-4 h-4 text-red-600" strokeWidth={3} />
                      )}
                      <span className={`text-xl font-black ${isPositive ? 'text-green-600' : 'text-red-600'}`}>
                        {c >= 0 ? '+' : ''}{c.toFixed(3)}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
});

const USFactorsWidget = memo(({ us = {} }) => {
  const rows = [
    { k: 'sp500_change', label: 'S&P 500', icon: TrendingUp, color: v => v > 0 ? 'text-green-600' : 'text-red-600', isPct: true },
    { k: 'nasdaq_change', label: 'Nasdaq', icon: Activity, color: v => v > 0 ? 'text-green-600' : 'text-red-600', isPct: true },
    { k: 'russell_2000_change', label: 'Russell 2000', icon: BarChart3, color: v => v > 0 ? 'text-green-600' : 'text-red-600', isPct: true },
    { k: 'treasury_yield_10y', label: '10Y Treasury', icon: Target, color: v => 'text-indigo-600' },
    { k: 'fed_sentiment_score', label: 'FED Sentiment', icon: Brain, color: v => v > 0 ? 'text-green-600' : 'text-red-600' },
  ];
  
  return (
    <div className="group relative rounded-2xl border-2 border-indigo-200 dark:border-indigo-700 bg-gradient-to-br from-indigo-50 via-blue-50 to-cyan-50 dark:from-indigo-900/20 dark:via-blue-900/20 dark:to-cyan-900/20 p-6 shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden">
      {/* Top accent bar */}
      <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-indigo-500 via-blue-500 to-cyan-500" />
      
      <div className="flex items-center gap-3 mb-5">
        <div className="bg-gradient-to-br from-indigo-100 to-blue-100 p-3 rounded-xl shadow-md group-hover:scale-110 transition-transform">
          <Globe className="w-6 h-6 text-indigo-600" strokeWidth={2.5} />
        </div>
        <h3 className="text-xl font-black text-gray-900 dark:text-white">US Market Factors</h3>
      </div>
      
      <div className="space-y-3">
        {rows.map(r => {
          const val = Number(us[r.k] || 0);
          const Icon = r.icon;
          return (
            <div key={r.k} className="flex items-center justify-between p-3 bg-white dark:bg-gray-700 rounded-xl shadow-sm hover:shadow-md transition-shadow">
              <div className="flex items-center gap-2">
                <Icon className="w-4 h-4 text-indigo-600" />
                <span className="text-sm font-semibold text-gray-700 dark:text-gray-300">{r.label}</span>
              </div>
              <span className={`text-lg font-black ${r.color(val)}`}>
                {r.isPct ? `${val > 0 ? '+' : ''}${val.toFixed(2)}%` : val.toFixed(2)}
              </span>
            </div>
          );
        })}
      </div>
    </div>
  );
});

const GlobalMarketsWidget = memo(({ global = {}, asianMarkets, europeanMarkets }) => {
  const rows = [
    { k: 'european_influence_score', label: 'Europe', icon: Globe, region: 'EU' },
    { k: 'asian_influence_score', label: 'Asia', icon: Globe, region: 'AS' },
  ];
  
  return (
    <div className="group relative rounded-2xl border-2 border-violet-200 dark:border-violet-700 bg-gradient-to-br from-violet-50 via-purple-50 to-fuchsia-50 dark:from-violet-900/20 dark:via-purple-900/20 dark:to-fuchsia-900/20 p-6 shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden">
      {/* Top accent bar */}
      <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-violet-500 via-purple-500 to-fuchsia-500" />
      
      <div className="flex items-center gap-3 mb-5">
        <div className="bg-gradient-to-br from-violet-100 to-purple-100 p-3 rounded-xl shadow-md group-hover:scale-110 transition-transform">
          <Globe className="w-6 h-6 text-violet-600" strokeWidth={2.5} />
        </div>
        <h3 className="text-xl font-black text-gray-900 dark:text-white">Global Markets</h3>
      </div>
      
      <div className="space-y-3">
        {rows.map(r => {
          const val = Number(global[r.k] || 0);
          const Icon = r.icon;
          return (
            <div key={r.k} className="flex items-center justify-between p-3 bg-white dark:bg-gray-700 rounded-xl shadow-sm hover:shadow-md transition-shadow">
              <div className="flex items-center gap-2">
                <Icon className="w-4 h-4 text-violet-600" />
                <span className="text-sm font-semibold text-gray-700 dark:text-gray-300">{r.label}</span>
              </div>
              <div className="flex items-center gap-2">
                {val >= 0 ? (
                  <TrendingUp className="w-4 h-4 text-green-600" strokeWidth={3} />
                ) : (
                  <TrendingDown className="w-4 h-4 text-red-600" strokeWidth={3} />
                )}
                <span className={`text-lg font-black ${val >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                  {val > 0 ? '+' : ''}{val.toFixed(2)}
                </span>
              </div>
            </div>
          );
        })}
      </div>
      
      <div className="mt-4 pt-3 border-t border-violet-200 dark:border-violet-700">
        <div className="text-xs text-gray-600 dark:text-gray-400 font-medium">📊 Detailed market snapshots below</div>
      </div>
    </div>
  );
});

// Technicals Widget - Enhanced with colorful design
const TechnicalsWidget = memo(({ tech = {} }) => {
  const rows = [
    { k: 'rsi_14', label: 'RSI-14', icon: Activity, color: v => v > 70 ? 'text-red-600' : v < 30 ? 'text-green-600' : 'text-gray-700' },
    { k: 'macd_hist', label: 'MACD Hist', icon: TrendingUp, color: v => v > 0 ? 'text-green-600' : 'text-red-600' },
    { k: 'bb_pct', label: 'BB Position', icon: Target, color: v => v > 0.8 ? 'text-red-600' : v < 0.2 ? 'text-green-600' : 'text-gray-700' },
    { k: 'price_change_1d', label: '1 Day', icon: Zap, color: v => v > 0 ? 'text-green-600' : 'text-red-600', isPct: true },
    { k: 'price_change_3d', label: '3 Days', icon: Activity, color: v => v > 0 ? 'text-green-600' : 'text-red-600', isPct: true },
    { k: 'price_change_7d', label: '7 Days', icon: TrendingUp, color: v => v > 0 ? 'text-green-600' : 'text-red-600', isPct: true },
  ];
  
  return (
    <div className="group relative rounded-2xl border-2 border-blue-200 dark:border-blue-700 bg-gradient-to-br from-blue-50 via-cyan-50 to-teal-50 dark:from-blue-900/20 dark:via-cyan-900/20 dark:to-teal-900/20 p-6 shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden">
      {/* Top accent bar */}
      <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 via-cyan-500 to-teal-500" />
      
      <div className="flex items-center gap-3 mb-5">
        <div className="bg-gradient-to-br from-blue-100 to-cyan-100 p-3 rounded-xl shadow-md group-hover:scale-110 transition-transform">
          <Activity className="w-6 h-6 text-blue-600" strokeWidth={2.5} />
        </div>
        <h3 className="text-xl font-black text-gray-900 dark:text-white">Technical Indicators</h3>
      </div>
      
      <div className="space-y-3">
        {rows.map(r => {
          const val = Number(tech[r.k] || 0);
          const Icon = r.icon;
          return (
            <div key={r.k} className="flex items-center justify-between p-3 bg-white dark:bg-gray-700 rounded-xl shadow-sm hover:shadow-md transition-shadow">
              <div className="flex items-center gap-2">
                <Icon className="w-4 h-4 text-blue-600" />
                <span className="text-sm font-semibold text-gray-700 dark:text-gray-300">{r.label}</span>
              </div>
              <span className={`text-lg font-black ${r.color(val)}`}>
                {r.isPct ? `${val > 0 ? '+' : ''}${val.toFixed(2)}%` : val.toFixed(2)}
              </span>
            </div>
          );
        })}
      </div>
    </div>
  );
});

// Fundamentals Widget - Enhanced with colorful design
const FundamentalsWidget = memo(({ fund = {} }) => {
  // Debug logging
  console.log('💰 FundamentalsWidget received data:', fund);
  console.log('💰 Fund keys:', Object.keys(fund));
  console.log('💰 Full fund object:', JSON.stringify(fund, null, 2));
  
  // Check if we have real data or just defaults
  const hasRealData = fund && Object.keys(fund).length > 1 && fund.score !== undefined;
  const isUsingDefaults = hasRealData && (
    (fund.pe_ratio === 20 && fund.pb_ratio === 3 && fund.eps_growth === 0 && fund.revenue_growth === 0)
  );
  
  const rows = [
    { k: 'pe_ratio', label: 'P/E Ratio', icon: Activity, color: v => v < 15 ? 'text-green-600' : v > 30 ? 'text-red-600' : 'text-gray-700', suffix: '' },
    { k: 'pb_ratio', label: 'P/B Ratio', icon: Target, color: v => v < 3 ? 'text-green-600' : v > 5 ? 'text-red-600' : 'text-gray-700', suffix: '' },
    { k: 'eps_growth', label: 'EPS Growth', icon: TrendingUp, color: v => v > 10 ? 'text-green-600' : v < 0 ? 'text-red-600' : 'text-gray-700', suffix: '%' },
    { k: 'revenue_growth', label: 'Revenue Growth', icon: BarChart3, color: v => v > 10 ? 'text-green-600' : v < 0 ? 'text-red-600' : 'text-gray-700', suffix: '%' },
    { k: 'roe', label: 'ROE', icon: Sparkles, color: v => v > 15 ? 'text-green-600' : v < 5 ? 'text-red-600' : 'text-gray-700', suffix: '%' },
    { k: 'profit_margin', label: 'Profit Margin', icon: DollarSign, color: v => v > 15 ? 'text-green-600' : v < 5 ? 'text-red-600' : 'text-gray-700', suffix: '%' },
    { k: 'debt_to_equity', label: 'Debt/Equity', icon: AlertTriangle, color: v => v < 1 ? 'text-green-600' : v > 2 ? 'text-red-600' : 'text-gray-700', suffix: '' },
    { k: 'dividend_yield', label: 'Dividend Yield', icon: DollarSign, color: v => v > 2 ? 'text-green-600' : 'text-gray-700', suffix: '%' },
  ];
  
  return (
    <div className="group relative rounded-2xl border-2 border-emerald-200 dark:border-emerald-700 bg-gradient-to-br from-emerald-50 via-green-50 to-lime-50 dark:from-emerald-900/20 dark:via-green-900/20 dark:to-lime-900/20 p-6 shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden">
      {/* Top accent bar */}
      <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 via-green-500 to-lime-500" />
      
      <div className="flex items-center gap-3 mb-5">
        <div className="bg-gradient-to-br from-emerald-100 to-green-100 p-3 rounded-xl shadow-md group-hover:scale-110 transition-transform">
          <BarChart3 className="w-6 h-6 text-emerald-600" strokeWidth={2.5} />
        </div>
        <div>
          <h3 className="text-xl font-black text-gray-900 dark:text-white">Fundamentals</h3>
          <div className="text-xs text-emerald-600 font-semibold">Financial Health & Valuation</div>
        </div>
      </div>
      
      <div className="grid grid-cols-2 gap-3">
        {rows.map(r => {
          const val = Number(fund[r.k] || 0);
          const Icon = r.icon;
          const hasData = fund[r.k] !== undefined && fund[r.k] !== null && fund[r.k] !== 0;
          
          return (
            <div key={r.k} className={`flex flex-col p-3 bg-white dark:bg-gray-700 rounded-xl shadow-sm hover:shadow-md transition-shadow ${!hasData ? 'opacity-50' : ''}`}>
              <div className="flex items-center gap-2 mb-2">
                <Icon className="w-4 h-4 text-emerald-600" />
                <span className="text-xs font-semibold text-gray-600 dark:text-gray-300">{r.label}</span>
              </div>
              <span className={`text-xl font-black ${r.color(val)}`}>
                {val.toFixed(val > 100 ? 0 : val > 10 ? 1 : 2)}{r.suffix}
              </span>
            </div>
          );
        })}
      </div>
      
      {/* Debug/Warning info */}
      {!hasRealData && (
        <div className="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-800">
          <div className="flex items-center gap-2 font-semibold mb-1">
            <AlertTriangle className="w-4 h-4" />
            No fundamental data available
          </div>
          <div className="text-xs text-yellow-700">
            The API may not have returned fundamental data. This stock might not have publicly available financials.
          </div>
        </div>
      )}
      {isUsingDefaults && (
        <div className="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800">
          <div className="flex items-center gap-2 font-semibold mb-1">
            <Info className="w-4 h-4" />
            Using estimated values
          </div>
          <div className="text-xs text-blue-700">
            Fundamental data from Yahoo Finance unavailable. Showing industry-average estimates.
          </div>
        </div>
      )}
    </div>
  );
});

// Volume/Liquidity Widget - Enhanced with colorful design
const VolumeWidget = memo(({ tech = {}, liq = {} }) => {
  const rows = [
    { k: 'volume_sma_ratio', label: 'Relative Volume', source: tech, icon: Activity, color: v => v > 1.5 ? 'text-green-600' : v < 0.8 ? 'text-red-600' : 'text-gray-700' },
    { k: 'inst_flow_score', label: 'Institutional Flow', source: liq, icon: TrendingUp, color: v => v > 0 ? 'text-green-600' : 'text-red-600' },
    { k: 'atr_14', label: 'Volatility (ATR)', source: tech, icon: Zap, color: v => v > 3 ? 'text-orange-600' : 'text-gray-700' },
  ];
  
  return (
    <div className="group relative rounded-2xl border-2 border-orange-200 dark:border-orange-700 bg-gradient-to-br from-orange-50 via-amber-50 to-yellow-50 dark:from-orange-900/20 dark:via-amber-900/20 dark:to-yellow-900/20 p-6 shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden">
      {/* Top accent bar */}
      <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-orange-500 via-amber-500 to-yellow-500" />
      
      <div className="flex items-center gap-3 mb-5">
        <div className="bg-gradient-to-br from-orange-100 to-amber-100 p-3 rounded-xl shadow-md group-hover:scale-110 transition-transform">
          <DollarSign className="w-6 h-6 text-orange-600" strokeWidth={2.5} />
        </div>
        <h3 className="text-xl font-black text-gray-900 dark:text-white">Volume & Flow</h3>
      </div>
      
      <div className="space-y-3">
        {rows.map(r => {
          const val = Number((r.source || {})[r.k] || 0);
          const Icon = r.icon;
          return (
            <div key={r.k} className="flex items-center justify-between p-3 bg-white dark:bg-gray-700 rounded-xl shadow-sm hover:shadow-md transition-shadow">
              <div className="flex items-center gap-2">
                <Icon className="w-4 h-4 text-orange-600" />
                <span className="text-sm font-semibold text-gray-700 dark:text-gray-300">{r.label}</span>
              </div>
              <span className={`text-lg font-black ${r.color(val)}`}>
                {val.toFixed(2)}
              </span>
            </div>
          );
        })}
      </div>
    </div>
  );
});

// Tags chips - Enhanced design
const TagsSection = memo(({ tags = [] }) => {
  if (!tags || tags.length === 0) return (
    <div className="group relative rounded-2xl border-2 border-purple-200 dark:border-purple-700 bg-gradient-to-br from-purple-50 via-pink-50 to-rose-50 dark:from-purple-900/20 dark:via-pink-900/20 dark:to-rose-900/20 p-6 shadow-lg">
      <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-purple-500 via-pink-500 to-rose-500" />
      <div className="text-sm text-gray-600 dark:text-gray-300 font-medium">No market tags available</div>
    </div>
  );
  
  // Color map for different tag types
  const getTagStyle = (tag) => {
    const lower = tag.toLowerCase();
    if (lower.includes('bullish') || lower.includes('oversold')) return 'bg-gradient-to-r from-green-500 to-emerald-500 text-white';
    if (lower.includes('bearish') || lower.includes('overbought')) return 'bg-gradient-to-r from-red-500 to-rose-500 text-white';
    if (lower.includes('fear')) return 'bg-gradient-to-r from-orange-500 to-amber-500 text-white';
    if (lower.includes('greed')) return 'bg-gradient-to-r from-blue-500 to-cyan-500 text-white';
    if (lower.includes('high') || lower.includes('breakout')) return 'bg-gradient-to-r from-violet-500 to-purple-500 text-white';
    return 'bg-gradient-to-r from-gray-600 to-gray-700 text-white';
  };
  
  return (
    <div className="group relative rounded-2xl border-2 border-purple-200 dark:border-purple-700 bg-gradient-to-br from-purple-50 via-pink-50 to-rose-50 dark:from-purple-900/20 dark:via-pink-900/20 dark:to-rose-900/20 p-6 shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden">
      {/* Top accent bar */}
      <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-purple-500 via-pink-500 to-rose-500" />
      
      <div className="flex items-center gap-3 mb-5">
        <div className="bg-gradient-to-br from-purple-100 to-pink-100 dark:from-purple-900/40 dark:to-pink-900/40 p-3 rounded-xl shadow-md group-hover:scale-110 transition-transform">
          <Sparkles className="w-6 h-6 text-purple-600 dark:text-purple-400" strokeWidth={2.5} />
        </div>
        <h3 className="text-xl font-black text-gray-900 dark:text-white">Market Conditions</h3>
      </div>
      
      <div className="flex flex-wrap gap-2">
        {tags.map((t, i) => (
          <span key={i} className={`px-4 py-2 rounded-full text-sm font-bold shadow-md hover:shadow-lg transform hover:scale-105 transition-all ${getTagStyle(t)}`}>
            {t}
          </span>
        ))}
      </div>
    </div>
  );
});

// Outlook table - Enhanced design
const OutlookSection = memo(({ outlook }) => {
  const st = outlook?.short_term || {};
  const mt = outlook?.mid_term || {};
  
  const Row = ({label, o, icon: Icon}) => {
    const dir = o?.direction || 'flat';
    const ret = o?.expected_return_pct || 0;
    const conf = o?.confidence || 0;
    const isPositive = dir === 'up' || ret > 0;
    
    return (
      <div className="flex items-center justify-between p-3 bg-white dark:bg-gray-700 rounded-xl shadow-sm hover:shadow-md transition-shadow">
        <div className="flex items-center gap-2">
          <Icon className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
          <span className="text-sm font-semibold text-gray-700 dark:text-gray-300">{label}</span>
        </div>
        <div className="flex items-center gap-3">
          {isPositive ? (
            <TrendingUp className="w-4 h-4 text-green-600 dark:text-green-400" strokeWidth={3} />
          ) : (
            <TrendingDown className="w-4 h-4 text-red-600 dark:text-red-400" strokeWidth={3} />
          )}
          <span className={`text-base font-black ${isPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
            {ret > 0 ? '+' : ''}{ret.toFixed(2)}%
          </span>
          <span className="text-xs text-gray-500 dark:text-gray-400 font-medium">
            {(conf * 100).toFixed(0)}%
          </span>
        </div>
      </div>
    );
  };
  
  return (
    <div className="group relative rounded-2xl border-2 border-indigo-200 dark:border-indigo-700 bg-gradient-to-br from-indigo-50 via-blue-50 to-purple-50 dark:from-indigo-900/20 dark:via-blue-900/20 dark:to-purple-900/20 p-6 shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden">
      {/* Top accent bar */}
      <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-indigo-500 via-blue-500 to-purple-500" />
      
      <div className="flex items-center gap-3 mb-5">
        <div className="bg-gradient-to-br from-indigo-100 to-blue-100 dark:from-indigo-900/40 dark:to-blue-900/40 p-3 rounded-xl shadow-md group-hover:scale-110 transition-transform">
          <Target className="w-6 h-6 text-indigo-600 dark:text-indigo-400" strokeWidth={2.5} />
        </div>
        <h3 className="text-xl font-black text-gray-900 dark:text-white">Price Outlook</h3>
      </div>
      
      <div className="space-y-3">
        <Row label="1 Day" o={st['1d']} icon={Zap} />
        <Row label="1 Week" o={st['1w']} icon={Activity} />
        <div className="h-px bg-indigo-200 dark:bg-indigo-700 my-2" />
        <Row label="1 Month" o={mt['1m']} icon={Clock} />
        <Row label="3 Months" o={mt['3m']} icon={Target} />
      </div>
    </div>
  );
});

/**
 * Header Section - Memoized
 */
const HeaderSection = memo(({ modelVersion, onRefetch, isFetching }) => (
  <div className="flex items-center justify-between mb-6 sm:mb-8">
    <div className="flex items-center gap-2 sm:gap-3">
      <div className="relative">
        <Sparkles className="w-6 h-6 sm:w-8 sm:h-8 text-indigo-600 animate-pulse" />
        <div className="absolute inset-0 blur-lg bg-indigo-400 animate-ping" />
      </div>
      <div>
        <h2 className="text-xl sm:text-2xl md:text-3xl font-black bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
          AI Prediction
        </h2>
        {modelVersion && (
          <span className="text-xs px-2 sm:px-3 py-1 bg-gradient-to-r from-indigo-500 to-purple-500 text-white rounded-full font-semibold shadow-lg">
            {modelVersion}
          </span>
        )}
      </div>
    </div>
    <button
      onClick={onRefetch}
      disabled={isFetching}
      className="group p-2 sm:p-3 hover:bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl transition-all duration-300 hover:scale-110 active:scale-95"
      title="Refresh prediction"
    >
      <RefreshCw className={`w-5 h-5 sm:w-6 sm:h-6 text-indigo-600 group-hover:text-purple-600 transition-colors ${isFetching ? 'animate-spin' : ''}`} />
    </button>
  </div>
));

HeaderSection.displayName = 'HeaderSection';

/**
 * Main Prediction Display - Memoized
 * Compact card with left border and price info on the right
 */
const MainPredictionDisplay = memo(({ isBullish, label, expectedMove, probability, theme, timestamp, currentPrice, dbChange, dbChangePercent, dbPreviousClose, dbLastCheckDate, apiChange, apiChangePercent, apiPreviousClose }) => {
  // Ensure values are valid numbers
  const validCurrentPrice = currentPrice && !isNaN(currentPrice) ? Number(currentPrice) : 0;
  const validExpectedMove = expectedMove && !isNaN(expectedMove) ? Number(expectedMove) : 0;
  
  // Check if database data is stale (more than 1 day old)
  const isDbStale = dbLastCheckDate ? 
    (new Date() - new Date(dbLastCheckDate)) > (24 * 60 * 60 * 1000) : true;
  
  // Use API previous close if DB is stale, otherwise use DB previous close
  const validPrevClose = (isDbStale && apiPreviousClose && !isNaN(apiPreviousClose)) 
    ? Number(apiPreviousClose)
    : (dbPreviousClose && !isNaN(dbPreviousClose) ? Number(dbPreviousClose) : validCurrentPrice);
  
  // Calculate target price from PREVIOUS CLOSE (not current price)
  // The prediction should be: Previous Close -> Target Price
  const targetPrice = validPrevClose > 0 ? validPrevClose * (1 + validExpectedMove / 100) : 0;
  
  // Calculate the actual expected change: from Previous Close to Target
  const expectedDollarChange = targetPrice - validPrevClose;
  
  // Calculate current change from previous close (for display)
  const currentChange = validCurrentPrice - validPrevClose;
  const currentChangePercent = validPrevClose > 0 ? ((currentChange / validPrevClose) * 100) : 0;
  
  // Check if we have database-based change values
  const hasDbChange = dbChange !== undefined && dbChangePercent !== undefined;
  const hasApiChange = apiChange !== undefined && apiChangePercent !== undefined;
  
  // Use API values if DB is stale or unavailable, otherwise use DB values
  const displayChange = (hasDbChange && !isDbStale) 
    ? Number(dbChange) 
    : (hasApiChange ? Number(apiChange) : currentChange);
  const displayChangePercent = (hasDbChange && !isDbStale) 
    ? Number(dbChangePercent) 
    : (hasApiChange ? Number(apiChangePercent) : currentChangePercent);

  return (
    <div className="mb-6 sm:mb-8">
      {/* Main Prediction Card with subtle tint */}
      <div className={`relative rounded-2xl border-l-4 sm:border-l-8 ${theme.borderColor} shadow-xl overflow-hidden`}>
        {/* Subtle background tint */}
        <div className={`absolute inset-0 ${theme.bgGradient} opacity-30`} />
        
        <div className="relative bg-white dark:bg-gray-800 p-4 sm:p-6">
          <div className="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4 sm:gap-6">
            {/* Left: Prediction Label */}
            <div className="flex items-center gap-3 sm:gap-4">
              <div className={`flex items-center justify-center w-12 h-12 sm:w-16 sm:h-16 rounded-xl sm:rounded-2xl ${theme.lightBg} dark:bg-opacity-20 shadow-lg`}>
                <theme.IconComponent className={`w-6 h-6 sm:w-8 sm:h-8 ${theme.textColor}`} strokeWidth={2.5} />
              </div>
              <div>
                <div className={`text-2xl sm:text-3xl font-black ${theme.textColor} mb-1`}>
                  {label}
                </div>
                <div className="flex items-center gap-2 text-xs sm:text-sm text-gray-600 dark:text-white">
                  <Target className="w-3 h-3 sm:w-4 sm:h-4" />
                  <span>Confidence: <span className="font-bold">{probability.toFixed(1)}%</span></span>
                </div>
              </div>
            </div>

            {/* Right: Price Information */}
            <div className="grid grid-cols-3 gap-2 sm:gap-3 md:gap-4 flex-1 w-full lg:max-w-xl">
              {/* Previous Close */}
              <div className="text-center p-2 sm:p-3 md:p-4 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-700 dark:to-gray-600 rounded-lg sm:rounded-xl border border-gray-300 dark:border-gray-600 sm:border-2">
                <div className="text-[10px] sm:text-xs text-gray-600 dark:text-white font-semibold mb-1 flex items-center justify-center gap-1">
                  <BarChart3 className="w-3 h-3 sm:w-3.5 sm:h-3.5 text-gray-500 dark:text-white" />
                  <span className="hidden xs:inline">Prev Close</span>
                  <span className="xs:hidden">Prev</span>
                </div>
                <div className="text-base sm:text-xl md:text-2xl font-black text-gray-700 dark:text-white">
                  ${validPrevClose > 0 ? validPrevClose.toFixed(2) : '0.00'}
                </div>
              </div>

              {/* Current Price */}
              <div className="text-center p-2 sm:p-3 md:p-4 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/30 dark:to-indigo-900/30 rounded-lg sm:rounded-xl border border-blue-200 dark:border-blue-700 sm:border-2">
                <div className="text-[10px] sm:text-xs text-gray-600 dark:text-white font-semibold mb-1 flex items-center justify-center gap-1">
                  <Activity className="w-3 h-3 sm:w-3.5 sm:h-3.5 text-blue-600 dark:text-white" />
                  <span>Current</span>
                </div>
                <div className="text-base sm:text-xl md:text-2xl font-black text-blue-600 dark:text-blue-400">
                  ${validCurrentPrice > 0 ? validCurrentPrice.toFixed(2) : '0.00'}
                </div>
                {displayChangePercent !== 0 && (
                  <div className={`text-[10px] sm:text-xs font-bold mt-1 ${
                    displayChangePercent > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'
                  }`}>
                    {displayChangePercent > 0 ? '+' : ''}{displayChangePercent.toFixed(2)}%
                  </div>
                )}
              </div>

              {/* Target Price */}
              <div className={`text-center p-2 sm:p-3 md:p-4 bg-gradient-to-br ${theme.bgGradient} dark:from-opacity-20 dark:to-opacity-20 rounded-lg sm:rounded-xl border ${theme.borderColor} dark:border-opacity-50 sm:border-2`}>
                <div className="text-[10px] sm:text-xs text-gray-600 dark:text-white font-semibold mb-1 flex items-center justify-center gap-1">
                  <Target className={`w-3 h-3 sm:w-3.5 sm:h-3.5 ${theme.textColor}`} />
                  <span>Target</span>
                </div>
                <div className={`text-base sm:text-xl md:text-2xl font-black ${theme.textColor}`}>
                  ${targetPrice > 0 ? targetPrice.toFixed(2) : '0.00'}
                </div>
                <div className={`text-[10px] sm:text-xs font-bold mt-1 ${theme.textColor}`}>
                  {validExpectedMove > 0 ? '+' : ''}{validExpectedMove.toFixed(2)}%
                </div>
              </div>
            </div>
          </div>

          {/* Timestamp and Current Price Change */}
          {timestamp && (
            <div className="mt-3 sm:mt-4 pt-3 sm:pt-4 border-t border-gray-200 dark:border-gray-600 text-xs">
              {/* Timestamp row */}
              <div className="flex flex-col xs:flex-row items-start xs:items-center justify-between text-gray-500 dark:text-white gap-2">
                <div className="flex items-center gap-1">
                  <Activity className="w-3 h-3" />
                  <span className="text-[10px] sm:text-xs">Updated: {new Date(timestamp).toLocaleTimeString()}</span>
                </div>
                <div className="flex items-center gap-2">
                  <div className={`w-2 h-2 rounded-full ${isBullish ? 'bg-green-500' : 'bg-red-500'} animate-pulse`} />
                  <span className="font-semibold text-[10px] sm:text-xs">Expected: {expectedDollarChange > 0 ? '+' : ''}${Math.abs(expectedDollarChange).toFixed(2)} ({validExpectedMove > 0 ? '+' : ''}{validExpectedMove.toFixed(2)}%)</span>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Single Confidence Progress Bar */}
      <div className="mt-3 sm:mt-4">
        <div className="relative h-2 sm:h-3 bg-gradient-to-r from-gray-200 to-gray-300 dark:from-gray-600 dark:to-gray-700 rounded-full overflow-hidden shadow-inner">
          <div
            className={`h-full bg-gradient-to-r ${theme.gradient} transition-all duration-1000 ease-out relative overflow-hidden`}
            style={{ width: `${probability}%` }}
          >
            <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-30 animate-shimmer" />
          </div>
        </div>
        <div className="mt-1 text-[10px] sm:text-xs text-gray-500 dark:text-white text-right">Confidence: {probability.toFixed(1)}%</div>
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
      <h3 className="text-xl font-black text-gray-900 dark:text-white mb-4 flex items-center gap-2">
        <Brain className="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
        Key Factors
      </h3>
      <div className="grid gap-3">
        {reasons.map((reason, index) => {
          const Icon = icons[index % icons.length];
          const formattedReason = formatReason(reason);
          
          return (
            <div 
              key={index} 
              className="group flex items-start gap-4 p-4 bg-gradient-to-r from-white to-gray-50 dark:from-gray-800 dark:to-gray-700 rounded-xl border-2 border-gray-200 dark:border-gray-600 hover:border-indigo-300 dark:hover:border-indigo-500 hover:shadow-lg transform transition-all duration-300"
            >
              <div className={`flex-shrink-0 w-10 h-10 rounded-xl ${theme.lightBg} dark:bg-opacity-20 flex items-center justify-center transform group-hover:scale-110 group-hover:rotate-12 transition-all duration-300`}>
                <Icon className={`w-5 h-5 ${theme.textColor}`} />
              </div>
              <div className="flex-1 flex items-center justify-between gap-3">
                <span className="text-sm text-gray-700 dark:text-white font-medium leading-relaxed">{formattedReason}</span>
                <span className={`flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-xs font-black ${theme.lightBg} dark:bg-opacity-20 ${theme.textColor}`}>
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
  // Calculate average market change percentages dynamically
  const calculateAvgChange = (markets) => {
    if (!markets || typeof markets !== 'object' || Object.keys(markets).length === 0) return 0;
    const changes = Object.values(markets)
      .map(m => m?.change_percent || 0)
      .filter(c => !isNaN(c));
    if (changes.length === 0) return 0;
    return changes.reduce((sum, c) => sum + c, 0) / changes.length;
  };
  
  const europeanAvgChange = calculateAvgChange(europeanMarkets);
  const asianAvgChange = calculateAvgChange(asianMarkets);
  
  const markets = [
    {
      name: 'European Markets',
      icon: Globe,
      score: europeanScore || 0,
      avgChange: europeanAvgChange,
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
      avgChange: asianAvgChange,
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
      avgChange: 0, // US factors don't have simple avg
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
                        {market.avgChange !== 0 && (
                          <span className={`px-2 py-0.5 rounded-full text-xs font-bold ${
                            market.avgChange > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                          } shadow`}>
                            {market.avgChange > 0 ? '+' : ''}{market.avgChange.toFixed(2)}% Avg Change
                          </span>
                        )}
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
                    style={{ width: `${Math.abs(market.contribution) * 1000}%`, maxWidth: '100%' }}
                  />
                </div>
                
                <div className="flex items-center justify-between text-xs text-gray-600">
                  <span className="font-semibold">Contribution to prediction: <span className={market.textColor}>{market.contribution > 0 ? '+' : ''}{market.contribution.toFixed(3)}</span></span>
                  {market.avgChange !== 0 && (
                    <span className={`font-bold ${
                      market.avgChange > 0 ? 'text-green-600' : 'text-red-600'
                    }`}>
                      {market.avgChange > 0 ? '+' : ''}{market.avgChange.toFixed(2)}% Market Avg
                    </span>
                  )}
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
