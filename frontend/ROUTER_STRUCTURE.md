# Router Structure Documentation

## 📁 Folder Structure

```
src/
├── router/
│   ├── index.jsx              # Main router configuration
│   ├── routes.jsx             # Route definitions
│   ├── useAppNavigate.js      # Custom navigation hook
│   └── layouts/
│       └── RootLayout.jsx     # Root layout wrapper
├── pages/
│   ├── HomePageNew.jsx        # Home page
│   ├── StockDetailPageNew.jsx # Stock detail page
│   └── ErrorPage.jsx          # Error/404 page
└── App.jsx                     # App entry point
```

## 🎯 Architecture Overview

We use **React Router v6+** with `createBrowserRouter` for modern routing features:

- ✅ Data loading and mutations
- ✅ Error boundaries
- ✅ Code splitting and lazy loading
- ✅ Nested layouts
- ✅ Better TypeScript support

## 📄 File Descriptions

### 1. `router/index.jsx`

Main router configuration using `createBrowserRouter`.

```jsx
import router from './router';
import { RouterProvider } from 'react-router-dom';

function App() {
  return <RouterProvider router={router} />;
}
```

**Features:**
- Centralized route configuration
- Error boundaries with custom ErrorPage
- Nested routes with RootLayout
- Ready for data loaders

### 2. `router/routes.jsx`

Centralized route definitions for easy management.

```jsx
export const routes = {
  home: {
    path: '/',
    name: 'Home',
    element: HomePageNew,
  },
  stockDetail: {
    path: '/stock/:symbol',
    name: 'Stock Detail',
    element: StockDetailPageNew,
  },
};

export const routePaths = {
  home: () => '/',
  stockDetail: (symbol) => `/stock/${symbol}`,
};
```

**Usage:**
```jsx
import { routePaths } from './router/routes';
navigate(routePaths.stockDetail('AAPL'));
```

### 3. `router/useAppNavigate.js`

Custom hook for type-safe navigation.

```jsx
import useAppNavigate from './router/useAppNavigate';

function MyComponent() {
  const nav = useAppNavigate();
  
  return (
    <>
      <button onClick={() => nav.toHome()}>Home</button>
      <button onClick={() => nav.toStockDetail('AAPL')}>View AAPL</button>
      <button onClick={() => nav.back()}>Back</button>
    </>
  );
}
```

**Methods:**
- `toHome()` - Navigate to home page
- `toStockDetail(symbol)` - Navigate to stock detail
- `to(path)` - Generic navigation
- `back()` - Go back in history
- `forward()` - Go forward in history
- `replace(path)` - Navigate without history entry

### 4. `router/layouts/RootLayout.jsx`

Root layout component that wraps all pages.

**Features:**
- Consistent structure across all pages
- Suspense boundary for lazy-loaded pages
- Loading states
- Could include navigation bar, footer, etc.

```jsx
<RootLayout>
  <Outlet /> {/* Child routes render here */}
</RootLayout>
```

### 5. `pages/ErrorPage.jsx`

Beautiful error page with GSAP animations.

**Features:**
- Handles 404 errors
- Displays routing errors
- Animated with GSAP
- Shows error details in development
- Action buttons (Go Home, Go Back)

## 🚀 Current Routes

| Path | Component | Description |
|------|-----------|-------------|
| `/` | HomePageNew | Home page with stock listings, search, news |
| `/stock/:symbol` | StockDetailPageNew | Stock detail with predictions, quote, news |
| `*` (404) | ErrorPage | Error page for invalid routes |

## 📝 How to Add New Routes

### 1. Create the Page Component

```jsx
// src/pages/WatchlistPage.jsx
function WatchlistPage() {
  return <div>My Watchlist</div>;
}
export default WatchlistPage;
```

### 2. Add to Routes Config

```jsx
// src/router/routes.jsx
export const routes = {
  // ...existing routes
  watchlist: {
    path: '/watchlist',
    name: 'Watchlist',
    element: WatchlistPage,
  },
};

export const routePaths = {
  // ...existing paths
  watchlist: () => '/watchlist',
};
```

### 3. Add to Router

```jsx
// src/router/index.jsx
import WatchlistPage from '../pages/WatchlistPage';

const router = createBrowserRouter([
  {
    path: '/',
    element: <RootLayout />,
    errorElement: <ErrorPage />,
    children: [
      // ...existing routes
      {
        path: 'watchlist',
        element: <WatchlistPage />,
      },
    ],
  },
]);
```

### 4. Add to Navigation Hook

```jsx
// src/router/useAppNavigate.js
export const useAppNavigate = () => {
  const navigate = useNavigate();
  
  return {
    // ...existing methods
    toWatchlist: useCallback(() => navigate('/watchlist'), [navigate]),
  };
};
```

