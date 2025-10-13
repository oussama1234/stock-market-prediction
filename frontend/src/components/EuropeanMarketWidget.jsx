import { memo, useMemo, useCallback } from 'react';
import { useQuery } from '@tanstack/react-query';
import { 
  TrendingUp, TrendingDown, Minus, RefreshCw, Globe2, 
  Zap, BarChart3, Activity, ArrowUpRight, ArrowDownRight
} from 'lucide-react';
import { marketAPI } from '../services/api';

/**
 * Enhanced European Market Widget - Modern, animated, performant
 * 
 * Features:
 * - Stunning gradients and glass morphism
 * - Smooth animations and hover effects
 * - Performance optimized with memoization
 * - Real-time market indicators
 * - Impact visualization with 50% weight
 */
export default function EuropeanMarketWidgetEnhanced({ compact = false }) {
  const { data, isLoading, error, refetch, isFetching } = useQuery({
    queryKey: ['european-markets'],
    queryFn: async () => await marketAPI.getEuropeanMarkets(),
    refetchInterval: 5 * 60 * 1000,
    staleTime: 4 * 60 * 1000,
  });

  const handleRefetch = useCallback(() => {
    refetch();
  }, [refetch]);

  if (isLoading) {
    return <LoadingSkeleton compact={compact} />;
  }

  if (error) {
    return <ErrorState onRetry={handleRefetch} />;
  }

  const markets = data?.data || {};
  const meta = data?.meta || {};

  if (compact) {
    return <CompactView markets={markets} meta={meta} onRefetch={handleRefetch} isFetching={isFetching} />;
  }

  return (
    <FullView 
      markets={markets} 
      meta={meta} 
      onRefetch={handleRefetch}
      isFetching={isFetching}
    />
  );
}

/**
 * Full View - Memoized
 */
const FullView = memo(({ markets, meta, onRefetch, isFetching }) => {
  const avgChange = meta.european_avg_change || 0;
  const isPositive = avgChange > 0;

  return (
    <div className="relative group">
      {/* Animated Gradient Glow */}
      <div className={`absolute -inset-0.5 bg-gradient-to-r ${
        isPositive 
          ? 'from-emerald-400 via-green-400 to-teal-400' 
          : 'from-rose-400 via-red-400 to-orange-400'
      } rounded-2xl opacity-20 blur-lg group-hover:opacity-30 transition-all duration-500`} />
      
      {/* Card Container */}
      <div className="relative bg-white/90 backdrop-blur-xl rounded-2xl shadow-2xl border border-gray-200/50 overflow-hidden">
        {/* Animated Background */}
        <div className="absolute inset-0 opacity-5">
          <div className="absolute inset-0 bg-gradient-to-br from-blue-50 via-indigo-50 to-violet-50" />
          <div className="absolute inset-0" style={{
            backgroundImage: `radial-gradient(circle at 20% 50%, rgba(59, 130, 246, 0.1) 0%, transparent 50%),
                              radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.1) 0%, transparent 50%)`
          }} />
        </div>

        <div className="relative p-6">
          {/* Header */}
          <HeaderSection 
            onRefetch={onRefetch}
            isFetching={isFetching}
            avgChange={avgChange}
          />

          {/* Market Cards Grid */}
          <div className="grid grid-cols-2 gap-3 mb-6">
            {Object.entries(markets).map(([key, market]) => (
              <MarketCardEnhanced key={key} market={market} />
            ))}
          </div>

          {/* European Influence Meter */}
          {meta.european_influence_score !== undefined && (
            <InfluenceMeterEnhanced
              score={meta.european_influence_score}
              impactPercent={meta.european_impact_percent}
              avgChange={meta.european_avg_change}
            />
          )}

          {/* Timestamp */}
          {meta.timestamp && (
            <div className="mt-4 flex items-center justify-center gap-2 text-xs text-gray-500">
              <Activity className="w-3 h-3" />
              <span>Updated: {new Date(meta.timestamp).toLocaleTimeString()}</span>
            </div>
          )}
        </div>
      </div>
    </div>
  );
});

FullView.displayName = 'FullView';

/**
 * Header Section - Memoized
 */
