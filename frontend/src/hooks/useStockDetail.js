import { useState, useCallback, useEffect } from 'react';
import { stockAPI, newsAPI, predictionAPI } from '../services/api';

export const useStockDetail = (symbol) => {
  const [stock, setStock] = useState(null);
  const [quote, setQuote] = useState(null);
  const [news, setNews] = useState([]);
  const [prediction, setPrediction] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [generatingPrediction, setGeneratingPrediction] = useState(false);
  const [refreshingQuote, setRefreshingQuote] = useState(false);

  const fetchStockDetails = useCallback(async () => {
    if (!symbol) return;

    try {
      setLoading(true);
      setError(null);

      console.log(`Fetching stock details for ${symbol}...`);

      // Fetch all data in parallel for better performance
      const [stockRes, quoteRes, newsRes, predictionRes] = await Promise.allSettled([
        stockAPI.getDetails(symbol),
        stockAPI.getQuote(symbol),
        newsAPI.getStock(symbol, 5),
        predictionAPI.get(symbol).catch(() => ({ data: null })), // Return null data if no prediction
      ]);

      console.log('Stock API response:', stockRes);
      console.log('Quote API response:', quoteRes);

      if (stockRes.status === 'fulfilled' && stockRes.value) {
        const response = stockRes.value;
        
        // Check if response is successful
        if (response.success === false) {
          throw new Error(response.message || 'Stock not found');
        }
        
        // API returns { success: true, data: { stock, quote, prediction } }
        const stockData = response.data;
        
        if (stockData && stockData.stock) {
          console.log('Setting stock data:', stockData.stock);
          setStock(stockData.stock);
          
          // Also set quote from stock data if available
          if (stockData.quote) {
            console.log('Setting quote from stock data:', stockData.quote);
            setQuote(stockData.quote);
          }
          
          // Set prediction from stock data if available
          if (stockData.prediction) {
            console.log('Setting prediction from stock data:', stockData.prediction);
            setPrediction(stockData.prediction);
          }
        } else {
          throw new Error('Invalid stock data received');
        }
      } else {
        const errorMsg = stockRes.reason?.message || 'Failed to load stock details';
        console.error('Stock fetch failed:', stockRes.reason);
        throw new Error(errorMsg);
      }

      // Override with direct quote endpoint if available
      if (quoteRes.status === 'fulfilled' && quoteRes.value?.data) {
        const quoteData = quoteRes.value.data;
        console.log('Updating quote from quote endpoint:', quoteData);
        setQuote(quoteData.quote || quoteData);
      }

      if (newsRes.status === 'fulfilled' && newsRes.value?.data) {
        console.log('Setting news:', newsRes.value.data.length, 'articles');
        setNews(newsRes.value.data || []);
      }

      // Override with direct prediction endpoint if available
      if (predictionRes.status === 'fulfilled' && predictionRes.value?.data) {
        const predData = predictionRes.value.data;
        if (predData && (predData.prediction || predData.id)) {
          console.log('Updating prediction from prediction endpoint:', predData);
          setPrediction(predData.prediction || predData);
        }
      }

      console.log('Stock details loaded successfully');

    } catch (err) {
      const errorMessage = err.message || 'Failed to load stock details';
      console.error('Error fetching stock details:', errorMessage, err);
      setError(errorMessage);
    } finally {
      setLoading(false);
    }
  }, [symbol]);

  const generatePrediction = useCallback(async () => {
    if (!symbol) return;

    try {
      setGeneratingPrediction(true);
      const response = await predictionAPI.generate(symbol);
      
      console.log('Raw prediction response:', response);
      
      // Response is already unwrapped: { success: true, data: prediction, message: '...' }
      const newPrediction = response.data;
      
      console.log('New prediction data:', newPrediction);
      console.log('Has indicators:', !!newPrediction?.indicators);
      console.log('Has reasoning:', !!newPrediction?.reasoning);
      
      setPrediction(newPrediction);
      
      // Also refresh the quote to get latest price
      try {
        const quoteResponse = await stockAPI.getQuote(symbol);
        const newQuote = quoteResponse.data || quoteResponse;
        console.log('New quote data:', newQuote);
        setQuote(newQuote);
      } catch (e) {
        console.warn('Failed to refresh quote:', e);
      }
      
      return newPrediction;
    } catch (err) {
      console.error('Failed to generate prediction:', err);
      throw err;
    } finally {
      setGeneratingPrediction(false);
    }
  }, [symbol]);

  const refreshQuote = useCallback(async () => {
    if (!symbol) return;

    try {
      setRefreshingQuote(true);
      console.log('Refreshing quote for', symbol);
      const response = await stockAPI.getQuote(symbol);
      const newQuote = response.data || response;
      console.log('Quote refreshed:', newQuote);
      setQuote(newQuote);
      return newQuote;
    } catch (err) {
      console.error('Failed to refresh quote:', err);
      throw err;
    } finally {
      setRefreshingQuote(false);
    }
  }, [symbol]);

  // Auto-fetch on mount or symbol change with retry
  useEffect(() => {
    let retryCount = 0;
    const maxRetries = 3; // Increased from 2 to 3
    let retryTimeout;
    
    const fetchWithRetry = async () => {
      try {
        await fetchStockDetails();
      } catch (err) {
        if (retryCount < maxRetries) {
          retryCount++;
          const delay = retryCount === 1 ? 2000 : 1500; // First retry after 2s, then 1.5s
          console.log(`Retrying stock fetch (${retryCount}/${maxRetries}) in ${delay}ms...`);
          retryTimeout = setTimeout(fetchWithRetry, delay);
        } else {
          console.error('Max retries reached. Stock may need to be created.');
        }
      }
    };
    
    fetchWithRetry();
    
    // Cleanup function
    return () => {
      if (retryTimeout) {
        clearTimeout(retryTimeout);
      }
    };
  }, [fetchStockDetails]);

  return {
    stock,
    quote,
    news,
    prediction,
    loading,
    error,
    generatingPrediction,
    refreshingQuote,
    fetchStockDetails,
    generatePrediction,
    refreshQuote,
  };
};

