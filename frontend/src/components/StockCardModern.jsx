import { memo, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import PropTypes from 'prop-types';
import { formatCurrency, formatPercentage, getChangeColor } from '../utils/formatters';
import StockLogo from './StockLogo';

/**
 * Modern StockCard with gradient backgrounds, animations, sentiment indicators,
 * live price updates, and responsive design matching StockDetails aesthetic
 */
const StockCardModern = memo(({ stock, onClick, onDelete }) => {
  const navigate = useNavigate();
  
  const {
    symbol,
    name,
    logo_url,
    latest_price,
    quote,
    active_prediction,
  } = stock;

  const handleClick = (e) => {
    if (e.target.closest('.delete-button')) {
      return;
    }
    
    if (onClick) {
      onClick();
    } else {
      navigate(`/stock/${symbol}`);
    }
  };
  
  const handleDelete = (e) => {
    e.stopPropagation();
    if (onDelete) {
      onDelete(symbol);
    }
  };

  // Price data with comprehensive fallback
  const currentPrice = quote?.current_price || quote?.close || latest_price?.close || null;
  const change = quote?.change ?? latest_price?.change ?? null;
  const changePercent = quote?.change_percent ?? latest_price?.change_percent ?? null;
  
  // Debug logging
  if (!currentPrice) {
    console.warn(`⚠️ No price data for ${symbol}:`, { 
      quote, 
      latest_price, 
      hasQuote: !!quote,
      hasLatestPrice: !!latest_price 
    });
  }
  
  // Prediction data
  const prediction = active_prediction;
  const direction = prediction?.direction || 'neutral';
  const confidenceScore = prediction?.confidence_score;
  const normalizedConfidence = confidenceScore ? 
    (confidenceScore < 1 ? Math.round(confidenceScore * 100) : Math.round(confidenceScore)) : null;
  const sentimentScore = prediction?.sentiment_score;
  const newsCount = prediction?.news_count || 0;

  // Determine card styling based on PRICE MOVEMENT (not prediction)
  const cardStyle = useMemo(() => {
    // Check if price is up or down based on change
    const isUp = change !== null && change !== undefined && change >= 0;
    const isDown = change !== null && change !== undefined && change < 0;
    
    if (isUp) {
      return {
        borderColor: 'border-green-500',
        bgGradient: 'from-green-50 to-emerald-50',
        textColor: 'text-green-700',
        icon: '📈',
        label: 'Up',
        badgeBg: 'bg-green-500',
      };
    } else if (isDown) {
      return {
        borderColor: 'border-red-500',
        bgGradient: 'from-red-50 to-rose-50',
        textColor: 'text-red-700',
        icon: '📉',
        label: 'Down',
        badgeBg: 'bg-red-500',
      };
    }
    // No price data - gray
    return {
      borderColor: 'border-gray-300',
      bgGradient: 'from-gray-50 to-slate-50',
      textColor: 'text-gray-700',
      icon: '➡️',
      label: 'N/A',
      badgeBg: 'bg-gray-500',
    };
  }, [change]);

  const priceChangeColor = change >= 0 ? 'text-green-600' : 'text-red-600';
  const priceChangeBg = change >= 0 ? 'bg-green-50' : 'bg-red-50';

  return (
    <div
      onClick={handleClick}
      className={`group relative bg-gradient-to-br ${cardStyle.bgGradient} rounded-2xl border-2 ${cardStyle.borderColor} p-6 cursor-pointer hover:shadow-2xl transition-all duration-300 hover:scale-[1.02] overflow-hidden`}
    >
      {/* Animated background gradient */}
      <div className="absolute inset-0 bg-gradient-to-br from-indigo-500/5 via-purple-500/5 to-pink-500/5 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

      {/* Delete button */}
      {onDelete && (
        <button
          onClick={handleDelete}
          className="delete-button absolute top-3 right-3 p-2 text-gray-400 hover:text-red-600 hover:bg-red-100 rounded-lg transition-all z-20 opacity-0 group-hover:opacity-100"
          title="Delete stock"
        >
          <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </button>
      )}

      {/* No badge - just card coloring based on price movement */}

      {/* Content */}
      <div className="relative z-10">
        {/* Header with Logo and Symbol */}
        <div className="flex items-center gap-4 mb-4">
          <div className="transform group-hover:scale-110 transition-transform duration-300">
            <StockLogo symbol={symbol} name={name} logoUrl={logo_url} size="lg" />
          </div>
          <div className="flex-1 min-w-0">
            <h3 className="text-2xl font-black text-gray-900 group-hover:text-transparent group-hover:bg-clip-text group-hover:bg-gradient-to-r group-hover:from-indigo-600 group-hover:to-purple-600 transition-all">
              {symbol}
            </h3>
            <p className="text-sm text-gray-600 truncate">{name}</p>
          </div>
        </div>

        {/* Price Section */}
        {currentPrice ? (
          <div className={`${priceChangeBg} rounded-xl p-4 mb-4`}>
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs font-medium text-gray-600">Current Price</span>
              {change !== null && change !== undefined && (
                <div className="flex items-center gap-1">
                  {change >= 0 ? (
                    <svg className="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L6.707 7.707a1 1 0 01-1.414 0z" clipRule="evenodd" />
                    </svg>
                  ) : (
                    <svg className="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l2.293-2.293a1 1 0 011.414 0z" clipRule="evenodd" />
                    </svg>
                  )}
                </div>
              )}
            </div>
            <div className="flex items-end justify-between">
              <div className="text-3xl font-black text-gray-900">
                {formatCurrency(currentPrice)}
              </div>
              {(change !== null && change !== undefined) && (
                <div className="text-right">
                  <div className={`text-lg font-bold ${priceChangeColor}`}>
                    {change >= 0 ? '+' : ''}{formatCurrency(Math.abs(change))}
                  </div>
                  <div className={`text-sm font-semibold ${priceChangeColor}`}>
                    {formatPercentage(changePercent)}
                  </div>
                </div>
              )}
            </div>
          </div>
        ) : (
          <div className="bg-gray-100 rounded-xl p-4 mb-4 text-center">
            <div className="text-2xl mb-2">📊</div>
            <div className="text-sm text-gray-600 font-medium">Loading price...</div>
          </div>
        )}

        {/* Prediction Details */}
        {prediction ? (
          <div className="space-y-3">
            {/* Confidence Bar */}
            {normalizedConfidence && (
              <div>
                <div className="flex items-center justify-between text-xs mb-1">
                  <span className="text-gray-600 font-medium">AI Confidence</span>
                  <span className="font-bold text-gray-900">{normalizedConfidence}%</span>
                </div>
                <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
                  <div
                    className={`h-full ${cardStyle.badgeBg} transition-all duration-1000`}
                    style={{ width: `${normalizedConfidence}%` }}
                  ></div>
                </div>
              </div>
            )}

            {/* Sentiment Score */}
            {sentimentScore !== null && sentimentScore !== undefined && (
              <div className="flex items-center justify-between bg-white bg-opacity-60 rounded-lg p-3">
                <div className="flex items-center gap-2">
                  <span className="text-xl">
                    {sentimentScore > 0.3 ? '😊' : sentimentScore < -0.3 ? '😟' : '😐'}
                  </span>
                  <span className="text-xs font-medium text-gray-600">Sentiment</span>
                </div>
                <div className="flex items-center gap-2">
                  <div className="flex-1 bg-gray-200 rounded-full h-2 w-20 overflow-hidden">
                    <div
                      className={`h-full transition-all ${
                        sentimentScore > 0 ? 'bg-green-500' : 'bg-red-500'
                      }`}
                      style={{ width: `${Math.abs(sentimentScore) * 100}%` }}
                    ></div>
                  </div>
                  <span className={`text-xs font-bold ${sentimentScore > 0 ? 'text-green-700' : 'text-red-700'}`}>
                    {Number(sentimentScore).toFixed(2)}
                  </span>
                </div>
              </div>
            )}

            {/* News Count */}
            {newsCount > 0 && (
              <div className="flex items-center gap-2 bg-white bg-opacity-60 rounded-lg p-3">
                <span className="text-xl">📰</span>
                <span className="text-xs font-medium text-gray-600">
                  {newsCount} news article{newsCount !== 1 ? 's' : ''} analyzed
                </span>
              </div>
            )}
          </div>
        ) : (
          /* No Prediction - Show Generating State */
          <div className="space-y-3">
            <div className="bg-gradient-to-r from-indigo-50 via-purple-50 to-pink-50 dark:from-indigo-900/20 dark:via-purple-900/20 dark:to-pink-900/20 rounded-xl p-4 border-2 border-dashed border-indigo-200 dark:border-indigo-800">
              <div className="flex items-center gap-3 mb-3">
                <div className="relative">
                  <div className="w-8 h-8 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 animate-pulse"></div>
                  <div className="absolute inset-0 w-8 h-8 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 animate-ping opacity-75"></div>
                </div>
                <div className="flex-1">
                  <div className="text-sm font-bold bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 to-purple-600 mb-1">
                    AI Analysis Pending
                  </div>
                  <div className="text-xs text-gray-600 dark:text-gray-400">
                    Generating prediction...
                  </div>
                </div>
              </div>
              
              {/* Placeholder bars */}
              <div className="space-y-2">
                <div className="flex items-center gap-2">
                  <div className="flex-1 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                    <div className="h-full bg-gradient-to-r from-indigo-400 to-purple-400 rounded-full animate-pulse" style={{ width: '60%' }}></div>
                  </div>
                  <span className="text-xs text-gray-400">Analyzing...</span>
                </div>
                <div className="flex items-center gap-2">
                  <div className="flex-1 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                    <div className="h-full bg-gradient-to-r from-purple-400 to-pink-400 rounded-full animate-pulse" style={{ width: '40%', animationDelay: '0.2s' }}></div>
                  </div>
                  <span className="text-xs text-gray-400">Processing...</span>
                </div>
              </div>
              
              <div className="mt-3 text-xs text-gray-500 dark:text-gray-400 text-center">
                Click to view stock details or wait for analysis to complete
              </div>
            </div>
          </div>
        )}

        {/* View Details Arrow */}
        <div className="mt-4 flex items-center justify-center text-sm font-semibold text-gray-500 group-hover:text-indigo-600 transition-colors">
          <span>View Details</span>
          <svg className="w-4 h-4 ml-1 transform group-hover:translate-x-2 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
          </svg>
        </div>
      </div>
    </div>
  );
});

StockCardModern.displayName = 'StockCardModern';

StockCardModern.propTypes = {
  stock: PropTypes.shape({
    symbol: PropTypes.string.isRequired,
    name: PropTypes.string,
    logo_url: PropTypes.string,
    latest_price: PropTypes.object,
    quote: PropTypes.object,
    active_prediction: PropTypes.object,
  }).isRequired,
  onClick: PropTypes.func,
  onDelete: PropTypes.func,
};

export default StockCardModern;
