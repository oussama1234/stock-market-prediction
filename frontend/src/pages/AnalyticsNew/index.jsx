import { useParams, Link } from 'react-router-dom';
import { useState, useEffect, useRef, memo } from 'react';
import { useAnalytics } from './hooks/useAnalytics';
import { StockLoader } from '../../components/loaders';
import StockLogo from '../../components/StockLogo';
import PredictionCardV2Enhanced from '../../components/PredictionCardV2';
import {
  ArrowLeft, RefreshCw, Clock, TrendingUp, TrendingDown, Activity,
  Target, Shield, Zap, AlertTriangle, CheckCircle, XCircle,
  BarChart3, LineChart, Gauge, ArrowUpCircle, ArrowDownCircle,
  ShieldCheck, ShieldAlert, Sparkles, Brain, Signal, Radio,
  ChevronUp, ChevronDown, TrendingUpDown, Layers
} from 'lucide-react';

export default function AnalyticsNew() {
  const { symbol } = useParams();
  const [refreshing, setRefreshing] = useState(false);
  const [currentTime, setCurrentTime] = useState(new Date());
  const { data, loading, error, lastUpdate, refresh, regenerateToday } = useAnalytics(symbol);
  const chartContainerRef = useRef(null);

  // Update current time every second
  useEffect(() => {
    const timer = setInterval(() => setCurrentTime(new Date()), 1000);
    return () => clearInterval(timer);
  }, []);

  const handleRefresh = async () => {
    setRefreshing(true);
    await refresh();
    setTimeout(() => setRefreshing(false), 500);
  };

  const formatTime = () => {
    if (!lastUpdate) return '';
    const diff = Math.floor((currentTime - lastUpdate) / 1000);
    if (diff < 60) return `${diff}s ago`;
    const mins = Math.floor(diff / 60);
    if (mins < 60) return `${mins}m ago`;
    return `${Math.floor(mins / 60)}h ago`;
  };

  // TradingView Integration
  useEffect(() => {
    if (!data || !chartContainerRef.current) return;

    const exchange = data.stock?.exchange || 'NASDAQ';
    const exchangeStr = exchange.toUpperCase();
    
    let tvExchange = 'NASDAQ';
    if (exchangeStr.includes('NYSE')) tvExchange = 'NYSE';
    else if (exchangeStr.includes('NASDAQ')) tvExchange = 'NASDAQ';
    else if (exchangeStr.includes('AMEX')) tvExchange = 'AMEX';
    
    // Clean symbol (remove any special characters)
    const cleanSymbol = symbol.toUpperCase().replace(/[^A-Z0-9]/g, '');
    const tradingViewSymbol = `${tvExchange}:${cleanSymbol}`;
    
    // Generate unique container ID
    const containerId = `tradingview_${cleanSymbol}_${Date.now()}`;
    chartContainerRef.current.id = containerId;

    const script = document.createElement('script');
    script.src = 'https://s3.tradingview.com/tv.js';
    script.async = true;
    script.onload = () => {
      if (window.TradingView && chartContainerRef.current) {
        try {
          new window.TradingView.widget({
            autosize: true,
            symbol: tradingViewSymbol,
            interval: '60',
            timezone: 'America/New_York',
            theme: 'light',
            style: '1',
            locale: 'en',
            toolbar_bg: '#f3f4f6',
            enable_publishing: false,
            allow_symbol_change: true,
            container_id: containerId,
            studies: ['MASimple@tv-basicstudies', 'RSI@tv-basicstudies', 'MACD@tv-basicstudies', 'BB@tv-basicstudies'],
          });
        } catch (error) {
          console.error('TradingView widget error:', error);
          if (chartContainerRef.current) {
            chartContainerRef.current.innerHTML = `<div class="flex items-center justify-center h-full text-gray-500">Unable to load TradingView chart for ${tradingViewSymbol}</div>`;
          }
        }
      }
    };
    
    script.onerror = () => {
      console.error('Failed to load TradingView script');
      if (chartContainerRef.current) {
        chartContainerRef.current.innerHTML = '<div class="flex items-center justify-center h-full text-gray-500">Failed to load chart</div>';
      }
    };
    
    document.head.appendChild(script);

    return () => {
      if (chartContainerRef.current) {
        chartContainerRef.current.innerHTML = '';
      }
      if (script.parentNode) {
        script.parentNode.removeChild(script);
      }
    };
  }, [symbol, data]);

  if (loading) {
    return <StockLoader symbol={symbol} message="Analyzing technical indicators, support/resistance, and signals" />;
  }

  if (error || !data) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 p-4">
        <div className="bg-white rounded-3xl shadow-2xl p-12 text-center max-w-2xl">
          <AlertTriangle className="w-20 h-20 text-red-500 mx-auto mb-4" />
          <h2 className="text-3xl font-bold text-gray-900 mb-4">Error Loading Analytics</h2>
          <p className="text-gray-600 mb-6">{error || `Unable to load ${symbol}`}</p>
          <div className="flex gap-4 justify-center">
            <button onClick={handleRefresh} className="flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white rounded-xl font-semibold hover:bg-indigo-700 transition-all hover:scale-105 shadow-lg">
              <RefreshCw className="w-5 h-5" />
              Try Again
            </button>
            <Link to={`/stock/${symbol}`} className="flex items-center gap-2 px-6 py-3 bg-gray-100 text-gray-800 rounded-xl font-semibold hover:bg-gray-200 transition-all hover:scale-105">
              <ArrowLeft className="w-5 h-5" />
              Back to Stock
            </Link>
          </div>
        </div>
      </div>
    );
  }

  const { stock, quote, prediction, support_resistance, indicators, fear_greed, alerts } = data;
  const currentPrice = quote?.current_price || 0;
  const change = quote?.change || 0;
  const changePct = quote?.change_percent || 0;
  const isPositive = change >= 0;
  const isBullish = prediction?.direction === 'up';
  const confidence = Math.round((prediction?.probability || 0.5) * 100);
  const predictedPrice = prediction?.predicted_price || currentPrice;
  const priceDiff = predictedPrice - currentPrice;
  const priceChangePercent = currentPrice > 0 ? ((priceDiff / currentPrice) * 100) : 0;

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 transition-colors duration-300">
      <div className="container mx-auto px-4 py-8">
        {/* Navigation */}
        <NavigationBar symbol={symbol} lastUpdate={lastUpdate} formatTime={formatTime} refreshing={refreshing} onRefresh={handleRefresh} />

        {/* Header */}
        <StockHeader stock={stock} symbol={symbol} currentPrice={currentPrice} change={change} changePct={changePct} isPositive={isPositive} />

        {/* Prediction Card */}
        <div className="mb-6">
          <PredictionCardV2Enhanced symbol={symbol} horizon="today" />
        </div>

        {/* TradingView Chart */}
        <TradingViewChart chartContainerRef={chartContainerRef} symbol={symbol} />

        {/* Technical Indicators & Support/Resistance */}
        <div className="grid lg:grid-cols-2 gap-6 mb-6">
          <TechnicalIndicators indicators={indicators} currentPrice={currentPrice} />
          <SupportResistance supportResistance={support_resistance} currentPrice={currentPrice} />
        </div>

        {/* Trading Signals */}
        <TradingSignals 
          indicators={indicators} 
          supportResistance={support_resistance} 
          prediction={prediction}
          isBullish={isBullish}
          confidence={confidence}
          currentPrice={currentPrice}
          predictedPrice={predictedPrice}
          priceChangePercent={priceChangePercent}
          alerts={alerts}
        />

        {/* Fear & Greed */}
        {fear_greed && <FearGreedWidget fearGreed={fear_greed} />}
      </div>
    </div>
  );
}

