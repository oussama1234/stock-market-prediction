import { useEffect, useState, memo } from 'react';
import PropTypes from 'prop-types';

/**
 * GenericLoader - Optimized gradient loading component with trending up animation
 * For general pages, lists, and non-stock-specific loading states
 */
const GenericLoader = memo(function GenericLoader({ 
  message = 'Loading...', 
  size = 'large',
  fullScreen = true 
}) {
  const [progress, setProgress] = useState(0);

  useEffect(() => {
    const interval = setInterval(() => {
      setProgress(prev => (prev >= 100 ? 0 : prev + 10));
    }, 400);
    return () => clearInterval(interval);
  }, []);

  const sizeClasses = {
    small: 'w-8 h-8',
    medium: 'w-16 h-16',
    large: 'w-24 h-24',
  };

  const containerClasses = fullScreen
    ? 'min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900'
    : 'flex items-center justify-center p-8';

  return (
    <div className={containerClasses}>
      <div className="text-center relative">
        {/* Optimized gradient orb - single element */}
        <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
          <div className="w-64 h-64 bg-gradient-to-r from-indigo-400 via-purple-400 to-pink-400 rounded-full opacity-20 blur-3xl animate-pulse" style={{ animationDuration: '2s' }}></div>
        </div>

        {/* Main content */}
        <div className="relative z-10">
          {/* Trending Up Icon with optimized animation */}
          <div className="mb-6 flex justify-center">
            <div className="relative">
              {/* Icon container - reduced shadow complexity */}
              <div className={`relative ${sizeClasses[size]} flex items-center justify-center bg-gradient-to-br from-green-400 via-emerald-500 to-teal-500 rounded-full shadow-xl`} style={{ animation: 'gentle-bounce 1.5s ease-in-out infinite' }}>
                {/* Trending Up Arrow */}
                <svg 
                  className="w-2/3 h-2/3 text-white" 
                  fill="none" 
                  stroke="currentColor" 
                  viewBox="0 0 24 24"
                >
                  <path 
                    strokeLinecap="round" 
                    strokeLinejoin="round" 
                    strokeWidth={3} 
                    d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" 
                  />
                </svg>
              </div>

              {/* Rotating ring - optimized */}
              <div className="absolute inset-0 border-3 border-transparent border-t-green-400 rounded-full animate-spin" style={{ animationDuration: '1.2s' }}></div>
            </div>
          </div>

          {/* Loading text */}
          <div className="mb-6">
            <h3 className="text-2xl font-black text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 dark:from-indigo-400 dark:via-purple-400 dark:to-pink-400 mb-2">
              {message}
            </h3>
            <div className="flex justify-center gap-1">
              {[0, 1, 2].map((i) => (
                <div
                  key={i}
                  className="w-2 h-2 bg-indigo-500 rounded-full"
                  style={{
                    animation: 'bounce 0.6s ease-in-out infinite',
                    animationDelay: `${i * 0.15}s`
                  }}
                />
              ))}
            </div>
          </div>

          {/* Optimized progress bar */}
          <div className="max-w-xs mx-auto mb-6">
            <div className="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
              <div 
                className="h-full bg-gradient-to-r from-green-400 via-emerald-500 to-teal-500 rounded-full transition-all duration-300"
                style={{ width: `${progress}%` }}
              />
            </div>
          </div>

          {/* Simplified animated chart lines - fewer elements */}
          <div className="flex items-end justify-center gap-1 h-12">
            {[30, 60, 45, 75, 55, 80].map((height, i) => (
              <div
                key={i}
                className="w-2 bg-gradient-to-t from-indigo-400 to-purple-400 rounded-full"
                style={{
                  height: `${height}%`,
                  animation: `pulse 1.5s ease-in-out ${i * 0.15}s infinite`,
                }}
              />
            ))}
          </div>

          {/* Status text */}
          <p className="mt-6 text-sm text-gray-600 dark:text-gray-400 font-medium">
            <span className="inline-flex items-center gap-2">
              <span className="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
              Preparing your data...
            </span>
          </p>
        </div>

        {/* Reduced floating particles */}
        {fullScreen && (
          <>
            <div className="absolute top-10 left-10 w-2 h-2 bg-yellow-400 rounded-full opacity-50 animate-ping" style={{ animationDuration: '3s' }}></div>
            <div className="absolute bottom-10 right-10 w-2 h-2 bg-purple-400 rounded-full opacity-50 animate-ping" style={{ animationDuration: '3s', animationDelay: '1s' }}></div>
          </>
        )}
      </div>
    </div>
  );
});

GenericLoader.propTypes = {
  message: PropTypes.string,
  size: PropTypes.oneOf(['small', 'medium', 'large']),
  fullScreen: PropTypes.bool,
};

export default GenericLoader;
