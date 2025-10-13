import { useState, useEffect, useCallback, useRef } from 'react';
import axios from 'axios';

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';

/**
 * Custom hook for fetching and managing analytics data
 * Implements auto-refresh during market hours
 */
export const useAnalytics = (symbol) => {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [lastUpdate, setLastUpdate] = useState(null);
  const [refreshing, setRefreshing] = useState(false);
  
  const refreshIntervalRef = useRef(null);
  const isFetchingRef = useRef(false);

  const fetchAnalytics = useCallback(async (isRefresh = false) => {
    if (!symbol || isFetchingRef.current) return;

    isFetchingRef.current = true;

    try {
      if (isRefresh) {
        setRefreshing(true);
      } else {
        setLoading(true);
      }
      setError(null);

      const response = await axios.get(
        `${API_BASE_URL}/stocks/${symbol}/analytics`
      );

      if (response.data.success) {
        setData(response.data.data);
        setLastUpdate(new Date());
      } else {
        setError(response.data.message || 'Failed to load analytics');
      }
    } catch (err) {
      setError(err.response?.data?.message || err.message || 'Failed to load analytics');
      console.error('Analytics fetch error:', err);
    } finally {
      setLoading(false);
      setRefreshing(false);
      isFetchingRef.current = false;
    }
  }, [symbol]);

  const regenerateToday = useCallback(async () => {
    if (!symbol) return;

    try {
      setRefreshing(true);
      const response = await axios.post(
        `${API_BASE_URL}/stocks/${symbol}/analytics/regenerate-today`
      );

      if (response.data.success) {
        setData(response.data.data);
        setLastUpdate(new Date());
        return { success: true };
      } else {
        setError(response.data.message);
        return { success: false, message: response.data.message };
      }
    } catch (err) {
      const message = err.response?.data?.message || 'Failed to regenerate';
      setError(message);
      return { success: false, message };
    } finally {
      setRefreshing(false);
    }
  }, [symbol]);

  // Initial fetch
  useEffect(() => {
    fetchAnalytics();
  }, [fetchAnalytics]);

  // Auto-refresh during market hours (every 60 seconds)
  useEffect(() => {
    const marketStatus = data?.market_status?.status;
    
    if (marketStatus === 'open') {
      // Refresh every 60 seconds during market hours
      refreshIntervalRef.current = setInterval(() => {
        fetchAnalytics(true);
      }, 60000);
    } else {
      // Refresh every 5 minutes when market is closed
      refreshIntervalRef.current = setInterval(() => {
        fetchAnalytics(true);
      }, 300000);
    }

    return () => {
      if (refreshIntervalRef.current) {
        clearInterval(refreshIntervalRef.current);
      }
    };
  }, [data?.market_status?.status, fetchAnalytics]);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      isFetchingRef.current = false;
      if (refreshIntervalRef.current) {
        clearInterval(refreshIntervalRef.current);
      }
    };
  }, []);

  return {
    data,
    loading,
    error,
    refreshing,
    lastUpdate,
    refresh: () => fetchAnalytics(true),
    regenerateToday,
  };
};
