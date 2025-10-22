import { useState, useEffect, useMemo, useCallback, memo } from 'react';
import axios from 'axios';

const FearGreedGauge = memo(({ size = 'large', showDetails = true }) => {
  const [fearGreed, setFearGreed] = useState(null);
  const [loading, setLoading] = useState(true);

  const fetchFearGreed = useCallback(async () => {
    try {
      const response = await axios.get('http://localhost:8000/api/market/fear-greed-index');
      if (response.data.success) {
        setFearGreed(response.data.data);
      }
    } catch (error) {
      console.error('Failed to fetch Fear & Greed Index:', error);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchFearGreed();
  }, [fetchFearGreed]);

  // Compute values (must be done before any early returns)
  const isLarge = size === 'large';
  
  // Helper functions moved inside to avoid stale closures
  const getColor = (value) => {
    if (value <= 24) return '#DC2626';
    if (value <= 44) return '#F59E0B';
    if (value <= 55) return '#10B981';
    if (value <= 75) return '#3B82F6';
    return '#8B5CF6';
  };

  // Early returns after hooks
  if (loading) {
    return (
      <div className={`flex items-center justify-center ${isLarge ? 'h-64' : 'h-32'}`}>
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 dark:border-indigo-400"></div>
      </div>
    );
  }

  if (!fearGreed) {
    return (
      <div className="text-center text-gray-500 dark:text-gray-400 transition-colors duration-300">
        <p>Fear & Greed data unavailable</p>
      </div>
    );
  }

  const { value, classification, description, market_impact } = fearGreed;
  const color = getColor(value);
  
  // Dark mode aware gradient
  const gradient = `conic-gradient(
    ${color} 0% ${value}%,
    rgb(229 231 235) ${value}% 100%
  )`;
  const gradientDark = `conic-gradient(
    ${color} 0% ${value}%,
    rgb(55 65 81) ${value}% 100%
  )`;
  
  const riskLevelText = market_impact.risk_level.replace('_', ' ').toUpperCase();
  
  const gaugeStyle = { background: gradient };
  const valueStyle = { color };
  const badgeStyle = { backgroundColor: color };

  return (
    <div className={`${isLarge ? 'p-6' : 'p-4'} bg-white dark:bg-gray-800 rounded-2xl shadow-lg transition-colors duration-300`}>
      {/* Header */}
      <div className="text-center mb-4">
        <h3 className={`${isLarge ? 'text-2xl' : 'text-lg'} font-bold text-gray-900 dark:text-white transition-colors duration-300`}>
          Market Fear & Greed Index
        </h3>
        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1 transition-colors duration-300">Real-time market sentiment</p>
      </div>

      {/* Gauge */}
      <div className="flex flex-col items-center">
        <div className="relative mb-8">
          {/* Circular gauge */}
          <div 
            className={`${isLarge ? 'w-48 h-48' : 'w-32 h-32'} rounded-full relative transition-all duration-300`}
            style={gaugeStyle}
          >
            {/* Inner circle - dark mode aware */}
            <div className={`absolute inset-0 m-auto ${isLarge ? 'w-36 h-36' : 'w-24 h-24'} bg-white dark:bg-gray-800 rounded-full flex flex-col items-center justify-center transition-colors duration-300 shadow-xl`}>
              <div className={`${isLarge ? 'text-5xl' : 'text-3xl'} font-bold transition-colors duration-300`} style={valueStyle}>
                {value}
              </div>
              <div className={`${isLarge ? 'text-sm' : 'text-xs'} text-gray-500 dark:text-gray-400 mt-1 transition-colors duration-300`}>
                / 100
              </div>
            </div>
          </div>
        </div>
        
        {/* Classification badge - separate from gauge */}
        <div 
          className="px-6 py-3 rounded-full text-white font-bold text-base shadow-xl mb-6"
          style={badgeStyle}
        >
          {classification}
        </div>

        {/* Scale indicators */}
        {isLarge && (
          <div className="flex justify-between w-full mt-16 px-4 text-xs font-medium">
            <div className="text-center">
              <div className="w-3 h-3 rounded-full bg-red-600 mx-auto mb-1 shadow-lg"></div>
              <span className="text-gray-600 dark:text-gray-400 transition-colors duration-300">Extreme<br/>Fear</span>
            </div>
            <div className="text-center">
              <div className="w-3 h-3 rounded-full bg-orange-500 mx-auto mb-1 shadow-lg"></div>
              <span className="text-gray-600 dark:text-gray-400 transition-colors duration-300">Fear</span>
            </div>
            <div className="text-center">
              <div className="w-3 h-3 rounded-full bg-green-500 mx-auto mb-1 shadow-lg"></div>
              <span className="text-gray-600 dark:text-gray-400 transition-colors duration-300">Neutral</span>
            </div>
            <div className="text-center">
              <div className="w-3 h-3 rounded-full bg-blue-500 mx-auto mb-1 shadow-lg"></div>
              <span className="text-gray-600 dark:text-gray-400 transition-colors duration-300">Greed</span>
            </div>
            <div className="text-center">
              <div className="w-3 h-3 rounded-full bg-purple-600 mx-auto mb-1 shadow-lg"></div>
              <span className="text-gray-600 dark:text-gray-400 transition-colors duration-300">Extreme<br/>Greed</span>
            </div>
          </div>
        )}

        {/* Description */}
        {showDetails && isLarge && (
          <div className="mt-6 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-xl w-full transition-colors duration-300">
            <p className="text-sm text-gray-700 dark:text-gray-300 leading-relaxed transition-colors duration-300">
              {description}
            </p>
            
            {/* Market Impact */}
            <div className="mt-4 grid grid-cols-3 gap-3">
              <div className="text-center p-2 bg-white dark:bg-gray-800 rounded-lg transition-colors duration-300 shadow-md">
                <div className="text-xs text-gray-500 dark:text-gray-400 transition-colors duration-300">Volatility</div>
                <div className="text-lg font-bold text-gray-900 dark:text-white transition-colors duration-300">
                  {market_impact.multiplier}x
                </div>
              </div>
              <div className="text-center p-2 bg-white dark:bg-gray-800 rounded-lg transition-colors duration-300 shadow-md">
                <div className="text-xs text-gray-500 dark:text-gray-400 transition-colors duration-300">Bias</div>
                <div className={`text-lg font-bold transition-colors duration-300 ${
                  market_impact.bias > 0 ? 'text-green-600 dark:text-green-400' : 
                  market_impact.bias < 0 ? 'text-red-600 dark:text-red-400' : 
                  'text-gray-900 dark:text-white'
                }`}>
                  {market_impact.bias > 0 ? '+' : ''}{market_impact.bias}
                </div>
              </div>
              <div className="text-center p-2 bg-white dark:bg-gray-800 rounded-lg transition-colors duration-300 shadow-md">
                <div className="text-xs text-gray-500 dark:text-gray-400 transition-colors duration-300">Risk</div>
                <div className={`text-lg font-bold transition-colors duration-300 ${
                  market_impact.risk_level === 'very_high' ? 'text-red-600 dark:text-red-400' :
                  market_impact.risk_level === 'high' ? 'text-orange-500 dark:text-orange-400' :
                  market_impact.risk_level === 'medium' ? 'text-yellow-500 dark:text-yellow-400' :
                  'text-green-600 dark:text-green-400'
                }`}>
                  {riskLevelText}
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
});

FearGreedGauge.displayName = 'FearGreedGauge';

export default FearGreedGauge;
