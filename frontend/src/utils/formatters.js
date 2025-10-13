/**
 * Format number as currency
 */
export const formatCurrency = (value, currency = 'USD') => {
  if (value === null || value === undefined || isNaN(value)) return 'N/A';
  
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value));
};

/**
 * Format number as percentage
 */
export const formatPercentage = (value, decimals = 2) => {
  if (value === null || value === undefined || isNaN(value)) return 'N/A';
  
  const numValue = Number(value);
  const sign = numValue >= 0 ? '+' : '';
  return `${sign}${numValue.toFixed(decimals)}%`;
};

/**
 * Format large numbers (1M, 1B, etc.)
 */
export const formatLargeNumber = (value) => {
  if (value === null || value === undefined) return 'N/A';
  
  if (value >= 1e12) return `${(value / 1e12).toFixed(2)}T`;
  if (value >= 1e9) return `${(value / 1e9).toFixed(2)}B`;
  if (value >= 1e6) return `${(value / 1e6).toFixed(2)}M`;
  if (value >= 1e3) return `${(value / 1e3).toFixed(2)}K`;
  
  return value.toFixed(2);
};

/**
 * Format volume in millions with 2 decimal places
 */
export const formatVolume = (value, decimals = 2) => {
  if (value === null || value === undefined || value === 0) return '0.00M';
  
  const volumeInMillions = value / 1e6;
  return `${volumeInMillions.toFixed(decimals)}M`;
};

/**
 * Format date to readable string
 */
export const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

/**
 * Format relative time (e.g., "2 hours ago")
 */
export const formatRelativeTime = (dateString) => {
  if (!dateString) return 'N/A';
  
  const date = new Date(dateString);
  const now = new Date();
  const diffMs = now - date;
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMins / 60);
  const diffDays = Math.floor(diffHours / 24);
  
  if (diffMins < 1) return 'just now';
  if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
  if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
  if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
  
  return formatDate(dateString);
};

/**
 * Get color class based on value (green for positive, red for negative)
 */
export const getChangeColor = (value) => {
  if (value > 0) return 'text-green-600';
  if (value < 0) return 'text-red-600';
  return 'text-gray-600';
};

/**
 * Get background color class based on value
 */
export const getChangeBgColor = (value) => {
  if (value > 0) return 'bg-green-100 text-green-800';
  if (value < 0) return 'bg-red-100 text-red-800';
  return 'bg-gray-100 text-gray-800';
};

/**
 * Truncate text with ellipsis
 */
export const truncateText = (text, maxLength = 100) => {
  if (!text) return '';
  if (text.length <= maxLength) return text;
  return text.substring(0, maxLength) + '...';
};

/**
 * Get sentiment color based on score
 */
export const getSentimentColor = (score) => {
  if (score >= 3) return 'text-green-600';
  if (score >= 1) return 'text-green-500';
  if (score > -1) return 'text-gray-600';
  if (score > -3) return 'text-orange-500';
  return 'text-red-600';
};

/**
 * Get sentiment label from score
 */
export const getSentimentLabel = (score) => {
  if (score >= 3) return 'Very Positive';
  if (score >= 1) return 'Positive';
  if (score > -1) return 'Neutral';
  if (score > -3) return 'Negative';
  return 'Very Negative';
};
