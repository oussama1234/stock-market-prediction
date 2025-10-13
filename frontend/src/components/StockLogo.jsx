import { memo, useState, useEffect } from 'react';
import PropTypes from 'prop-types';

/**
 * StockLogo component that displays a company logo with fallback
 * Shows gradient avatar with first letter if logo fails to load
 */
const StockLogo = memo(({ symbol, name, logoUrl, size = 'md', className = '' }) => {
  const [showFallback, setShowFallback] = useState(false);
  const [imageSrc, setImageSrc] = useState(null);

  // Set image source on mount or when logoUrl changes
  useEffect(() => {
    // Only set image src if logoUrl is a valid string with content
    if (logoUrl && logoUrl.trim() && logoUrl !== 'null' && logoUrl !== 'undefined') {
      setShowFallback(false);
      setImageSrc(logoUrl);
    } else {
      // No valid logo URL, show fallback immediately
      setShowFallback(true);
      setImageSrc(null);
    }
  }, [logoUrl, symbol]);

  const sizeClasses = {
    xs: 'w-6 h-6',
    sm: 'w-8 h-8',
    md: 'w-12 h-12',
    lg: 'w-16 h-16',
    xl: 'w-20 h-20',
  };

  const textSizes = {
    xs: 'text-xs',
    sm: 'text-sm',
    md: 'text-lg',
    lg: 'text-2xl',
    xl: 'text-3xl',
  };

  const handleImageError = () => {
    // Show fallback immediately on any error
    setShowFallback(true);
  };

  // Fallback: First letter of symbol with gradient background
  if (showFallback || !imageSrc || !imageSrc.trim() || !symbol) {
    return (
      <div
        className={`${sizeClasses[size]} ${className} flex items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 text-white font-bold ${textSizes[size]} flex-shrink-0 shadow-sm`}
        title={name || symbol}
      >
        {symbol ? symbol.charAt(0).toUpperCase() : '?'}
      </div>
    );
  }

  return (
    <div className={`${sizeClasses[size]} ${className} flex-shrink-0 bg-white rounded-lg shadow-sm border border-gray-100 p-1.5 overflow-hidden`}>
      <img
        src={imageSrc}
        alt={`${symbol} logo`}
        className="w-full h-full object-contain"
        onError={handleImageError}
        loading="lazy"
        referrerPolicy="no-referrer"
        title={name || symbol}
      />
    </div>
  );
});

StockLogo.displayName = 'StockLogo';

StockLogo.propTypes = {
  symbol: PropTypes.string.isRequired,
  name: PropTypes.string,
  logoUrl: PropTypes.string,
  size: PropTypes.oneOf(['xs', 'sm', 'md', 'lg', 'xl']),
  className: PropTypes.string,
};

export default StockLogo;
