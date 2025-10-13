import { memo } from 'react';
import { useNavigate } from 'react-router-dom';
import PropTypes from 'prop-types';
import { formatCurrency, formatPercentage, getChangeColor } from '../utils/formatters';
import StockLogo from './StockLogo';

const StockCard = memo(({ stock, onClick, onDelete }) => {
  const navigate = useNavigate();
  
  const {
    symbol,
    name,
    logo_url,
    latest_price,
    quote,
  } = stock;

  const handleClick = (e) => {
    // Don't navigate if clicking on delete button
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

  const currentPrice = quote?.current_price || latest_price?.close;
  const change = quote?.change || latest_price?.change;
  const changePercent = quote?.change_percent || latest_price?.change_percent;
  
  // Normalize confidence score (old: 0-1, new: 0-100)
  const confidenceScore = stock.active_prediction?.confidence_score;
  const normalizedConfidence = confidenceScore ? 
    (confidenceScore < 1 ? Math.round(confidenceScore * 100) : Math.round(confidenceScore)) : null;

  return (
    <div
      onClick={handleClick}
      className="card cursor-pointer hover:shadow-lg transition-all duration-200 transform hover:-translate-y-1 relative"
    >
      {onDelete && (
        <button
          onClick={handleDelete}
          className="delete-button absolute top-3 right-3 p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors z-10"
          title="Delete stock"
        >
          <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </button>
      )}
      <div className="flex items-center gap-3 mb-4">
        <StockLogo symbol={symbol} name={name} logoUrl={logo_url} size="lg" />
        <div className="flex-1 min-w-0">
          <h3 className="text-xl font-bold text-gray-900">{symbol}</h3>
          <p className="text-sm text-gray-600 truncate">{name}</p>
        </div>
      </div>
      <div className="flex justify-between items-start mb-3">
        <div className="text-xs text-gray-500">
          Latest Price
        </div>
        {currentPrice && (
          <div className="text-right">
            <div className="text-2xl font-bold text-gray-900">
              {formatCurrency(currentPrice)}
            </div>
          </div>
        )}
      </div>

      {(change !== null && change !== undefined) && (
        <div className="flex items-center gap-2">
          <span className={`text-sm font-semibold ${getChangeColor(change)}`}>
            {formatCurrency(Math.abs(change))}
          </span>
          <span className={`text-sm font-semibold ${getChangeColor(changePercent)}`}>
            {formatPercentage(changePercent)}
          </span>
        </div>
      )}

      {stock.active_prediction && (
        <div className="mt-3 pt-3 border-t border-gray-200">
          <div className="flex justify-between items-center text-sm">
            <span className="text-gray-600">Prediction:</span>
            <span className={`font-semibold ${
              stock.active_prediction.direction === 'up' ? 'text-green-600' :
              stock.active_prediction.direction === 'down' ? 'text-red-600' :
              'text-gray-600'
            }`}>
              {stock.active_prediction.direction.toUpperCase()}
            </span>
          </div>
          <div className="flex justify-between items-center text-sm mt-1">
            <span className="text-gray-600">Confidence:</span>
            <span className="font-semibold text-primary-600">
              {normalizedConfidence}%
            </span>
          </div>
        </div>
      )}
    </div>
  );
});

StockCard.displayName = 'StockCard';

StockCard.propTypes = {
  stock: PropTypes.shape({
    symbol: PropTypes.string.isRequired,
    name: PropTypes.string,
    latest_price: PropTypes.object,
    quote: PropTypes.object,
    active_prediction: PropTypes.object,
  }).isRequired,
  onClick: PropTypes.func,
  onDelete: PropTypes.func,
};

export default StockCard;
