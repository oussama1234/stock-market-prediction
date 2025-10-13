import { memo, useCallback, useMemo } from 'react';
import PropTypes from 'prop-types';
import { Search, Calendar, Filter, Sparkles } from 'lucide-react';

const presets = [
  { key: 'today', label: 'Today' },
  { key: 'last_7d', label: 'Last 7 days' },
  { key: 'last_30d', label: 'Last 30 days' },
  { key: 'custom', label: 'Custom' },
];

const MarketFilters = memo(function MarketFilters({
  query,
  setQuery,
  datePreset,
  setDatePreset,
  from,
  setFrom,
  to,
  setTo,
  onApply,
  disabled,
  loading,
}) {
  const isCustom = datePreset === 'custom';

  const onPresetClick = useCallback((key) => {
    setDatePreset(key);
  }, [setDatePreset]);

  const applyDisabled = useMemo(() => disabled || (isCustom && !(from && to)), [disabled, isCustom, from, to]);

  return (
    <div className="group relative">
      {/* Colorful gradient border effect */}
      <div className="absolute -inset-0.5 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 rounded-2xl opacity-20 group-hover:opacity-40 blur-sm transition-opacity duration-500"></div>
      
      <div className="relative bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl border border-gray-200/50 dark:border-gray-800/50 rounded-2xl shadow-xl p-6 md:p-8">
        {/* Header with icon */}
        <div className="flex items-center gap-3 mb-6">
          <div className="p-2 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-500 shadow-lg">
            <Filter className="w-5 h-5 text-white" />
          </div>
          <h2 className="text-xl font-black bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600">
            Filter News
          </h2>
        </div>

        <div className="space-y-6">
          {/* Keyword search with enhanced styling */}
          <div>
            <label className="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">
              <Search className="w-4 h-4 text-indigo-600" />
              Search Keywords
            </label>
            <div className="group/input relative">
              {/* Gradient glow on focus */}
              <div className="absolute -inset-0.5 bg-gradient-to-r from-cyan-500 via-purple-500 to-pink-500 rounded-xl opacity-0 group-focus-within/input:opacity-100 blur transition-opacity duration-500"></div>
              
              <div className="relative flex items-center">
                <div className="absolute left-4 text-gray-400 group-focus-within/input:text-purple-600 transition-colors">
                  <Search className="w-5 h-5" />
                </div>
                <input
                  type="text"
                  value={query}
                  onChange={(e) => setQuery(e.target.value)}
                  placeholder="e.g. tariff, ban, raised, guidance, earnings"
                  className="relative w-full pl-12 pr-4 py-4 rounded-xl bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-2 border-gray-200 dark:border-gray-700 focus:border-purple-500 dark:focus:border-purple-500 focus:outline-none transition-all duration-300 font-medium placeholder:text-gray-400"
                />
              </div>
            </div>
            <p className="mt-2 text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
              <Sparkles className="w-3 h-3" />
              Try: <span className="font-bold text-red-600">tariff</span>, <span className="font-bold text-green-600">raised</span>, <span className="font-bold text-blue-600">earnings</span>
            </p>
          </div>

          {/* Date presets with colorful pills */}
          <div>
            <label className="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">
              <Calendar className="w-4 h-4 text-indigo-600" />
              Time Period
            </label>
            <div className="flex flex-wrap gap-3">
              {presets.map((p, idx) => {
                const gradients = [
                  'from-blue-500 to-cyan-500',
                  'from-purple-500 to-pink-500',
                  'from-emerald-500 to-teal-500',
                  'from-orange-500 to-red-500',
                ];
                const gradient = gradients[idx % gradients.length];
                const isActive = datePreset === p.key;
                
                return (
                  <button
                    key={p.key}
                    type="button"
                    onClick={() => onPresetClick(p.key)}
                    className={`group/btn relative px-5 py-3 rounded-xl text-sm font-bold transition-all duration-300 overflow-hidden ${
                      isActive
                        ? 'text-white shadow-lg transform scale-105'
                        : 'text-gray-700 dark:text-gray-300 hover:scale-105'
                    }`}
                  >
                    {isActive ? (
                      <>
                        <div className={`absolute inset-0 bg-gradient-to-r ${gradient}`}></div>
                        <div className="absolute inset-0 bg-white/20 animate-pulse"></div>
                      </>
                    ) : (
                      <>
                        <div className="absolute inset-0 bg-gray-100 dark:bg-gray-800 group-hover/btn:bg-gray-200 dark:group-hover/btn:bg-gray-700 transition-colors"></div>
                        <div className={`absolute inset-0 bg-gradient-to-r ${gradient} opacity-0 group-hover/btn:opacity-20 transition-opacity`}></div>
                      </>
                    )}
                    <span className="relative z-10">{p.label}</span>
                  </button>
                );
              })}
            </div>
          </div>

          {/* Custom date range with enhanced inputs */}
          {isCustom && (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2 animate-slide-down">
              <div>
                <label className="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">
                  <Calendar className="w-4 h-4 text-emerald-600" />
                  From Date
                </label>
                <div className="group/date relative">
                  <div className="absolute -inset-0.5 bg-gradient-to-r from-emerald-500 to-teal-500 rounded-xl opacity-0 group-focus-within/date:opacity-100 blur transition-opacity duration-500"></div>
                  <input
                    type="date"
                    value={from || ''}
                    onChange={(e) => setFrom(e.target.value)}
                    className="relative w-full px-4 py-3 rounded-xl bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-2 border-gray-200 dark:border-gray-700 focus:border-emerald-500 dark:focus:border-emerald-500 focus:outline-none transition-all font-medium"
                  />
                </div>
              </div>
              <div>
                <label className="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">
                  <Calendar className="w-4 h-4 text-pink-600" />
                  To Date
                </label>
                <div className="group/date relative">
                  <div className="absolute -inset-0.5 bg-gradient-to-r from-pink-500 to-rose-500 rounded-xl opacity-0 group-focus-within/date:opacity-100 blur transition-opacity duration-500"></div>
                  <input
                    type="date"
                    value={to || ''}
                    onChange={(e) => setTo(e.target.value)}
                    className="relative w-full px-4 py-3 rounded-xl bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-2 border-gray-200 dark:border-gray-700 focus:border-pink-500 dark:focus:border-pink-500 focus:outline-none transition-all font-medium"
                  />
                </div>
              </div>
            </div>
          )}

          {/* Apply button with enhanced gradients */}
          <div className="flex justify-end pt-2">
            <button
              type="button"
              disabled={applyDisabled}
              onClick={onApply}
              className="group/apply relative inline-flex items-center gap-3 px-8 py-4 rounded-xl font-bold transition-all disabled:opacity-50 disabled:cursor-not-allowed overflow-hidden shadow-lg hover:shadow-2xl transform hover:scale-105 active:scale-95"
            >
              <div className="absolute inset-0 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600"></div>
              <div className="absolute inset-0 bg-gradient-to-r from-cyan-600 via-indigo-600 to-purple-600 opacity-0 group-hover/apply:opacity-100 transition-opacity duration-500"></div>
              {/* Shimmer effect */}
              <div className="absolute inset-0 -translate-x-full group-hover/apply:translate-x-full transition-transform duration-1000 bg-gradient-to-r from-transparent via-white/30 to-transparent"></div>
              
              {loading ? (
                <>
                  <svg className="relative z-10 animate-spin h-5 w-5 text-white" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none"></circle>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <span className="relative z-10 text-white">Applying...</span>
                </>
              ) : (
                <>
                  <Filter className="relative z-10 w-5 h-5 text-white" />
                  <span className="relative z-10 text-white">Apply Filters</span>
                  <svg className="relative z-10 w-5 h-5 text-white transform group-hover/apply:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M13 7l5 5m0 0l-5 5m5-5H6" />
                  </svg>
                </>
              )}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
});

MarketFilters.propTypes = {
  query: PropTypes.string.isRequired,
  setQuery: PropTypes.func.isRequired,
  datePreset: PropTypes.string.isRequired,
  setDatePreset: PropTypes.func.isRequired,
  from: PropTypes.string,
  setFrom: PropTypes.func.isRequired,
  to: PropTypes.string,
  setTo: PropTypes.func.isRequired,
  onApply: PropTypes.func.isRequired,
  disabled: PropTypes.bool,
  loading: PropTypes.bool,
};

export default MarketFilters;
