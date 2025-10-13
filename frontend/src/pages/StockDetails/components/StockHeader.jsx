import { useMemo } from 'react';
import StockLogo from '../../../components/StockLogo';
import { formatVolume } from '../../../utils/formatters';

/**
 * StockHeader - Beautiful animated header with all stock information
 * Shows logo, price, changes, volume, and key metrics with hover effects
 */
export default function StockHeader({ stock, quote }) {
  // Debug logging
  console.log('StockHeader - stock:', stock);
  console.log('StockHeader - quote:', quote);
  
  const priceData = useMemo(() => {
    if (!quote) {
      console.warn('StockHeader - No quote data available');
      return null;
    }
    
    // Prefer database-based change values (change since last saved close)
    // Fall back to API change values (change since yesterday) if DB values not available
    const useDbChange = quote.db_change !== undefined;
    const change = useDbChange ? (quote.db_change || 0) : (quote.change || 0);
    const changePct = useDbChange ? (quote.db_change_percent || 0) : (quote.change_percent || 0);
    const prevClose = useDbChange ? (quote.db_previous_close || 0) : (quote.previous_close || 0);
    const isPositive = change >= 0;
    
    // Log which values are being used
    if (useDbChange) {
      console.log('📊 Using DB-based change values:', {
        db_change: quote.db_change,
        db_change_percent: quote.db_change_percent,
        db_previous_close: quote.db_previous_close,
        db_last_check_date: quote.db_last_check_date
      });
    } else {
      console.log('📊 Using API change values (DB values not available):', {
        api_change: quote.change,
        api_change_percent: quote.change_percent,
        api_previous_close: quote.previous_close
      });
    }
    
    return {
      current: Number(quote.current_price || 0).toFixed(2),
      change: change.toFixed(2),
      changePct: changePct.toFixed(2),
      isPositive,
      open: Number(quote.open || 0).toFixed(2),
      high: Number(quote.high || 0).toFixed(2),
      low: Number(quote.low || 0).toFixed(2),
      prevClose: Number(prevClose).toFixed(2),
      volume: formatVolume(quote.volume || 0),
      nextOpen: Number(quote.next_open_estimate || quote.previous_close || 0).toFixed(2),
      lastCheckDate: quote.db_last_check_date || null,
      usingDbValues: useDbChange,
    };
  }, [quote]);

  if (!stock || !priceData) return null;

  return (
    <div className="bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-600 rounded-3xl shadow-2xl p-8 mb-6 text-white overflow-hidden relative">
      {/* Animated Background Pattern */}
      <div className="absolute inset-0 opacity-10">
        <div className="absolute top-0 left-0 w-96 h-96 bg-white rounded-full blur-3xl animate-pulse"></div>
        <div className="absolute bottom-0 right-0 w-96 h-96 bg-yellow-300 rounded-full blur-3xl animate-pulse animation-delay-2000"></div>
      </div>

      <div className="relative z-10">
        {/* Top Section: Logo, Name, Exchange */}
        <div className="flex items-start justify-between mb-8">
          <div className="flex items-center gap-6">
            <div className="transform hover:scale-110 transition-transform duration-300">
              <StockLogo symbol={stock.symbol} logoUrl={stock.logo_url} size="xl" />
            </div>
            <div>
              <h1 className="text-5xl font-black mb-2 animate-fade-in">{stock.symbol}</h1>
              <p className="text-2xl text-white/90 font-medium mb-1">{stock.name}</p>
              <div className="flex items-center gap-3 text-sm text-white/70">
                <span className="px-3 py-1 bg-white/20 rounded-full backdrop-blur-sm">
                  {stock.exchange}
                </span>
                {stock.industry && (
                  <span className="px-3 py-1 bg-white/20 rounded-full backdrop-blur-sm">
                    {stock.industry}
                  </span>
                )}
                <span className="px-3 py-1 bg-white/20 rounded-full backdrop-blur-sm">
                  {stock.currency || 'USD'}
                </span>
              </div>
            </div>
          </div>

          {/* Market Status Badge */}
          <div className="px-4 py-2 bg-white/20 backdrop-blur-md rounded-xl flex items-center gap-2">
            <span className="relative flex h-3 w-3">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
              <span className="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
            </span>
            <span className="text-sm font-semibold">
              {quote.market_status === 'open' ? 'Market Open' : 'Market Closed'}
            </span>
          </div>
        </div>

        {/* Price Section */}
        <div className="grid lg:grid-cols-2 gap-8">
          {/* Main Price */}
          <div>
            <div className="text-7xl font-black mb-4 animate-fade-in-up">
              ${priceData.current}
            </div>
            <div>
              <div className={`inline-flex items-center gap-3 px-6 py-3 rounded-2xl text-2xl font-bold backdrop-blur-md ${
                priceData.isPositive 
                  ? 'bg-green-500/30 text-green-100' 
                  : 'bg-red-500/30 text-red-100'
              }`}>
                <span className="text-3xl">
                  {priceData.isPositive ? '▲' : '▼'}
                </span>
                <span>
                  {priceData.isPositive ? '+' : ''}{priceData.change}
                </span>
                <span className="text-xl">
                  ({priceData.isPositive ? '+' : ''}{priceData.changePct}%)
                </span>
              </div>
              {priceData.usingDbValues && priceData.lastCheckDate && (
                <div className="mt-2 text-xs text-white/70 flex items-center gap-2">
                  <span className="px-2 py-1 bg-white/10 rounded-full backdrop-blur-sm">
                    📊 Change since last check: {priceData.lastCheckDate}
                  </span>
                </div>
              )}
            </div>
          </div>

          {/* Price Stats Grid */}
          <div className="grid grid-cols-2 gap-4">
            <div className="bg-white/10 backdrop-blur-md rounded-xl p-4 hover:bg-white/20 transition-all hover:scale-105 cursor-pointer">
              <div className="text-sm text-white/70 mb-1 flex items-center gap-2">
                <span className="text-lg">🔓</span> Open
              </div>
              <div className="text-2xl font-bold">${priceData.open}</div>
            </div>

            <div className="bg-white/10 backdrop-blur-md rounded-xl p-4 hover:bg-white/20 transition-all hover:scale-105 cursor-pointer">
              <div className="text-sm text-white/70 mb-1 flex items-center gap-2">
                <span className="text-lg">🔝</span> High
              </div>
              <div className="text-2xl font-bold text-green-300">${priceData.high}</div>
            </div>

            <div className="bg-white/10 backdrop-blur-md rounded-xl p-4 hover:bg-white/20 transition-all hover:scale-105 cursor-pointer">
              <div className="text-sm text-white/70 mb-1 flex items-center gap-2">
                <span className="text-lg">🔻</span> Low
              </div>
              <div className="text-2xl font-bold text-red-300">${priceData.low}</div>
            </div>

            <div className="bg-white/10 backdrop-blur-md rounded-xl p-4 hover:bg-white/20 transition-all hover:scale-105 cursor-pointer">
              <div className="text-sm text-white/70 mb-1 flex items-center gap-2">
                <span className="text-lg">⏮️</span> Prev Close
              </div>
              <div className="text-2xl font-bold">${priceData.prevClose}</div>
            </div>

            <div className="bg-white/10 backdrop-blur-md rounded-xl p-4 hover:bg-white/20 transition-all hover:scale-105 cursor-pointer">
              <div className="text-sm text-white/70 mb-1 flex items-center gap-2">
                <span className="text-lg">📊</span> Volume
              </div>
              <div className="text-xl font-bold">{priceData.volume}</div>
            </div>

            <div className="bg-white/10 backdrop-blur-md rounded-xl p-4 hover:bg-white/20 transition-all hover:scale-105 cursor-pointer">
              <div className="text-sm text-white/70 mb-1 flex items-center gap-2">
                <span className="text-lg">🔮</span> Next Open Est.
              </div>
              <div className="text-2xl font-bold text-yellow-300">${priceData.nextOpen}</div>
            </div>
          </div>
        </div>

        {/* Additional Info Bar */}
        {(stock.market_cap || stock.website) && (
          <div className="mt-6 pt-6 border-t border-white/20 flex flex-wrap items-center gap-4 text-sm">
            {stock.market_cap && (
              <div className="flex items-center gap-2 bg-white/10 px-4 py-2 rounded-full backdrop-blur-sm">
                <span>💰</span>
                <span className="text-white/70">Market Cap:</span>
                <span className="font-bold">${(stock.market_cap / 1000).toFixed(1)}B</span>
              </div>
            )}
            {stock.shares_outstanding && (
              <div className="flex items-center gap-2 bg-white/10 px-4 py-2 rounded-full backdrop-blur-sm">
                <span>📈</span>
                <span className="text-white/70">Shares:</span>
                <span className="font-bold">{stock.shares_outstanding.toLocaleString()}M</span>
              </div>
            )}
            {stock.website && (
              <a
                href={stock.website}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center gap-2 bg-white/10 px-4 py-2 rounded-full backdrop-blur-sm hover:bg-white/30 transition-all"
              >
                <span>🌐</span>
                <span className="font-medium">Visit Website</span>
              </a>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
