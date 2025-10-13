import { useState, useEffect, useMemo, memo, useCallback } from 'react';
import { Link, useLocation } from 'react-router-dom';

function Navigation() {
  const [isScrolled, setIsScrolled] = useState(false);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [isDarkMode, setIsDarkMode] = useState(() => {
    // Initialize from localStorage
    const saved = localStorage.getItem('darkMode');
    return saved ? JSON.parse(saved) : false;
  });
  const location = useLocation();

  // Handle scroll effect
  useEffect(() => {
    const handleScroll = () => {
      setIsScrolled(window.scrollY > 20);
    };
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  // Close mobile menu on route change
  useEffect(() => {
    setIsMobileMenuOpen(false);
  }, [location]);

  // Dark mode effect
  useEffect(() => {
    localStorage.setItem('darkMode', JSON.stringify(isDarkMode));
    if (isDarkMode) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
  }, [isDarkMode]);

  // Toggle dark mode
  const toggleDarkMode = useCallback(() => {
    setIsDarkMode(prev => !prev);
  }, []);

  const isActive = (path) => {
    if (path === '/') {
      return location.pathname === '/';
    }
    return location.pathname.startsWith(path);
  };

  const navLinks = useMemo(() => [
    { 
      path: '/', 
      label: 'Home', 
      icon: (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
      )
    },
    { 
      path: '/fear-greed', 
      label: 'Fear & Greed', 
      icon: (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      )
    },
    { 
      path: '/news', 
      label: 'News', 
      icon: (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
        </svg>
      )
    },
  ], []);

  return (
    <>
      <nav 
        className={`fixed top-0 left-0 right-0 z-50 transition-all duration-500 ${
          isDarkMode
            ? isScrolled
              ? 'bg-gray-900/95 backdrop-blur-xl shadow-2xl py-3'
              : 'bg-gray-900/90 backdrop-blur-lg py-5'
            : isScrolled
              ? 'bg-white/95 backdrop-blur-xl shadow-lg py-3'
              : 'bg-white/90 backdrop-blur-lg py-5'
        }`}
      >
        <div className="container mx-auto px-4">
          <div className="flex items-center justify-between">
            {/* Logo */}
            <Link 
              to="/" 
              className="group flex items-center gap-3 transition-all duration-500 hover:scale-105"
            >
              <div className="relative">
                {/* Animated gradient glow */}
                <div className="absolute inset-0 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 rounded-xl blur-md opacity-50 group-hover:opacity-100 group-hover:blur-lg transition-all duration-500 animate-pulse"></div>
                <div className="relative bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-600 p-2.5 rounded-xl shadow-2xl group-hover:shadow-purple-500/50 transition-all duration-500 group-hover:rotate-3">
                  <svg className="w-6 h-6 text-white transform group-hover:scale-110 transition-transform duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                  </svg>
                </div>
              </div>
              <div>
                <h1 className={`text-xl font-black transition-all duration-300 group-hover:tracking-wide ${
                  isDarkMode ? 'text-white' : 'text-gray-900'
                }`}>
                  Market<span className="text-transparent bg-clip-text bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 animate-gradient">AI</span>
                </h1>
                <p className={`text-xs font-semibold transition-colors duration-300 ${
                  isDarkMode ? 'text-gray-400 group-hover:text-purple-400' : 'text-gray-600 group-hover:text-indigo-600'
                }`}>
                  Stock Prediction
                </p>
              </div>
            </Link>

            {/* Desktop Navigation */}
            <div className="hidden md:flex items-center gap-2">
              {navLinks.map((link) => (
                <Link
                  key={link.path}
                  to={link.path}
                  className="group relative flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold transition-all duration-500 hover:scale-105"
                >
                  {/* Active background with gradient */}
                  {isActive(link.path) && (
                    <>
                      <div className="absolute inset-0 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 rounded-xl shadow-2xl"></div>
                      <div className="absolute -inset-1 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 rounded-xl blur-md opacity-50 animate-pulse"></div>
                    </>
                  )}
                  
                  {/* Hover background with colorful gradient */}
                  {!isActive(link.path) && (
                    <>
                      <div className="absolute inset-0 rounded-xl bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 opacity-0 group-hover:opacity-100 transition-all duration-500"></div>
                      <div className="absolute -inset-1 rounded-xl bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 opacity-0 group-hover:opacity-30 blur-md transition-all duration-500"></div>
                    </>
                  )}

                  {/* Content */}
                  <span className={`relative z-10 flex items-center gap-2 transition-all duration-300 ${
                    isActive(link.path)
                      ? 'text-white'
                      : isDarkMode
                        ? 'text-gray-300 group-hover:text-white'
                        : 'text-gray-700 group-hover:text-white'
                  }`}>
                    <span className="transition-all group-hover:scale-125 group-hover:rotate-12 duration-500">
                      {link.icon}
                    </span>
                    <span className="group-hover:tracking-wide transition-all duration-300">{link.label}</span>
                  </span>
                </Link>
              ))}

              {/* Dark Mode Toggle */}
              <button
                onClick={toggleDarkMode}
                className={`group relative ml-2 p-2.5 rounded-xl transition-all duration-500 hover:scale-110 overflow-hidden ${
                  isDarkMode
                    ? 'bg-gradient-to-br from-gray-800 to-gray-900 text-yellow-400 shadow-lg shadow-yellow-500/20'
                    : 'bg-gradient-to-br from-gray-100 to-gray-200 text-gray-700 shadow-lg shadow-indigo-500/10'
                }`}
                aria-label="Toggle dark mode"
              >
                {/* Animated background on hover */}
                <div className={`absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500 ${
                  isDarkMode
                    ? 'bg-gradient-to-br from-yellow-600/20 to-orange-600/20'
                    : 'bg-gradient-to-br from-indigo-500/10 to-purple-500/10'
                }`}></div>
                
                <div className="relative z-10">
                  {isDarkMode ? (
                    <svg className="w-5 h-5 transform group-hover:rotate-180 group-hover:scale-110 transition-all duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                  ) : (
                    <svg className="w-5 h-5 transform group-hover:-rotate-12 group-hover:scale-110 transition-all duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                  )}
                </div>
              </button>

              {/* CTA Button */}
              <button className="group relative ml-2 px-6 py-2.5 overflow-hidden rounded-xl font-bold transition-all duration-500 hover:scale-105 hover:shadow-2xl">
                {/* Animated gradient background */}
                <div className="absolute inset-0 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 animate-gradient"></div>
                <div className="absolute inset-0 bg-gradient-to-r from-pink-600 via-purple-600 to-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                
                {/* Glowing border effect */}
                <div className="absolute -inset-0.5 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 rounded-xl opacity-70 group-hover:opacity-100 blur group-hover:blur-md transition-all duration-500"></div>
                
                {/* Shimmer effect */}
                <div className="absolute inset-0 -translate-x-full group-hover:translate-x-full transition-transform duration-1000 bg-gradient-to-r from-transparent via-white/30 to-transparent"></div>
                
                <span className="relative z-10 flex items-center gap-2 text-white">
                  <svg className="w-5 h-5 transform group-hover:rotate-12 group-hover:scale-125 transition-all duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M13 10V3L4 14h7v7l9-11h-7z" />
                  </svg>
                  <span className="group-hover:tracking-wider transition-all duration-300">Get Started</span>
                </span>
              </button>
            </div>

            {/* Mobile Menu Button */}
            <button
              onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
              className={`md:hidden p-2 rounded-lg transition-colors duration-300 ${
                isDarkMode
                  ? 'text-gray-300 hover:bg-gray-800'
                  : 'text-gray-900 hover:bg-gray-100'
              }`}
            >
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                {isMobileMenuOpen ? (
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                ) : (
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                )}
              </svg>
            </button>
          </div>
        </div>

        {/* Mobile Menu */}
        <div 
          className={`md:hidden overflow-hidden transition-all duration-500 ${
            isMobileMenuOpen ? 'max-h-[500px] opacity-100' : 'max-h-0 opacity-0'
          }`}
        >
          <div className={`container mx-auto px-4 py-4 rounded-b-2xl shadow-xl mt-2 backdrop-blur-xl ${
            isDarkMode ? 'bg-gray-900/95' : 'bg-white/95'
          }`}>
            {navLinks.map((link, index) => (
              <Link
                key={link.path}
                to={link.path}
                className={`flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-300 ${
                  isActive(link.path)
                    ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-md'
                    : isDarkMode
                      ? 'text-gray-300 hover:bg-gray-800'
                      : 'text-gray-700 hover:bg-gradient-to-r hover:from-indigo-50 hover:to-purple-50'
                }`}
                style={{
                  animationDelay: `${index * 50}ms`,
                  animation: isMobileMenuOpen ? 'slideDown 0.3s ease-out forwards' : 'none'
                }}
              >
                {link.icon}
                <span>{link.label}</span>
              </Link>
            ))}
            
            {/* Dark Mode Toggle in Mobile */}
            <button
              onClick={toggleDarkMode}
              className={`w-full mt-3 flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold transition-all duration-300 ${
                isDarkMode
                  ? 'bg-gray-800 text-yellow-400 hover:bg-gray-700'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
              }`}
            >
              {isDarkMode ? (
                <>
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                  </svg>
                  <span>Light Mode</span>
                </>
              ) : (
                <>
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                  </svg>
                  <span>Dark Mode</span>
                </>
              )}
            </button>
            
            <button className="w-full mt-2 px-6 py-3 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all duration-300">
              Get Started
            </button>
          </div>
        </div>
      </nav>
      
      {/* Visible Gradient Border Shadow at Bottom of Navbar */}
      <div className={`fixed left-0 right-0 z-40 pointer-events-none transition-all duration-500 ${
        isScrolled ? 'top-[60px] opacity-0' : 'top-[84px] opacity-100'
      }`}>
        {/* Main gradient line */}
        <div className="h-1 bg-gradient-to-r from-cyan-500 via-purple-500 to-pink-500 shadow-lg shadow-purple-500/50"></div>
        {/* Glow effect below the line */}
        <div className="h-6 bg-gradient-to-b from-purple-500/30 via-pink-500/20 to-transparent"></div>
      </div>

      <style>{`
        @keyframes slideDown {
          from {
            opacity: 0;
            transform: translateY(-10px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }
        
        @keyframes gradient {
          0%, 100% {
            background-position: 0% 50%;
          }
          50% {
            background-position: 100% 50%;
          }
        }
        
        .animate-gradient {
          background-size: 200% 200%;
          animation: gradient 3s ease infinite;
        }
      `}</style>
    </>
  );
}

export default memo(Navigation);
