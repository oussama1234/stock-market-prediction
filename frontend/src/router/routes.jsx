import { lazy } from 'react';

// Lazy load pages for better performance
const HomePageNew = lazy(() => import('../pages/HomePageNew'));
const StockDetailsNew = lazy(() => import('../pages/StockDetailsNew'));
const MarketsPage = lazy(() => import('../pages/MarketsPage'));

/**
 * Routes Configuration
 * Centralized route definitions for easy management
 */
export const routes = {
  home: {
    path: '/',
    name: 'Home',
    element: HomePageNew,
  },
  stockDetail: {
    path: '/stock/:symbol',
    name: 'Stock Detail',
    element: StockDetailsNew,
  },
  news: {
    path: '/news',
    name: 'News',
    element: MarketsPage,
  },
};

/**
 * Route paths for navigation
 * Usage: navigate(routePaths.stockDetail('AAPL'))
 */
export const routePaths = {
  home: () => '/',
  stockDetail: (symbol) => `/stock/${symbol}`,
  news: () => '/news',
};

export default routes;
