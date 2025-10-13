import axios from 'axios';

const API_BASE_URL = import.meta.env.VITE_API_URL || '/api';

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Request interceptor
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor
api.interceptors.response.use(
  (response) => response.data,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

// Stock API
export const stockAPI = {
  search: (query) => api.get(`/stocks/search?q=${query}`),
  getDetails: (symbol) => api.get(`/stocks/${symbol}`),
  getQuote: (symbol) => api.get(`/stocks/${symbol}/quote`),
  getPopular: () => api.get('/stocks/popular'),
  delete: (symbol) => api.delete(`/stocks/${symbol}`),
  regenerateToday: (symbol, params = {}) => api.post(`/stocks/${symbol}/regenerate-today`, null, { params }),
};

// News API
export const newsAPI = {
  getMarket: (limit = 20) => api.get(`/news/market?limit=${limit}`),
  getMarketAdvanced: (params = {}) => api.get('/news/market-advanced', { params }),
  getStock: (symbol, limit = 10) => api.get(`/news/stock/${symbol}?limit=${limit}`),
  getTrackedStocks: (limit = 9) => api.get(`/news/tracked-stocks?limit=${limit}`),
  getFeed: (symbols = [], limit = 30) => 
    api.get('/news/feed', { params: { symbols, limit } }),
  search: (query, limit = 20) => api.get(`/news/search?q=${query}&limit=${limit}`),
};

// Prediction API
export const predictionAPI = {
  get: (symbol) => api.get(`/predictions/${symbol}`),
  generate: (symbol) => api.post(`/predictions/${symbol}/generate`),
  getHistory: (symbol, limit = 10) => 
    api.get(`/predictions/${symbol}/history?limit=${limit}`),
  // quick_model_v2 predictions with Asian markets
  predict: (symbol, horizon = 'today') => 
    api.post('/predictions/predict', { symbol, horizon }),
  predictBatch: (symbols, horizon = 'today') => 
    api.post('/predictions/batch', { symbols, horizon }),
};

// Analytics API
export const analyticsAPI = {
  getAll: (symbol) => api.get(`/stocks/${symbol}/analytics`),
  getLive: (symbol) => api.get(`/stocks/${symbol}/analytics/live`),
  getIntraday: (symbol) => api.get(`/stocks/${symbol}/analytics/intraday`),
  getForecast: (symbol) => api.get(`/stocks/${symbol}/analytics/forecast`),
  getVolume: (symbol) => api.get(`/stocks/${symbol}/analytics/volume`),
  getPerformance: (symbol) => api.get(`/stocks/${symbol}/analytics/performance`),
};

// Watchlist API
export const watchlistAPI = {
  getAll: () => api.get('/watchlist'),
  getFavorites: () => api.get('/watchlist/favorites'),
  add: (data) => api.post('/watchlist', data),
  update: (id, data) => api.put(`/watchlist/${id}`, data),
  remove: (id) => api.delete(`/watchlist/${id}`),
};

// Scenarios API
export const scenariosAPI = {
  get: (symbol, timeframe = 'today') => 
    api.get(`/scenarios/${symbol}`, { params: { timeframe } }),
  generate: (symbol, timeframe = 'today') => 
    api.post(`/scenarios/${symbol}/generate`, { timeframe }),
  vote: (scenarioId) => api.post(`/scenarios/${scenarioId}/vote`),
  bookmark: (scenarioId) => api.post(`/scenarios/${scenarioId}/bookmark`),
};

// Market API
export const marketAPI = {
  getFearGreedIndex: () => api.get('/market/fear-greed-index'),
  getIndices: () => api.get('/market/indices'),
  getSentiment: () => api.get('/market/sentiment'),
  // Asian markets for quick_model_v4 (20% weight)
  getAsianMarkets: () => api.get('/asian-markets'),
  // European markets for quick_model_v4 (50% weight)
  getEuropeanMarkets: () => api.get('/european-markets'),
};

// Keywords API
export const keywordsAPI = {
  getAll: () => api.get('/keywords'),
  getHighImpact: () => api.get('/keywords/high-impact'),
  clearCache: () => api.post('/keywords/clear-cache'),
};

// Auth API
export const authAPI = {
  login: (credentials) => api.post('/login', credentials),
  register: (data) => api.post('/register', data),
  logout: () => api.post('/logout'),
  getUser: () => api.get('/user'),
};

export default api;
