import { memo } from 'react';
import PropTypes from 'prop-types';
import { useScenarios } from '../hooks/useScenarios';
import ScenarioCard from './ScenarioCard';
import { GenericLoader } from './loaders';

const ScenariosSection = memo(({ symbol, timeframe = 'today' }) => {
  const {
    scenarios,
    loading,
    generating,
    error,
    topScenario,
    generateScenarios,
    voteScenario,
    bookmarkScenario,
  } = useScenarios(symbol, timeframe);
  
  // Check if winner is determined (market closed with results)
  const winnerDetermined = scenarios.some(s => s.is_winner === true);
  
  // Check if there's a winner
  const winnerScenario = scenarios.find(s => s.is_winner);
  
  // Group scenarios by strategy type
  // Exclude top scenario and winner from other sections to avoid duplicates
  const momentumScenarios = scenarios.filter(s => {
    const isTopScenario = topScenario && String(s.id) === String(topScenario.id);
    const isWinnerScenario = winnerScenario && String(s.id) === String(winnerScenario.id);
    const isCorrectType = ['bullish', 'bearish', 'neutral', 'momentum_reversal', 'volatility_breakout'].includes(s.scenario_type);
    return isCorrectType && !isTopScenario && !isWinnerScenario;
  });
  
  const volumeVolatilityScenarios = scenarios.filter(s => {
    const isTopScenario = topScenario && String(s.id) === String(topScenario.id);
    const isWinnerScenario = winnerScenario && String(s.id) === String(winnerScenario.id);
    const isCorrectType = ['accumulation_phase', 'distribution_phase', 'volatility_expansion'].includes(s.scenario_type);
    return isCorrectType && !isTopScenario && !isWinnerScenario;
  });
  
  // Get smart timeframe description
  const getTimeframeDescription = () => {
    const now = new Date();
    const etTime = new Date(now.toLocaleString('en-US', { timeZone: 'America/New_York' }));
    const dayOfWeek = etTime.getDay();
    const hours = etTime.getHours();
    const minutes = etTime.getMinutes();
    const currentMinutes = hours * 60 + minutes;
    const marketOpen = 9 * 60 + 30;
    const marketClose = 16 * 60;
    
    if (timeframe === 'today') {
      if (dayOfWeek === 0 || dayOfWeek === 6) return 'Next Trading Day';
      if (currentMinutes < marketOpen) return 'Today\'s Market';
      if (currentMinutes >= marketOpen && currentMinutes < marketClose) return 'Rest of Today';
      if (dayOfWeek === 5) return 'Monday\'s Market';
      return 'Tomorrow\'s Market';
    } else {
      // For tomorrow timeframe
      if (dayOfWeek === 0 || dayOfWeek === 6) return 'Monday\'s Market'; // Weekend -> Monday
      if (dayOfWeek === 5 && currentMinutes >= marketClose) return 'Monday\'s Market'; // Friday after close -> Monday
      // Weekdays: show the actual next day
      const nextDayName = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][(dayOfWeek + 1) % 7];
      return `${nextDayName}\'s Market`;
    }
  };

  if (loading) {
    return (
      <div className="bg-white rounded-2xl shadow-xl p-8 border border-gray-200">
        <GenericLoader message="Loading market scenarios" size="medium" fullScreen={false} />
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-white rounded-2xl shadow-xl p-8 border border-red-200">
        <div className="text-center">
          <div className="text-red-500 text-5xl mb-4">⚠️</div>
          <h3 className="text-xl font-bold text-gray-900 mb-2">Error Loading Scenarios</h3>
          <p className="text-gray-600 mb-4">{error}</p>
          <button
            onClick={generateScenarios}
            className="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
          >
            Try Generating
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-8">
      {/* Header with Gradient Background */}
      <div className="relative overflow-hidden bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 rounded-3xl shadow-2xl p-8">
        {/* Animated Background Pattern */}
        <div className="absolute inset-0 opacity-10">
          <div className="absolute top-0 left-0 w-96 h-96 bg-white rounded-full blur-3xl animate-pulse"></div>
          <div className="absolute bottom-0 right-0 w-96 h-96 bg-white rounded-full blur-3xl animate-pulse" style={{ animationDelay: '1s' }}></div>
        </div>
        
        <div className="relative flex items-center justify-between">
          <div className="flex-1">
            <div className="flex items-center gap-4 mb-3">
              <div className="w-16 h-16 bg-white/20 backdrop-blur-lg rounded-2xl flex items-center justify-center shadow-xl">
                <span className="text-4xl">🎯</span>
              </div>
              <div>
                <h2 className="text-3xl font-black text-white mb-1">
                  Multi-Scenario Market Analysis
                </h2>
                <p className="text-blue-100 text-sm font-medium">
                  {scenarios.length} predictive scenario{scenarios.length !== 1 ? 's' : ''} • 90-day historical analysis • {getTimeframeDescription()} outlook
                </p>
              </div>
            </div>
            <div className="flex flex-wrap gap-2 mt-4">
              <span className="px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full text-xs font-semibold text-white">
                RSI • MACD • EMA • Bollinger Bands
              </span>
              <span className="px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full text-xs font-semibold text-white">
                ATR • VWAP • OBV • VFI
              </span>
              <span className="px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full text-xs font-semibold text-white">
                Sentiment • Volume Flow
              </span>
            </div>
          </div>
          
          <button
            onClick={generateScenarios}
            disabled={generating || (timeframe === 'today' && winnerDetermined)}
            className="relative group px-8 py-4 bg-white/20 backdrop-blur-lg hover:bg-white/30 text-white font-bold rounded-2xl shadow-2xl hover:shadow-white/20 transform hover:scale-105 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-3 border-2 border-white/30"
            title={timeframe === 'today' && winnerDetermined ? 'Cannot regenerate - results are final' : ''}
          >
          {timeframe === 'today' && winnerDetermined ? (
            <>
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
              Results Final
            </>
          ) : generating ? (
            <>
              <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none"></circle>
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Generating...
            </>
          ) : (
            <>
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              Regenerate
            </>
          )}
          </button>
        </div>
      </div>

      {/* Winner Scenario - Show when market closed */}
      {winnerScenario && (
        <div className="bg-gradient-to-r from-yellow-50 to-amber-50 rounded-2xl shadow-2xl p-6 border-4 border-yellow-300">
          <div className="flex items-center gap-3 mb-4">
            <span className="text-4xl">🏆</span>
            <div>
              <h3 className="text-2xl font-bold text-gray-900">Winning Prediction!</h3>
              <p className="text-sm text-gray-600">This scenario correctly predicted today's market movement</p>
            </div>
          </div>
          <ScenarioCard 
            scenario={winnerScenario} 
            onVote={voteScenario}
            onBookmark={bookmarkScenario}
            isHighlighted
          />
        </div>
      )}
      
      {/* Top Scenario Highlight - Only show if no winner or different from winner */}
      {topScenario && scenarios.length > 1 && (!winnerScenario || topScenario.id !== winnerScenario.id) && (
        <div className="bg-gradient-to-r from-amber-50 to-orange-50 rounded-2xl shadow-lg p-6 border-2 border-amber-200">
          <div className="flex items-center gap-3 mb-4">
            <span className="text-3xl">⭐</span>
            <div>
              <h3 className="text-lg font-bold text-gray-900">Highest Confidence Scenario</h3>
              <p className="text-sm text-gray-600">Based on technical analysis and market sentiment</p>
            </div>
          </div>
          <ScenarioCard 
            scenario={topScenario} 
            onVote={voteScenario}
            onBookmark={bookmarkScenario}
            isHighlighted
          />
        </div>
      )}

      {/* Momentum & Trend Strategy Scenarios */}
      {momentumScenarios.length > 0 && (
        <div className="space-y-4">
          <div className="flex items-center gap-3 px-2">
            <span className="text-2xl">📈</span>
            <div>
              <h3 className="text-xl font-bold text-gray-900">Momentum & Trend Scenarios</h3>
              <p className="text-sm text-gray-600">Based on RSI, MACD, EMA, Moving Averages, and Sentiment Analysis</p>
            </div>
          </div>
          <div className="grid lg:grid-cols-2 gap-6">
            {momentumScenarios.map((scenario) => (
              <ScenarioCard
                key={scenario.id}
                scenario={scenario}
                onVote={voteScenario}
                onBookmark={bookmarkScenario}
              />
            ))}
          </div>
        </div>
      )}
      
      {/* Volume Flow & Volatility Expansion Scenarios */}
      {volumeVolatilityScenarios.length > 0 && (
        <div className="space-y-4">
          <div className="flex items-center gap-3 px-2">
            <span className="text-2xl">📊</span>
            <div>
              <h3 className="text-xl font-bold text-gray-900">Volume & Volatility Expansion Scenarios</h3>
              <p className="text-sm text-gray-600">Advanced Volume Flow Index (VFI), OBV Divergence, Bollinger Bands, and Institutional Activity</p>
            </div>
          </div>
          <div className="grid lg:grid-cols-2 gap-6">
            {volumeVolatilityScenarios.map((scenario) => (
              <ScenarioCard
                key={scenario.id}
                scenario={scenario}
                onVote={voteScenario}
                onBookmark={bookmarkScenario}
              />
            ))}
          </div>
        </div>
      )}
      
      {/* No Scenarios Message */}
      {scenarios.length === 0 && (
        <div className="bg-white rounded-2xl shadow-xl p-12 text-center border border-gray-200">
          <div className="text-6xl mb-4">📊</div>
          <h3 className="text-2xl font-bold text-gray-900 mb-2">No Scenarios Available</h3>
          <p className="text-gray-600 mb-6">
            Generate market prediction scenarios to help with your trading decisions
          </p>
          <button
            onClick={generateScenarios}
            disabled={generating}
            className="px-8 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold rounded-xl hover:shadow-lg transform hover:scale-105 transition-all"
          >
            {generating ? 'Generating...' : 'Generate Scenarios'}
          </button>
        </div>
      )}
    </div>
  );
});

ScenariosSection.displayName = 'ScenariosSection';

ScenariosSection.propTypes = {
  symbol: PropTypes.string.isRequired,
  timeframe: PropTypes.oneOf(['today', 'tomorrow']),
};

export default ScenariosSection;
