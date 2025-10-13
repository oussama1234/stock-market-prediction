<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Market Influence Weights
    |--------------------------------------------------------------------------
    |
    | Defines the weights for different market influences in prediction model
    | Total should sum to 100%
    |
    */
    
    'influence_weights' => [
        'local' => 50,     // Local US market influence (50%)
        'european' => 30,  // European market influence (30%)
        'asian' => 20,     // Asian market influence (20%)
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Influence Scale Factors
    |--------------------------------------------------------------------------
    |
    | Scale factors to normalize market influences
    |
    */
    
    'influence_scales' => [
        'local' => [
            'max_impact' => 0.50,      // Maximum 50% impact on prediction
            'sentiment_weight' => 0.8,
            'momentum_weight' => 0.2,
        ],
        
        'european' => [
            'max_impact' => 0.30,      // Maximum 30% impact on prediction
            'sentiment_weight' => 0.7,  // Weight for sentiment vs raw change
            'momentum_weight' => 0.3,   // Weight for momentum indicator
        ],
        
        'asian' => [
            'max_impact' => 0.20,      // Maximum 20% impact on prediction
            'sentiment_weight' => 0.6,
            'momentum_weight' => 0.4,
        ],
        
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Cache durations for prediction data
    |
    */
    
    'cache' => [
        'market_data_ttl' => 300,      // 5 minutes for market data
        'prediction_ttl' => 600,        // 10 minutes for predictions
        'influence_ttl' => 600,         // 10 minutes for influence calculations
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Model Settings
    |--------------------------------------------------------------------------
    |
    | Settings for prediction model execution
    |
    */
    
    'model' => [
        'python_path' => env('PYTHON_PATH', 'python'),
        'script_path' => env('MODEL_SCRIPT_PATH', base_path('ml-models/quick_model_v4.py')),
        'timeout' => env('MODEL_TIMEOUT', 30),  // 30 seconds timeout
        'features' => [
            'use_european_market' => true,
            'use_asian_market' => true,
            'use_sentiment' => true,
            'use_technical' => true,
            'use_rebound_logic' => true,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Sentiment Thresholds
    |--------------------------------------------------------------------------
    |
    | Thresholds for determining market sentiment
    |
    */
    
    'sentiment_thresholds' => [
        'very_bullish' => 1.5,
        'bullish' => 0.5,
        'neutral_positive' => 0.1,
        'neutral_negative' => -0.1,
        'bearish' => -0.5,
        'very_bearish' => -1.5,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Queue Settings
    |--------------------------------------------------------------------------
    |
    | Settings for job queue processing
    |
    */
    
    'queue' => [
        'connection' => env('QUEUE_CONNECTION', 'redis'),
        'prediction_queue' => 'predictions',
        'market_queue' => 'markets',
        'retry_times' => 3,
        'retry_delay' => 5,  // seconds
    ],
];
