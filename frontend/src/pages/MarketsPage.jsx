import { useEffect, useMemo, useRef, useCallback, lazy, Suspense, useState } from 'react';
import useMarketNews from '../hooks/useMarketNews';
import { GenericLoader } from '../components/loaders';
import MarketFilters from '../components/markets/MarketFilters';
import MarketNewsGrid from '../components/markets/MarketNewsGrid';
import { Newspaper, TrendingUp, Activity } from 'lucide-react';

const NewsCard = lazy(() => import('../components/NewsCard'));

function MarketsPage() {
  const {
    items,
    loading,
    error,
    query,
    datePreset,
    from,
    to,
    hasMore,
    lastUpdated,
    totalCount,
    setQuery,
    setDatePreset,
    setFrom,
    setTo,
    resetAndFetch,
    loadMore,
  } = useMarketNews();

  const [currentTime, setCurrentTime] = useState(new Date());

  // Initial fetch on mount
  useEffect(() => {
    resetAndFetch();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Update current time every second for live "ago" updates
  useEffect(() => {
    const interval = setInterval(() => {
      setCurrentTime(new Date());
    }, 1000);
    return () => clearInterval(interval);
  }, []);

  // Refs for potential animations/observers
  const headerRef = useRef(null);

  const updatedText = useMemo(() => {
    const diffSec = Math.max(0, Math.floor((currentTime - lastUpdated) / 1000));
    
    if (diffSec < 60) {
      return `${diffSec}s ago`;
    } else if (diffSec < 3600) {
      const minutes = Math.floor(diffSec / 60);
      return `${minutes}m ago`;
    } else if (diffSec < 86400) {
      const hours = Math.floor(diffSec / 3600);
      return `${hours}h ago`;
    } else {
      const days = Math.floor(diffSec / 86400);
      return `${days}d ago`;
    }
  }, [currentTime, lastUpdated]);

  const onApply = useCallback(async () => {
    await resetAndFetch();
  }, [resetAndFetch]);

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 dark:from-gray-900 dark:via-gray-850 dark:to-gray-900 transition-colors duration-300">
      {/* Modern Header - No Hero, Just Clean Title */}
      <div className="relative bg-white/60 dark:bg-gray-900/60 backdrop-blur-xl border-b border-gray-200/50 dark:border-gray-800/50">
        <div className="container mx-auto px-4 py-8">
          <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
              <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-gradient-to-r from-emerald-500/10 via-green-500/10 to-teal-500/10 border border-emerald-500/20 mb-3">
                <span className="relative flex h-2 w-2">
                  <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-500 opacity-75"></span>
                  <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                <span className="text-xs font-bold bg-clip-text text-transparent bg-gradient-to-r from-emerald-600 to-teal-600">LIVE UPDATES</span>
              </div>
              <h1 className="text-4xl md:text-5xl font-black mb-2 bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600">
                Market News
              </h1>
              <p className="text-gray-600 dark:text-gray-400 text-base md:text-lg max-w-2xl">
                Real-time news aggregated from multiple sources • AI-powered importance ranking • Advanced filtering
              </p>
            </div>
            
            {/* Stats Pills */}
            <div className="flex flex-wrap gap-3">
              <div className="group relative overflow-hidden px-4 py-3 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-500 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105">
                <div className="absolute top-0 right-0 w-16 h-16 bg-white/20 rounded-full -mr-8 -mt-8 group-hover:scale-150 transition-transform duration-500"></div>
                <div className="relative flex items-center gap-2">
                  <Newspaper className="w-5 h-5 text-white" />
                  <div>
                    <div className="text-xs font-medium text-blue-100">Articles</div>
                    <div className="text-xl font-black text-white">{totalCount || items.length}</div>
                  </div>
                </div>
              </div>
              
              <div className="group relative overflow-hidden px-4 py-3 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105">
                <div className="absolute top-0 right-0 w-16 h-16 bg-white/20 rounded-full -mr-8 -mt-8 group-hover:scale-150 transition-transform duration-500"></div>
                <div className="relative flex items-center gap-2">
                  <Activity className="w-5 h-5 text-white" />
                  <div>
                    <div className="text-xs font-medium text-purple-100">Updated</div>
                    <div className="text-sm font-bold text-white">{updatedText}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Filters Card Block */}
      <div className="container mx-auto px-4 py-8">
        <MarketFilters
          query={query}
          setQuery={setQuery}
          datePreset={datePreset}
          setDatePreset={setDatePreset}
          from={from}
          setFrom={setFrom}
          to={to}
          setTo={setTo}
          onApply={onApply}
          disabled={loading}
          loading={loading}
        />
      </div>

      {/* News Grid */}
      <div className="container mx-auto px-4 pb-12">
        <Suspense fallback={<GenericLoader message="Loading news" size="medium" fullScreen={false} />}>
          <MarketNewsGrid
            items={items}
            loading={loading}
            error={error}
            hasMore={hasMore}
            onLoadMore={loadMore}
            CardComponent={NewsCard}
          />
        </Suspense>
      </div>
    </div>
  );
}

export default MarketsPage;
