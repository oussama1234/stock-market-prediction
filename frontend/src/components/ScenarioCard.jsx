import { memo, useMemo } from 'react';
import PropTypes from 'prop-types';
import { formatPercentage } from '../utils/formatters';

const ScenarioCard = memo(({ scenario, onVote, onBookmark, isHighlighted = false }) => {
  const {
    id,
    scenario_type,
    scenario_name,
    description,
    expected_change,
    target_price,
    current_price,
    confidence_level,
    confidence_label,
    trigger_indicators,
    related_news,
    suggested_action,
    action_reasoning,
    votes_count,
    bookmarks_count,
    color_class,
    is_winner,
    actual_close_price,
    actual_change_percent,
    open_price,
    is_ai_prediction,
    ai_confidence,
    ai_reasoning,
    ai_final_score,
  } = scenario;

  // Memoized color classes
  const colorClasses = useMemo(() => {
    const colors = {
      green: {
        bg: 'from-green-50 to-emerald-50',
        border: 'border-green-200',
        badge: 'bg-green-100 text-green-700',
        text: 'text-green-600',
        icon: 'bg-green-500',
      },
      red: {
        bg: 'from-red-50 to-pink-50',
        border: 'border-red-200',
        badge: 'bg-red-100 text-red-700',
        text: 'text-red-600',
        icon: 'bg-red-500',
      },
      gray: {
        bg: 'from-gray-50 to-slate-50',
        border: 'border-gray-200',
        badge: 'bg-gray-100 text-gray-700',
        text: 'text-gray-600',
        icon: 'bg-gray-500',
      },
      purple: {
        bg: 'from-purple-50 to-pink-50',
        border: 'border-purple-200',
        badge: 'bg-purple-100 text-purple-700',
        text: 'text-purple-600',
        icon: 'bg-purple-500',
      },
      orange: {
        bg: 'from-orange-50 to-amber-50',
        border: 'border-orange-200',
        badge: 'bg-orange-100 text-orange-700',
        text: 'text-orange-600',
        icon: 'bg-orange-500',
      },
      blue: {
        bg: 'from-blue-50 to-cyan-50',
        border: 'border-blue-300',
        badge: 'bg-blue-100 text-blue-700',
        text: 'text-blue-600',
        icon: 'bg-blue-500',
      },
    };
    return colors[color_class] || colors.gray;
  }, [color_class]);

  // Memoized action icon
  const actionIcon = useMemo(() => {
    switch (suggested_action) {
      case 'buy':
        return '🚀';
      case 'sell':
        return '📉';
      case 'hold':
        return '⏸️';
      case 'wait':
        return '⏳';
      default:
        return '📊';
    }
  }, [suggested_action]);

  return (
    <div className={`bg-gradient-to-br ${colorClasses.bg} rounded-2xl shadow-lg border-2 ${is_winner ? 'border-yellow-400 ring-4 ring-yellow-200' : is_ai_prediction ? 'border-blue-400 ring-2 ring-blue-200' : colorClasses.border} p-6 hover:shadow-xl transition-all ${isHighlighted ? 'ring-2 ring-amber-400' : ''}`}>
      {/* AI High-Confidence Badge */}
      {is_ai_prediction && !is_winner && (
        <div className="mb-4 -mx-6 -mt-6 bg-gradient-to-r from-blue-500 via-cyan-500 to-blue-600 px-6 py-3 rounded-t-2xl">
          <div className="flex items-center gap-2">
            <span className="text-2xl">🤖</span>
            <div>
              <div className="text-white font-bold text-lg">AI High-Confidence Prediction</div>
              <div className="text-blue-100 text-xs">Based on historical patterns, news sentiment, and technical analysis</div>
            </div>
            {ai_confidence && (
              <div className="ml-auto">
                <div className="bg-white/20 backdrop-blur-sm rounded-lg px-3 py-1.5">
                  <div className="text-white font-bold text-lg">{ai_confidence}%</div>
                  <div className="text-blue-100 text-xs">Confidence</div>
                </div>
              </div>
            )}
          </div>
        </div>
      )}
      
      {/* Winner Badge */}
      {is_winner && (
        <div className="mb-4 -mx-6 -mt-6 bg-gradient-to-r from-yellow-400 to-amber-500 px-6 py-3 rounded-t-2xl">
          <div className="flex items-center gap-2">
            <span className="text-2xl">🏆</span>
            <div>
              <div className="text-white font-bold text-lg">WINNER - Correctly Predicted!</div>
              <div className="text-yellow-100 text-xs">This scenario best matched the actual market movement</div>
            </div>
          </div>
        </div>
      )}
      
      {/* Header */}
      <div className="flex items-start justify-between mb-4">
        <div className="flex-1">
          <div className="flex items-center gap-3 mb-2">
            <h3 className="text-xl font-bold text-gray-900">
              {is_ai_prediction && <span className="mr-2">🤖</span>}
              {scenario_name}
            </h3>
            <span className={`px-3 py-1 rounded-full text-xs font-bold ${colorClasses.badge}`}>
              {scenario_type.replace('_', ' ').toUpperCase()}
            </span>
          </div>
          <p className="text-sm text-gray-600">{description}</p>
        </div>
      </div>

      {/* Price Change & Target */}
      <div className="grid grid-cols-2 gap-4 mb-4">
        <div className="bg-white/70 rounded-xl p-4">
          <div className="text-xs text-gray-600 font-semibold mb-1">
            {actual_change_percent !== null && actual_change_percent !== undefined ? 'Expected vs Actual Change' : 'Expected Change'}
          </div>
          <div className={`text-2xl font-bold ${colorClasses.text}`}>
            {formatPercentage(expected_change.percent, 2)}
          </div>
          {actual_change_percent !== null && actual_change_percent !== undefined ? (
            <div className="mt-2 pt-2 border-t border-gray-200">
              <div className="text-xs text-gray-600 font-semibold mb-1">Actual Result</div>
              <div className={`text-xl font-bold ${actual_change_percent >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                {formatPercentage(actual_change_percent, 2)}
              </div>
            </div>
          ) : (
            <div className="text-xs text-gray-500 mt-1">
              Range: {formatPercentage(expected_change.min, 1)} to {formatPercentage(expected_change.max, 1)}
            </div>
          )}
        </div>

        <div className="bg-white/70 rounded-xl p-4">
          <div className="text-xs text-gray-600 font-semibold mb-1">
            {actual_close_price ? 'Target vs Actual Price' : 'Target Price'}
          </div>
          <div className="text-2xl font-bold text-gray-900">
            ${target_price.toFixed(2)}
          </div>
          {actual_close_price ? (
            <div className="mt-2 pt-2 border-t border-gray-200">
              <div className="text-xs text-gray-600 font-semibold mb-1">Actual Close</div>
              <div className="text-xl font-bold text-gray-900">
                ${actual_close_price.toFixed(2)}
              </div>
              {open_price && (
                <div className="text-xs text-gray-500 mt-1">
                  Open: ${open_price.toFixed(2)}
                </div>
              )}
            </div>
          ) : (
            <div className="text-xs text-gray-500 mt-1">
              Current: ${current_price.toFixed(2)}
            </div>
          )}
        </div>
      </div>

      {/* Confidence Level */}
      <div className="bg-white/70 rounded-xl p-4 mb-4">
        <div className="flex items-center justify-between mb-2">
          <span className="text-sm text-gray-700 font-semibold">
            {is_ai_prediction ? '🤖 AI Confidence Level' : 'Confidence Level'}
          </span>
          <span className={`text-sm font-bold ${is_ai_prediction ? 'text-blue-600' : 'text-indigo-600'}`}>
            {confidence_level}% - {confidence_label}
          </span>
        </div>
        <div className="w-full h-3 bg-gray-200 rounded-full overflow-hidden">
          <div 
            className={`h-full rounded-full transition-all duration-500 ${is_ai_prediction ? 'bg-gradient-to-r from-blue-500 to-cyan-500' : 'bg-gradient-to-r from-indigo-500 to-purple-500'}`}
            style={{ width: `${confidence_level}%` }}
          ></div>
        </div>
        {is_ai_prediction && ai_final_score !== null && ai_final_score !== undefined && (
          <div className="mt-2 pt-2 border-t border-gray-200">
            <div className="text-xs text-gray-600">
              AI Final Score: <span className="font-bold text-blue-600">{ai_final_score.toFixed(3)}</span>
            </div>
          </div>
        )}
      </div>

      {/* Key Indicators */}
      {trigger_indicators && Object.keys(trigger_indicators).length > 0 && (
        <div className="bg-white/70 rounded-xl p-4 mb-4">
          <div className="text-sm text-gray-700 font-semibold mb-3">Key Indicators</div>
          <div className="grid grid-cols-2 gap-2">
            {Object.entries(trigger_indicators).map(([key, value]) => (
              <div key={key} className="flex items-center gap-2 text-xs">
                <span className="font-semibold text-gray-600">{key}:</span>
                <span className="text-gray-800">{value}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Related News */}
      {related_news && related_news.length > 0 && (
        <div className="bg-white/70 rounded-xl p-4 mb-4">
          <div className="text-sm text-gray-700 font-semibold mb-2 flex items-center gap-2">
            📰 Related News
          </div>
          <div className="space-y-2">
            {related_news.slice(0, 3).map((news, index) => (
              <div key={index} className="text-xs text-gray-700 line-clamp-1">
                • {news.title}
              </div>
            ))}
          </div>
        </div>
      )}

      {/* AI Reasoning (if AI prediction) */}
      {is_ai_prediction && ai_reasoning && (
        <div className="bg-gradient-to-br from-blue-50 to-cyan-50 border border-blue-200 rounded-xl p-4 mb-4">
          <div className="text-sm text-blue-900 font-semibold mb-2 flex items-center gap-2">
            <span>🧠</span>
            <span>AI Analysis</span>
          </div>
          <p className="text-xs text-blue-800 leading-relaxed">{ai_reasoning}</p>
        </div>
      )}
      
      {/* Action Recommendation */}
      <div className="bg-white/70 rounded-xl p-4 mb-4">
        <div className="flex items-center gap-3 mb-2">
          <span className="text-2xl">{actionIcon}</span>
          <div>
            <div className="text-xs text-gray-600 font-semibold">Suggested Action</div>
            <div className={`text-lg font-bold ${colorClasses.text} uppercase`}>
              {suggested_action}
            </div>
          </div>
        </div>
        {action_reasoning && (
          <p className="text-xs text-gray-600 mt-2 italic">{action_reasoning}</p>
        )}
      </div>

      {/* Interaction Buttons */}
      <div className="flex items-center gap-3">
        <button
          onClick={() => onVote(id)}
          className="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-white border-2 border-gray-200 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition-all"
        >
          <svg className="w-5 h-5 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
            <path d="M2 10.5a1.5 1.5 0 113 0v6a1.5 1.5 0 01-3 0v-6zM6 10.333v5.43a2 2 0 001.106 1.79l.05.025A4 4 0 008.943 18h5.416a2 2 0 001.962-1.608l1.2-6A2 2 0 0015.56 8H12V4a2 2 0 00-2-2 1 1 0 00-1 1v.667a4 4 0 01-.8 2.4L6.8 7.933a4 4 0 00-.8 2.4z" />
          </svg>
          <span className="text-sm font-semibold text-gray-700">{votes_count}</span>
        </button>

        <button
          onClick={() => onBookmark(id)}
          className="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-white border-2 border-gray-200 rounded-lg hover:border-amber-500 hover:bg-amber-50 transition-all"
        >
          <svg className="w-5 h-5 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
            <path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-2.5L5 18V4z" />
          </svg>
          <span className="text-sm font-semibold text-gray-700">{bookmarks_count}</span>
        </button>
      </div>
    </div>
  );
});

ScenarioCard.displayName = 'ScenarioCard';

ScenarioCard.propTypes = {
  scenario: PropTypes.shape({
    id: PropTypes.number.isRequired,
    scenario_type: PropTypes.string.isRequired,
    scenario_name: PropTypes.string.isRequired,
    description: PropTypes.string.isRequired,
    expected_change: PropTypes.shape({
      percent: PropTypes.number.isRequired,
      min: PropTypes.number.isRequired,
      max: PropTypes.number.isRequired,
    }).isRequired,
    target_price: PropTypes.number.isRequired,
    current_price: PropTypes.number.isRequired,
    confidence_level: PropTypes.number.isRequired,
    confidence_label: PropTypes.string.isRequired,
    trigger_indicators: PropTypes.object,
    related_news: PropTypes.array,
    suggested_action: PropTypes.string.isRequired,
    action_reasoning: PropTypes.string,
    votes_count: PropTypes.number.isRequired,
    bookmarks_count: PropTypes.number.isRequired,
    color_class: PropTypes.string.isRequired,
  }).isRequired,
  onVote: PropTypes.func.isRequired,
  onBookmark: PropTypes.func.isRequired,
  isHighlighted: PropTypes.bool,
};

export default ScenarioCard;
