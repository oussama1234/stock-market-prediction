# 📈 AI-Powered Stock Market Prediction Platform for #educational purposes, its not a financial advice#

An advanced stock market prediction system that combines machine learning, real-time data analysis, and sentiment analysis to provide intelligent trading insights.

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![Laravel](https://img.shields.io/badge/Laravel-11.x-red.svg)
![React](https://img.shields.io/badge/React-18.x-blue.svg)
![Python](https://img.shields.io/badge/Python-3.11-green.svg)

## 🌟 Features

### Core Functionality
- **🤖 AI-Powered Predictions**: Machine learning models trained on historical data, technical indicators, and market sentiment
- **📊 Real-Time Market Data**: Live stock quotes, prices, and market indices from multiple data sources
- **📰 News Sentiment Analysis**: Automatic analysis of news articles to detect bullish/bearish sentiment
- **🌍 Global Market Integration**: European and Asian market influences on US stocks
- **📈 Technical Analysis**: RSI, MACD, Bollinger Bands, Volume analysis, and more
- **🎯 Multi-Horizon Predictions**: Today, 3-day, and 7-day forecasts
- **🔥 Trending Stocks**: Real-time tracking of biggest market movers

### Advanced Features
- **Auto-Regenerating Predictions**: Automatically updates predictions when significant news is detected
- **Keyword Detection**: Identifies important keywords like "earnings", "tariff", "partnership", "surge"
- **Fear & Greed Index**: Market sentiment indicator integration
- **Stock Comparison**: Compare multiple stocks side-by-side
- **Price Alerts**: Set custom price alerts for stocks
- **Watchlist**: Track your favorite stocks
- **Dark Mode**: Beautiful dark theme support

## 🏗️ Architecture

### Backend (Laravel 11)
- **API**: RESTful API with comprehensive endpoints
- **Database**: MySQL 8.0 with optimized queries
- **Cache**: Redis for high-performance caching
- **Queue System**: Laravel queues for background jobs
- **Python Integration**: Machine learning models via Python scripts

### Frontend (React + Vite)
- **Modern UI**: Tailwind CSS with glassmorphism effects
- **Icons**: Lucide React for consistent iconography
- **State Management**: React hooks and context
- **Routing**: React Router v6
- **API Client**: Axios with interceptors

### Python ML Models
- **Quick Model V4**: Fast predictions using technical indicators
- **Feature Engineering**: 40+ technical and sentiment features
- **Sentiment Analysis**: NLP-based news sentiment scoring
- **Market Influence**: Global market correlation analysis

## 📋 Prerequisites

- **Docker Desktop** (Windows/Mac) or Docker Engine (Linux)
- **Node.js** 18.x or higher
- **npm** or **yarn**
- **Git**

## 🚀 Quick Start

### 1. Clone the Repository

```bash
git clone https://github.com/yourusername/stock-market-prediction.git
cd stock-market-prediction
```

### 2. Environment Setup

#### Backend Environment
```bash
# Copy environment file
cp backend/.env.example backend/.env

# Update the following in backend/.env:
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=market_prediction
DB_USERNAME=market_user
DB_PASSWORD=your_secure_password

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# API Keys (required)
FINNHUB_API_KEY=your_finnhub_api_key
ALPHA_VANTAGE_API_KEY=your_alpha_vantage_key
NEWS_API_KEY=your_newsapi_key
```

#### Frontend Environment
```bash
# Copy environment file
cp frontend/.env.example frontend/.env

# Update the following in frontend/.env:
VITE_API_URL=http://localhost:8000/api
VITE_APP_NAME="Smart Trading AI"
```

### 3. Start Docker Services

```bash
# Start all services
docker-compose up -d

# Check services are running
docker ps
```

### 4. Install Dependencies & Setup Database

```bash
# Backend dependencies (if needed)
docker exec market-prediction-php-fpm composer install

# Run database migrations
docker exec market-prediction-php-fpm php artisan migrate --seed

# Frontend dependencies
cd frontend
npm install
```

### 5. Start Frontend Development Server

```bash
cd frontend
npm run dev
```

## 🌐 Access URLs

### Main Applications
| Service | URL | Description |
|---------|-----|-------------|
| **Frontend (Vite)** | http://localhost:5173 | React application |
| **Backend API** | http://localhost:8000/api | Laravel API endpoints |
| **API Documentation** | http://localhost:8000/api/docs | API documentation (if configured) |

### Development Tools
| Tool | URL | Credentials |
|------|-----|-------------|
| **phpMyAdmin** | http://localhost:8080 | Server: `mysql`<br>Username: `market_user`<br>Password: (from .env) |
| **Redis Commander** | http://localhost:8081 | Redis GUI |

### Docker Containers
```bash
# View running containers
docker ps

# Container names:
# - market-prediction-nginx (Web server)
# - market-prediction-php-fpm (Laravel)
# - market-prediction-mysql (Database)
# - market-prediction-redis (Cache)
# - market-prediction-phpmyadmin
# - market-prediction-redis-commander
# - market-prediction-queue-worker (Background jobs)
# - market-prediction-scheduler (Cron jobs)
# - market-prediction-frontend (React)
```

## 🔧 Development Workflow

### Backend Commands

```bash
# Access PHP-FPM container
docker exec -it market-prediction-php-fpm bash

# Run Laravel commands
docker exec market-prediction-php-fpm php artisan [command]

# Common commands:
docker exec market-prediction-php-fpm php artisan cache:clear
docker exec market-prediction-php-fpm php artisan config:clear
docker exec market-prediction-php-fpm php artisan route:list
docker exec market-prediction-php-fpm php artisan queue:work

# Run migrations
docker exec market-prediction-php-fpm php artisan migrate

# Seed database with test data
docker exec market-prediction-php-fpm php artisan db:seed

# View logs
docker logs market-prediction-php-fpm --tail 100 -f
docker logs market-prediction-queue-worker --tail 100 -f
```

### Frontend Commands

```bash
cd frontend

# Development server
npm run dev

# Build for production
npm run build

# Preview production build
npm run preview

# Lint code
npm run lint
```

### Python Model Commands

```bash
# Test prediction model
docker exec market-prediction-php-fpm python /var/www/html/python/models/quick_model_v4.py predict --features '{...}'

# Train model (if training is set up)
docker exec market-prediction-php-fpm python /var/www/html/python/train_model.py
```

## 📊 API Endpoints

### Stocks
- `GET /api/stocks/search?q={query}` - Search stocks
- `GET /api/stocks/popular` - Get popular stocks
- `GET /api/stocks/{symbol}` - Get stock details
- `GET /api/stocks/{symbol}/quote` - Get current quote
- `DELETE /api/stocks/{symbol}` - Delete stock data

### Predictions
- `GET /api/predictions/{symbol}` - Get prediction
- `POST /api/predictions/{symbol}/regenerate` - Regenerate prediction
- `GET /api/predictions/{symbol}/scenarios` - Get scenarios

### News
- `GET /api/news/market` - Get market news
- `GET /api/news/stock/{symbol}` - Get stock news
- `GET /api/news/market-advanced` - Get advanced market news

### Market Data
- `GET /api/market/indices` - Get market indices (S&P 500, NASDAQ, DOW)
- `GET /api/market/fear-greed` - Get Fear & Greed Index
- `GET /api/market/asian` - Get Asian market data
- `GET /api/market/european` - Get European market data

## 🗄️ Database Structure

### Key Tables
- **stocks** - Stock information (symbol, name, sector, etc.)
- **stock_prices** - Historical price data
- **predictions** - ML predictions
- **news_articles** - News articles with sentiment scores
- **market_indices** - Market index data
- **market_scenarios** - Prediction scenarios

## 🔄 Background Jobs

The application uses Laravel queues for background processing:

- **FetchNewsArticlesJob** - Fetch news articles
- **AnalyzeNewsSentimentJob** - Analyze news sentiment
- **UpdateStockPricesJob** - Update stock prices
- **GeneratePredictionsJob** - Generate predictions
- **DetectReboundAndRegenerateJob** - Detect price rebounds

### Queue Management

```bash
# Process queue
docker exec market-prediction-php-fpm php artisan queue:work

# View failed jobs
docker exec market-prediction-php-fpm php artisan queue:failed

# Retry failed job
docker exec market-prediction-php-fpm php artisan queue:retry {job-id}
```

## 🧪 Testing

```bash
# Backend tests
docker exec market-prediction-php-fpm php artisan test

# Frontend tests
cd frontend
npm run test
```

## 📦 Deployment

### Production Build

```bash
# Build frontend
cd frontend
npm run build

# Optimize backend
docker exec market-prediction-php-fpm php artisan config:cache
docker exec market-prediction-php-fpm php artisan route:cache
docker exec market-prediction-php-fpm php artisan view:cache
```

### Environment Variables (Production)

Update these in production:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://yourdomain.com`
- Strong `DB_PASSWORD`
- Secure `REDIS_PASSWORD`
- Valid API keys

## 🛠️ Troubleshooting

### Common Issues

**Port Already in Use**
```bash
# Check what's using the port
netstat -ano | findstr :8000  # Windows
lsof -i :8000  # Mac/Linux

# Stop Docker and restart
docker-compose down
docker-compose up -d
```

**Database Connection Error**
```bash
# Check MySQL is running
docker exec market-prediction-mysql mysql -u market_user -p -e "SHOW DATABASES;"

# Restart MySQL
docker restart market-prediction-mysql
```

**Cache Issues**
```bash
# Clear all caches
docker exec market-prediction-php-fpm php artisan cache:clear
docker exec market-prediction-php-fpm php artisan config:clear
docker exec market-prediction-php-fpm php artisan view:clear
```

**Queue Not Processing**
```bash
# Check queue worker
docker logs market-prediction-queue-worker

# Restart queue worker
docker restart market-prediction-queue-worker
```

## 📝 Configuration

### API Keys Setup

You need to obtain API keys from:

1. **Finnhub** (https://finnhub.io/)
   - Free tier: 60 requests/minute
   - Used for: Real-time stock quotes

2. **Alpha Vantage** (https://www.alphavantage.co/)
   - Free tier: 5 requests/minute
   - Used for: Historical data, technical indicators

3. **NewsAPI** (https://newsapi.org/)
   - Free tier: 100 requests/day
   - Used for: Market news articles

### Caching Strategy

- **Stock Quotes**: 30 seconds
- **News Articles**: 5 minutes
- **Predictions**: 1 hour
- **Market Indices**: 1 minute

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- **Finnhub API** for real-time market data
- **Alpha Vantage** for historical stock data
- **NewsAPI** for market news
- **Lucide React** for beautiful icons
- **Tailwind CSS** for styling
- **Laravel** and **React** communities

## 📞 Support

For issues and questions:
- Open an issue on GitHub
- Email: oussama.meq@gmail.com

## 🗺️ Roadmap

- [ ] Add more ML models (LSTM, Transformer)
- [ ] Real-time WebSocket updates
- [ ] Mobile app (React Native)
- [ ] Portfolio tracking
- [ ] Trading signals
- [ ] Backtesting feature
- [ ] Social trading features
- [ ] Multi-language support

---

**Made with ❤️ by me**

*Last Updated: October 2025*
