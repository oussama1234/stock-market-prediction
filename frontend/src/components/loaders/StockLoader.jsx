import { useEffect, useState } from 'react';
import PropTypes from 'prop-types';
import StockLogo from '../StockLogo';

/**
 * StockLoader - Beautiful loading component for stock-specific pages
 * Shows stock logo, symbol, and animated indicators
 */
export default function StockLoader({ symbol, message = 'Loading stock data...' }) {
  const [dots, setDots] = useState('');

  useEffect(() => {
    const interval = setInterval(() => {
      setDots(prev => prev.length >= 3 ? '' : prev + '.');
    }, 500);
    return () => clearInterval(interval);
  }, []);

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900">
      <div className="text-center">
        {/* Animated background circles */}
        <div className="relative">
          <div className="absolute inset-0 flex items-center justify-center">
            <div className="w-64 h-64 bg-gradient-to-r from-indigo-400 to-purple-400 rounded-full opacity-20 animate-ping"></div>
          </div>
          <div className="absolute inset-0 flex items-center justify-center">
            <div className="w-48 h-48 bg-gradient-to-r from-blue-400 to-indigo-400 rounded-full opacity-30 animate-pulse"></div>
          </div>
          
          {/* Stock Logo Container */}
          <div className="relative z-10 bg-white dark:bg-gray-800 rounded-3xl shadow-2xl p-12 backdrop-blur-lg bg-opacity-90 dark:bg-opacity-90">
            {/* Logo with pulsing animation */}
            <div className="mb-6 transform hover:scale-110 transition-transform duration-300">
              <div className="inline-block relative">
                <div className="absolute inset-0 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full blur-xl opacity-50 animate-pulse"></div>
                <div className="relative">
                  <StockLogo symbol={symbol} size="2xl" />
                </div>
              </div>
            </div>

            {/* Symbol */}
            <div className="mb-4">
              <h2 className="text-4xl font-black text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-purple-600 dark:from-indigo-400 dark:to-purple-400">
                {symbol}
              </h2>
            </div>

            {/* Loading message */}
            <p className="text-lg text-gray-600 dark:text-gray-300 mb-6 font-medium">
              {message}{dots}
            </p>

            {/* Animated bars */}
            <div className="flex items-end justify-center gap-2 h-12 mb-6">
              {[0, 1, 2, 3, 4].map((i) => (
                <div
                  key={i}
                  className="w-3 bg-gradient-to-t from-indigo-600 to-purple-600 rounded-full animate-pulse"
                  style={{
                    height: '100%',
                    animation: `pulse 1.5s ease-in-out ${i * 0.15}s infinite`,
                    animationDelay: `${i * 0.15}s`
                  }}
                />
              ))}
            </div>

            {/* Spinning chart icon */}
            <div className="flex justify-center">
              <div className="relative">
                <div className="absolute inset-0 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full blur-md opacity-50"></div>
                <svg className="relative w-16 h-16 animate-spin text-indigo-600 dark:text-indigo-400" style={{ animationDuration: '3s' }} fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              </div>
            </div>

            {/* Status indicators */}
            <div className="mt-6 flex items-center justify-center gap-2 text-sm text-gray-500 dark:text-gray-400">
              <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
              <span>Fetching live data</span>
            </div>
          </div>
        </div>

        {/* Floating elements */}
        <div className="absolute top-1/4 left-1/4 w-20 h-20 bg-gradient-to-br from-yellow-300 to-orange-400 rounded-full opacity-20 animate-bounce" style={{ animationDuration: '3s' }}></div>
        <div className="absolute bottom-1/4 right-1/4 w-16 h-16 bg-gradient-to-br from-pink-300 to-purple-400 rounded-full opacity-20 animate-bounce" style={{ animationDuration: '4s', animationDelay: '1s' }}></div>
      </div>
    </div>
  );
}

StockLoader.propTypes = {
  symbol: PropTypes.string.isRequired,
  message: PropTypes.string,
};