## 🎨 Navigation Examples

### Using useNavigate (React Router)

```jsx
import { useNavigate } from 'react-router-dom';

function MyComponent() {
  const navigate = useNavigate();
  
  const handleClick = () => {
    navigate('/stock/AAPL');
  };
  
  return <button onClick={handleClick}>View AAPL</button>;
}
```

### Using useAppNavigate (Custom Hook)

```jsx
import useAppNavigate from './router/useAppNavigate';

function MyComponent() {
  const nav = useAppNavigate();
  
  const handleClick = () => {
    nav.toStockDetail('AAPL');
  };
  
  return <button onClick={handleClick}>View AAPL</button>;
}
```

### Using Link Component

```jsx
import { Link } from 'react-router-dom';

function MyComponent() {
  return (
    <>
      <Link to="/">Home</Link>
      <Link to="/stock/AAPL">Apple Stock</Link>
    </>
  );
}
```

## 🔄 Data Loading (Future Enhancement)

React Router v6+ supports data loaders:

```jsx
// Example loader for stock detail page
async function stockLoader({ params }) {
  const stock = await fetchStock(params.symbol);
  return { stock };
}

// In router config
{
  path: 'stock/:symbol',
  element: <StockDetailPageNew />,
  loader: stockLoader,
  errorElement: <StockErrorPage />,
}

// In component
import { useLoaderData } from 'react-router-dom';

function StockDetailPageNew() {
  const { stock } = useLoaderData();
  // Data is pre-loaded before render
}
```

## 🛡️ Error Handling

### Route-Level Errors

```jsx
{
  path: 'stock/:symbol',
  element: <StockDetailPageNew />,
  errorElement: <StockErrorPage />, // Custom error page
}
```

### Global Error Boundary

```jsx
{
  path: '/',
  element: <RootLayout />,
  errorElement: <ErrorPage />, // Catches all route errors
}
```

## 🎭 Protected Routes (Future Enhancement)

```jsx
// src/router/ProtectedRoute.jsx
function ProtectedRoute({ children }) {
  const { user } = useAuth();
  
  if (!user) {
    return <Navigate to="/login" replace />;
  }
  
  return children;
}

// In router
{
  path: 'dashboard',
  element: <ProtectedRoute><Dashboard /></ProtectedRoute>,
}
```

## 📱 Nested Routes Example

```jsx
// Layout with nested routes
{
  path: 'settings',
  element: <SettingsLayout />,
  children: [
    {
      index: true,
      element: <GeneralSettings />,
    },
    {
      path: 'profile',
      element: <ProfileSettings />,
    },
    {
      path: 'security',
      element: <SecuritySettings />,
    },
  ],
}
```

## 🔧 Configuration

### Base URL

Set in `vite.config.js`:

```js
export default defineConfig({
  base: '/', // or '/app/' for subdirectory
});
```

### History Mode

Using `createBrowserRouter` (HTML5 History API):
- Clean URLs: `/stock/AAPL`
- Requires server configuration for production

Alternative - Hash Router:
```jsx
import { createHashRouter } from 'react-router-dom';
// URLs will be: /#/stock/AAPL
```

## 🚀 Performance Optimizations

### 1. Lazy Loading

```jsx
const HomePageNew = lazy(() => import('../pages/HomePageNew'));
```

### 2. Code Splitting

Automatically handled by Vite when using lazy loading.

### 3. Prefetching

```jsx
<Link to="/stock/AAPL" prefetch="intent">
  Apple Stock
</Link>
```

## 📊 Benefits of This Structure

✅ **Organized** - Clear separation of concerns  
✅ **Scalable** - Easy to add new routes  
✅ **Type-safe** - Helper functions prevent typos  
✅ **Maintainable** - Centralized configuration  
✅ **Modern** - Uses latest React Router features  
✅ **Performance** - Built-in code splitting  
✅ **Error Handling** - Comprehensive error boundaries  

## 🎯 Best Practices

1. **Keep route definitions in one place** - `router/routes.jsx`
2. **Use custom navigation hook** - Type-safe navigation
3. **Lazy load pages** - Better performance
4. **Use layouts** - Consistent structure
5. **Handle errors** - Graceful error pages
6. **Document routes** - Keep this file updated

## 📚 Resources

- [React Router Docs](https://reactrouter.com/)
- [Data Routers Guide](https://reactrouter.com/en/main/routers/picking-a-router)
- [Vite Code Splitting](https://vitejs.dev/guide/features.html#async-chunk-loading-optimization)

---

**Created:** 2025-10-09  
**Status:** ✅ Production Ready  
**React Router Version:** 7.9.4
