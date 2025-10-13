import { createBrowserRouter } from 'react-router-dom';
import RootLayout from './layouts/RootLayout';
import HomePageNew from '../pages/HomePageNew';
import StockDetails from '../pages/StockDetails';
import AnalyticsNew from '../pages/AnalyticsNew';
import FearGreedPage from '../pages/FearGreedPage';
import MarketsPage from '../pages/MarketsPage';
import ErrorPage from '../pages/ErrorPage';

/**
 * Application Router Configuration
 * Uses React Router v6+ with createBrowserRouter for:
 * - Data loading and mutations
 * - Error boundaries
 * - Code splitting
 * - Better TypeScript support
 * 
 * Routes:
 * - /                         - Home page with stock listings
 * - /stock/:symbol            - Stock detail page with predictions and news
 * - /stock/:symbol/analytics  - Live analytics dashboard with forecasts
 */
const router = createBrowserRouter([
  {
    path: '/',
    element: <RootLayout />,
    errorElement: <ErrorPage />,
    children: [
      {
        index: true,
        element: <HomePageNew />,
        // Can add loader here for data fetching before render
      },
      {
        path: 'stock/:symbol',
        element: <StockDetails />,
        // Can add loader here for stock data pre-fetching
      },
      {
        path: 'stock/:symbol/analytics',
        element: <AnalyticsNew />,
        // Can add loader here for analytics data pre-fetching
      },
      {
        path: 'fear-greed',
        element: <FearGreedPage />,
      },
      {
        path: 'news',
        element: <MarketsPage />,
      },
    ],
  },
]);

export default router;