const HeaderSection = memo(({ onRefetch, isFetching, avgChange }) => {
  const isPositive = avgChange > 0;

  return (
    <div className="flex items-center justify-between mb-6">
      <div className="flex items-center gap-3">
        <div className="relative">
          <Globe2 className="w-7 h-7 text-blue-600 animate-pulse" />
          <div className="absolute inset-0 blur-lg bg-blue-400 opacity-50" />
        </div>
        <div>
          <h3 className="text-xl font-black bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">
            European Markets
          </h3>
          <div className="flex items-center gap-2 mt-1">
            <span className="text-xs px-2 py-0.5 bg-gradient-to-r from-blue-400 to-indigo-400 text-white rounded-full font-bold shadow-sm animate-pulse">
              LIVE
            </span>
            <span className={`text-sm font-black ${isPositive ? 'text-green-600' : 'text-red-600'}`}>
              {isPositive ? '↗' : '↘'} {isPositive ? '+' : ''}{avgChange.toFixed(2)}%
            </span>
            <span className="text-xs px-2 py-0.5 bg-gradient-to-r from-purple-500 to-pink-500 text-white rounded-full font-bold shadow-sm">
              30% Weight
            </span>
          </div>
        </div>
      </div>
      <button
        onClick={onRefetch}
        disabled={isFetching}
        className="group p-2.5 hover:bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl transition-all duration-300 hover:scale-110 active:scale-95"
        title="Refresh data"
      >
        <RefreshCw className={`w-5 h-5 text-blue-600 group-hover:text-indigo-600 transition-colors ${isFetching ? 'animate-spin' : ''}`} />
      </button>
    </div>
  );
});

HeaderSection.displayName = 'HeaderSection';

/**
 * Enhanced Market Card - Memoized
 */
const MarketCardEnhanced = memo(({ market }) => {
  const isPositive = market.change_percent > 0;
  const isNeutral = market.change_percent === 0;
  const hasError = market.error;

  const theme = useMemo(() => {
    if (hasError) {
      return {
        gradient: 'from-gray-400 to-gray-500',
        bgGradient: 'from-gray-50 to-gray-100',
        border: 'border-gray-300',
        textColor: 'text-gray-500',
        icon: Minus,
      };
    }
    if (isPositive) {
      return {
        gradient: 'from-emerald-400 via-green-400 to-teal-400',
        bgGradient: 'from-emerald-50 via-green-50 to-teal-50',
        border: 'border-emerald-300',
        textColor: 'text-emerald-600',
        icon: ArrowUpRight,
      };
    }
    if (isNeutral) {
      return {
        gradient: 'from-blue-400 to-indigo-400',
        bgGradient: 'from-blue-50 to-indigo-50',
        border: 'border-blue-300',
        textColor: 'text-blue-600',
        icon: Minus,
      };
    }
    return {
      gradient: 'from-rose-400 via-red-400 to-orange-400',
      bgGradient: 'from-rose-50 via-red-50 to-orange-50',
      border: 'border-rose-300',
      textColor: 'text-rose-600',
      icon: ArrowDownRight,
    };
  }, [isPositive, isNeutral, hasError]);

  const Icon = theme.icon;

  return (
    <div className="relative">
      <div className={`relative bg-gradient-to-br ${theme.bgGradient} rounded-xl p-4 border-2 ${theme.border} shadow-lg`}>
        <div className="flex items-start justify-between mb-2">
          <div className="flex-1">
            <div className="text-xs font-bold text-gray-600 mb-1 truncate" title={market.name}>
              {market.name}
            </div>
            {!hasError && (
              <div className={`text-2xl font-black ${theme.textColor} flex items-center gap-1`}>
                {isPositive ? '+' : ''}{market.change_percent?.toFixed(2)}%
              </div>
            )}
            {hasError && (
              <div className="text-xs text-gray-400 font-medium">No data</div>
            )}
          </div>
          <div className={`flex-shrink-0 w-10 h-10 rounded-full bg-white flex items-center justify-center shadow-md`}>
            <Icon className={`w-5 h-5 ${theme.textColor}`} />
          </div>
        </div>

        {market.price && !hasError && (
          <div className="flex items-center justify-between pt-2 border-t border-white/50">
            <span className="text-xs text-gray-600 font-medium">Index</span>
            <span className="text-xs font-bold text-gray-700">
              {market.price.toLocaleString()}
            </span>
          </div>
        )}
      </div>
    </div>
  );
});

MarketCardEnhanced.displayName = 'MarketCardEnhanced';

