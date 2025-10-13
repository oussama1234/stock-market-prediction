import { useMemo } from 'react';
import { TrendingUp, TrendingDown, BarChart3, AlertTriangle, Sparkles } from 'lucide-react';

/**
 * AlertBanner - Shows critical news sentiment alerts with animated icons
 * Displays bullish/bearish alerts based on keyword detection
 */
export default function AlertBanner({ newsSentiment, autoRegenerating }) {
  const alertData = useMemo(() => {
    // Show sentiment card if we have sentiment data (even without major alerts)
    if (!newsSentiment || !newsSentiment.overallSentiment) {
      return null;
    }

    const { overallSentiment, confidence, majorAlerts = [], analysisCount = 0 } = newsSentiment;
    
    // Don't show if no analysis
    if (analysisCount === 0) {
      return null;
    }
    
    // Don't show if truly neutral (no sentiment detected)
    if (overallSentiment === 'neutral' && (!majorAlerts || majorAlerts.length === 0)) {
      return null;
    }
    
    let bgColor, textColor, IconComponent, iconColor, borderColor, message;
    
    if (overallSentiment === 'bullish') {
      bgColor = 'bg-gradient-to-r from-green-50 to-emerald-50';
      textColor = 'text-green-800';
      borderColor = 'border-green-300';
      IconComponent = TrendingUp;
      iconColor = 'text-green-600';
      message = 'Bullish News Detected';
    } else if (overallSentiment === 'bearish') {
      bgColor = 'bg-gradient-to-r from-red-50 to-rose-50';
      textColor = 'text-red-800';
      borderColor = 'border-red-300';
      IconComponent = TrendingDown;
      iconColor = 'text-red-600';
      message = 'Bearish News Alert';
    } else if (overallSentiment === 'slightly bullish') {
      bgColor = 'bg-gradient-to-r from-blue-50 to-cyan-50';
      textColor = 'text-blue-800';
      borderColor = 'border-blue-300';
      IconComponent = BarChart3;
      iconColor = 'text-blue-600';
      message = 'Slight Bullish Trend';
    } else if (overallSentiment === 'slightly bearish') {
      bgColor = 'bg-gradient-to-r from-orange-50 to-amber-50';
      textColor = 'text-orange-800';
      borderColor = 'border-orange-300';
      IconComponent = AlertTriangle;
      iconColor = 'text-orange-600';
      message = 'Slight Bearish Pressure';
    } else {
      return null;
    }

    return { bgColor, textColor, borderColor, IconComponent, iconColor, message, confidence, majorAlerts };
  }, [newsSentiment]);

  if (!alertData) return null;

  const { bgColor, textColor, borderColor, IconComponent, iconColor, message, confidence, majorAlerts } = alertData;

  return (
    <div className={`${bgColor} border-2 ${borderColor} rounded-2xl p-6 mb-6 shadow-lg animate-fade-in-down`}>
      <div className="flex items-start gap-4">
        {/* Animated Icon */}
        <div className={`${iconColor} animate-bounce`}>
          <IconComponent className="w-12 h-12" strokeWidth={2.5} />
        </div>
        
        <div className="flex-1">
          {/* Header */}
          <div className="flex items-center justify-between mb-3">
            <h3 className={`text-xl font-bold ${textColor} flex items-center gap-2`}>
              {message}
              {autoRegenerating && (
                <span className="inline-flex items-center gap-1 text-sm font-normal text-indigo-600 animate-pulse">
                  <svg className="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Auto-analyzing...
                </span>
              )}
            </h3>
            <div className={`px-3 py-1 rounded-full text-sm font-bold ${textColor} bg-white bg-opacity-50`}>
              {confidence}% Confidence
            </div>
          </div>

          {/* Major Alerts */}
          {majorAlerts && majorAlerts.length > 0 ? (
            <div className="space-y-2">
              {majorAlerts.slice(0, 3).map((alert, idx) => (
                <div key={idx} className="flex items-start gap-3 bg-white bg-opacity-60 rounded-lg p-3 hover:bg-opacity-80 transition-all">
                  <div className={`flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold ${
                    alert.sentiment === 'bullish' ? 'bg-green-500 text-white' :
                    alert.sentiment === 'bearish' ? 'bg-red-500 text-white' :
                    'bg-gray-500 text-white'
                  }`}>
                    {idx + 1}
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className={`font-semibold ${textColor} text-sm line-clamp-2 mb-1`}>
                      {alert.article?.title || 'News article'}
                    </p>
                    {alert.matchedKeywords && alert.matchedKeywords.length > 0 && (
                      <div className="flex flex-wrap gap-1 mt-2">
                        {alert.matchedKeywords.slice(0, 4).map((kw, i) => (
                          <span
                            key={i}
                            className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                              kw.impact === 'bullish' ? 'bg-green-200 text-green-800' : 'bg-red-200 text-red-800'
                            }`}
                          >
                            {kw.keyword}
                          </span>
                        ))}
                      </div>
                    )}
                  </div>
                  <div className={`flex-shrink-0 text-lg font-bold ${textColor}`}>
                    {alert.score > 0 ? '+' : ''}{alert.score?.toFixed(1) || '0.0'}
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="bg-white bg-opacity-60 rounded-lg p-4">
              <p className={`${textColor} text-sm flex items-center gap-2`}>
                {newsSentiment.overallScore > 0 ? (
                  <>
                    <TrendingUp className="w-4 h-4" />
                    Bullish sentiment detected across {newsSentiment.analysisCount} news articles
                  </>
                ) : (
                  <>
                    <TrendingDown className="w-4 h-4" />
                    Bearish sentiment detected across {newsSentiment.analysisCount} news articles
                  </>
                )}
              </p>
              <p className={`${textColor} text-xs mt-2 opacity-75`}>
                Overall Score: {newsSentiment.overallScore > 0 ? '+' : ''}{newsSentiment.overallScore?.toFixed(2)}
              </p>
            </div>
          )}

          {/* Info Text */}
          <div className={`mt-3 text-sm ${textColor} opacity-80 flex items-center gap-2`}>
            <Sparkles className="w-4 h-4" />
            AI is monitoring news sentiment and will auto-regenerate predictions when significant changes occur
          </div>
        </div>
      </div>
    </div>
  );
}
