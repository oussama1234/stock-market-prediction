import { useState, useCallback, useMemo } from 'react';
import { newsAPI } from '../services/api';

export const useNews = () => {
  const [news, setNews] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const getMarketNews = useCallback(async (limit = 20) => {
    try {
      setLoading(true);
      setError(null);
      const response = await newsAPI.getMarket(limit);
      setNews(response.data || []);
    } catch (err) {
      setError(err.message || 'Failed to fetch market news');
      setNews([]);
    } finally {
      setLoading(false);
    }
  }, []);

  const getStockNews = useCallback(async (symbol, limit = 10) => {
    try {
      setLoading(true);
      setError(null);
      const response = await newsAPI.getStock(symbol, limit);
      setNews(response.data || []);
    } catch (err) {
      setError(err.message || 'Failed to fetch stock news');
      setNews([]);
    } finally {
      setLoading(false);
    }
  }, []);

  const getNewsFeed = useCallback(async (symbols = [], limit = 30) => {
    try {
      setLoading(true);
      setError(null);
      const response = await newsAPI.getFeed(symbols, limit);
      setNews(response.data || []);
    } catch (err) {
      setError(err.message || 'Failed to fetch news feed');
      setNews([]);
    } finally {
      setLoading(false);
    }
  }, []);

  const searchNews = useCallback(async (query, limit = 20) => {
    try {
      setLoading(true);
      setError(null);
      const response = await newsAPI.search(query, limit);
      setNews(response.data || []);
    } catch (err) {
      setError(err.message || 'Failed to search news');
      setNews([]);
    } finally {
      setLoading(false);
    }
  }, []);

  const clearNews = useCallback(() => {
    setNews([]);
    setError(null);
  }, []);

  return useMemo(
    () => ({
      news,
      loading,
      error,
      getMarketNews,
      getStockNews,
      getNewsFeed,
      searchNews,
      clearNews,
    }),
    [news, loading, error, getMarketNews, getStockNews, getNewsFeed, searchNews, clearNews]
  );
};