/**
 * Enhanced Influence Meter - Memoized
 */
const InfluenceMeterEnhanced = memo(({ score, impactPercent, avgChange }) => {
  const isBullish = score > 0;
  const isNeutral = Math.abs(score) < 0.1;
  
  const strength = Math.abs(score) > 0.6 ? 'Strong' : Math.abs(score) > 0.3 ? 'Moderate' : 'Weak';
  const direction = isBullish ? 'Bullish' : 'Bearish';

  const theme = useMemo(() => ({
    gradient: isBullish 
      ? 'from-emerald-400 via-green-400 to-teal-400'
      : 'from-rose-400 via-red-400 to-orange-400',
    bgColor: isBullish ? 'bg-emerald-50' : 'bg-rose-50',
    textColor: isBullish ? 'text-emerald-700' : 'text-rose-700',
    badgeBg: isBullish ? 'from-emerald-500 to-green-500' : 'from-rose-500 to-red-500',
  }), [isBullish]);

  return (
    <div className="pt-6 border-t-2 border-gray-200">
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-2">
          <Zap className="w-5 h-5 text-blue-600" />
          <span className="text-sm font-black text-gray-900">Market Influence</span>
          <span className="text-xs px-2 py-0.5 bg-gradient-to-r from-purple-500 to-pink-500 text-white rounded-full font-bold">
            30% Weight
          </span>
        </div>
        {!isNeutral && (
          <div className={`px-3 py-1 rounded-full text-xs font-bold text-white bg-gradient-to-r ${theme.badgeBg} shadow-lg`}>
            {strength} {direction}
          </div>
        )}
        {isNeutral && (
          <div className="px-3 py-1 rounded-full text-xs font-bold text-gray-600 bg-gray-200">
            Neutral
          </div>
        )}
      </div>

      {/* Animated Progress Bar */}
      <div className="relative h-4 bg-gradient-to-r from-gray-200 to-gray-300 rounded-full overflow-hidden mb-4 shadow-inner">
        <div
          className={`h-full bg-gradient-to-r ${theme.gradient} transition-all duration-1000 ease-out relative`}
          style={{ width: `${Math.abs(score) * 100}%` }}
        >
          <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-40 animate-shimmer" />
        </div>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-3 gap-3">
        <div className="text-center p-3 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl border border-blue-200">
          <div className="flex items-center justify-center gap-1 mb-1">
            <BarChart3 className="w-3 h-3 text-blue-600" />
            <div className="text-xs text-gray-600 font-semibold">Score</div>
          </div>
          <div className={`text-lg font-black ${theme.textColor}`}>
            {score > 0 ? '+' : ''}{score.toFixed(2)}
          </div>
        </div>
        
        <div className="text-center p-3 bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl border border-purple-200">
          <div className="flex items-center justify-center gap-1 mb-1">
            <Activity className="w-3 h-3 text-purple-600" />
            <div className="text-xs text-gray-600 font-semibold">Impact</div>
          </div>
          <div className="text-lg font-black text-purple-600">
            {(impactPercent * 100).toFixed(0)}%
          </div>
        </div>
        
        <div className="text-center p-3 bg-gradient-to-br from-orange-50 to-amber-50 rounded-xl border border-orange-200">
          <div className="flex items-center justify-center gap-1 mb-1">
            <TrendingUp className="w-3 h-3 text-orange-600" />
            <div className="text-xs text-gray-600 font-semibold">Avg</div>
          </div>
          <div className={`text-lg font-black ${theme.textColor}`}>
            {avgChange > 0 ? '+' : ''}{avgChange.toFixed(2)}%
          </div>
        </div>
      </div>

      {/* Info Box */}
      <div className="mt-4 p-4 bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 rounded-xl border-2 border-blue-200">
        <p className="text-xs text-blue-900 leading-relaxed font-medium">
          <strong className="font-black">Impact Analysis:</strong> European markets have{' '}
          <span className="font-black text-indigo-600">30%</span>{' '}
          weight in today's predictions with a{' '}
          <span className={`font-black ${theme.textColor}`}>{strength.toLowerCase()} {!isNeutral && direction.toLowerCase()}</span>{' '}
          influence (score: {score.toFixed(2)}). Local US factors now have the highest weight (50%).
        </p>
      </div>
    </div>
  );
});

InfluenceMeterEnhanced.displayName = 'InfluenceMeterEnhanced';

