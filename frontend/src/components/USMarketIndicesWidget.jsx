import { memo, useMemo, useCallback } from 'react';
import { useQuery } from '@tanstack/react-query';
import { 
  TrendingUp, TrendingDown, RefreshCw, BarChart3,
  Activity, Zap, Target, Flame
} from 'lucide-react';
import { marketAPI } from '../services/api';

/**
 * US Market Indices Widget - Displays market indices like homepage
 * Beautiful gradient cards matching homepage design
 */
export default function USMarketIndicesWidget() {
  const { data, isLoading, error, refetch, isFetching } = useQuery({
    queryKey: ['us-market-indices'],
    queryFn: async () => await marketAPI.getIndices(),
    refetchInterval: 5 * 60 * 1000,
    staleTime: 4 * 60 * 1000,
  });

  const handleRefetch = useCallback(() => {
    refetch();
  }, [refetch]);

  if (isLoading) {
    return <LoadingSkeleton />;
  }

  if (error) {
    return <ErrorState onRetry={handleRefetch} />;
  }

  const marketIndices = data?.data || {};
  const indicesLoading = isLoading;

  return (
    <>
      <div className="text-lg sm:text-xl md:text-2xl font-black text-gray-900 dark:text-white mb-3 sm:mb-4">
        US Market Indices
      </div>
      <div className="grid grid-cols-1 gap-3 sm:gap-4 md:gap-6">
        {/* S&P 500 Card */}
        <div className="group relative overflow-hidden rounded-xl sm:rounded-2xl bg-gradient-to-br from-blue-500 to-cyan-500 p-4 sm:p-6 shadow-lg sm:shadow-xl hover:shadow-2xl transition-all duration-500 hover:scale-105">
          <div className="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 group-hover:scale-150 transition-transform duration-500"></div>
          <div className="relative z-10">
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-2 min-w-0">
                <BarChart3 className="w-5 sm:w-6 h-5 sm:h-6 text-white flex-shrink-0" />
                <h3 className="text-base sm:text-lg md:text-xl font-bold text-white truncate">S&P 500</h3>
              </div>
              {marketIndices.sp500 && (
                <div className={`px-2 sm:px-3 py-1 rounded-full text-[10px] sm:text-xs font-bold text-white shadow-lg flex-shrink-0 ml-2 ${
                  Number(marketIndices.sp500.change_percent) >= 0 
                    ? 'bg-green-500/70' 
                    : 'bg-red-500/70'
                }`}>
                  {Number(marketIndices.sp500.change_percent) >= 0 ? <TrendingUp className="inline w-2.5 h-2.5 sm:w-3 sm:h-3" /> : <TrendingDown className="inline w-2.5 h-2.5 sm:w-3 sm:h-3" />}
                  {' '}{Number(marketIndices.sp500.change_percent) >= 0 ? '+' : ''}{Number(marketIndices.sp500.change_percent).toFixed(2)}%
                </div>
              )}
            </div>
            {!indicesLoading && marketIndices.sp500 ? (
              <>
                <div className="text-2xl sm:text-3xl font-black text-white mb-2">
                  ${Number(marketIndices.sp500.current_price).toFixed(2)}
                </div>
                <div className="flex items-center gap-1 sm:gap-2 text-blue-100">
                  <Activity className="w-3 sm:w-4 h-3 sm:h-4" />
                  <span className="text-xs sm:text-sm font-medium">
                    {marketIndices.sp500.change_percent >= 0 ? 'Bullish' : 'Bearish'}
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
        <div className="group relative overflow-hidden rounded-xl sm:rounded-2xl bg-gradient-to-br from-purple-500 to-pink-500 p-4 sm:p-6 shadow-lg sm:shadow-xl hover:shadow-2xl transition-all duration-500 hover:scale-105">
          <div className="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 group-hover:scale-150 transition-transform duration-500"></div>
          <div className="relative z-10">
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-2 min-w-0">
                <Zap className="w-5 sm:w-6 h-5 sm:h-6 text-white flex-shrink-0" />
                <h3 className="text-base sm:text-lg md:text-xl font-bold text-white truncate">NASDAQ</h3>
              </div>
              {marketIndices.nasdaq && (
                <div className={`px-2 sm:px-3 py-1 rounded-full text-[10px] sm:text-xs font-bold text-white shadow-lg flex-shrink-0 ml-2 ${
                  Number(marketIndices.nasdaq.change_percent) >= 0 
                    ? 'bg-green-500/70' 
                    : 'bg-red-500/70'
                }`}>
                  {Number(marketIndices.nasdaq.change_percent) >= 0 ? <TrendingUp className="inline w-2.5 h-2.5 sm:w-3 sm:h-3" /> : <TrendingDown className="inline w-2.5 h-2.5 sm:w-3 sm:h-3" />}
                  {' '}{Number(marketIndices.nasdaq.change_percent) >= 0 ? '+' : ''}{Number(marketIndices.nasdaq.change_percent).toFixed(2)}%
                </div>
              )}
            </div>
            {!indicesLoading && marketIndices.nasdaq ? (
              <>
                <div className="text-2xl sm:text-3xl font-black text-white mb-2">
                  ${Number(marketIndices.nasdaq.current_price).toFixed(2)}
                </div>
                <div className="flex items-center gap-1 sm:gap-2 text-purple-100">
                  <Activity className="w-3 sm:w-4 h-3 sm:h-4" />
                  <span className="text-xs sm:text-sm font-medium">
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
        <div className="group relative overflow-hidden rounded-xl sm:rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-500 p-4 sm:p-6 shadow-lg sm:shadow-xl hover:shadow-2xl transition-all duration-500 hover:scale-105">
          <div className="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 group-hover:scale-150 transition-transform duration-500"></div>
          <div className="relative z-10">
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-2 min-w-0">
                <Target className="w-5 sm:w-6 h-5 sm:h-6 text-white flex-shrink-0" />
                <h3 className="text-base sm:text-lg md:text-xl font-bold text-white truncate">DOW JONES</h3>
              </div>
              {marketIndices.dow && (
                <div className={`px-2 sm:px-3 py-1 rounded-full text-[10px] sm:text-xs font-bold text-white shadow-lg flex-shrink-0 ml-2 ${
                  Number(marketIndices.dow.change_percent) >= 0 
                    ? 'bg-green-500/70' 
                    : 'bg-red-500/70'
                }`}>
                  {Number(marketIndices.dow.change_percent) >= 0 ? <TrendingUp className="inline w-2.5 h-2.5 sm:w-3 sm:h-3" /> : <TrendingDown className="inline w-2.5 h-2.5 sm:w-3 sm:h-3" />}
                  {' '}{Number(marketIndices.dow.change_percent) >= 0 ? '+' : ''}{Number(marketIndices.dow.change_percent).toFixed(2)}%
                </div>
              )}
            </div>
            {!indicesLoading && marketIndices.dow ? (
              <>
                <div className="text-2xl sm:text-3xl font-black text-white mb-2">
                  ${Number(marketIndices.dow.current_price).toFixed(2)}
                </div>
                <div className="flex items-center gap-1 sm:gap-2 text-emerald-100">
                  <Activity className="w-3 sm:w-4 h-3 sm:h-4" />
                  <span className="text-xs sm:text-sm font-medium">
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

        {/* Russell 2000 Card */}
        <div className="group relative overflow-hidden rounded-xl sm:rounded-2xl bg-gradient-to-br from-orange-500 to-amber-500 p-4 sm:p-6 shadow-lg sm:shadow-xl hover:shadow-2xl transition-all duration-500 hover:scale-105">
          <div className="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 group-hover:scale-150 transition-transform duration-500"></div>
          <div className="relative z-10">
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-2 min-w-0">
                <Flame className="w-5 sm:w-6 h-5 sm:h-6 text-white flex-shrink-0" />
                <h3 className="text-base sm:text-lg md:text-xl font-bold text-white truncate">Russell 2000</h3>
              </div>
              {marketIndices.russell2000 && (
                <div className={`px-2 sm:px-3 py-1 rounded-full text-[10px] sm:text-xs font-bold text-white shadow-lg flex-shrink-0 ml-2 ${
                  Number(marketIndices.russell2000.change_percent) >= 0 
                    ? 'bg-green-500/70' 
                    : 'bg-red-500/70'
                }`}>
                  {Number(marketIndices.russell2000.change_percent) >= 0 ? <TrendingUp className="inline w-2.5 h-2.5 sm:w-3 sm:h-3" /> : <TrendingDown className="inline w-2.5 h-2.5 sm:w-3 sm:h-3" />}
                  {' '}{Number(marketIndices.russell2000.change_percent) >= 0 ? '+' : ''}{Number(marketIndices.russell2000.change_percent).toFixed(2)}%
                </div>
              )}
            </div>
            {!indicesLoading && marketIndices.russell2000 ? (
              <>
                <div className="text-2xl sm:text-3xl font-black text-white mb-2">
                  ${Number(marketIndices.russell2000.current_price).toFixed(2)}
                </div>
                <div className="flex items-center gap-1 sm:gap-2 text-orange-100">
                  <Activity className="w-3 sm:w-4 h-3 sm:h-4" />
                  <span className="text-xs sm:text-sm font-medium">
                    {marketIndices.russell2000.change_percent >= 0 ? 'Bullish' : 'Bearish'} Trend
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
    </>
  );
}

/**
 * Loading Skeleton - Memoized
 */

/**
 * Loading Skeleton - Memoized
 */
const LoadingSkeleton = memo(() => (
  <div className="bg-white rounded-2xl shadow-xl p-4 sm:p-6 animate-pulse">
    <div className="h-6 bg-gray-200 rounded w-1/3 mb-4" />
    <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
      {[1, 2, 3, 4].map((i) => (
        <div key={i} className="h-32 bg-gray-200 rounded-lg" />
      ))}
    </div>
  </div>
));

LoadingSkeleton.displayName = 'LoadingSkeleton';

/**
 * Error State - Memoized
 */
const ErrorState = memo(({ onRetry }) => (
  <div className="bg-gradient-to-br from-red-50 to-pink-50 rounded-2xl shadow-lg p-4 sm:p-6 border-2 border-red-200">
    <div className="flex items-center gap-2 mb-4">
      <BarChart3 className="w-5 h-5 text-red-600" />
      <h3 className="text-lg font-bold text-gray-900">US Markets</h3>
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
