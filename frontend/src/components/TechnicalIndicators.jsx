import { useMemo } from 'react';

export default function TechnicalIndicators({ indicators }) {
  if (!indicators) return null;

  const { technical, sentiment, volume, market, signals } = indicators;

  // Calculate RSI level color
  const getRSIColor = (value) => {
    if (value < 30) return 'text-green-600';
    if (value > 70) return 'text-red-600';
    return 'text-blue-600';
  };

  const getRSIBgColor = (value) => {
    if (value < 30) return 'bg-green-50 border-green-200';
    if (value > 70) return 'bg-red-50 border-red-200';
    return 'bg-blue-50 border-blue-200';
  };

  // Signal strength bar color
  const getSignalColor = (value) => {
    if (value > 0.3) return 'from-green-500 to-emerald-500';
    if (value < -0.3) return 'from-red-500 to-orange-500';
    return 'from-blue-500 to-indigo-500';
  };

  return (
    <div className="space-y-6">
      {/* Technical Indicators Overview */}
      <div>
        <h3 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
          <span className="text-2xl">📊</span>
          Technical Indicators
        </h3>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {/* RSI */}
          {technical?.rsi && (
            <div className={`p-4 rounded-xl border-2 ${getRSIBgColor(technical.rsi.value)}`}>
              <div className="flex items-center justify-between mb-2">
                <div className="text-xs text-gray-700 font-semibold uppercase tracking-wide">RSI (14)</div>
                <div className={`text-2xl font-black ${getRSIColor(technical.rsi.value)}`}>
                  {technical.rsi.value}
                </div>
              </div>
              <div className="text-xs text-gray-600 font-medium capitalize">
                {technical.rsi.signal.replace(/_/g, ' ')}
              </div>
              <div className="mt-2 h-2 bg-white rounded-full overflow-hidden">
                <div 
                  className={`h-full transition-all duration-500 ${
                    technical.rsi.value < 30 ? 'bg-green-500' :
                    technical.rsi.value > 70 ? 'bg-red-500' :
                    'bg-blue-500'
                  }`}
                  style={{ width: `${technical.rsi.value}%` }}
                ></div>
              </div>
            </div>
          )}

          {/* MACD */}
          {technical?.macd && (
            <div className="p-4 bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl border-2 border-purple-200">
              <div className="flex items-center justify-between mb-2">
                <div className="text-xs text-gray-700 font-semibold uppercase tracking-wide">MACD</div>
                <div className={`text-2xl font-black ${
                  technical.macd.value > 0 ? 'text-green-600' : 'text-red-600'
                }`}>
                  {technical.macd.value}
                </div>
              </div>
              <div className="text-xs text-gray-600 font-medium capitalize">
                {technical.macd.signal.replace(/_/g, ' ')}
              </div>
              {technical.macd.histogram && (
                <div className="mt-2 text-xs text-gray-500">
                  Histogram: <span className="font-bold">{technical.macd.histogram}</span>
                </div>
              )}
            </div>
          )}

          {/* Moving Averages */}
          {technical?.moving_averages && (
            <div className="p-4 bg-gradient-to-br from-cyan-50 to-blue-50 rounded-xl border-2 border-cyan-200">
              <div className="text-xs text-gray-700 font-semibold uppercase tracking-wide mb-2">Moving Averages</div>
              <div className="space-y-1">
                <div className="flex justify-between items-center">
                  <span className="text-xs text-gray-600">MA5:</span>
                  <span className="text-sm font-bold text-gray-900">
                    {technical.moving_averages.ma5 ? `$${technical.moving_averages.ma5}` : 'N/A'}
                  </span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-xs text-gray-600">MA20:</span>
                  <span className="text-sm font-bold text-gray-900">
                    {technical.moving_averages.ma20 ? `$${technical.moving_averages.ma20}` : 'N/A'}
                  </span>
                </div>
                <div className="mt-2 pt-2 border-t border-cyan-200">
                  <div className={`text-xs font-bold capitalize ${
                    technical.moving_averages.signal === 'bullish' ? 'text-green-600' :
                    technical.moving_averages.signal === 'bearish' ? 'text-red-600' :
                    'text-blue-600'
                  }`}>
                    {technical.moving_averages.signal}
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Bollinger Bands */}
          {technical?.bollinger_bands && (
            <div className="p-4 bg-gradient-to-br from-amber-50 to-yellow-50 rounded-xl border-2 border-amber-200">
              <div className="text-xs text-gray-700 font-semibold uppercase tracking-wide mb-2">Bollinger Bands</div>
              <div className="space-y-1">
                <div className="flex justify-between items-center">
                  <span className="text-xs text-gray-600">Upper:</span>
                  <span className="text-sm font-bold text-gray-900">
                    {technical.bollinger_bands.upper ? `$${technical.bollinger_bands.upper}` : 'N/A'}
                  </span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-xs text-gray-600">Middle:</span>
                  <span className="text-sm font-bold text-gray-900">
                    {technical.bollinger_bands.middle ? `$${technical.bollinger_bands.middle}` : 'N/A'}
                  </span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-xs text-gray-600">Lower:</span>
                  <span className="text-sm font-bold text-gray-900">
                    {technical.bollinger_bands.lower ? `$${technical.bollinger_bands.lower}` : 'N/A'}
                  </span>
                </div>
                <div className="mt-2 pt-2 border-t border-amber-200">
                  <div className={`text-xs font-bold capitalize ${
                    technical.bollinger_bands.signal === 'oversold' ? 'text-green-600' :
                    technical.bollinger_bands.signal === 'overbought' ? 'text-red-600' :
                    'text-blue-600'
                  }`}>
                    {technical.bollinger_bands.position?.replace(/_/g, ' ') || 'middle'}
                  </div>
                </div>
              </div>
            </div>
          )}

        </div>
      </div>

      {/* Additional Metrics */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {/* Momentum */}
        {technical?.momentum && (
          <div className="p-4 bg-gradient-to-br from-indigo-50 to-purple-50 rounded-xl border border-indigo-200">
            <div className="text-xs text-gray-700 font-semibold uppercase tracking-wide mb-1">Momentum</div>
            <div className={`text-2xl font-bold ${
              technical.momentum.value > 0 ? 'text-green-600' : 'text-red-600'
            }`}>
              {technical.momentum.value > 0 ? '+' : ''}{technical.momentum.value}%
            </div>
            <div className="text-xs text-gray-600 font-medium capitalize mt-1">
              {technical.momentum.signal.replace(/_/g, ' ')}
            </div>
          </div>
        )}

        {/* Volatility */}
        {technical?.volatility && (
          <div className="p-4 bg-gradient-to-br from-pink-50 to-rose-50 rounded-xl border border-pink-200">
            <div className="text-xs text-gray-700 font-semibold uppercase tracking-wide mb-1">Volatility</div>
            <div className="text-2xl font-bold text-pink-600">
              {(technical.volatility.value * 100).toFixed(1)}%
            </div>
            <div className="text-xs text-gray-600 font-medium capitalize mt-1">
              {technical.volatility.level.replace(/_/g, ' ')}
            </div>
          </div>
        )}

        {/* Volume Trend */}
        {volume && (
          <div className="p-4 bg-gradient-to-br from-teal-50 to-cyan-50 rounded-xl border border-teal-200">
            <div className="text-xs text-gray-700 font-semibold uppercase tracking-wide mb-1">Volume</div>
            <div className="text-2xl font-bold text-teal-600">
              {volume.ratio}x
            </div>
            <div className="text-xs text-gray-600 font-medium capitalize mt-1">
              {volume.trend} - {volume.signal.replace(/_/g, ' ')}
            </div>
          </div>
        )}
      </div>

      {/* Support & Resistance */}
      {technical?.support_resistance && (technical.support_resistance.support || technical.support_resistance.resistance) && (
        <div className="p-5 bg-gradient-to-br from-slate-50 to-gray-50 rounded-xl border-2 border-gray-200">
          <div className="text-sm font-bold text-gray-900 mb-3 flex items-center gap-2">
            <span>📈</span>
            Support & Resistance Levels
          </div>
          <div className="grid grid-cols-2 gap-4">
            {technical.support_resistance.support && (
              <div>
                <div className="text-xs text-gray-600 mb-1">Support</div>
                <div className="text-xl font-bold text-green-600">
                  ${technical.support_resistance.support}
                </div>
              </div>
            )}
            {technical.support_resistance.resistance && (
              <div>
                <div className="text-xs text-gray-600 mb-1">Resistance</div>
                <div className="text-xl font-bold text-red-600">
                  ${technical.support_resistance.resistance}
                </div>
              </div>
            )}
          </div>
          {technical.support_resistance.position && (
            <div className="mt-3 pt-3 border-t border-gray-200">
              <div className="text-xs text-gray-600">
                Position: <span className="font-bold capitalize">{technical.support_resistance.position.replace(/_/g, ' ')}</span>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Signal Strength Visualization */}
      {signals && (
        <div className="p-5 bg-white rounded-xl border-2 border-gray-200">
          <div className="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
            <span>🎯</span>
            Signal Strength Analysis
          </div>
          <div className="space-y-3">
            {Object.entries(signals).map(([key, value]) => (
              <div key={key}>
                <div className="flex items-center justify-between mb-1">
                  <span className="text-xs text-gray-600 font-medium capitalize">{key}</span>
                  <span className={`text-sm font-bold ${
                    value > 0 ? 'text-green-600' : value < 0 ? 'text-red-600' : 'text-gray-600'
                  }`}>
                    {value > 0 ? '+' : ''}{value}
                  </span>
                </div>
                <div className="relative h-2 bg-gray-200 rounded-full overflow-hidden">
                  <div 
                    className={`absolute h-full bg-gradient-to-r ${getSignalColor(value)} transition-all duration-500`}
                    style={{ 
                      width: `${Math.abs(value) * 100}%`,
                      left: value < 0 ? `${50 + (value * 50)}%` : '50%',
                      right: value > 0 ? `${50 - (value * 50)}%` : '50%'
                    }}
                  ></div>
                  {/* Center line */}
                  <div className="absolute left-1/2 top-0 w-px h-full bg-gray-400"></div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