// Navigation Component
const NavigationBar = memo(({ symbol, lastUpdate, formatTime, refreshing, onRefresh }) => (
  <div className="flex items-center justify-between mb-6">
    <Link 
      to={`/stock/${symbol}`} 
      className="group inline-flex items-center gap-2 px-4 py-2 bg-white/80 backdrop-blur-sm rounded-xl shadow-md hover:shadow-lg text-indigo-600 hover:text-indigo-700 font-semibold transition-all hover:scale-105"
    >
      <ArrowLeft className="w-5 h-5 group-hover:-translate-x-1 transition-transform" />
      Back to Stock Details
    </Link>
    <div className="flex items-center gap-3">
      {lastUpdate && (
        <div className="flex items-center gap-2 px-4 py-2 bg-white/80 backdrop-blur-sm rounded-xl shadow-md">
          <Clock className="w-4 h-4 text-gray-500" />
          <span className="text-xs font-medium text-gray-600">Updated {formatTime()}</span>
        </div>
      )}
      <button 
        onClick={onRefresh} 
        disabled={refreshing} 
        className="group inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold rounded-xl hover:from-indigo-700 hover:to-purple-700 transition-all hover:scale-105 disabled:opacity-50 shadow-lg hover:shadow-xl"
      >
        <RefreshCw className={`w-5 h-5 ${refreshing ? 'animate-spin' : 'group-hover:rotate-180'} transition-transform duration-500`} />
        {refreshing ? 'Refreshing...' : 'Refresh'}
      </button>
    </div>
  </div>
));

