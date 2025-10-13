import { useState, useCallback, useEffect, useMemo } from 'react';
import { scenariosAPI } from '../services/api';

export const useScenarios = (symbol, timeframe = 'today') => {
  const [scenarios, setScenarios] = useState([]);
  const [stockInfo, setStockInfo] = useState(null);
  const [loading, setLoading] = useState(true);
  const [generating, setGenerating] = useState(false);
  const [error, setError] = useState(null);

  // Fetch scenarios
  const fetchScenarios = useCallback(async () => {
    if (!symbol) return;

    try {
      setLoading(true);
      setError(null);

      const response = await scenariosAPI.get(symbol, timeframe);

      if (response.success) {
        setScenarios(response.data.scenarios);
        setStockInfo(response.data.stock);
      } else {
        setError(response.message || 'Failed to fetch scenarios');
      }
    } catch (err) {
      console.error('Error fetching scenarios:', err);
      setError(err.response?.data?.message || 'Failed to load scenarios');
    } finally {
      setLoading(false);
    }
  }, [symbol, timeframe]);

  // Generate new scenarios
  const generateScenarios = useCallback(async () => {
    if (!symbol) return;

    try {
      setGenerating(true);
      setError(null);

      const response = await scenariosAPI.generate(symbol, timeframe);

      if (response.success) {
        setScenarios(response.data.scenarios);
        return response.data.scenarios;
      } else {
        throw new Error('Failed to generate scenarios');
      }
    } catch (err) {
      console.error('Error generating scenarios:', err);
      setError('Failed to generate scenarios');
      throw err;
    } finally {
      setGenerating(false);
    }
  }, [symbol, timeframe]);

  // Vote for a scenario (optimistic update)
  const voteScenario = useCallback(async (scenarioId) => {
    try {
      // Optimistic update
      setScenarios(prev => prev.map(s => 
        s.id === scenarioId 
          ? { ...s, votes_count: s.votes_count + 1 }
          : s
      ));

      await scenariosAPI.vote(scenarioId);
    } catch (err) {
      console.error('Error voting:', err);
      // Revert on error
      fetchScenarios();
    }
  }, [fetchScenarios]);

  // Bookmark a scenario (optimistic update)
  const bookmarkScenario = useCallback(async (scenarioId) => {
    try {
      // Optimistic update
      setScenarios(prev => prev.map(s => 
        s.id === scenarioId 
          ? { ...s, bookmarks_count: s.bookmarks_count + 1 }
          : s
      ));

      await scenariosAPI.bookmark(scenarioId);
    } catch (err) {
      console.error('Error bookmarking:', err);
      // Revert on error
      fetchScenarios();
    }
  }, [fetchScenarios]);

  // Memoized scenario groups
  const groupedScenarios = useMemo(() => {
    const groups = {
      bullish: [],
      bearish: [],
      neutral: [],
      momentum_reversal: [],
      volatility_breakout: [],
    };

    scenarios.forEach(scenario => {
      if (groups[scenario.scenario_type]) {
        groups[scenario.scenario_type].push(scenario);
      }
    });

    return groups;
  }, [scenarios]);

  // Memoized top scenario
  // Prioritize AI predictions, then highest confidence
  const topScenario = useMemo(() => {
    if (scenarios.length === 0) return null;
    
    // First, check if there's an AI prediction
    const aiScenario = scenarios.find(s => s.is_ai_prediction === true);
    if (aiScenario) {
      return aiScenario;
    }
    
    // Otherwise, return highest confidence scenario
    return scenarios.reduce((max, scenario) => 
      scenario.confidence_level > max.confidence_level ? scenario : max
    , scenarios[0]);
  }, [scenarios]);

  // Auto-fetch on mount or symbol/timeframe change
  useEffect(() => {
    fetchScenarios();
  }, [fetchScenarios]);

  return {
    scenarios,
    stockInfo,
    loading,
    generating,
    error,
    groupedScenarios,
    topScenario,
    fetchScenarios,
    generateScenarios,
    voteScenario,
    bookmarkScenario,
  };
};
