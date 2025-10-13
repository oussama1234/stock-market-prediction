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
  ShieldCheck, ShieldAlert, Sparkles, Brain, Signal, Radio
} from 'lucide-react';

export default function AnalyticsNew() {
  const { symbol } = useParams();
  const [refreshing, setRefreshing] = useState(false);
  const [regenerating, setRegenerating] = useState(false);
  const [currentTime, setCurrentTime] = useState(new Date());
  const { data, loading, error, lastUpdate, refresh, regenerateToday } = useAnalytics(symbol);
  const chartContainerRef = useRef(null);

  // Update current time every second to keep the timer live
  useEffect(() => {
    const timer = setInterval(() => {
      setCurrentTime(new Date());
    }, 1000);
    return () => clearInterval(timer);
  }, []);

  const handleRefresh = async () => {
    setRefreshing(true);
    await refresh();
    setTimeout(() => setRefreshing(false), 500);
  };

  const handleRegenerate = async () => {
    setRegenerating(true);
    try {
      const result = await regenerateToday();
      if (!result?.success) {
        alert(result?.message || 'Failed to regenerate');
      }
    } finally {
      setRegenerating(false);
    }
  };

  const formatTime = () => {
    if (!lastUpdate) return '';
    const diff = Math.floor((currentTime - lastUpdate) / 1000);
    if (diff < 60) return `${diff}s ago`;
    const mins = Math.floor(diff / 60);
    if (mins < 60) return `${mins}m ago`;
    return `${Math.floor(mins / 60)}h ago`;
  };

  // Debug log for support/resistance data - MUST be before conditional returns
  useEffect(() => {
    if (data) {
      console.log('=== Analytics Data Debug ===');
      console.log('Full Data:', data);
      console.log('Support/Resistance:', data.support_resistance);
      console.log('Supports:', data.support_resistance?.supports);
      console.log('Resistances:', data.support_resistance?.resistances);
      console.log('Indicators:', data.indicators);
      console.log('Prediction:', data.prediction);
      console.log('========================');
    }
  }, [data]);

  // TradingView Integration
  useEffect(() => {
    if (!data || !chartContainerRef.current) return;

    // Determine correct exchange for TradingView
    const exchange = data.stock?.exchange || 'NASDAQ';
    const exchangeStr = exchange.toUpperCase();
    
    // Map common exchanges to TradingView format
    let tvExchange = 'NASDAQ'; // Default
    
    if (exchangeStr.includes('NYSE') || exchangeStr.includes('NEW YORK')) {
      tvExchange = 'NYSE';
    } else if (exchangeStr.includes('NASDAQ')) {
      tvExchange = 'NASDAQ';
    } else if (exchangeStr.includes('AMEX') || exchangeStr.includes('NYSEARCA')) {
      tvExchange = 'AMEX';
    } else if (exchangeStr.includes('OTC')) {
      tvExchange = 'OTC';
    } else if (exchangeStr.includes('BATS')) {
      tvExchange = 'BATS';
    }
    
    const tradingViewSymbol = `${tvExchange}:${symbol}`;
    
    console.log(`📈 Loading TradingView chart for ${tradingViewSymbol}`);

    const script = document.createElement('script');
    script.src = 'https://s3.tradingview.com/tv.js';
    script.async = true;
    script.onload = () => {
      if (window.TradingView) {
        chartContainerRef.current.innerHTML = '';
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
          container_id: chartContainerRef.current.id,
          studies: ['MASimple@tv-basicstudies', 'RSI@tv-basicstudies', 'MACD@tv-basicstudies', 'BB@tv-basicstudies'],
        });
      }
    };
    document.head.appendChild(script);

    return () => {
      if (chartContainerRef.current) {
        chartContainerRef.current.innerHTML = '';
      }
    };
  }, [symbol, data]);

  if (loading) {
    return (
      <StockLoader 
        symbol={symbol} 
        message="Analyzing technical indicators, support/resistance, and signals" 
      />
    );
  }

  if (error || !data) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 p-4">
        <div className="bg-white rounded-2xl shadow-2xl p-12 text-center max-w-2xl">
          <div className="text-6xl mb-4">⚠️</div>
          <h2 className="text-3xl font-bold text-gray-900 mb-4">Error Loading Analytics</h2>
          <p className="text-gray-600 mb-6">{error || `Unable to load ${symbol}`}</p>
          <div className="flex gap-4 justify-center">
            <button onClick={handleRefresh} className="px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all hover:scale-105">
              🔄 Try Again
            </button>
            <Link to={`/stock/${symbol}`} className="px-6 py-3 bg-gray-100 text-gray-800 rounded-lg font-semibold hover:bg-gray-200 transition-all hover:scale-105">
              ← Back to Stock
            </Link>
          </div>
        </div>
      </div>
    );
  }

  const { stock, quote, prediction, override, support_resistance, indicators, fear_greed, alerts } = data;
  const currentPrice = quote?.current_price || 0;
  const change = quote?.change || 0;
  const changePct = quote?.change_percent || 0;
  const isPositive = change >= 0;
  const isBullish = prediction?.direction === 'up';
  const predLabel = prediction?.label || (isBullish ? 'BULLISH' : 'BEARISH');
  const confidence = Math.round((prediction?.probability || 0.5) * 100);
  
  // Calculate prediction metrics
  const predictedPrice = prediction?.predicted_price || currentPrice;
  const priceDiff = predictedPrice - currentPrice;
  const priceChangePercent = currentPrice > 0 ? ((priceDiff / currentPrice) * 100) : 0;
  const upProbability = prediction?.probability || 0.5;
  const downProbability = 1 - upProbability;
  
  // Risk level calculation
  const getRiskLevel = () => {
    if (confidence >= 80) return { label: 'Low Risk', color: 'text-green-600', bg: 'bg-green-100' };
    if (confidence >= 60) return { label: 'Medium Risk', color: 'text-yellow-600', bg: 'bg-yellow-100' };
    return { label: 'High Risk', color: 'text-red-600', bg: 'bg-red-100' };
  };
  const riskLevel = getRiskLevel();

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 transition-colors duration-300">
      <div className="container mx-auto px-4 py-8">
        {/* Navigation */}
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
              onClick={handleRefresh} 
              disabled={refreshing} 
              className="group inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold rounded-xl hover:from-indigo-700 hover:to-purple-700 transition-all hover:scale-105 disabled:opacity-50 shadow-lg hover:shadow-xl"
            >
              <RefreshCw className={`w-5 h-5 ${refreshing ? 'animate-spin' : 'group-hover:rotate-180'} transition-transform duration-500`} />
              {refreshing ? 'Refreshing...' : 'Refresh'}
            </button>
          </div>
        </div>

        {/* Beautiful Header - Modern Design */}
        <div className="group relative bg-white rounded-3xl shadow-2xl hover:shadow-3xl p-8 mb-6 overflow-hidden transition-all duration-500">
          {/* Animated gradient background */}
          <div className="absolute inset-0 bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 opacity-70"></div>
          <div className="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
            <div className="absolute top-0 left-0 w-96 h-96 bg-indigo-200 rounded-full blur-3xl animate-pulse"></div>
            <div className="absolute bottom-0 right-0 w-96 h-96 bg-purple-200 rounded-full blur-3xl animate-pulse"></div>
          </div>
          
          <div className="relative z-10">
            <div className="flex items-start justify-between mb-6">
              {/* Left side - Stock info */}
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
                    <div className="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-100 text-indigo-700 rounded-full backdrop-blur-sm shadow-sm hover:shadow-md transition-shadow">
                      <LineChart className="w-3.5 h-3.5" />
                      <span className="text-xs font-semibold">Technical Analysis</span>
                    </div>
                    <div className="flex items-center gap-1.5 px-3 py-1.5 bg-purple-100 text-purple-700 rounded-full backdrop-blur-sm shadow-sm hover:shadow-md transition-shadow">
                      <Zap className="w-3.5 h-3.5" />
                      <span className="text-xs font-semibold">Live Signals</span>
                    </div>
                    <div className="flex items-center gap-1.5 px-3 py-1.5 bg-pink-100 text-pink-700 rounded-full backdrop-blur-sm shadow-sm hover:shadow-md transition-shadow">
                      <Target className="w-3.5 h-3.5" />
                      <span className="text-xs font-semibold">S/R Levels</span>
                    </div>
                    <div className="flex items-center gap-1.5 px-3 py-1.5 bg-green-100 text-green-700 rounded-full backdrop-blur-sm shadow-sm hover:shadow-md transition-shadow animate-pulse">
                      <Radio className="w-3.5 h-3.5" />
                      <span className="text-xs font-semibold">Live</span>
                    </div>
                  </div>
                </div>
              </div>
              
              {/* Right side - Price info */}
              <div className="text-right">
                <div className="text-6xl font-black text-gray-900 mb-3 tracking-tight">
                  ${currentPrice.toFixed(2)}
                </div>
                <div className={`inline-flex items-center gap-2 px-5 py-3 rounded-2xl text-xl font-bold shadow-lg backdrop-blur-md transition-all hover:scale-105 ${
                  isPositive 
                    ? 'bg-gradient-to-r from-green-500 to-emerald-500 text-white' 
                    : 'bg-gradient-to-r from-red-500 to-rose-500 text-white'
                }`}>
                  {isPositive ? (
                    <TrendingUp className="w-6 h-6" />
                  ) : (
                    <TrendingDown className="w-6 h-6" />
                  )}
                  <span>{isPositive ? '+' : ''}{changePct.toFixed(2)}%</span>
                </div>
                <div className="mt-2 text-sm text-gray-600 font-medium">
                  {isPositive ? '+' : ''}{change.toFixed(2)} today
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Prediction Card - PredictionCardV2 */}
        <div className="mb-6">
          <PredictionCardV2Enhanced symbol={symbol} horizon="today" />
        </div>

        {/* TradingView Chart - Modern Design */}
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

        {/* Technical Indicators & Support/Resistance Grid */}
        <div className="grid lg:grid-cols-2 gap-6 mb-6">
          {/* Technical Indicators - Modern Redesign */}
          <div className="relative bg-white rounded-3xl shadow-xl hover:shadow-2xl p-6 transition-all duration-300">
            {/* Gradient overlay */}
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
                {/* RSI - Modern Card */}
                {indicators?.rsi && (
                  <div className="group relative bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-100 hover:border-purple-200">
                    <div className="flex items-center justify-between mb-4">
                      <div className="flex items-center gap-3">
                        <div className={`p-2.5 rounded-xl ${
                          indicators.rsi > 70 ? 'bg-red-100' : 
                          indicators.rsi < 30 ? 'bg-green-100' : 
                          'bg-blue-100'
                        }`}>
                          <Gauge className={`w-5 h-5 ${
                            indicators.rsi > 70 ? 'text-red-600' : 
                            indicators.rsi < 30 ? 'text-green-600' : 
                            'text-blue-600'
                          }`} />
                        </div>
                        <div>
                          <div className="text-sm font-bold text-gray-700">RSI (14)</div>
                          <span className={`inline-block px-2.5 py-1 text-xs font-bold rounded-full ${
                            indicators.rsi > 70 ? 'bg-red-100 text-red-700' : 
                            indicators.rsi < 30 ? 'bg-green-100 text-green-700' : 
                            'bg-blue-100 text-blue-700'
                          }`}>
                            {indicators.rsi > 70 ? 'OVERBOUGHT' : indicators.rsi < 30 ? 'OVERSOLD' : 'NEUTRAL'}
                          </span>
                        </div>
                      </div>
                      <div className="text-4xl font-black text-gray-900">{indicators.rsi.toFixed(1)}</div>
                    </div>
                  
                  {/* RSI Bar with zones */}
                  <div className="relative">
                    <div className="h-4 bg-gradient-to-r from-green-200 via-blue-200 to-red-200 rounded-full overflow-hidden">
                      <div 
                        className={`h-full ${
                          indicators.rsi > 70 ? 'bg-red-600' : 
                          indicators.rsi < 30 ? 'bg-green-600' : 
                          'bg-blue-600'
                        } transition-all duration-500 shadow-lg`} 
                        style={{ width: `${indicators.rsi}%` }}
                      ></div>
                    </div>
                    {/* Zone markers */}
                    <div className="flex justify-between text-xs text-gray-500 mt-1 px-1">
                      <span>0</span>
                      <span className="text-green-600 font-semibold">30</span>
                      <span>50</span>
                      <span className="text-red-600 font-semibold">70</span>
                      <span>100</span>
                    </div>
                  </div>
                  
                  {/* Signal strength */}
                  <div className="mt-3 pt-3 border-t border-gray-200">
                    <div className="flex items-center justify-between text-xs">
                      <span className="text-gray-600">Signal Strength:</span>
                      <div className="flex gap-1">
                        {[1, 2, 3, 4, 5].map(i => (
                          <div 
                            key={i} 
                            className={`w-2 h-4 rounded-sm ${
                              (indicators.rsi > 70 || indicators.rsi < 30) && i <= Math.abs(indicators.rsi > 70 ? indicators.rsi - 70 : 30 - indicators.rsi) / 6
                                ? indicators.rsi > 70 ? 'bg-red-500' : 'bg-green-500'
                                : 'bg-gray-300'
                            }`}
                          ></div>
                        ))}
                      </div>
                    </div>
                  </div>
                </div>
              )}
              
              {/* MACD - Enhanced */}
              {indicators?.macd && (
                <div className="bg-white rounded-xl p-5 shadow-md hover:shadow-lg transition-shadow">
                  <div className="flex items-center justify-between mb-3">
                    <div className="flex items-center gap-2">
                      <span className="text-sm font-bold text-gray-700">📉 MACD</span>
                      <span className={`px-2 py-1 text-xs font-bold rounded-full ${
                        indicators.macd.signal === 'bullish' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                      }`}>
                        {indicators.macd.signal?.toUpperCase() || 'NEUTRAL'}
                      </span>
                    </div>
                    <div className="flex items-center gap-2">
                      <span className={`text-3xl ${indicators.macd.signal === 'bullish' ? 'text-green-600' : 'text-red-600'}`}>
                        {indicators.macd.signal === 'bullish' ? '▲' : '▼'}
                      </span>
                      <span className="text-2xl font-black text-gray-900">{indicators.macd.value.toFixed(3)}</span>
                    </div>
                  </div>
                  
                  {/* MACD histogram visualization */}
                  <div className="grid grid-cols-2 gap-3 mt-3">
                    <div className="text-center p-3 bg-gray-50 rounded-lg">
                      <div className="text-xs text-gray-600 mb-1">MACD Line</div>
                      <div className="text-lg font-bold text-gray-900">{indicators.macd.value.toFixed(3)}</div>
                    </div>
                    {indicators.macd.signal_line !== undefined && (
                      <div className="text-center p-3 bg-gray-50 rounded-lg">
                        <div className="text-xs text-gray-600 mb-1">Signal Line</div>
                        <div className="text-lg font-bold text-gray-900">{indicators.macd.signal_line.toFixed(3)}</div>
                      </div>
                    )}
                  </div>
                  
                  {/* Momentum indicator */}
                  <div className="mt-3 pt-3 border-t border-gray-200">
                    <div className="text-xs text-gray-600 mb-2">Momentum:</div>
                    <div className="flex gap-1">
                      {[...Array(10)].map((_, i) => (
                        <div 
                          key={i} 
                          className={`flex-1 h-3 rounded ${
                            indicators.macd.signal === 'bullish' 
                              ? i < 7 ? 'bg-green-500' : 'bg-gray-300'
                              : i < 3 ? 'bg-red-500' : 'bg-gray-300'
                          }`}
                        ></div>
                      ))}
                    </div>
                  </div>
                </div>
              )}
              
              {/* Moving Averages - Enhanced */}
              {indicators?.ema_20 && (
                <div className="bg-white rounded-xl p-5 shadow-md hover:shadow-lg transition-shadow">
                  <div className="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                    📈 Moving Averages
                    <span className={`px-2 py-1 text-xs font-bold rounded-full ${
                      currentPrice > indicators.ema_20 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                    }`}>
                      {currentPrice > indicators.ema_20 ? 'ABOVE EMA' : 'BELOW EMA'}
                    </span>
                  </div>
                  <div className="space-y-3">
                    {/* EMA 20 */}
                    <div className="flex justify-between items-center p-3 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg">
                      <div>
                        <div className="text-xs text-gray-600 font-semibold">EMA 20</div>
                        <div className="text-xl font-black text-blue-600">${indicators.ema_20.toFixed(2)}</div>
                      </div>
                      <div className="text-right">
                        <div className="text-xs text-gray-600">Distance</div>
                        <div className={`text-sm font-bold ${
                          currentPrice > indicators.ema_20 ? 'text-green-600' : 'text-red-600'
                        }`}>
                          {currentPrice > indicators.ema_20 ? '+' : ''}
                          {(((currentPrice - indicators.ema_20) / indicators.ema_20) * 100).toFixed(2)}%
                        </div>
                      </div>
                    </div>
                    
                    {/* EMA 50 */}
                    {indicators.ema_50 && (
                      <div className="flex justify-between items-center p-3 bg-gradient-to-r from-purple-50 to-pink-50 rounded-lg">
                        <div>
                          <div className="text-xs text-gray-600 font-semibold">EMA 50</div>
                          <div className="text-xl font-black text-purple-600">${indicators.ema_50.toFixed(2)}</div>
                        </div>
                        <div className="text-right">
                          <div className="text-xs text-gray-600">Distance</div>
                          <div className={`text-sm font-bold ${
                            currentPrice > indicators.ema_50 ? 'text-green-600' : 'text-red-600'
                          }`}>
                            {currentPrice > indicators.ema_50 ? '+' : ''}
                            {(((currentPrice - indicators.ema_50) / indicators.ema_50) * 100).toFixed(2)}%
                          </div>
                        </div>
                      </div>
                    )}
                  </div>
                  
                  {/* Trend indicator */}
                  {indicators.ema_50 && (
                    <div className="mt-3 pt-3 border-t border-gray-200">
                      <div className="flex items-center justify-between">
                        <span className="text-xs text-gray-600">Trend:</span>
                        <span className={`px-3 py-1 text-xs font-bold rounded-full ${
                          indicators.ema_20 > indicators.ema_50 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                        }`}>
                          {indicators.ema_20 > indicators.ema_50 ? '▲ BULLISH CROSSOVER' : '▼ BEARISH CROSSOVER'}
                        </span>
                      </div>
                    </div>
                  )}
                </div>
              )}
              
              {/* Bollinger Bands if available */}
              {indicators?.bollinger_bands && (
                <div className="bg-white rounded-xl p-5 shadow-md hover:shadow-lg transition-shadow">
                  <div className="text-sm font-bold text-gray-700 mb-3">🎯 Bollinger Bands</div>
                  <div className="space-y-2">
                    <div className="flex justify-between items-center text-sm">
                      <span className="text-gray-600">Upper Band:</span>
                      <span className="font-bold text-red-600">${indicators.bollinger_bands.upper.toFixed(2)}</span>
                    </div>
                    <div className="flex justify-between items-center text-sm">
                      <span className="text-gray-600">Middle Band:</span>
                      <span className="font-bold text-blue-600">${indicators.bollinger_bands.middle.toFixed(2)}</span>
                    </div>
                    <div className="flex justify-between items-center text-sm">
                      <span className="text-gray-600">Lower Band:</span>
                      <span className="font-bold text-green-600">${indicators.bollinger_bands.lower.toFixed(2)}</span>
                    </div>
                  </div>
                </div>
              )}
              
              {/* Volume if available */}
              {indicators?.volume_trend && (
                <div className="bg-white rounded-xl p-5 shadow-md hover:shadow-lg transition-shadow">
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-bold text-gray-700">📊 Volume Trend</span>
                    <span className={`px-3 py-1 text-xs font-bold rounded-full ${
                      indicators.volume_trend === 'increasing' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                    }`}>
                      {indicators.volume_trend?.toUpperCase() || 'NORMAL'}
                    </span>
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Support & Resistance */}
          <div className="bg-gradient-to-br from-green-50 to-emerald-100 rounded-2xl shadow-xl p-6">
            <h3 className="text-2xl font-bold text-gray-900 mb-4">🎯 Support & Resistance</h3>
            <div className="space-y-4">
              <div className="bg-white rounded-xl p-4">
                <div className="text-sm font-semibold text-green-700 mb-3">🟢 Support Levels</div>
                {support_resistance?.supports && support_resistance.supports.length > 0 ? (
                  <div className="space-y-2">
                    {support_resistance.supports.slice(0, 3).map((s, i) => (
                      <div key={i} className="flex justify-between items-center p-2 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                        <div>
                          <div className="text-gray-900 font-bold">${(s.price || s).toFixed(2)}</div>
                          <div className="text-xs text-gray-500">Level {i + 1}</div>
                        </div>
                        <div className="text-right">
                          <div className="text-xs font-semibold text-green-700">
                            {s.distance_percent ? `${s.distance_percent.toFixed(2)}% below` : 'Support'}
                          </div>
                          {s.strength && (
                            <div className="text-xs text-gray-500">Strength: {s.strength}</div>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="text-center py-4">
                    <div className="text-3xl mb-2">🔍</div>
                    <p className="text-sm text-gray-500">No support levels detected</p>
                    <p className="text-xs text-gray-400 mt-1">Support levels will appear when technical analysis identifies them</p>
                  </div>
                )}
              </div>
              <div className="bg-white rounded-xl p-4">
                <div className="text-sm font-semibold text-red-700 mb-3">🔴 Resistance Levels</div>
                {support_resistance?.resistances && support_resistance.resistances.length > 0 ? (
                  <div className="space-y-2">
                    {support_resistance.resistances.slice(0, 3).map((r, i) => (
                      <div key={i} className="flex justify-between items-center p-2 bg-red-50 rounded-lg hover:bg-red-100 transition-colors">
                        <div>
                          <div className="text-gray-900 font-bold">${(r.price || r).toFixed(2)}</div>
                          <div className="text-xs text-gray-500">Level {i + 1}</div>
                        </div>
                        <div className="text-right">
                          <div className="text-xs font-semibold text-red-700">
                            {r.distance_percent ? `${r.distance_percent.toFixed(2)}% above` : 'Resistance'}
                          </div>
                          {r.strength && (
                            <div className="text-xs text-gray-500">Strength: {r.strength}</div>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="text-center py-4">
                    <div className="text-3xl mb-2">🔍</div>
                    <p className="text-sm text-gray-500">No resistance levels detected</p>
                    <p className="text-xs text-gray-400 mt-1">Resistance levels will appear when technical analysis identifies them</p>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Buy/Sell Alerts Section - Enhanced */}
        <div className="bg-gradient-to-br from-yellow-50 via-orange-50 to-amber-100 rounded-2xl shadow-xl p-6 mb-6">
          <div className="flex items-center justify-between mb-6">
            <h3 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
              ⚡ Trading Signals & Alerts
              <span className="text-xs font-normal text-gray-600 bg-white px-3 py-1 rounded-full">AI-Powered</span>
            </h3>
            <div className="flex items-center gap-2">
              <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
              <span className="text-xs text-gray-600 font-semibold">Live</span>
            </div>
          </div>
          
          <div className="space-y-4">
            {/* Generate intelligent signals from technical indicators */}
            {(() => {
              const signals = [];
              
              // RSI-based signals
              if (indicators?.rsi) {
                if (indicators.rsi < 30) {
                  signals.push({
                    type: 'BUY',
                    title: '🟢 Strong Buy Signal - RSI Oversold',
                    description: `RSI is at ${indicators.rsi.toFixed(1)}, indicating oversold conditions. Historical data suggests potential reversal.`,
                    confidence: Math.min(0.9, 0.6 + (30 - indicators.rsi) / 50),
                    reasons: [
                      `RSI below 30 (${indicators.rsi.toFixed(1)})`,
                      'Oversold momentum suggests buying opportunity',
                      'Price may rebound from current levels'
                    ],
                    priority: 'high'
                  });
                } else if (indicators.rsi > 70) {
                  signals.push({
                    type: 'SELL',
                    title: '🔴 Caution - RSI Overbought',
                    description: `RSI is at ${indicators.rsi.toFixed(1)}, indicating overbought conditions. Consider taking profits or reducing position.`,
                    confidence: Math.min(0.85, 0.55 + (indicators.rsi - 70) / 50),
                    reasons: [
                      `RSI above 70 (${indicators.rsi.toFixed(1)})`,
                      'Overbought conditions may lead to correction',
                      'Consider profit-taking strategy'
                    ],
                    priority: 'high'
                  });
                }
              }
              
              // MACD-based signals
              if (indicators?.macd) {
                if (indicators.macd.signal === 'bullish' && indicators.macd.value > 0) {
                  signals.push({
                    type: 'BUY',
                    title: '📈 MACD Bullish Crossover',
                    description: 'MACD line crossed above signal line, indicating positive momentum shift.',
                    confidence: 0.75,
                    reasons: [
                      'MACD bullish crossover detected',
                      'Positive momentum building',
                      'Trend reversal potential'
                    ],
                    priority: 'medium'
                  });
                } else if (indicators.macd.signal === 'bearish' && indicators.macd.value < 0) {
                  signals.push({
                    type: 'SELL',
                    title: '📉 MACD Bearish Crossover',
                    description: 'MACD line crossed below signal line, indicating negative momentum.',
                    confidence: 0.72,
                    reasons: [
                      'MACD bearish crossover detected',
                      'Negative momentum building',
                      'Consider protective measures'
                    ],
                    priority: 'medium'
                  });
                }
              }
              
              // Moving Average signals
              if (indicators?.ema_20 && indicators?.ema_50) {
                if (indicators.ema_20 > indicators.ema_50 && currentPrice > indicators.ema_20) {
                  signals.push({
                    type: 'BUY',
                    title: '🎯 Golden Cross Pattern',
                    description: 'Price above both EMAs with EMA 20 > EMA 50. Strong bullish trend confirmed.',
                    confidence: 0.82,
                    reasons: [
                      'Price trading above key moving averages',
                      'EMA 20 above EMA 50 (bullish crossover)',
                      'Uptrend confirmation signal'
                    ],
                    priority: 'high'
                  });
                } else if (indicators.ema_20 < indicators.ema_50 && currentPrice < indicators.ema_20) {
                  signals.push({
                    type: 'SELL',
                    title: '⚠️ Death Cross Warning',
                    description: 'Price below both EMAs with EMA 20 < EMA 50. Bearish trend confirmed.',
                    confidence: 0.78,
                    reasons: [
                      'Price trading below key moving averages',
                      'EMA 20 below EMA 50 (bearish crossover)',
                      'Downtrend confirmation signal'
                    ],
                    priority: 'high'
                  });
                }
              }
              
              // Support/Resistance signals
              if (support_resistance?.supports && support_resistance.supports.length > 0) {
                const nearestSupport = support_resistance.supports[0];
                const supportPrice = nearestSupport.price || nearestSupport;
                const distanceToSupport = Math.abs(((currentPrice - supportPrice) / supportPrice) * 100);
                
                if (distanceToSupport < 2) {
                  signals.push({
                    type: 'BUY',
                    title: '🛡️ Near Support Level',
                    description: `Price is within ${distanceToSupport.toFixed(1)}% of strong support at $${supportPrice.toFixed(2)}. Potential bounce opportunity.`,
                    confidence: 0.7,
                    reasons: [
                      `Support level at $${supportPrice.toFixed(2)}`,
                      'Price near historical support zone',
                      'Risk/reward ratio favorable for entry'
                    ],
                    priority: 'medium'
                  });
                }
              }
              
              if (support_resistance?.resistances && support_resistance.resistances.length > 0) {
                const nearestResistance = support_resistance.resistances[0];
                const resistancePrice = nearestResistance.price || nearestResistance;
                const distanceToResistance = Math.abs(((currentPrice - resistancePrice) / resistancePrice) * 100);
                
                if (distanceToResistance < 2) {
                  signals.push({
                    type: 'SELL',
                    title: '🚫 Near Resistance Level',
                    description: `Price is within ${distanceToResistance.toFixed(1)}% of resistance at $${resistancePrice.toFixed(2)}. Consider profit-taking.`,
                    confidence: 0.68,
                    reasons: [
                      `Resistance level at $${resistancePrice.toFixed(2)}`,
                      'Price approaching historical resistance',
                      'Probability of pullback increases'
                    ],
                    priority: 'medium'
                  });
                }
              }
              
              // Prediction-based signal
              if (prediction) {
                if (isBullish && confidence > 70) {
                  signals.push({
                    type: 'BUY',
                    title: '🤖 AI Prediction: Strong Bullish',
                    description: `AI model predicts ${priceChangePercent.toFixed(2)}% upside with ${confidence}% confidence.`,
                    confidence: confidence / 100,
                    reasons: [
                      `${confidence}% confidence in bullish direction`,
                      `Target price: $${predictedPrice.toFixed(2)}`,
                      `Expected gain: ${priceChangePercent.toFixed(2)}%`
                    ],
                    priority: 'high'
                  });
                } else if (!isBullish && confidence > 70) {
                  signals.push({
                    type: 'SELL',
                    title: '🤖 AI Prediction: Strong Bearish',
                    description: `AI model predicts ${Math.abs(priceChangePercent).toFixed(2)}% downside with ${confidence}% confidence.`,
                    confidence: confidence / 100,
                    reasons: [
                      `${confidence}% confidence in bearish direction`,
                      `Target price: $${predictedPrice.toFixed(2)}`,
                      `Expected decline: ${Math.abs(priceChangePercent).toFixed(2)}%`
                    ],
                    priority: 'high'
                  });
                }
              }
              
              // Add backend alerts if available
              if (alerts && alerts.length > 0) {
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
              
              // Display signals
              if (signals.length === 0) {
                return (
                  <div className="bg-white rounded-xl p-8 text-center">
                    <div className="text-5xl mb-3">📊</div>
                    <h4 className="text-lg font-bold text-gray-900 mb-2">No Active Signals</h4>
                    <p className="text-sm text-gray-600">All indicators are neutral. Market is in equilibrium.</p>
                    <p className="text-xs text-gray-500 mt-2">Signals will appear when technical conditions warrant action.</p>
                  </div>
                );
              }
              
              return signals.slice(0, 5).map((signal, i) => (
                <div 
                  key={i} 
                  className={`bg-white rounded-xl p-5 border-l-4 shadow-md hover:shadow-lg transition-all ${
                    signal.type === 'BUY' 
                      ? 'border-green-500 hover:border-green-600' 
                      : signal.type === 'SELL' 
                      ? 'border-red-500 hover:border-red-600' 
                      : 'border-yellow-500 hover:border-yellow-600'
                  }`}
                >
                  {/* Header */}
                  <div className="flex items-start justify-between mb-3">
                    <div className="flex-1">
                      <div className="flex items-center gap-2 mb-1">
                        <span className={`px-3 py-1 text-xs font-black rounded-full ${
                          signal.type === 'BUY' 
                            ? 'bg-green-100 text-green-700' 
                            : signal.type === 'SELL' 
                            ? 'bg-red-100 text-red-700' 
                            : 'bg-yellow-100 text-yellow-700'
                        }`}>
                          {signal.type}
                        </span>
                        <span className={`px-2 py-1 text-xs font-bold rounded-full ${
                          signal.priority === 'high' 
                            ? 'bg-orange-100 text-orange-700' 
                            : 'bg-blue-100 text-blue-700'
                        }`}>
                          {signal.priority?.toUpperCase() || 'MEDIUM'} PRIORITY
                        </span>
                      </div>
                      <h4 className="text-lg font-bold text-gray-900 mb-2">{signal.title}</h4>
                      <p className="text-sm text-gray-700 leading-relaxed">{signal.description}</p>
                    </div>
                    
                    {/* Confidence meter */}
                    <div className="ml-4 text-right">
                      <div className="text-xs text-gray-600 mb-1">Confidence</div>
                      <div className="text-3xl font-black text-gray-900">{Math.round(signal.confidence * 100)}%</div>
                      <div className="mt-2 w-20 h-2 bg-gray-200 rounded-full overflow-hidden">
                        <div 
                          className={`h-full ${
                            signal.type === 'BUY' ? 'bg-green-500' : 'bg-red-500'
                          }`} 
                          style={{ width: `${signal.confidence * 100}%` }}
                        ></div>
                      </div>
                    </div>
                  </div>
                  
                  {/* Reasons */}
                  {signal.reasons && signal.reasons.length > 0 && (
                    <div className="mt-4 pt-4 border-t border-gray-200">
                      <div className="text-xs font-semibold text-gray-600 mb-2">💡 Key Factors:</div>
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
                  
                  {/* Action timestamp */}
                  <div className="mt-3 pt-3 border-t border-gray-100">
                    <div className="flex items-center justify-between text-xs text-gray-500">
                      <span>🕒 Generated: {new Date().toLocaleTimeString()}</span>
                      <span className={`font-semibold ${
                        signal.type === 'BUY' ? 'text-green-600' : 'text-red-600'
                      }`}>
                        {signal.type === 'BUY' ? 'Consider entry' : 'Consider exit'}
                      </span>
                    </div>
                  </div>
                </div>
              ));
            })()}
          </div>
        </div>

        {/* Fear & Greed Widget */}
        {fear_greed && (
          <div className="bg-gradient-to-br from-pink-50 to-rose-100 rounded-2xl shadow-xl p-6">
            <h3 className="text-2xl font-bold text-gray-900 mb-4">😱 Fear & Greed Index</h3>
            <div className="bg-white rounded-xl p-6 text-center">
              <div className="text-7xl font-black mb-2">{fear_greed.value}</div>
              <div className="text-2xl font-bold text-gray-900 mb-2">{fear_greed.classification}</div>
              <p className="text-sm text-gray-600">{fear_greed.description}</p>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