// Stock Header Component
const StockHeader = memo(({ stock, symbol, currentPrice, change, changePct, isPositive }) => (
  <div className="group relative bg-white rounded-3xl shadow-2xl hover:shadow-3xl p-8 mb-6 overflow-hidden transition-all duration-500">
    <div className="absolute inset-0 bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 opacity-70"></div>
    <div className="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
      <div className="absolute top-0 left-0 w-96 h-96 bg-indigo-200 rounded-full blur-3xl animate-pulse"></div>
      <div className="absolute bottom-0 right-0 w-96 h-96 bg-purple-200 rounded-full blur-3xl animate-pulse"></div>
    </div>
    
    <div className="relative z-10">
      <div className="flex items-start justify-between">
        <div className="flex items-center gap-6">
          <div className="transform hover:scale-110 hover:rotate-3 transition-all duration-300">
            <StockLogo symbol={stock?.symbol} logoUrl={stock?.logo_url} size="xl" />
          </div>
          <div>
            <div className="flex items-center gap-3 mb-2">
              <BarChart3 className="w-8 h-8 text-indigo-600" />
              <h1 className="text-5xl font-black bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 bg-clip-text text-transparent">
                {symbol} Analytics
              </h1>
            </div>
            <p className="text-2xl text-gray-700 font-semibold mb-3">{stock?.name}</p>
            <div className="flex items-center gap-2 flex-wrap">
              <Badge icon={<LineChart className="w-3.5 h-3.5" />} text="Technical Analysis" color="indigo" />
              <Badge icon={<Zap className="w-3.5 h-3.5" />} text="Live Signals" color="purple" />
              <Badge icon={<Target className="w-3.5 h-3.5" />} text="S/R Levels" color="pink" />
              <Badge icon={<Radio className="w-3.5 h-3.5" />} text="Live" color="green" pulse />
            </div>
          </div>
        </div>
        
        <div className="text-right">
          <div className="text-6xl font-black text-gray-900 mb-3 tracking-tight">
            ${currentPrice.toFixed(2)}
          </div>
          <div className={`inline-flex items-center gap-2 px-5 py-3 rounded-2xl text-xl font-bold shadow-lg backdrop-blur-md transition-all hover:scale-105 ${
            isPositive 
              ? 'bg-gradient-to-r from-green-500 to-emerald-500 text-white' 
              : 'bg-gradient-to-r from-red-500 to-rose-500 text-white'
          }`}>
            {isPositive ? <TrendingUp className="w-6 h-6" /> : <TrendingDown className="w-6 h-6" />}
            <span>{isPositive ? '+' : ''}{changePct.toFixed(2)}%</span>
          </div>
          <div className="mt-2 text-sm text-gray-600 font-medium">
            {isPositive ? '+' : ''}{change.toFixed(2)} today
          </div>
        </div>
      </div>
    </div>
  </div>
));

// Badge Component
const Badge = ({ icon, text, color, pulse }) => (
  <div className={`flex items-center gap-1.5 px-3 py-1.5 bg-${color}-100 text-${color}-700 rounded-full backdrop-blur-sm shadow-sm hover:shadow-md transition-shadow ${pulse ? 'animate-pulse' : ''}`}>
    {icon}
    <span className="text-xs font-semibold">{text}</span>
  </div>
);

// TradingView Chart Component
const TradingViewChart = memo(({ chartContainerRef, symbol }) => (
  <div className="group relative bg-white rounded-3xl shadow-xl hover:shadow-2xl p-6 mb-6 transition-all duration-300">
    <div className="flex items-center gap-3 mb-6">
      <div className="p-3 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-xl shadow-lg">
        <LineChart className="w-6 h-6 text-white" />
      </div>
      <div>
        <h2 className="text-2xl font-bold text-gray-900">Live Trading Chart</h2>
        <p className="text-sm text-gray-600">Real-time price action with indicators</p>
      </div>
    </div>
    <div ref={chartContainerRef} id={`tradingview_${symbol}_${Date.now()}`} style={{ height: '800px' }} className="w-full rounded-2xl overflow-hidden shadow-inner"></div>
  </div>
));

// Technical Indicators Component
const TechnicalIndicators = memo(({ indicators, currentPrice }) => (
  <div className="relative bg-white rounded-3xl shadow-xl hover:shadow-2xl p-6 transition-all duration-300">
    <div className="absolute inset-0 bg-gradient-to-br from-purple-50 via-indigo-50 to-blue-50 rounded-3xl opacity-50"></div>
    
    <div className="relative z-10">
      <div className="flex items-center gap-3 mb-6">
        <div className="p-3 bg-gradient-to-br from-purple-500 to-indigo-500 rounded-xl shadow-lg">
          <Activity className="w-6 h-6 text-white" />
        </div>
        <div>
          <h3 className="text-2xl font-bold text-gray-900">Technical Indicators</h3>
          <div className="flex items-center gap-2">
            <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
            <span className="text-xs font-semibold text-gray-600">Live Analysis</span>
          </div>
        </div>
      </div>
      
      <div className="space-y-4">
        {indicators?.rsi && <RSIIndicator rsi={indicators.rsi} />}
        {indicators?.macd && <MACDIndicator macd={indicators.macd} />}
        {indicators?.ema_20 && <MovingAverages ema20={indicators.ema_20} ema50={indicators.ema_50} currentPrice={currentPrice} />}
      </div>
    </div>
  </div>
));

