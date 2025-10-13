import { memo, Suspense } from 'react';
import PropTypes from 'prop-types';
import { GenericLoader } from '../loaders';
import { ChevronDown, Loader2, AlertCircle } from 'lucide-react';

const MarketNewsGrid = memo(function MarketNewsGrid({ items, loading, error, onLoadMore, hasMore, CardComponent }) {
  return (
    <div>
      {/* Error Message */}
      {error && (
        <div className="mb-6 group relative">
          <div className="absolute -inset-0.5 bg-gradient-to-r from-red-500 to-rose-500 rounded-2xl opacity-20 blur-sm"></div>
          <div className="relative bg-red-50 dark:bg-red-900/20 border-2 border-red-200 dark:border-red-800 rounded-2xl p-4 shadow-lg">
            <div className="flex items-center gap-3">
              <div className="flex-shrink-0">
                <div className="p-2 rounded-xl bg-gradient-to-br from-red-500 to-rose-500 shadow-lg">
                  <AlertCircle className="w-5 h-5 text-white" />
                </div>
              </div>
              <div className="flex-1">
                <h3 className="text-sm font-black text-red-900 dark:text-red-100 mb-1">Error Loading News</h3>
                <p className="text-xs text-red-700 dark:text-red-200">{error}</p>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Loading State */}
      {loading && items.length === 0 ? (
        <div className="relative">
          <div className="absolute -inset-0.5 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 rounded-2xl opacity-20 blur-sm animate-pulse"></div>
          <div className="relative bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl border border-gray-200/50 dark:border-gray-800/50 rounded-2xl shadow-xl p-12">
            <GenericLoader message="Loading market news" size="medium" fullScreen={false} />
          </div>
        </div>
      ) : items.length === 0 ? (
        /* Empty State */
        <div className="relative">
          <div className="absolute -inset-0.5 bg-gradient-to-r from-gray-400 to-gray-500 rounded-2xl opacity-10 blur-sm"></div>
          <div className="relative bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl border border-gray-200/50 dark:border-gray-800/50 rounded-2xl shadow-xl p-12 text-center">
            <div className="w-24 h-24 mx-auto mb-6 rounded-3xl bg-gradient-to-br from-gray-400 to-gray-500 flex items-center justify-center">
              <span className="text-5xl">📰</span>
            </div>
            <h3 className="text-2xl font-black mb-2 bg-clip-text text-transparent bg-gradient-to-r from-gray-600 to-gray-800 dark:from-gray-300 dark:to-gray-100">
              No News Found
            </h3>
            <p className="text-gray-600 dark:text-gray-400">Try adjusting your filters or search terms</p>
          </div>
        </div>
      ) : (
        <Suspense fallback={<GenericLoader message="Loading news" size="small" fullScreen={false} />}>
          {/* News Grid */}
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            {items.map((article, idx) => (
              <div 
                key={article.url || idx} 
                className="animate-fade-in-up"
                style={{ 
                  animationDelay: `${(idx % 9) * 50}ms`,
                  animationFillMode: 'backwards'
                }}
              >
                <CardComponent article={article} />
              </div>
            ))}
          </div>
          
          {/* Load More Button */}
          {hasMore && (
            <div className="flex justify-center">
              <button
                onClick={onLoadMore}
                disabled={loading}
                className="group relative inline-flex items-center gap-3 px-10 py-5 rounded-2xl font-black transition-all duration-500 disabled:opacity-50 disabled:cursor-not-allowed overflow-hidden shadow-xl hover:shadow-2xl transform hover:scale-105 active:scale-95"
              >
                {/* Animated gradient backgrounds */}
                <div className="absolute inset-0 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600"></div>
                <div className="absolute inset-0 bg-gradient-to-r from-cyan-600 via-indigo-600 to-purple-600 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                
                {/* Shimmer effect */}
                <div className="absolute inset-0 -translate-x-full group-hover:translate-x-full transition-transform duration-1000 bg-gradient-to-r from-transparent via-white/30 to-transparent"></div>
                
                {/* Pulsing border */}
                <div className="absolute -inset-1 bg-gradient-to-r from-cyan-500 via-purple-500 to-pink-500 rounded-2xl opacity-0 group-hover:opacity-50 blur animate-pulse"></div>
                
                <span className="relative z-10 flex items-center gap-3 text-white">
                  {loading ? (
                    <>
                      <Loader2 className="w-5 h-5 animate-spin" />
                      <span>Loading More News...</span>
                    </>
                  ) : (
                    <>
                      <span>Load More Articles</span>
                      <ChevronDown className="w-5 h-5 group-hover:translate-y-1 transition-transform" />
                    </>
                  )}
                </span>
              </button>
            </div>
          )}
        </Suspense>
      )}
    </div>
  );
});

MarketNewsGrid.propTypes = {
  items: PropTypes.array.isRequired,
  loading: PropTypes.bool,
  error: PropTypes.string,
  onLoadMore: PropTypes.func.isRequired,
  hasMore: PropTypes.bool,
  CardComponent: PropTypes.elementType.isRequired,
};

export default MarketNewsGrid;
