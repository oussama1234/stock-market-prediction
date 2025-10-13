import { Suspense, useMemo, memo, useCallback, useRef, useState, useEffect } from 'react';
import { lazy } from 'react';
import { GenericLoader } from '../components/loaders';
import { 
  TrendingUp, TrendingDown, Activity, AlertTriangle, 
  Smile, Frown, Meh, ThumbsUp, ThumbsDown, Info,
  BarChart3, Target, Zap, Shield, TrendingDownIcon
} from 'lucide-react';

// Lazy load the FearGreedGauge component
const FearGreedGauge = lazy(() => import('../components/FearGreedGauge'));

const FearGreedPage = memo(() => {
  const headerRef = useRef(null);
  const [currentTime, setCurrentTime] = useState(new Date());
  
  // Update time every second for live feel
  useEffect(() => {
    const interval = setInterval(() => setCurrentTime(new Date()), 1000);
    return () => clearInterval(interval);
  }, []);

  // Memoized page title and description
  const pageInfo = useMemo(() => ({
    title: 'Market Fear & Greed Index',
    subtitle: 'Real-time market sentiment analysis',
    description: 'The Fear & Greed Index measures investor emotions and market sentiment from 0 (Extreme Fear) to 100 (Extreme Greed).',
  }), []);

  // Memoized info cards with Lucide icons
  const infoCards = useMemo(() => [
    {
      id: 1,
      Icon: ThumbsUp,
      title: 'Extreme Greed',
      range: '75-100',
      colorBg: 'from-green-500 to-emerald-500',
      colorHover: 'from-green-600 to-emerald-600',
      description: 'Market shows signs of overvaluation',
      emoji: '😄',
      tips: 'Consider taking profits • Watch for corrections',
    },
    {
      id: 2,
      Icon: Smile,
      title: 'Greed',
      range: '55-75',
      colorBg: 'from-lime-500 to-green-500',
      colorHover: 'from-lime-600 to-green-600',
      description: 'Investors show confidence',
      emoji: '😊',
      tips: 'Positive sentiment • Monitor momentum',
    },
    {
      id: 3,
      Icon: Meh,
      title: 'Neutral',
      range: '45-55',
      colorBg: 'from-yellow-500 to-amber-500',
      colorHover: 'from-yellow-600 to-amber-600',
      description: 'Market sentiment is balanced',
      emoji: '😐',
      tips: 'Wait for clear signals • Stay patient',
    },
    {
      id: 4,
      Icon: AlertTriangle,
      title: 'Fear',
      range: '25-45',
      colorBg: 'from-orange-500 to-red-500',
      colorHover: 'from-orange-600 to-red-600',
      description: 'Investors show caution',
      emoji: '😟',
      tips: 'Potential buying opportunity • Assess risks',
    },
    {
      id: 5,
      Icon: ThumbsDown,
      title: 'Extreme Fear',
      range: '0-25',
      colorBg: 'from-red-600 to-rose-600',
      colorHover: 'from-red-700 to-rose-700',
      description: 'Market shows signs of undervaluation',
      emoji: '😢',
      tips: 'Strong buy signal • Markets oversold',
    },
  ], []);

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 dark:from-gray-900 dark:via-gray-850 dark:to-gray-900 transition-colors duration-300">
      {/* Modern Header - No Hero */}
      <div ref={headerRef} className="relative bg-white/60 dark:bg-gray-900/60 backdrop-blur-xl border-b border-gray-200/50 dark:border-gray-800/50">
        <div className="container mx-auto px-4 py-8">
          <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
              <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-gradient-to-r from-indigo-500/10 via-purple-500/10 to-pink-500/10 border border-indigo-500/20 mb-3">
                <span className="relative flex h-2 w-2">
                  <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-500 opacity-75"></span>
                  <span className="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                </span>
                <span className="text-xs font-black bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 to-purple-600">LIVE SENTIMENT</span>
              </div>
              <h1 className="text-4xl md:text-5xl font-black mb-2 bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600">
                Fear & Greed Index
              </h1>
              <p className="text-gray-600 dark:text-gray-400 text-base md:text-lg max-w-2xl">
                Real-time market sentiment • AI-powered analysis • Investor emotions tracking
              </p>
            </div>
            
            {/* Live Stats Pills */}
            <div className="flex flex-wrap gap-3">
              <div className="group relative overflow-hidden px-4 py-3 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-500 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105">
                <div className="absolute top-0 right-0 w-16 h-16 bg-white/20 rounded-full -mr-8 -mt-8 group-hover:scale-150 transition-transform duration-500"></div>
                <div className="relative flex items-center gap-2">
                  <Activity className="w-5 h-5 text-white" />
                  <div>
                    <div className="text-xs font-medium text-indigo-100">Updated</div>
                    <div className="text-sm font-black text-white">Live</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div className="container mx-auto px-4 py-8">

        {/* Main Gauge Section with Gradient Border */}
        <div className="max-w-5xl mx-auto mb-8">
          <div className="group relative">
            {/* Gradient glow */}
            <div className="absolute -inset-0.5 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 rounded-3xl opacity-20 group-hover:opacity-40 blur-sm transition-opacity duration-500"></div>
            
            <div className="relative bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl border border-gray-200/50 dark:border-gray-800/50 rounded-3xl shadow-2xl p-8 md:p-12 transition-colors duration-300">
              <Suspense 
                fallback={<GenericLoader message="Loading market sentiment" size="large" fullScreen={false} />}
              >
                <FearGreedGauge size="large" showDetails={true} />
              </Suspense>
            </div>
          </div>
        </div>

        {/* Info Cards Grid */}
        <div className="mb-8">
          <div className="flex items-center justify-center gap-3 mb-8">
            <BarChart3 className="w-6 h-6 text-indigo-600" />
            <h2 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600">
              Understanding the Index
            </h2>
          </div>
          
          <div className="grid md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
            {infoCards.map((card, index) => {
              const { Icon } = card;
              return (
                <div
                  key={card.id}
                  className="group relative"
                  style={{
                    animationDelay: `${index * 50}ms`,
                    animation: 'fadeInUp 0.5s ease-out forwards',
                    opacity: 0,
                  }}
                >
                  {/* Gradient glow on hover */}
                  <div className={`absolute -inset-0.5 rounded-2xl opacity-0 group-hover:opacity-100 blur transition-all duration-500 bg-gradient-to-r ${card.colorBg}`}></div>
                  
                  <div className="relative bg-white dark:bg-gray-900 rounded-2xl p-5 shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2 border-2 border-gray-200 dark:border-gray-800 overflow-hidden">
                    {/* Gradient Background on Hover */}
                    <div className={`absolute inset-0 bg-gradient-to-br ${card.colorBg} opacity-0 group-hover:opacity-10 transition-opacity duration-300`}></div>
                    
                    {/* Content */}
                    <div className="relative z-10">
                      {/* Icon & Emoji */}
                      <div className="flex items-center justify-between mb-4">
                        <div className={`p-3 rounded-xl bg-gradient-to-br ${card.colorBg} text-white shadow-lg transform group-hover:scale-110 group-hover:rotate-6 transition-all duration-300`}>
                          <Icon className="w-5 h-5" />
                        </div>
                        <span className="text-3xl transform group-hover:scale-125 transition-transform duration-300">{card.emoji}</span>
                      </div>
                      
                      {/* Title */}
                      <h3 className="text-lg font-black text-gray-900 dark:text-white mb-2 group-hover:text-transparent group-hover:bg-clip-text group-hover:bg-gradient-to-r group-hover:from-indigo-600 group-hover:to-purple-600 transition-all duration-300">
                        {card.title}
                      </h3>
                      
                      {/* Range Badge */}
                      <div className={`inline-block px-3 py-1.5 rounded-full bg-gradient-to-r ${card.colorBg} text-white text-xs font-black mb-3 shadow-lg`}>
                        {card.range}
                      </div>
                      
                      {/* Description */}
                      <p className="text-xs text-gray-600 dark:text-gray-400 mb-3 leading-relaxed">
                        {card.description}
                      </p>
                      
                      {/* Tips */}
                      <div className="pt-3 border-t-2 border-gray-100 dark:border-gray-800">
                        <p className="text-xs text-gray-500 dark:text-gray-400 font-medium">
                          {card.tips}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        {/* Additional Info Section */}
        <div className="max-w-5xl mx-auto">
          <div className="group relative">
            {/* Gradient glow */}
            <div className="absolute -inset-0.5 bg-gradient-to-r from-cyan-500 via-indigo-500 to-purple-500 rounded-2xl opacity-20 group-hover:opacity-40 blur-sm transition-opacity duration-500"></div>
            
            <div className="relative bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 rounded-2xl p-8 border-2 border-indigo-200 dark:border-indigo-800 transition-colors duration-300">
              <div className="flex items-start gap-4">
                <div className="flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-600 to-purple-600 flex items-center justify-center text-white shadow-lg group-hover:scale-110 group-hover:rotate-6 transition-all duration-300">
                  <Info className="w-6 h-6" />
                </div>
                
                <div className="flex-1">
                  <h3 className="text-2xl font-black mb-4 bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 to-purple-600">
                    How to Use This Index
                  </h3>
                  <div className="grid md:grid-cols-2 gap-4">
                    <div className="flex items-start gap-3 p-4 bg-white dark:bg-gray-800/50 rounded-xl">
                      <ThumbsDown className="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" />
                      <div>
                        <div className="font-black text-gray-900 dark:text-white mb-1">Extreme Fear</div>
                        <p className="text-sm text-gray-600 dark:text-gray-300">May indicate a buying opportunity as markets could be oversold</p>
                      </div>
                    </div>
                    <div className="flex items-start gap-3 p-4 bg-white dark:bg-gray-800/50 rounded-xl">
                      <ThumbsUp className="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" />
                      <div>
                        <div className="font-black text-gray-900 dark:text-white mb-1">Extreme Greed</div>
                        <p className="text-sm text-gray-600 dark:text-gray-300">May suggest caution as markets could be overbought</p>
                      </div>
                    </div>
                    <div className="flex items-start gap-3 p-4 bg-white dark:bg-gray-800/50 rounded-xl">
                      <Activity className="w-5 h-5 text-indigo-600 flex-shrink-0 mt-0.5" />
                      <div>
                        <div className="font-black text-gray-900 dark:text-white mb-1">Updates</div>
                        <p className="text-sm text-gray-600 dark:text-gray-300">The index is updated daily based on multiple market indicators</p>
                      </div>
                    </div>
                    <div className="flex items-start gap-3 p-4 bg-white dark:bg-gray-800/50 rounded-xl">
                      <Shield className="w-5 h-5 text-purple-600 flex-shrink-0 mt-0.5" />
                      <div>
                        <div className="font-black text-gray-900 dark:text-white mb-1">Note</div>
                        <p className="text-sm text-gray-600 dark:text-gray-300">Use as one of many tools in your investment decision process</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Animation Styles */}
      <style>{`
        @keyframes fadeInUp {
          from {
            opacity: 0;
            transform: translateY(30px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }
        
        .animate-fade-in-up {
          animation: fadeInUp 0.8s ease-out forwards;
        }
      `}</style>
    </div>
  );
});

FearGreedPage.displayName = 'FearGreedPage';

export default FearGreedPage;