// RSI Indicator Component
const RSIIndicator = memo(({ rsi }) => {
  const status = rsi > 70 ? 'overbought' : rsi < 30 ? 'oversold' : 'neutral';
  const colors = {
    overbought: { bg: 'bg-red-100', text: 'text-red-700', bar: 'bg-red-600', label: 'OVERBOUGHT' },
    oversold: { bg: 'bg-green-100', text: 'text-green-700', bar: 'bg-green-600', label: 'OVERSOLD' },
    neutral: { bg: 'bg-blue-100', text: 'text-blue-700', bar: 'bg-blue-600', label: 'NEUTRAL' }
  };
  const color = colors[status];

  return (
    <div className="group relative bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-100 hover:border-purple-200">
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-3">
          <div className={`p-2.5 rounded-xl ${color.bg}`}>
            <Gauge className={`w-5 h-5 ${color.text}`} />
          </div>
          <div>
            <div className="text-sm font-bold text-gray-700">RSI (14)</div>
            <span className={`inline-block px-2.5 py-1 text-xs font-bold rounded-full ${color.bg} ${color.text}`}>
              {color.label}
            </span>
          </div>
        </div>
        <div className="text-4xl font-black text-gray-900">{rsi.toFixed(1)}</div>
      </div>
      
      <div className="relative">
        <div className="h-4 bg-gradient-to-r from-green-200 via-blue-200 to-red-200 rounded-full overflow-hidden">
          <div className={`h-full ${color.bar} transition-all duration-500 shadow-lg`} style={{ width: `${rsi}%` }}></div>
        </div>
        <div className="flex justify-between text-xs text-gray-500 mt-2 px-1">
          <span>0</span>
          <span className="text-green-600 font-semibold">30</span>
          <span>50</span>
          <span className="text-red-600 font-semibold">70</span>
          <span>100</span>
        </div>
      </div>
      
      <div className="mt-4 pt-4 border-t border-gray-200">
        <div className="flex items-center justify-between text-xs">
          <span className="text-gray-600">Signal Strength:</span>
          <div className="flex gap-1">
            {[1, 2, 3, 4, 5].map(i => (
              <div 
                key={i} 
                className={`w-2 h-4 rounded-sm transition-all ${
                  (rsi > 70 || rsi < 30) && i <= Math.abs(rsi > 70 ? rsi - 70 : 30 - rsi) / 6
                    ? rsi > 70 ? 'bg-red-500' : 'bg-green-500'
                    : 'bg-gray-300'
                }`}
              ></div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
});

// MACD Indicator Component
const MACDIndicator = memo(({ macd }) => {
  const isBullish = macd.signal === 'bullish';
  
  return (
    <div className="group relative bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-100 hover:border-purple-200">
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-3">
          <div className={`p-2.5 rounded-xl ${isBullish ? 'bg-green-100' : 'bg-red-100'}`}>
            {isBullish ? <TrendingUp className="w-5 h-5 text-green-600" /> : <TrendingDown className="w-5 h-5 text-red-600" />}
          </div>
          <div>
            <div className="text-sm font-bold text-gray-700">MACD</div>
            <span className={`inline-block px-2.5 py-1 text-xs font-bold rounded-full ${isBullish ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
              {macd.signal?.toUpperCase() || 'NEUTRAL'}
            </span>
          </div>
        </div>
        <div className="flex items-center gap-2">
          {isBullish ? <ChevronUp className="w-8 h-8 text-green-600" /> : <ChevronDown className="w-8 h-8 text-red-600" />}
          <span className="text-3xl font-black text-gray-900">{macd.value.toFixed(3)}</span>
        </div>
      </div>
      
      <div className="grid grid-cols-2 gap-3 mt-3">
        <div className="text-center p-3 bg-gray-50 rounded-lg">
          <div className="text-xs text-gray-600 mb-1">MACD Line</div>
          <div className="text-lg font-bold text-gray-900">{macd.value.toFixed(3)}</div>
        </div>
        {macd.signal_line !== undefined && (
          <div className="text-center p-3 bg-gray-50 rounded-lg">
            <div className="text-xs text-gray-600 mb-1">Signal Line</div>
            <div className="text-lg font-bold text-gray-900">{macd.signal_line.toFixed(3)}</div>
          </div>
        )}
      </div>
      
      <div className="mt-4 pt-4 border-t border-gray-200">
        <div className="text-xs text-gray-600 mb-2">Momentum:</div>
        <div className="flex gap-1">
          {[...Array(10)].map((_, i) => (
            <div 
              key={i} 
              className={`flex-1 h-3 rounded transition-all ${
                isBullish 
                  ? i < 7 ? 'bg-green-500' : 'bg-gray-300'
                  : i < 3 ? 'bg-red-500' : 'bg-gray-300'
              }`}
            ></div>
          ))}
        </div>
      </div>
    </div>
  );
});

// Moving Averages Component
const MovingAverages = memo(({ ema20, ema50, currentPrice }) => {
  const aboveEMA20 = currentPrice > ema20;
  const trend = ema50 && ema20 > ema50 ? 'bullish' : 'bearish';
  
  return (
    <div className="group relative bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-100 hover:border-purple-200">
      <div className="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
        <div className="p-2 bg-blue-100 rounded-lg">
          <TrendingUpDown className="w-4 h-4 text-blue-600" />
        </div>
        <span>Moving Averages</span>
        <span className={`ml-auto px-2.5 py-1 text-xs font-bold rounded-full ${aboveEMA20 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
          {aboveEMA20 ? 'ABOVE EMA' : 'BELOW EMA'}
        </span>
      </div>
      
      <div className="space-y-3">
        <div className="flex justify-between items-center p-3 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl">
          <div>
            <div className="text-xs text-gray-600 font-semibold">EMA 20</div>
            <div className="text-xl font-black text-blue-600">${ema20.toFixed(2)}</div>
          </div>
          <div className="text-right">
            <div className="text-xs text-gray-600">Distance</div>
            <div className={`text-sm font-bold ${currentPrice > ema20 ? 'text-green-600' : 'text-red-600'}`}>
              {currentPrice > ema20 ? '+' : ''}{(((currentPrice - ema20) / ema20) * 100).toFixed(2)}%
            </div>
          </div>
        </div>
        
        {ema50 && (
          <div className="flex justify-between items-center p-3 bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl">
            <div>
              <div className="text-xs text-gray-600 font-semibold">EMA 50</div>
              <div className="text-xl font-black text-purple-600">${ema50.toFixed(2)}</div>
            </div>
            <div className="text-right">
              <div className="text-xs text-gray-600">Distance</div>
              <div className={`text-sm font-bold ${currentPrice > ema50 ? 'text-green-600' : 'text-red-600'}`}>
                {currentPrice > ema50 ? '+' : ''}{(((currentPrice - ema50) / ema50) * 100).toFixed(2)}%
              </div>
            </div>
          </div>
        )}
      </div>
      
      {ema50 && (
        <div className="mt-4 pt-4 border-t border-gray-200">
          <div className="flex items-center justify-between">
            <span className="text-xs text-gray-600">Trend:</span>
            <span className={`px-3 py-1 text-xs font-bold rounded-full ${trend === 'bullish' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
              {trend === 'bullish' ? '▲ BULLISH CROSSOVER' : '▼ BEARISH CROSSOVER'}
            </span>
          </div>
        </div>
      )}
    </div>
  );
});

// Support & Resistance Component
const SupportResistance = memo(({ supportResistance, currentPrice }) => (
  <div className="relative bg-white rounded-3xl shadow-xl hover:shadow-2xl p-6 transition-all duration-300">
    <div className="absolute inset-0 bg-gradient-to-br from-green-50 via-emerald-50 to-teal-50 rounded-3xl opacity-50"></div>
    
    <div className="relative z-10">
      <div className="flex items-center gap-3 mb-6">
        <div className="p-3 bg-gradient-to-br from-green-500 to-emerald-500 rounded-xl shadow-lg">
          <Target className="w-6 h-6 text-white" />
        </div>
        <div>
          <h3 className="text-2xl font-bold text-gray-900">Support & Resistance</h3>
          <p className="text-xs text-gray-600">Key price levels</p>
        </div>
      </div>
      
      <div className="space-y-4">
        <div className="bg-white rounded-2xl p-4 shadow-md">
          <div className="flex items-center gap-2 text-sm font-semibold text-green-700 mb-3">
            <ArrowDownCircle className="w-4 h-4" />
            Support Levels
          </div>
          {supportResistance?.supports && supportResistance.supports.length > 0 ? (
            <div className="space-y-2">
              {supportResistance.supports.slice(0, 3).map((s, i) => (
                <div key={i} className="flex justify-between items-center p-3 bg-green-50 rounded-xl hover:bg-green-100 transition-colors group">
                  <div>
                    <div className="text-gray-900 font-bold text-lg">${(s.price || s).toFixed(2)}</div>
                    <div className="text-xs text-gray-500">Level {i + 1}</div>
                  </div>
                  <div className="text-right">
                    <div className="text-xs font-semibold text-green-700">
                      {s.distance_percent ? `${Math.abs(s.distance_percent).toFixed(2)}% below` : 'Support'}
                    </div>
                    {s.strength && (
                      <div className="text-xs text-gray-500">Strength: {s.strength}</div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <EmptyState icon={<Layers className="w-8 h-8" />} text="No support levels detected" />
          )}
        </div>
        
        <div className="bg-white rounded-2xl p-4 shadow-md">
          <div className="flex items-center gap-2 text-sm font-semibold text-red-700 mb-3">
            <ArrowUpCircle className="w-4 h-4" />
            Resistance Levels
          </div>
          {supportResistance?.resistances && supportResistance.resistances.length > 0 ? (
            <div className="space-y-2">
              {supportResistance.resistances.slice(0, 3).map((r, i) => (
                <div key={i} className="flex justify-between items-center p-3 bg-red-50 rounded-xl hover:bg-red-100 transition-colors group">
                  <div>
                    <div className="text-gray-900 font-bold text-lg">${(r.price || r).toFixed(2)}</div>
                    <div className="text-xs text-gray-500">Level {i + 1}</div>
                  </div>
                  <div className="text-right">
                    <div className="text-xs font-semibold text-red-700">
                      {r.distance_percent ? `${Math.abs(r.distance_percent).toFixed(2)}% above` : 'Resistance'}
                    </div>
                    {r.strength && (
                      <div className="text-xs text-gray-500">Strength: {r.strength}</div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <EmptyState icon={<Layers className="w-8 h-8" />} text="No resistance levels detected" />
          )}
        </div>
      </div>
    </div>
  </div>
));

// Trading Signals Component
const TradingSignals = memo(({ indicators, supportResistance, prediction, isBullish, confidence, currentPrice, predictedPrice, priceChangePercent, alerts }) => {
  const signals = generateSignals({ indicators, supportResistance, prediction, isBullish, confidence, currentPrice, predictedPrice, priceChangePercent, alerts });

  return (
    <div className="relative bg-white rounded-3xl shadow-xl hover:shadow-2xl p-6 mb-6 transition-all duration-300">
      <div className="absolute inset-0 bg-gradient-to-br from-yellow-50 via-orange-50 to-amber-50 rounded-3xl opacity-50"></div>
      
      <div className="relative z-10">
        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-3">
            <div className="p-3 bg-gradient-to-br from-yellow-500 to-orange-500 rounded-xl shadow-lg">
              <Zap className="w-6 h-6 text-white" />
            </div>
            <div>
              <h3 className="text-2xl font-bold text-gray-900">Trading Signals & Alerts</h3>
              <p className="text-xs text-gray-600">AI-powered analysis</p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
            <span className="text-xs text-gray-600 font-semibold">Live</span>
          </div>
        </div>
        
        <div className="space-y-4">
          {signals.length === 0 ? (
            <EmptyState 
              icon={<BarChart3 className="w-12 h-12" />} 
              title="No Active Signals"
              text="All indicators are neutral. Market is in equilibrium."
            />
          ) : (
            signals.slice(0, 5).map((signal, i) => (
              <SignalCard key={i} signal={signal} />
            ))
          )}
        </div>
      </div>
    </div>
  );
});

// Generate Signals Helper
function generateSignals({ indicators, supportResistance, prediction, isBullish, confidence, currentPrice, predictedPrice, priceChangePercent, alerts }) {
  const signals = [];
  
  // RSI signals
  if (indicators?.rsi) {
    if (indicators.rsi < 30) {
      signals.push({
        type: 'BUY',
        title: 'Strong Buy Signal - RSI Oversold',
        description: `RSI is at ${indicators.rsi.toFixed(1)}, indicating oversold conditions.`,
        confidence: Math.min(0.9, 0.6 + (30 - indicators.rsi) / 50),
        reasons: [`RSI below 30 (${indicators.rsi.toFixed(1)})`, 'Oversold momentum suggests buying opportunity', 'Price may rebound from current levels'],
        priority: 'high'
      });
    } else if (indicators.rsi > 70) {
      signals.push({
        type: 'SELL',
        title: 'Caution - RSI Overbought',
        description: `RSI is at ${indicators.rsi.toFixed(1)}, indicating overbought conditions.`,
        confidence: Math.min(0.85, 0.55 + (indicators.rsi - 70) / 50),
        reasons: [`RSI above 70 (${indicators.rsi.toFixed(1)})`, 'Overbought conditions may lead to correction', 'Consider profit-taking strategy'],
        priority: 'high'
      });
    }
  }
  
  // MACD signals
  if (indicators?.macd?.signal === 'bullish' && indicators.macd.value > 0) {
    signals.push({
      type: 'BUY',
      title: 'MACD Bullish Crossover',
      description: 'MACD line crossed above signal line.',
      confidence: 0.75,
      reasons: ['MACD bullish crossover detected', 'Positive momentum building', 'Trend reversal potential'],
      priority: 'medium'
    });
  } else if (indicators?.macd?.signal === 'bearish' && indicators.macd.value < 0) {
    signals.push({
      type: 'SELL',
      title: 'MACD Bearish Crossover',
      description: 'MACD line crossed below signal line.',
      confidence: 0.72,
      reasons: ['MACD bearish crossover detected', 'Negative momentum building', 'Consider protective measures'],
      priority: 'medium'
    });
  }
  
  // Moving average signals
  if (indicators?.ema_20 && indicators?.ema_50) {
    if (indicators.ema_20 > indicators.ema_50 && currentPrice > indicators.ema_20) {
      signals.push({
        type: 'BUY',
        title: 'Golden Cross Pattern',
        description: 'Price above both EMAs with bullish crossover.',
        confidence: 0.82,
        reasons: ['Price trading above key moving averages', 'EMA 20 above EMA 50', 'Uptrend confirmation'],
        priority: 'high'
      });
    } else if (indicators.ema_20 < indicators.ema_50 && currentPrice < indicators.ema_20) {
      signals.push({
        type: 'SELL',
        title: 'Death Cross Warning',
        description: 'Price below both EMAs with bearish crossover.',
        confidence: 0.78,
        reasons: ['Price trading below key moving averages', 'EMA 20 below EMA 50', 'Downtrend confirmation'],
        priority: 'high'
      });
    }
  }
  
  // Support/Resistance signals
  if (supportResistance?.supports?.[0]) {
    const supportPrice = supportResistance.supports[0].price || supportResistance.supports[0];
    const distanceToSupport = Math.abs(((currentPrice - supportPrice) / supportPrice) * 100);
    if (distanceToSupport < 2) {
      signals.push({
        type: 'BUY',
        title: 'Near Support Level',
        description: `Price within ${distanceToSupport.toFixed(1)}% of strong support.`,
        confidence: 0.7,
        reasons: [`Support at $${supportPrice.toFixed(2)}`, 'Price near historical support zone', 'Favorable risk/reward ratio'],
        priority: 'medium'
      });
    }
  }
  
  if (supportResistance?.resistances?.[0]) {
    const resistancePrice = supportResistance.resistances[0].price || supportResistance.resistances[0];
    const distanceToResistance = Math.abs(((currentPrice - resistancePrice) / resistancePrice) * 100);
    if (distanceToResistance < 2) {
      signals.push({
        type: 'SELL',
        title: 'Near Resistance Level',
        description: `Price within ${distanceToResistance.toFixed(1)}% of resistance.`,
        confidence: 0.68,
        reasons: [`Resistance at $${resistancePrice.toFixed(2)}`, 'Price approaching historical resistance', 'Probability of pullback increases'],
        priority: 'medium'
      });
    }
  }
  
  // AI prediction signals
  if (prediction && isBullish && confidence > 70) {
    signals.push({
      type: 'BUY',
      title: 'AI Prediction: Strong Bullish',
      description: `AI model predicts ${priceChangePercent.toFixed(2)}% upside.`,
      confidence: confidence / 100,
      reasons: [`${confidence}% confidence in bullish direction`, `Target price: $${predictedPrice.toFixed(2)}`, `Expected gain: ${priceChangePercent.toFixed(2)}%`],
      priority: 'high'
    });
  } else if (prediction && !isBullish && confidence > 70) {
    signals.push({
      type: 'SELL',
      title: 'AI Prediction: Strong Bearish',
      description: `AI model predicts ${Math.abs(priceChangePercent).toFixed(2)}% downside.`,
      confidence: confidence / 100,
      reasons: [`${confidence}% confidence in bearish direction`, `Target price: $${predictedPrice.toFixed(2)}`, `Expected decline: ${Math.abs(priceChangePercent).toFixed(2)}%`],
      priority: 'high'
    });
  }
  
  // Backend alerts
  if (alerts?.length > 0) {
    alerts.forEach(alert => {
      signals.push({
        type: alert.type || 'INFO',
        title: alert.title,
        description: alert.description,
        confidence: alert.confidence || 0.5,
        reasons: alert.reasons || [],
        priority: alert.priority || 'medium'
      });
    });
  }
  
  // Sort by priority and confidence
  const priorityOrder = { high: 3, medium: 2, low: 1 };
  signals.sort((a, b) => {
    const priorityDiff = (priorityOrder[b.priority] || 2) - (priorityOrder[a.priority] || 2);
    if (priorityDiff !== 0) return priorityDiff;
    return b.confidence - a.confidence;
  });
  
  return signals;
}

// Signal Card Component
const SignalCard = memo(({ signal }) => {
  const typeColors = {
    BUY: { border: 'border-green-500', bg: 'bg-green-100', text: 'text-green-700', bar: 'bg-green-500', icon: <CheckCircle /> },
    SELL: { border: 'border-red-500', bg: 'bg-red-100', text: 'text-red-700', bar: 'bg-red-500', icon: <XCircle /> },
    INFO: { border: 'border-yellow-500', bg: 'bg-yellow-100', text: 'text-yellow-700', bar: 'bg-yellow-500', icon: <AlertTriangle /> }
  };
  const color = typeColors[signal.type] || typeColors.INFO;
  const priorityColor = signal.priority === 'high' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700';

  return (
    <div className={`group relative bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all duration-300 border-l-4 ${color.border}`}>
      <div className="flex items-start justify-between mb-4">
        <div className="flex-1">
          <div className="flex items-center gap-2 mb-2">
            <span className={`px-3 py-1 text-xs font-black rounded-full ${color.bg} ${color.text}`}>
              {signal.type}
            </span>
            <span className={`px-2 py-1 text-xs font-bold rounded-full ${priorityColor}`}>
              {signal.priority?.toUpperCase() || 'MEDIUM'} PRIORITY
            </span>
          </div>
          <h4 className="text-lg font-bold text-gray-900 mb-2">{signal.title}</h4>
          <p className="text-sm text-gray-700 leading-relaxed">{signal.description}</p>
        </div>
        
        <div className="ml-4 text-right">
          <div className="text-xs text-gray-600 mb-1">Confidence</div>
          <div className="text-3xl font-black text-gray-900">{Math.round(signal.confidence * 100)}%</div>
          <div className="mt-2 w-20 h-2 bg-gray-200 rounded-full overflow-hidden">
            <div className={`h-full ${color.bar}`} style={{ width: `${signal.confidence * 100}%` }}></div>
          </div>
        </div>
      </div>
      
      {signal.reasons?.length > 0 && (
        <div className="mt-4 pt-4 border-t border-gray-200">
          <div className="flex items-center gap-2 text-xs font-semibold text-gray-600 mb-2">
            <Brain className="w-4 h-4" />
            Key Factors:
          </div>
          <div className="space-y-1">
            {signal.reasons.slice(0, 3).map((reason, j) => (
              <div key={j} className="flex items-start gap-2 text-xs text-gray-700">
                <span className={signal.type === 'BUY' ? 'text-green-500' : 'text-red-500'}>•</span>
                <span>{reason}</span>
              </div>
            ))}
          </div>
        </div>
      )}
      
      <div className="mt-4 pt-4 border-t border-gray-100">
        <div className="flex items-center justify-between text-xs text-gray-500">
          <div className="flex items-center gap-1">
            <Clock className="w-3 h-3" />
            <span>Generated: {new Date().toLocaleTimeString()}</span>
          </div>
          <span className={`font-semibold ${color.text}`}>
            {signal.type === 'BUY' ? 'Consider entry' : 'Consider exit'}
          </span>
        </div>
      </div>
    </div>
  );
});

// Fear & Greed Widget
const FearGreedWidget = memo(({ fearGreed }) => (
  <div className="relative bg-white rounded-3xl shadow-xl hover:shadow-2xl p-6 transition-all duration-300">
    <div className="absolute inset-0 bg-gradient-to-br from-pink-50 via-rose-50 to-red-50 rounded-3xl opacity-50"></div>
    
    <div className="relative z-10">
      <div className="flex items-center gap-3 mb-6">
        <div className="p-3 bg-gradient-to-br from-pink-500 to-rose-500 rounded-xl shadow-lg">
          <Gauge className="w-6 h-6 text-white" />
        </div>
        <div>
          <h3 className="text-2xl font-bold text-gray-900">Fear & Greed Index</h3>
          <p className="text-xs text-gray-600">Market sentiment indicator</p>
        </div>
      </div>
      
      <div className="bg-white rounded-2xl p-8 text-center shadow-md">
        <div className="text-7xl font-black mb-3 bg-gradient-to-r from-pink-600 to-rose-600 bg-clip-text text-transparent">
          {fearGreed.value}
        </div>
        <div className="text-2xl font-bold text-gray-900 mb-3">{fearGreed.classification}</div>
        <p className="text-sm text-gray-600">{fearGreed.description}</p>
      </div>
    </div>
  </div>
));

// Empty State Component
const EmptyState = ({ icon, title, text }) => (
  <div className="text-center py-8">
    <div className="text-gray-400 mb-3">{icon}</div>
    {title && <h4 className="text-lg font-bold text-gray-900 mb-2">{title}</h4>}
    <p className="text-sm text-gray-500">{text}</p>
  </div>
);
