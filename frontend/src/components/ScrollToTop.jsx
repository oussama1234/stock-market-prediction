import { useState, useEffect } from 'react';
import { ChevronUp } from 'lucide-react';

/**
 * ScrollToTop Button Component
 * Appears on all pages when user scrolls down
 * Features gradient background, hover effects, and smooth scroll
 */
const ScrollToTop = () => {
  const [showScrollTop, setShowScrollTop] = useState(false);

  // Show/hide button based on scroll position
  useEffect(() => {
    const handleScroll = () => {
      setShowScrollTop(window.scrollY > 400);
    };
    
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  // Scroll to top function
  const scrollToTop = () => {
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  };

  return (
    <button
      onClick={scrollToTop}
      className={`fixed bottom-8 right-8 z-50 group p-4 rounded-2xl shadow-2xl transition-all duration-500 transform ${
        showScrollTop ? 'translate-y-0 opacity-100 scale-100' : 'translate-y-20 opacity-0 scale-50 pointer-events-none'
      }`}
      aria-label="Scroll to top"
    >
      {/* Gradient background */}
      <div className="absolute inset-0 rounded-2xl bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600"></div>
      <div className="absolute inset-0 rounded-2xl bg-gradient-to-r from-cyan-600 via-indigo-600 to-purple-600 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
      
      {/* Pulsing ring on hover */}
      <div className="absolute -inset-1 rounded-2xl bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 opacity-0 group-hover:opacity-75 blur-lg animate-pulse"></div>
      
      {/* Icon */}
      <ChevronUp className="relative z-10 w-6 h-6 text-white transform group-hover:scale-110 group-hover:-translate-y-1 transition-all duration-300" />
      
      {/* Tooltip */}
      <div className="absolute bottom-full right-0 mb-2 px-3 py-1.5 bg-gray-900 text-white text-xs font-bold rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap">
        Back to Top
        <div className="absolute top-full right-4 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"></div>
      </div>
    </button>
  );
};

export default ScrollToTop;
