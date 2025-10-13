/**
 * Logo Utilities
 * Helpers for fetching and displaying stock logos
 */

/**
 * Get logo URL for a stock symbol
 * Prioritizes backend-provided URL, falls back to Finnhub
 * 
 * @param {string} symbol - Stock ticker symbol
 * @param {string} logoUrl - Backend-provided logo URL (optional)
 * @returns {string|null} Logo URL or null
 */
export const getLogoUrl = (symbol, logoUrl = null) => {
  if (logoUrl) {
    return logoUrl;
  }
  
  if (!symbol) {
    return null;
  }
  
  // Finnhub static CDN (most reliable)
  return `https://static2.finnhub.io/file/publicdatany/finnhubimage/stock_logo/${symbol.toUpperCase()}.png`;
};

/**
 * Get fallback avatar letter for stock symbol
 * @param {string} symbol - Stock ticker symbol
 * @returns {string} First letter of symbol
 */
export const getLogoFallbackLetter = (symbol) => {
  if (!symbol || typeof symbol !== 'string') {
    return '?';
  }
  return symbol.charAt(0).toUpperCase();
};

/**
 * Get gradient colors based on symbol
 * Provides consistent colors for the same symbol
 * @param {string} symbol - Stock ticker symbol
 * @returns {object} Gradient colors
 */
export const getLogoGradient = (symbol) => {
  const gradients = [
    { from: 'from-indigo-500', via: 'via-purple-500', to: 'to-pink-500' },
    { from: 'from-blue-500', via: 'via-cyan-500', to: 'to-teal-500' },
    { from: 'from-purple-500', via: 'via-pink-500', to: 'to-rose-500' },
    { from: 'from-green-500', via: 'via-emerald-500', to: 'to-teal-500' },
    { from: 'from-orange-500', via: 'via-amber-500', to: 'to-yellow-500' },
    { from: 'from-red-500', via: 'via-pink-500', to: 'to-purple-500' },
  ];
  
  // Use symbol to consistently pick gradient
  const hash = symbol ? symbol.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0) : 0;
  const index = hash % gradients.length;
  
  return gradients[index];
};

/**
 * Check if a logo URL is accessible
 * @param {string} url - Logo URL to check
 * @returns {Promise<boolean>} True if accessible
 */
export const isLogoAccessible = async (url) => {
  if (!url) return false;
  
  try {
    const response = await fetch(url, { 
      method: 'HEAD',
      mode: 'no-cors', // Bypass CORS for checking
    });
    return true;
  } catch (error) {
    return false;
  }
};

export default {
  getLogoUrl,
  getLogoFallbackLetter,
  getLogoGradient,
  isLogoAccessible,
};
