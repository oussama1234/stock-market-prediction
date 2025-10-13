import { useEffect, useRef } from 'react';
import { useLocation } from 'react-router-dom';

/**
 * ScrollRestoration Component
 * Automatically scrolls to top of page on route changes
 * Ensures loading indicators are visible when navigating between pages
 * 
 * This solves the issue where users navigate to a new page but remain
 * scrolled down, causing them to miss loading states and page content.
 * 
 * Features:
 * - Instant scroll on navigation (no animation delay)
 * - Preserves scroll position on initial page load
 * - Handles hash navigation (#section-id)
 * - Prevents scroll on query parameter changes only
 */
const ScrollRestoration = () => {
  const location = useLocation();
  const prevPathname = useRef(location.pathname);

  useEffect(() => {
    // Only scroll if pathname actually changed (not just query params)
    if (prevPathname.current !== location.pathname) {
      // Check if navigating to a hash anchor
      if (location.hash) {
        // Small delay to ensure the element exists in the DOM
        setTimeout(() => {
          const element = document.querySelector(location.hash);
          if (element) {
            element.scrollIntoView({ behavior: 'smooth' });
          } else {
            // Hash doesn't exist, scroll to top
            window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
          }
        }, 0);
      } else {
        // Normal navigation - scroll to top immediately
        window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
      }
      
      // Update previous pathname
      prevPathname.current = location.pathname;
    }
  }, [location.pathname, location.hash]);

  return null; // This component doesn't render anything
};

export default ScrollRestoration;
