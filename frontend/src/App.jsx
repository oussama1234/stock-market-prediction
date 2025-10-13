import { RouterProvider } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useEffect } from 'react';
import router from './router';
import { fetchKeywords } from './utils/keywordDetection';

// Create a client
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      refetchOnWindowFocus: false,
      retry: 1,
      staleTime: 5 * 60 * 1000, // 5 minutes
    },
  },
});

// Pre-fetch keywords on app load
fetchKeywords().catch(err => console.warn('Initial keyword fetch failed:', err));

/**
 * Main App Component
 * Uses React Router v6+ with RouterProvider for data router features
 */
function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>
  );
}

export default App;
