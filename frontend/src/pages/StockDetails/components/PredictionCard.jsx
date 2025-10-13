import { useMemo } from 'react';

/**
 * PredictionCard - Shows AI prediction with model reasoning and confidence
 * Displays direction, confidence, predicted price, and rationale with animations
 */
export default function PredictionCard({ prediction, onRegenerate, regenerating }) {
  const predictionData = useMemo(() => {
    if (!prediction) return null;

    const direction = prediction.direction || 'neutral';
    const confidence = ((prediction.probability || prediction.confidence_score || 0.5) * 100).toFixed(1);
    const predictedPrice = Number(prediction.predicted_price || 0).toFixed(2);
    const currentPrice = Number(prediction.current_price || 0).toFixed(2);
    const predictedChange = prediction.predicted_change_percent || 0;

    let bgColor, textColor, icon, label;
    
    if (direction === 'up') {
      bgColor = 'bg-gradient-to-br from-green-50 to-emerald-100';
      textColor = 'text-green-700';
      icon = '📈';
      label = prediction.label || 'Bullish';
    } else if (direction === 'down') {
      bgColor = 'bg-gradient-to-br from-red-50 to-rose-100';
      textColor = 'text-red-700';
      icon = '📉';
      label = prediction.label || 'Bearish';
    } else {
      bgColor = 'bg-gradient-to-br from-gray-50 to-slate-100';
      textColor = 'text-gray-700';
      icon = '➡️';
      label = prediction.label || 'Neutral';
    }

    return {
      direction,
      confidence,
      predictedPrice,
      currentPrice,
      predictedChange,
      bgColor,
      textColor,
      icon,
      label,
      rationale: prediction.rationale,
      modelVersion: prediction.model_version || 'AI v2.0',
      horizon: prediction.horizon || 'today',
    };
  }, [prediction]);

  if (!predictionData) {
    return (
      <div className="bg-white rounded-2xl shadow-xl p-8 border-2 border-gray-200">
        <div className="text-center py-12">
          <div className="text-6xl mb-4">🤖</div>
          <h3 className="text-2xl font-bold text-gray-900 mb-2">No Prediction Available</h3>
          <p className="text-gray-600 mb-6">Generate an AI prediction to see insights</p>
          <button
            onClick={() => onRegenerate('today')}
            disabled={regenerating}
            className="px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 disabled:opacity-50 transition-all hover:scale-105"
          >
            {regenerating ? 'Generating...' : 'Generate Prediction'}
          </button>
        </div>
      </div>
    );
  }

  const { bgColor, textColor, icon, label, confidence, predictedPrice, currentPrice, predictedChange, rationale, modelVersion, horizon } = predictionData;

  return (
    <div className={`${bgColor} rounded-2xl shadow-xl p-8 border-2 ${textColor} border-opacity-30 hover:shadow-2xl transition-all`}>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <span className="text-4xl animate-pulse">{icon}</span>
          <div>
            <h3 className="text-2xl font-bold text-gray-900">AI Prediction</h3>
            <p className="text-sm text-gray-600">Powered by {modelVersion}</p>
          </div>
        </div>
        <div className={`px-4 py-2 rounded-full text-lg font-bold ${textColor} bg-white bg-opacity-60`}>
          {label}
        </div>
      </div>

      {/* Main Stats Grid */}
      <div className="grid md:grid-cols-3 gap-4 mb-6">
        <div className="bg-white bg-opacity-70 rounded-xl p-4 hover:bg-opacity-90 transition-all">
          <div className="text-sm text-gray-600 mb-1 flex items-center gap-2">
            <span>🎯</span> Confidence
          </div>
          <div className="text-3xl font-black text-gray-900">{confidence}%</div>
          <div className="mt-2 h-2 bg-gray-200 rounded-full overflow-hidden">
            <div 
              className={`h-full ${textColor} bg-opacity-60 transition-all duration-1000`}
              style={{ width: `${confidence}%` }}
            ></div>
          </div>
        </div>

        <div className="bg-white bg-opacity-70 rounded-xl p-4 hover:bg-opacity-90 transition-all">
          <div className="text-sm text-gray-600 mb-1 flex items-center gap-2">
            <span>💲</span> Predicted Price
          </div>
          <div className="text-3xl font-black text-gray-900">${predictedPrice}</div>
          <div className={`text-sm font-semibold mt-1 ${textColor}`}>
            {predictedChange > 0 ? '+' : ''}{predictedChange.toFixed(2)}% change
          </div>
        </div>

        <div className="bg-white bg-opacity-70 rounded-xl p-4 hover:bg-opacity-90 transition-all">
          <div className="text-sm text-gray-600 mb-1 flex items-center gap-2">
            <span>📊</span> Current Price
          </div>
          <div className="text-3xl font-black text-gray-900">${currentPrice}</div>
          <div className="text-sm text-gray-600 mt-1">
            Horizon: {horizon === 'today' ? '24h' : 'Tomorrow'}
          </div>
        </div>
      </div>

      {/* AI Reasoning */}
      {rationale && (
        <div className="bg-white bg-opacity-70 rounded-xl p-6 mb-6">
          <h4 className="text-lg font-bold text-gray-900 mb-3 flex items-center gap-2">
            <span>🧠</span> AI Reasoning
          </h4>
          <p className="text-gray-700 leading-relaxed">{rationale}</p>
        </div>
      )}

      {/* Model Info & Indicators */}
      <div className="bg-white bg-opacity-70 rounded-xl p-6 mb-6">
        <h4 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
          <span>📈</span> Analysis Factors
        </h4>
        <div className="grid md:grid-cols-2 gap-4">
          <div className="flex items-start gap-3">
            <div className="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center flex-shrink-0">
              <span>📉</span>
            </div>
            <div>
              <div className="font-semibold text-gray-900">Price Trends</div>
              <div className="text-sm text-gray-600">
                Historical price movement patterns
              </div>
            </div>
          </div>
          <div className="flex items-start gap-3">
            <div className="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
              <span>🎲</span>
            </div>
            <div>
              <div className="font-semibold text-gray-900">Machine Learning</div>
              <div className="text-sm text-gray-600">
                Advanced AI pattern recognition
              </div>
            </div>
          </div>
          <div className="flex items-start gap-3">
            <div className="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
              <span>⚡</span>
            </div>
            <div>
              <div className="font-semibold text-gray-900">Real-time Data</div>
              <div className="text-sm text-gray-600">
                Live market data integration
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Regenerate Button */}
      <div>
        <button
          onClick={() => onRegenerate('today')}
          disabled={regenerating}
          className="w-full px-6 py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl font-bold hover:from-indigo-700 hover:to-purple-700 disabled:opacity-50 transition-all hover:scale-105 shadow-lg flex items-center justify-center gap-2"
        >
          {regenerating ? (
            <>
              <svg className="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>Regenerating...</span>
            </>
          ) : (
            <>
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              <span>Regenerate Prediction</span>
            </>
          )}
        </button>
      </div>
    </div>
  );
}
