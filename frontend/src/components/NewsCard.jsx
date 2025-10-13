import { memo, useMemo } from 'react';
import PropTypes from 'prop-types';
import { formatRelativeTime, getSentimentColor, getSentimentLabel } from '../utils/formatters';
import { TrendingUp, TrendingDown, Minus, Clock, ExternalLink, AlertTriangle, AlertCircle, Info, Tag } from 'lucide-react';
import { analyzeNewsKeywords } from '../utils/keywordDetection';

const NewsCard = memo(({ article }) => {
  const {
    title,
    description,
    url,
    image_url,
    source,
    published_at,
    sentiment_score,
    is_important,
    importance,
    importance_keywords,
  } = article;

  // Analyze article using comprehensive keyword detection system
  const analysis = useMemo(() => 
    analyzeNewsKeywords(title, description),
    [title, description]
  );

  // Determine impact level using keyword analysis and sentiment score
  const impact = useMemo(() => {
    if (importance) return importance; // backend-provided: high|medium|low|none
    
    // Use keyword analysis score for impact determination
    const absScore = Math.abs(analysis.score);
    
    // High impact: score >= 6 OR has high-confidence alert
    if (absScore >= 6 || (analysis.needsAlert && analysis.confidence >= 80)) {
      return 'high';
    }
    
    // Medium impact: score >= 3 OR has alert
    if (absScore >= 3 || analysis.needsAlert) {
      return 'medium';
    }
    
    // Low impact: score > 0
    if (absScore > 0) {
      return 'low';
    }
    
    // Fallback to sentiment_score
    const abs = typeof sentiment_score === 'number' ? Math.abs(sentiment_score) : 0;
    if (abs >= 0.85) return 'high';
    if (abs >= 0.4) return 'medium';
    return 'low';
  }, [importance, analysis, sentiment_score]);

  // Determine highlight class based on importance and sentiment direction
  const getHighlightClass = () => {
    if (impact === 'high') {
      return 'border-l-4 border-red-500 bg-red-50/30'; // Critical/high -> red
    }
    if (impact === 'medium') {
      return 'border-l-4 border-amber-500 bg-amber-50/30'; // Medium -> amber
    }
    if (impact === 'low') {
      return 'border-l-4 border-blue-500/60 bg-blue-50/30'; // Low -> soft blue
    }
    return '';
  };

  // Use keywords from comprehensive analysis
  const detectedKeywords = useMemo(() => {
    if (!analysis.matchedKeywords || analysis.matchedKeywords.length === 0) {
      return [];
    }
    
    // Convert matched keywords to badge format
    // Sort by weight (highest impact first) and limit to top 4
    const sorted = [...analysis.matchedKeywords]
      .sort((a, b) => b.weight - a.weight)
      .slice(0, 4)
      .map(kw => ({
        word: kw.keyword,
        type: kw.impact === 'bullish' ? 'positive' : 'negative',
        weight: kw.weight
      }));
    
    return sorted;
  }, [analysis]);

  const getImportanceBadge = () => {
    if (impact === 'high') {
      return (
        <div className="absolute top-3 right-3 z-10">
          <span className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-black bg-gradient-to-r from-red-500 to-rose-500 text-white rounded-full shadow-lg animate-pulse">
            <AlertTriangle className="w-3.5 h-3.5" />
            HIGH IMPACT
          </span>
        </div>
      );
    }
    if (impact === 'medium') {
      return (
        <div className="absolute top-3 right-3 z-10">
          <span className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-black bg-gradient-to-r from-amber-400 to-orange-400 text-white rounded-full shadow-lg">
            <AlertCircle className="w-3.5 h-3.5" />
            MEDIUM
          </span>
        </div>
      );
    }
    if (impact === 'low') {
      return (
        <div className="absolute top-3 right-3 z-10">
          <span className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-black bg-gradient-to-r from-blue-400 to-cyan-400 text-white rounded-full shadow-lg">
            <Info className="w-3.5 h-3.5" />
            LOW
          </span>
        </div>
      );
    }
    return null;
  };

  return (
    <div className="group relative">
      {/* Gradient glow on hover */}
      <div className={`absolute -inset-0.5 rounded-2xl opacity-0 group-hover:opacity-100 blur transition-all duration-500 ${
        impact === 'high' ? 'bg-gradient-to-r from-red-500 via-rose-500 to-pink-500' :
        impact === 'medium' ? 'bg-gradient-to-r from-amber-500 via-orange-500 to-yellow-500' :
        'bg-gradient-to-r from-blue-500 via-cyan-500 to-teal-500'
      }`}></div>
      
      <a
        href={url}
        target="_blank"
        rel="noopener noreferrer"
        className={`relative block bg-white dark:bg-gray-900 rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-500 overflow-hidden transform hover:-translate-y-2 border-2 ${
          impact === 'high' ? 'border-red-200 dark:border-red-900/50' :
          impact === 'medium' ? 'border-amber-200 dark:border-amber-900/50' :
          'border-gray-200 dark:border-gray-800'
        }`}
      >
        {getImportanceBadge()}
        
        {/* Article Image with Overlay */}
        <div className="relative mb-4 rounded-t-xl overflow-hidden bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900">
          {/* Gradient overlay on hover */}
          <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 z-10"></div>
          
          {/* External link icon on hover */}
          <div className="absolute bottom-3 right-3 z-20 opacity-0 group-hover:opacity-100 transform translate-y-2 group-hover:translate-y-0 transition-all duration-300">
            <div className="p-2 rounded-lg bg-white/90 dark:bg-gray-900/90 backdrop-blur-sm shadow-lg">
              <ExternalLink className="w-4 h-4 text-indigo-600" />
            </div>
          </div>
          
          {image_url ? (
            <img
              src={image_url}
              alt={title}
              className="w-full h-48 object-cover transition-transform duration-700 group-hover:scale-110"
              loading="lazy"
              onError={(e) => {
                e.target.style.display = 'none';
                const placeholder = e.target.nextElementSibling;
                if (placeholder) placeholder.style.display = 'flex';
              }}
            />
          ) : null}
          {/* Placeholder for missing images */}
          <div 
            className="w-full h-48 flex items-center justify-center"
            style={{ display: image_url ? 'none' : 'flex' }}
          >
            <div className="text-center">
              <div className="w-20 h-20 mx-auto mb-3 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center">
                <Tag className="w-10 h-10 text-white" />
              </div>
              <p className="text-sm text-gray-600 dark:text-gray-400 font-bold">{source || 'News Article'}</p>
            </div>
          </div>
        </div>

        <div className="px-5 pb-5">
          {/* Keyword Badges */}
          {detectedKeywords.length > 0 && (
            <div className="flex flex-wrap gap-2 mb-3">
              {detectedKeywords.map((kw, idx) => {
                const colors = {
                  negative: 'bg-gradient-to-r from-red-500 to-rose-500 text-white shadow-lg shadow-red-500/30',
                  positive: 'bg-gradient-to-r from-green-500 to-emerald-500 text-white shadow-lg shadow-green-500/30',
                  neutral: 'bg-gradient-to-r from-blue-500 to-cyan-500 text-white shadow-lg shadow-blue-500/30',
                };
                return (
                  <span 
                    key={idx}
                    className={`inline-flex items-center gap-1 px-2.5 py-1 text-xs font-black rounded-full uppercase ${colors[kw.type]} transform hover:scale-110 transition-transform duration-300`}
                  >
                    <Tag className="w-3 h-3" />
                    {kw.word}
                  </span>
                );
              })}
            </div>
          )}
          
          <h3 className="font-black text-lg mb-3 line-clamp-2 text-gray-900 dark:text-white group-hover:text-transparent group-hover:bg-clip-text group-hover:bg-gradient-to-r group-hover:from-indigo-600 group-hover:via-purple-600 group-hover:to-pink-600 transition-all duration-300">
            {title}
          </h3>

          {description && (
            <p className="text-gray-600 dark:text-gray-400 text-sm mb-4 line-clamp-3 leading-relaxed">
              {description}
            </p>
          )}

          {/* Metadata bar */}
          <div className="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-4">
            <span className="font-bold text-gray-700 dark:text-gray-300">{source}</span>
            <span className="flex items-center gap-1">
              <Clock className="w-3 h-3" />
              {formatRelativeTime(published_at)}
            </span>
          </div>

          {/* Sentiment indicator */}
          {sentiment_score !== null && sentiment_score !== undefined && (
            <div className="pt-4 border-t-2 border-gray-100 dark:border-gray-800">
              <div className="flex justify-between items-center">
                <span className="text-xs font-bold text-gray-600 dark:text-gray-400">Market Sentiment</span>
                <span className={`inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-black rounded-full ${
                  sentiment_score > 0.3 ? 'bg-gradient-to-r from-green-500 to-emerald-500 text-white shadow-lg shadow-green-500/30' :
                  sentiment_score < -0.3 ? 'bg-gradient-to-r from-red-500 to-rose-500 text-white shadow-lg shadow-red-500/30' :
                  'bg-gradient-to-r from-gray-400 to-gray-500 text-white shadow-lg shadow-gray-500/30'
                }`}>
                  {sentiment_score > 0.3 ? (
                    <>
                      <TrendingUp className="w-3.5 h-3.5" />
                      {getSentimentLabel(sentiment_score)}
                    </>
                  ) : sentiment_score < -0.3 ? (
                    <>
                      <TrendingDown className="w-3.5 h-3.5" />
                      {getSentimentLabel(sentiment_score)}
                    </>
                  ) : (
                    <>
                      <Minus className="w-3.5 h-3.5" />
                      {getSentimentLabel(sentiment_score)}
                    </>
                  )}
                </span>
              </div>
            </div>
          )}
        </div>
      </a>
    </div>
  );
});

NewsCard.displayName = 'NewsCard';

NewsCard.propTypes = {
  article: PropTypes.shape({
    title: PropTypes.string.isRequired,
    description: PropTypes.string,
    url: PropTypes.string.isRequired,
    image_url: PropTypes.string,
    source: PropTypes.string,
    published_at: PropTypes.string,
    sentiment_score: PropTypes.number,
    is_important: PropTypes.bool,
    importance: PropTypes.oneOf(['high','medium','low','none']),
    importance_keywords: PropTypes.arrayOf(PropTypes.string),
  }).isRequired,
};

export default NewsCard;
