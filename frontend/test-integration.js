/**
 * Frontend Integration Test for quick_model_v2
 * 
 * This script tests the integration of:
 * - PredictionCardV2 component
 * - AsianMarketWidget component
 * - CorrectionWarningAlert component
 * - API endpoints for predictions and Asian markets
 * 
 * Usage: node test-integration.js
 */

const API_BASE = 'http://localhost:8000/api';

// Test configuration
const TEST_SYMBOL = 'AAPL';
const TEST_HORIZONS = ['today', 'week', 'month'];

// ANSI color codes for console output
const colors = {
  reset: '\x1b[0m',
  bright: '\x1b[1m',
  green: '\x1b[32m',
  red: '\x1b[31m',
  yellow: '\x1b[33m',
  blue: '\x1b[34m',
  cyan: '\x1b[36m',
};

function log(message, color = colors.reset) {
  console.log(`${color}${message}${colors.reset}`);
}

function logSuccess(message) {
  log(`✓ ${message}`, colors.green);
}

function logError(message) {
  log(`✗ ${message}`, colors.red);
}

function logInfo(message) {
  log(`ℹ ${message}`, colors.cyan);
}

function logWarning(message) {
  log(`⚠ ${message}`, colors.yellow);
}

/**
 * Test Asian Market API endpoint
 */
async function testAsianMarkets() {
  log('\n' + '='.repeat(60), colors.bright);
  log('Testing Asian Markets API', colors.bright);
  log('='.repeat(60), colors.bright);

  try {
    const response = await fetch(`${API_BASE}/asian-markets`);
    
    if (!response.ok) {
      logError(`API returned status ${response.status}`);
      return false;
    }

    const data = await response.json();
    
    // Check response structure
    if (!data.data) {
      logError('Response missing "data" field');
      return false;
    }

    logSuccess('Asian Markets API endpoint working');
    
    // Check for expected fields
    const markets = data.data.markets || data.data;
    const meta = data.meta || data.data;
    
    logInfo(`Found ${Object.keys(markets).length} markets`);
    
    // Validate market data structure
    for (const [key, market] of Object.entries(markets)) {
      if (!market.name || market.change_percent === undefined) {
        logWarning(`Market ${key} missing required fields`);
      } else {
        logSuccess(`${market.name}: ${market.change_percent > 0 ? '+' : ''}${market.change_percent.toFixed(2)}%`);
      }
    }
    
    // Check for Asian influence score
    if (meta.influence_score !== undefined || meta.asian_influence_score !== undefined) {
      const score = meta.influence_score || meta.asian_influence_score;
      logSuccess(`Asian Influence Score: ${score.toFixed(3)}`);
    } else {
      logWarning('Asian influence score not found');
    }
    
    return true;
  } catch (error) {
    logError(`Error testing Asian Markets: ${error.message}`);
    return false;
  }
}

/**
 * Test Prediction API endpoint with quick_model_v2
 */
