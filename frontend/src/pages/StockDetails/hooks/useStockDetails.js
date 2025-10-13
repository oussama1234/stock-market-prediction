import { useState, useEffect, useCallback, useRef } from 'react';
import { stockAPI, newsAPI } from '../../../services/api';
import { aggregateNewsSentiment } from '../../../utils/keywordDetection';

/**
 * Custom hook for managing stock details with automatic news-based prediction
 * Monitors news sentiment and triggers prediction regeneration when significant changes occur
 */
export function useStockDetails(symbol) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [newsSentiment, setNewsSentiment] = useState(null);
  const [autoRegenerating, setAutoRegenerating] = useState(false);
  
  const lastSentimentRef = useRef(null);
  const newsCheckIntervalRef = useRef(null);

  // Fetch stock details
  const fetchStockDetails = useCallback(async (silent = false) => {
    try {
      if (!silent) setLoading(true);
      setError(null);

      const response = await stockAPI.getDetails(symbol);
      
      if (response?.success || response?.data) {
        const stockData = response.data || response;
        setData(stockData);
        
        // Analyze news sentiment
        if (stockData.news && stockData.news.length > 0) {
          const sentiment = aggregateNewsSentiment(stockData.news);
          console.log('📊 News Sentiment Analysis:', {
            analysisCount: sentiment.analysisCount,
            overallSentiment: sentiment.overallSentiment,
            overallScore: sentiment.overallScore,
            confidence: sentiment.confidence,
            majorAlertsCount: sentiment.majorAlerts?.length || 0
          });
          setNewsSentiment(sentiment);
          
          // Check if we need to auto-regenerate based on news
          checkAutoRegenerate(sentiment);
        } else {
          console.log('⚠️ No news articles found for sentiment analysis');
        }
        
        return stockData;
      }
    } catch (err) {
      console.error('Error fetching stock details:', err);
      setError(err.message || 'Failed to load stock');
    } finally {
      if (!silent) setLoading(false);
    }
  }, [symbol]);

  // Check if we need to auto-regenerate prediction based on news sentiment
  const checkAutoRegenerate = useCallback((sentiment) => {
    if (!sentiment || !sentiment.majorAlerts || sentiment.majorAlerts.length === 0) {
      return;
    }

    const lastSentiment = lastSentimentRef.current;
    
    // First time or significant sentiment change
    if (!lastSentiment || 
        Math.abs(sentiment.overallScore - lastSentiment.overallScore) > 2 ||
        sentiment.majorAlerts.length > lastSentiment.majorAlerts.length) {
      
      console.log('🔔 Significant news detected! Auto-regenerating prediction...');
      lastSentimentRef.current = sentiment;
      
      // Trigger automatic regeneration
      handleAutoRegenerate();
    }
  }, []);

  // Automatic regeneration triggered by news
  const handleAutoRegenerate = useCallback(async () => {
    if (autoRegenerating) return; // Prevent multiple simultaneous regenerations
    
    try {
      setAutoRegenerating(true);
      console.log('🤖 Auto-regenerating prediction based on news sentiment...');
      
      // Use async regeneration to avoid blocking
      await stockAPI.regenerateToday(symbol, { horizon: 'today', async: true });
      
      // Wait a bit then refresh
      setTimeout(async () => {
        await fetchStockDetails(true);
        setAutoRegenerating(false);
      }, 2000);
      
    } catch (err) {
      console.error('Auto-regeneration failed:', err);
      setAutoRegenerating(false);
    }
  }, [symbol, autoRegenerating, fetchStockDetails]);

  // Manual regeneration
  const regenerate = useCallback(async (horizon = 'today') => {
    try {
      setAutoRegenerating(true);
      await stockAPI.regenerateToday(symbol, { horizon });
      
      setTimeout(async () => {
        await fetchStockDetails(true);
        setAutoRegenerating(false);
      }, 1500);
      
      return true;
    } catch (err) {
      console.error('Regeneration failed:', err);
      setAutoRegenerating(false);
      return false;
    }
  }, [symbol, fetchStockDetails]);

  // Check for new news periodically (every 2 minutes)
  useEffect(() => {
    if (!symbol) return;

    // Initial fetch
    fetchStockDetails();

    // Set up periodic news checking
    newsCheckIntervalRef.current = setInterval(() => {
      console.log('🔍 Checking for new news...');
      fetchStockDetails(true); // Silent refresh
    }, 120000); // 2 minutes

    return () => {
      if (newsCheckIntervalRef.current) {
        clearInterval(newsCheckIntervalRef.current);
      }
    };
  }, [symbol, fetchStockDetails]);

  return {
    data,
    loading,
    error,
    newsSentiment,
    autoRegenerating,
    regenerate,
    refresh: fetchStockDetails,
    isLive: newsCheckIntervalRef.current !== null, // Live if interval is active
  };
}
