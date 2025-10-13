import { Outlet } from 'react-router-dom';
import { Suspense } from 'react';
import { GenericLoader } from '../../components/loaders';
import Navigation from '../../components/Navigation';
import Footer from '../../components/Footer';
import ScrollToTop from '../../components/ScrollToTop';
import ScrollRestoration from '../../components/ScrollRestoration';

/**
 * Root Layout Component
 * Provides consistent structure for all pages
 * Includes navigation, footer, and loading states
 */
function RootLayout() {
  return (
    <div className="min-h-screen flex flex-col bg-white dark:bg-gray-900 transition-colors duration-300">
      {/* Auto-scroll to top on route change */}
      <ScrollRestoration />
      
      {/* Navigation */}
      <Navigation />
      
      {/* Main Content Area - Add padding for fixed navigation */}
      <main className="flex-1 pt-20 md:pt-24">
        <Suspense 
          fallback={<GenericLoader message="Loading page" size="large" fullScreen={true} />}
        >
          <Outlet />
        </Suspense>
      </main>
      
      {/* Footer */}
      <Footer />
      
      {/* Scroll to Top Button - Available on all pages */}
      <ScrollToTop />
    </div>
  );
}

export default RootLayout;