/**
 * Compact View - Memoized
 */
const CompactView = memo(({ markets, meta, onRefetch, isFetching }) => {
  const avgChange = meta.european_avg_change || 0;
  const isPositive = avgChange > 0;

  return (
    <div className="bg-gradient-to-br from-white to-blue-50 rounded-xl shadow-lg p-4 border border-blue-200 hover:shadow-xl transition-shadow">
      <div className="flex items-center justify-between mb-3">
        <div className="flex items-center gap-2">
          <Globe2 className="w-4 h-4 text-blue-600" />
          <span className="text-sm font-bold text-gray-900">European Markets</span>
          <span className="text-xs px-1.5 py-0.5 bg-purple-500 text-white rounded-full font-bold">
            30%
          </span>
        </div>
        <div className="flex items-center gap-2">
          <span className={`text-sm font-black ${isPositive ? 'text-green-600' : 'text-red-600'}`}>
            {isPositive ? '+' : ''}{avgChange.toFixed(2)}%
          </span>
          <button
            onClick={onRefetch}
            disabled={isFetching}
            className="p-1 hover:bg-blue-100 rounded transition-colors"
          >
            <RefreshCw className={`w-3 h-3 text-blue-600 ${isFetching ? 'animate-spin' : ''}`} />
          </button>
        </div>
      </div>

      <div className="space-y-2">
        {Object.entries(markets).slice(0, 3).map(([key, market]) => (
          <div key={key} className="flex items-center justify-between text-xs p-2 bg-white rounded-lg shadow-sm">
            <span className="text-gray-700 font-medium">{market.name.split(' ')[0]}</span>
            <span className={`font-bold ${market.change_percent > 0 ? 'text-green-600' : 'text-red-600'}`}>
              {market.change_percent > 0 ? '+' : ''}{market.change_percent?.toFixed(2)}%
            </span>
          </div>
        ))}
      </div>
    </div>
  );
});

CompactView.displayName = 'CompactView';

/**
 * Loading Skeleton - Memoized
 */
const LoadingSkeleton = memo(({ compact }) => {
  if (compact) {
    return (
      <div className="bg-white rounded-xl shadow-lg p-4 animate-pulse">
        <div className="h-4 bg-blue-200 rounded w-2/3 mb-3" />
        <div className="space-y-2">
          <div className="h-8 bg-blue-200 rounded" />
          <div className="h-8 bg-blue-200 rounded" />
          <div className="h-8 bg-blue-200 rounded" />
        </div>
      </div>
    );
  }

  return (
    <div className="bg-white rounded-2xl shadow-xl p-6 animate-pulse">
      <div className="h-6 bg-blue-200 rounded w-1/2 mb-6" />
      <div className="grid grid-cols-2 gap-3 mb-6">
        {[1, 2, 3, 4, 5].map((i) => (
          <div key={i} className="h-24 bg-blue-200 rounded-xl" />
        ))}
      </div>
      <div className="h-16 bg-blue-200 rounded-xl" />
    </div>
  );
});

LoadingSkeleton.displayName = 'LoadingSkeleton';

/**
 * Error State - Memoized
 */
const ErrorState = memo(({ onRetry }) => (
  <div className="bg-gradient-to-br from-red-50 to-pink-50 rounded-xl shadow-lg p-6 border-2 border-red-200">
    <div className="flex items-center gap-2 mb-4">
      <Globe2 className="w-5 h-5 text-red-600" />
      <h3 className="text-lg font-bold text-gray-900">European Markets</h3>
    </div>
    <div className="text-center py-4">
      <p className="text-sm text-red-600 mb-3 font-medium">Failed to load market data</p>
      <button
        onClick={onRetry}
        className="px-4 py-2 bg-gradient-to-r from-red-500 to-rose-500 text-white rounded-lg hover:from-red-600 hover:to-rose-600 transition-all font-semibold text-sm shadow-lg transform hover:scale-105"
      >
        Try Again
      </button>
    </div>
  </div>
));

ErrorState.displayName = 'ErrorState';

/**
 * Hook for using European market data
 */
export function useEuropeanMarkets() {
  return useQuery({
    queryKey: ['european-markets'],
    queryFn: async () => await marketAPI.getEuropeanMarkets(),
    refetchInterval: 5 * 60 * 1000,
    staleTime: 4 * 60 * 1000,
  });
}
