<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Scheduled Tasks
 * 
 * Schedule stock price updates during market hours
 * Market hours: 9:30 AM - 4:00 PM EST (Monday-Friday)
 */
Schedule::command('stocks:update-prices --async')
    ->weekdays()
    ->hourly()
    ->between('9:00', '16:00')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->description('Update stock prices during market hours');

// Also update once after market close to capture final prices
Schedule::command('stocks:update-prices --async')
    ->weekdays()
    ->dailyAt('16:30')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->description('Update final stock prices after market close');

/**
 * Auto-generate predictions for all stocks
 * 
 * Runs automatically to ensure all stocks have up-to-date predictions
 * using EnhancedPredictionService with all features:
 * - Sentiment analysis
 * - Priority keywords (tariff, seasons, etc.)
 * - Technical indicators
 * - Fear & Greed Index
 * - News analysis
 */

// Generate predictions every 4 hours during market hours
Schedule::command('predictions:auto-generate')
    ->weekdays()
    ->cron('0 */4 * * *') // Every 4 hours
    ->between('6:00', '20:00')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->description('Auto-generate predictions for stocks (every 4 hours)');

// Generate predictions once in the morning before market opens
Schedule::command('predictions:auto-generate')
    ->weekdays()
    ->dailyAt('08:00')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->description('Morning prediction generation before market opens');

// Generate predictions after market close with latest data
Schedule::command('predictions:auto-generate')
    ->weekdays()
    ->dailyAt('17:00')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->description('Evening prediction generation after market close');

/**
 * Rebound Detection & News Sentiment Analysis
 * 
 * Automatically analyze news sentiment and detect rebound patterns
 * Runs periodically to catch breaking news and sentiment shifts
 */

// Hourly rebound detection during market hours (catches intraday news)
Schedule::job(new \App\Jobs\ProcessAllStocksReboundDetectionJob(false), 'default')
    ->weekdays()
    ->hourly()
    ->between('9:00', '16:00')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->description('Hourly rebound detection during market hours');

// After market close - comprehensive analysis with reprocessing
Schedule::job(new \App\Jobs\ProcessAllStocksReboundDetectionJob(true), 'default')
    ->weekdays()
    ->dailyAt('16:45')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->description('After-hours comprehensive rebound analysis');

// Early morning analysis (catches overnight news)
Schedule::job(new \App\Jobs\ProcessAllStocksReboundDetectionJob(false), 'default')
    ->weekdays()
    ->dailyAt('07:00')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->description('Morning rebound analysis for overnight news');

// Mid-day analysis (catches lunch-time and afternoon news)
Schedule::job(new \App\Jobs\ProcessAllStocksReboundDetectionJob(false), 'default')
    ->weekdays()
    ->dailyAt('13:00')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->description('Mid-day rebound analysis');
