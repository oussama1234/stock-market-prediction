import { useState, useCallback, useMemo } from 'react';
import { stockAPI } from '../services/api';

export const useStocks = () => {
  const [stocks, setStocks] = useState([]);
  const [popularStocks, setPopularStocks] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const searchStocks = useCallback(async (query) => {
    if (!query.trim()) {
      setStocks([]);
      return;
    }

    try {
      setLoading(true);
      setError(null);
      const response = await stockAPI.search(query);
      console.log('🔍 Search API Response:', response);
      console.log('🔍 Search Results Data:', response.data);
      setStocks(response.data || []);
    } catch (err) {
      setError(err.message || 'Failed to search stocks');
      setStocks([]);
    } finally {
      setLoading(false);
    }
  }, []);

  const getStockDetails = useCallback(async (symbol) => {
    try {
      setLoading(true);
      setError(null);
      const response = await stockAPI.getDetails(symbol);
      return response.data;
    } catch (err) {
      setError(err.message || 'Failed to fetch stock details');
      throw err;
    } finally {
      setLoading(false);
    }
  }, []);

  const getStockQuote = useCallback(async (symbol) => {
    try {
      const response = await stockAPI.getQuote(symbol);
      return response.data;
    } catch (err) {
      console.error('Failed to fetch quote:', err);
      throw err;
    }
  }, []);

  const getPopularStocks = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await stockAPI.getPopular();
      setPopularStocks(response.data || []);
    } catch (err) {
      setError(err.message || 'Failed to fetch popular stocks');
      setPopularStocks([]);
    } finally {
      setLoading(false);
    }
  }, []);

  const clearStocks = useCallback(() => {
    setStocks([]);
    setError(null);
  }, []);

  return useMemo(
    () => ({
      stocks,
      popularStocks,
      loading,
      error,
      searchStocks,
      getStockDetails,
      getStockQuote,
      getPopularStocks,
      clearStocks,
      clearSearch: clearStocks, // Alias for clearing search results
    }),
    [stocks, popularStocks, loading, error, searchStocks, getStockDetails, getStockQuote, getPopularStocks, clearStocks]
  );
};
