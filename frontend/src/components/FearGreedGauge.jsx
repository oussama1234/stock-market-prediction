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
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
      </div>
    );
  }

  if (!fearGreed) {
    return (
      <div className="text-center text-gray-500">
        <p>Fear & Greed data unavailable</p>
      </div>
    );
  }

  const { value, classification, description, market_impact } = fearGreed;
  const color = getColor(value);
  const gradient = `conic-gradient(
    ${color} 0% ${value}%,
    #E5E7EB ${value}% 100%
  )`;
  const riskLevelText = market_impact.risk_level.replace('_', ' ').toUpperCase();
  
  const gaugeStyle = { background: gradient };
  const valueStyle = { color };
  const badgeStyle = { backgroundColor: color };

  return (
    <div className={`${isLarge ? 'p-6' : 'p-4'} bg-white rounded-2xl shadow-lg`}>
      {/* Header */}
      <div className="text-center mb-4">
        <h3 className={`${isLarge ? 'text-2xl' : 'text-lg'} font-bold text-gray-900`}>
          Market Fear & Greed Index
        </h3>
        <p className="text-sm text-gray-500 mt-1">Real-time market sentiment</p>
      </div>

      {/* Gauge */}
      <div className="flex flex-col items-center">
        <div className="relative mb-8">
          {/* Circular gauge */}
          <div 
            className={`${isLarge ? 'w-48 h-48' : 'w-32 h-32'} rounded-full relative`}
            style={gaugeStyle}
          >
            {/* Inner white circle */}
            <div className={`absolute inset-0 m-auto ${isLarge ? 'w-36 h-36' : 'w-24 h-24'} bg-white rounded-full flex flex-col items-center justify-center`}>
              <div className={`${isLarge ? 'text-5xl' : 'text-3xl'} font-bold`} style={valueStyle}>
                {value}
              </div>
              <div className={`${isLarge ? 'text-sm' : 'text-xs'} text-gray-500 mt-1`}>
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
              <div className="w-3 h-3 rounded-full bg-red-600 mx-auto mb-1"></div>
              <span className="text-gray-600">Extreme<br/>Fear</span>
            </div>
            <div className="text-center">
              <div className="w-3 h-3 rounded-full bg-orange-500 mx-auto mb-1"></div>
              <span className="text-gray-600">Fear</span>
            </div>
            <div className="text-center">
              <div className="w-3 h-3 rounded-full bg-green-500 mx-auto mb-1"></div>
              <span className="text-gray-600">Neutral</span>
            </div>
            <div className="text-center">
              <div className="w-3 h-3 rounded-full bg-blue-500 mx-auto mb-1"></div>
              <span className="text-gray-600">Greed</span>
            </div>
            <div className="text-center">
              <div className="w-3 h-3 rounded-full bg-purple-600 mx-auto mb-1"></div>
              <span className="text-gray-600">Extreme<br/>Greed</span>
            </div>
          </div>
        )}

        {/* Description */}
        {showDetails && isLarge && (
          <div className="mt-6 p-4 bg-gray-50 rounded-xl w-full">
            <p className="text-sm text-gray-700 leading-relaxed">
              {description}
            </p>
            
            {/* Market Impact */}
            <div className="mt-4 grid grid-cols-3 gap-3">
              <div className="text-center p-2 bg-white rounded-lg">
                <div className="text-xs text-gray-500">Volatility</div>
                <div className="text-lg font-bold text-gray-900">
                  {market_impact.multiplier}x
                </div>
              </div>
              <div className="text-center p-2 bg-white rounded-lg">
                <div className="text-xs text-gray-500">Bias</div>
                <div className={`text-lg font-bold ${market_impact.bias > 0 ? 'text-green-600' : market_impact.bias < 0 ? 'text-red-600' : 'text-gray-900'}`}>
                  {market_impact.bias > 0 ? '+' : ''}{market_impact.bias}
                </div>
              </div>
              <div className="text-center p-2 bg-white rounded-lg">
                <div className="text-xs text-gray-500">Risk</div>
                <div className={`text-lg font-bold ${
                  market_impact.risk_level === 'very_high' ? 'text-red-600' :
                  market_impact.risk_level === 'high' ? 'text-orange-500' :
                  market_impact.risk_level === 'medium' ? 'text-yellow-500' :
                  'text-green-600'
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