async function testPrediction(symbol, horizon) {
  log(`\nTesting prediction for ${symbol} (${horizon})...`, colors.blue);
  
  try {
    const response = await fetch(`${API_BASE}/predictions/predict`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ symbol, horizon }),
    });
    
    if (!response.ok) {
      logError(`API returned status ${response.status}`);
      const errorData = await response.json();
      logError(`Error: ${JSON.stringify(errorData)}`);
      return false;
    }

    const data = await response.json();
    
    if (!data.data) {
      logError('Response missing "data" field');
      return false;
    }

    const prediction = data.data;
    
    logSuccess(`Prediction endpoint working for ${horizon} horizon`);
    
    // Validate prediction structure
    const requiredFields = ['symbol', 'horizon', 'prediction'];
    for (const field of requiredFields) {
      if (!prediction[field]) {
        logError(`Missing required field: ${field}`);
        return false;
      }
    }
    
    // Log prediction details
    const pred = prediction.prediction;
    logInfo(`  Label: ${pred.label}`);
    logInfo(`  Probability: ${((pred.probability || 0) * 100).toFixed(1)}%`);
    
    if (pred.expected_pct_move !== undefined) {
      logInfo(`  Expected Move: ${pred.expected_pct_move > 0 ? '+' : ''}${pred.expected_pct_move.toFixed(2)}%`);
    }
    
    // Check for quick_model_v2 specific fields (only for 'today' horizon)
    if (horizon === 'today') {
      // Check for Asian influence
      if (prediction.asian_influence) {
        logSuccess('  ✓ Asian influence data present');
        const ai = prediction.asian_influence;
        logInfo(`    - Influence Score: ${ai.score?.toFixed(3) || 'N/A'}`);
        logInfo(`    - Impact: ${((ai.impact_percent || 0) * 100).toFixed(0)}%`);
      } else {
        logWarning('  ⚠ Asian influence data missing (expected for today horizon)');
      }
      
      // Check for correction warning
      if (prediction.correction_warning) {
        logSuccess('  ✓ Correction warning data present');
        const cw = prediction.correction_warning;
        logInfo(`    - Warning: ${cw.warning}`);
        logInfo(`    - Severity: ${cw.severity}`);
        if (cw.reasons && cw.reasons.length > 0) {
          logInfo(`    - Reasons: ${cw.reasons.length} triggers`);
        }
      } else {
        logWarning('  ⚠ Correction warning data missing');
      }
      
      // Check for top reasons
      if (pred.top_reasons && pred.top_reasons.length > 0) {
        logSuccess(`  ✓ Top reasons present (${pred.top_reasons.length} factors)`);
        pred.top_reasons.slice(0, 3).forEach((reason, i) => {
          logInfo(`    ${i + 1}. ${reason}`);
        });
      } else {
        logWarning('  ⚠ Top reasons missing');
      }
    }
    
    return true;
  } catch (error) {
    logError(`Error testing prediction: ${error.message}`);
    return false;
  }
}

/**
 * Test all horizons for a symbol
 */
async function testAllHorizons(symbol) {
  log('\n' + '='.repeat(60), colors.bright);
  log(`Testing Predictions for ${symbol}`, colors.bright);
  log('='.repeat(60), colors.bright);
  
  const results = [];
  
  for (const horizon of TEST_HORIZONS) {
    const success = await testPrediction(symbol, horizon);
    results.push({ horizon, success });
  }
  
  // Summary
  log('\n' + '-'.repeat(60), colors.bright);
  log('Summary:', colors.bright);
  const successful = results.filter(r => r.success).length;
  log(`${successful}/${results.length} horizons tested successfully`, 
    successful === results.length ? colors.green : colors.yellow);
  
  return results.every(r => r.success);
}

/**
 * Main test runner
 */
async function runTests() {
  log('\n' + '='.repeat(60), colors.bright);
  log('🚀 Frontend Integration Test Suite', colors.bright);
  log('   Testing quick_model_v2 Integration', colors.bright);
  log('='.repeat(60) + '\n', colors.bright);
  
  logInfo(`API Base URL: ${API_BASE}`);
  logInfo(`Test Symbol: ${TEST_SYMBOL}`);
  logInfo(`Test Horizons: ${TEST_HORIZONS.join(', ')}\n`);
  
  const results = {
    asianMarkets: false,
    predictions: false,
  };
  
  // Test 1: Asian Markets
  results.asianMarkets = await testAsianMarkets();
  
  // Test 2: Predictions
  results.predictions = await testAllHorizons(TEST_SYMBOL);
  
  // Final Summary
  log('\n' + '='.repeat(60), colors.bright);
  log('📊 Final Results', colors.bright);
  log('='.repeat(60), colors.bright);
  
  const tests = [
    { name: 'Asian Markets API', passed: results.asianMarkets },
    { name: 'Prediction API', passed: results.predictions },
  ];
  
  tests.forEach(test => {
    if (test.passed) {
      logSuccess(`${test.name}: PASSED`);
    } else {
      logError(`${test.name}: FAILED`);
    }
  });
  
  const allPassed = Object.values(results).every(r => r);
  
  log('\n' + '='.repeat(60), colors.bright);
  if (allPassed) {
    logSuccess('✨ All tests passed! Frontend integration is ready.');
    log('='.repeat(60) + '\n', colors.green);
    process.exit(0);
  } else {
    logError('❌ Some tests failed. Please check the output above.');
    log('='.repeat(60) + '\n', colors.red);
    process.exit(1);
  }
}

// Run tests
runTests().catch(error => {
  logError(`\nFatal error: ${error.message}`);
  console.error(error);
  process.exit(1);
});
