import { useNavigate } from 'react-router-dom';
import { useCallback } from 'react';

/**
 * Custom navigation hook with type-safe route helpers
 * Provides convenient methods for navigation throughout the app
 * 
 * Usage:
 * const nav = useAppNavigate();
 * nav.toHome();
 * nav.toStockDetail('AAPL');
 */
export const useAppNavigate = () => {
  const navigate = useNavigate();

  return {
    // Navigation methods
    toHome: useCallback(() => navigate('/'), [navigate]),
    toStockDetail: useCallback((symbol) => navigate(`/stock/${symbol}`), [navigate]),
    
    // Generic navigation
    to: useCallback((path) => navigate(path), [navigate]),
    back: useCallback(() => navigate(-1), [navigate]),
    forward: useCallback(() => navigate(1), [navigate]),
    
    // Replace (no history entry)
    replace: useCallback((path) => navigate(path, { replace: true }), [navigate]),
  };
};

export default useAppNavigate;
