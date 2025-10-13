import { useMemo, useState } from 'react';
import { formatDistanceToNow } from 'date-fns';
import { analyzeNewsKeywords } from '../../../utils/keywordDetection';
import { Rocket, Clock, Building, TrendingUp, TrendingDown, Flame, AlertCircle } from 'lucide-react';

/**
 * NewsGrid - Modern 3-column grid layout for news articles with sentiment indicators
 * Shows keyword highlights, sentiment badges, and load more functionality
 */
export default function NewsGrid({ news = [] }) {
  const [visibleCount, setVisibleCount] = useState(6);
  const [isLoadingMore, setIsLoadingMore] = useState(false);

  // Sort news by date descending (most recent first) and analyze
  const analyzedNews = useMemo(() => {
    const sorted = [...news].sort((a, b) => {
      const dateA = new Date(a.published_at);
      const dateB = new Date(b.published_at);
      return dateB - dateA; // Descending order
    });
    
    return sorted.map(article => ({
      ...article,
      analysis: analyzeNewsKeywords(article.title, article.description),
    }));
  }, [news]);

  const visibleNews = analyzedNews.slice(0, visibleCount);
  const hasMore = visibleCount < analyzedNews.length;

  const handleLoadMore = () => {
    setIsLoadingMore(true);
    // Simulate loading delay for smooth UX
    setTimeout(() => {
      setVisibleCount(prev => prev + 6);
      setIsLoadingMore(false);
    }, 500);
  };

  if (!news || news.length === 0) {
    return (
      <div className="bg-white rounded-2xl shadow-xl p-12 text-center">
        <Building className="w-16 h-16 text-gray-400 mx-auto mb-4" />
        <h3 className="text-2xl font-bold text-gray-900 mb-2">No News Available</h3>
        <p className="text-gray-600">Check back later for the latest updates</p>
      </div>
    );
  }

  return (
    <div className="bg-white rounded-2xl shadow-xl p-6">
      <h2 className="text-2xl font-bold text-gray-900 mb-6 flex items-center gap-2">
        <Building className="w-7 h-7 text-indigo-600" />
        Latest News
        <span className="text-sm font-normal text-gray-500 ml-2">({analyzedNews.length} articles)</span>
      </h2>

      {/* 3-Column Grid Layout */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
        {visibleNews.map((article, idx) => {
          const { analysis } = article;
          
          // Check if this is important news with surge expectation
          const isImportant = article.is_important && article.expected_surge_percent >= 6;
          const isToday = article.importance_date === new Date().toISOString().split('T')[0];
          const showSurgeBadge = isImportant && isToday;
          
          const sentimentColor = 
            showSurgeBadge ? 'border-yellow-500 border-4' : // Special border for important surge news
            analysis.sentiment === 'bullish' ? 'border-green-500' :
            analysis.sentiment === 'bearish' ? 'border-red-500' :
            analysis.sentiment === 'slightly bullish' ? 'border-blue-500' :
            analysis.sentiment === 'slightly bearish' ? 'border-orange-500' :
            'border-gray-300';

          const sentimentBadge =
            analysis.sentiment === 'bullish' ? 'bg-green-500 text-white' :
            analysis.sentiment === 'bearish' ? 'bg-red-500 text-white' :
            analysis.sentiment === 'slightly bullish' ? 'bg-blue-500 text-white' :
            analysis.sentiment === 'slightly bearish' ? 'bg-orange-500 text-white' :
            'bg-gray-500 text-white';

          const sentimentBg =
            analysis.sentiment === 'bullish' ? 'bg-green-50' :
            analysis.sentiment === 'bearish' ? 'bg-red-50' :
            analysis.sentiment === 'slightly bullish' ? 'bg-blue-50' :
            analysis.sentiment === 'slightly bearish' ? 'bg-orange-50' :
            'bg-gray-50';

          return (
            <a
              key={idx}
              href={article.url}
              target="_blank"
              rel="noopener noreferrer"
              className={`block bg-white border-2 ${sentimentColor} rounded-xl overflow-hidden hover:shadow-2xl transition-all hover:scale-[1.03] group cursor-pointer`}
            >
              {/* Image */}
              <div className="relative h-48 overflow-hidden bg-gradient-to-br from-gray-100 to-gray-200">
                {article.image_url ? (
                  <>
                    <img
                      src={article.image_url}
                      alt={article.title}
                      className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                      loading="lazy"
                      onError={(e) => { 
                        e.target.style.display = 'none';
                        const placeholder = e.target.nextElementSibling;
                        if (placeholder) placeholder.style.display = 'flex';
                      }}
                    />
                    {/* Placeholder for error cases */}
                    <div 
                      className="w-full h-full flex items-center justify-center absolute inset-0"
                      style={{ display: 'none' }}
                    >
                      <div className="text-center">
                        <svg className="w-16 h-16 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                        </svg>
                        <p className="text-xs text-gray-500 font-medium">{article.source || 'News Article'}</p>
                      </div>
                    </div>
                  </>
                ) : (
                  // No image_url provided
                  <div className="w-full h-full flex items-center justify-center">
                    <div className="text-center">
                      <svg className="w-16 h-16 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                      </svg>
                      <p className="text-xs text-gray-500 font-medium">{article.source || 'News Article'}</p>
                    </div>
                  </div>
                )}
                
                {/* IMPORTANT NEWS SURGE Badge - Highest Priority */}
                {showSurgeBadge && (
                  <div className="absolute top-3 left-3 right-3 flex flex-col gap-2">
                    <div className="bg-gradient-to-r from-yellow-500 via-amber-500 to-orange-500 text-white px-3 py-2 rounded-lg text-sm font-black shadow-2xl animate-pulse">
                      <div className="flex items-center gap-2">
                        <Rocket className="w-5 h-5 animate-bounce" />
                        <span>SURGE EXPECTED: +{article.expected_surge_percent}%</span>
                      </div>
                      <div className="text-xs font-semibold mt-1 opacity-90 flex items-center gap-1">
                        <Clock className="w-3 h-3" />
                        <span>TODAY ONLY</span>
                      </div>
                    </div>
                  </div>
                )}
                
                {/* Sentiment Badge Overlay */}
                {!showSurgeBadge && analysis.sentiment !== 'neutral' && (
                  <div className={`absolute top-3 right-3 px-3 py-1 rounded-full text-xs font-bold ${sentimentBadge} shadow-lg flex items-center gap-1`}>
                    {analysis.sentiment === 'bullish' && <TrendingUp className="w-3 h-3" />}
                    {analysis.sentiment === 'bearish' && <TrendingDown className="w-3 h-3" />}
                    {analysis.sentiment === 'slightly bullish' && <TrendingUp className="w-3 h-3" />}
                    {analysis.sentiment === 'slightly bearish' && <TrendingDown className="w-3 h-3" />}
                    <span>{analysis.sentiment.toUpperCase()}</span>
                  </div>
                )}
                {analysis.needsAlert && !showSurgeBadge && (
                  <AlertCircle className="absolute top-3 left-3 w-6 h-6 text-yellow-500 animate-pulse" />
                )}
              </div>

              {/* Card Content */}
              <div className={`p-5 ${sentimentBg}`}>
                {/* Title */}
                <h3 className="font-bold text-gray-900 mb-2 group-hover:text-indigo-600 transition-colors line-clamp-2 text-lg leading-tight">
                  {article.title}
                </h3>

                {/* Description */}
                {article.description && (
                  <p className="text-sm text-gray-600 mb-3 line-clamp-3">
                    {article.description}
                  </p>
                )}

                {/* Surge Keywords - Show if important surge news */}
                {showSurgeBadge && article.surge_keywords && article.surge_keywords.length > 0 && (
                  <div className="flex flex-wrap gap-1 mb-3">
                    {article.surge_keywords.slice(0, 5).map((kw, i) => (
                      <span
                        key={i}
                        className="px-2 py-1 rounded-full text-xs font-bold bg-gradient-to-r from-yellow-200 to-amber-300 text-amber-900 border border-amber-400 flex items-center gap-1"
                      >
                        <Flame className="w-3 h-3" />
                        {kw}
                      </span>
                    ))}
                  </div>
                )}
                
                {/* Regular Keywords */}
                {!showSurgeBadge && analysis.matchedKeywords.length > 0 && (
                  <div className="flex flex-wrap gap-1 mb-3">
                    {analysis.matchedKeywords.slice(0, 4).map((kw, i) => (
                      <span
                        key={i}
                        className={`px-2 py-1 rounded-full text-xs font-medium ${
                          kw.impact === 'bullish' 
                            ? 'bg-green-200 text-green-800' 
                            : 'bg-red-200 text-red-800'
                        }`}
                      >
                        {kw.keyword}
                      </span>
                    ))}
                  </div>
                )}

                {/* Meta Info */}
                <div className="flex items-center justify-between text-xs text-gray-500 pt-3 border-t border-gray-200">
                  <div className="flex items-center gap-3">
                    <span className="flex items-center gap-1" title={new Date(article.published_at).toLocaleString()}>
                      <Clock className="w-3 h-3" />
                      {formatDistanceToNow(new Date(article.published_at), { addSuffix: true })}
                    </span>
                    <span className="flex items-center gap-1">
                      <Building className="w-3 h-3" />
                      {article.source}
                    </span>
                  </div>
                  {analysis.confidence > 0 && (
                    <div className="text-sm font-bold text-gray-700">
                      {analysis.confidence}%
                    </div>
                  )}
                </div>

                {/* Sentiment Score */}
                {article.sentiment_score !== null && article.sentiment_score !== undefined && (
                  <div className="mt-2 flex items-center gap-2">
                    <span className="text-xs text-gray-500">Sentiment Score:</span>
                    <div className="flex-1 bg-gray-200 rounded-full h-2 overflow-hidden">
                      <div 
                        className={`h-full transition-all ${
                          article.sentiment_score > 0 ? 'bg-green-500' : 'bg-red-500'
                        }`}
                        style={{ width: `${Math.abs(article.sentiment_score) * 100}%` }}
                      />
                    </div>
                    <span className="text-xs font-bold text-gray-700">
                      {Number(article.sentiment_score).toFixed(2)}
                    </span>
                  </div>
                )}
              </div>
            </a>
          );
        })}
      </div>

      {/* Load More Button */}
      {hasMore && (
        <div className="flex justify-center mt-8">
          <button
            onClick={handleLoadMore}
            disabled={isLoadingMore}
            className="px-8 py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold rounded-xl hover:from-indigo-700 hover:to-purple-700 transition-all hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-3 shadow-lg hover:shadow-xl"
          >
            {isLoadingMore ? (
              <>
                <svg className="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Loading...</span>
              </>
            ) : (
              <>
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                </svg>
                <span>Load More ({analyzedNews.length - visibleCount} remaining)</span>
              </>
            )}
          </button>
        </div>
      )}
    </div>
  );
}
