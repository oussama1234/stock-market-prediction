import { useRouteError, useNavigate } from 'react-router-dom';

/**
 * Error Page Component
 * Displays friendly error messages for routing errors and 404s
 */
function ErrorPage() {
  const error = useRouteError();
  const navigate = useNavigate();
  const is404 = error?.status === 404;

  return (
    <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 flex items-center justify-center p-4">
      <div className="max-w-2xl w-full">
        {/* Animated Icon */}
        <div className="flex justify-center mb-8">
          <div className="w-32 h-32 bg-gradient-to-br from-red-500 to-pink-500 rounded-full flex items-center justify-center shadow-2xl">
            <svg className="w-16 h-16 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2.5}>
              {is404 ? (
                <path strokeLinecap="round" strokeLinejoin="round" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              ) : (
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              )}
            </svg>
          </div>
        </div>

        {/* Error Content */}
        <div className="bg-white rounded-2xl shadow-2xl p-8 md:p-12 text-center border-2 border-gray-300">
          <h1 className="text-7xl md:text-9xl font-black mb-6">
            <span className="bg-gradient-to-r from-red-500 via-pink-500 to-red-600 bg-clip-text text-transparent">
              {is404 ? '404' : 'Oops!'}
            </span>
          </h1>
          
          <h2 className="text-3xl md:text-4xl font-black text-gray-900 mb-6">
            {is404 ? 'Page Not Found' : 'Something Went Wrong'}
          </h2>
          
          <p className="text-gray-900 text-xl font-semibold mb-8 max-w-lg mx-auto leading-relaxed">
            {is404 
              ? "The page you're looking for doesn't exist or has been moved."
              : error?.message || 'An unexpected error occurred. Please try again.'}
          </p>

          {/* Error Details (in development) */}
          {import.meta.env.DEV && error?.stack && (
            <details className="mb-8 text-left bg-red-50 rounded-lg p-4">
              <summary className="cursor-pointer font-semibold text-red-700 mb-2">
                Error Details (Dev Only)
              </summary>
              <pre className="text-xs text-red-600 overflow-auto max-h-48">
                {error.stack}
              </pre>
            </details>
          )}

          {/* Action Buttons */}
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <button
              onClick={() => navigate('/')}
              className="px-8 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold rounded-xl hover:shadow-lg transform hover:scale-105 transition-all flex items-center justify-center gap-2"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
              </svg>
              Go Home
            </button>
            
            <button
              onClick={() => navigate(-1)}
              className="px-8 py-3 bg-white border-2 border-gray-300 text-gray-700 font-semibold rounded-xl hover:border-gray-400 hover:shadow-md transform hover:scale-105 transition-all flex items-center justify-center gap-2"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
              Go Back
            </button>
          </div>
        </div>

        {/* Helpful Links */}
        <div className="mt-8 bg-white rounded-xl shadow-xl p-6 border-2 border-gray-300">
          <p className="text-gray-900 font-bold text-lg mb-4 text-center">Need help? Try these:</p>
          <div className="flex flex-wrap justify-center gap-3">
            <button
              onClick={() => navigate('/')}
              className="px-6 py-2.5 bg-gradient-to-r from-indigo-50 to-purple-50 text-indigo-700 font-medium rounded-lg hover:from-indigo-100 hover:to-purple-100 transition-all shadow-sm hover:shadow-md flex items-center gap-2"
            >
              <span className="text-xl">📊</span>
              Browse Stocks
            </button>
            <button
              onClick={() => window.location.reload()}
              className="px-6 py-2.5 bg-gradient-to-r from-blue-50 to-cyan-50 text-blue-700 font-medium rounded-lg hover:from-blue-100 hover:to-cyan-100 transition-all shadow-sm hover:shadow-md flex items-center gap-2"
            >
              <span className="text-xl">🔄</span>
              Refresh Page
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

export default ErrorPage;
