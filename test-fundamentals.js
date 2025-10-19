// Quick test to check if fundamental data is in API response
fetch('http://localhost/api/predict/AVGO?model=v6')
  .then(res => res.json())
  .then(data => {
    console.log('✅ API Response received');
    console.log('📊 Model Version:', data.data?.model_version);
    console.log('📈 Scores:', data.data?.scores);
    console.log('🔧 Factors:', data.data?.factors);
    console.log('💰 Fundamentals:', data.data?.factors?.fundamentals);
    
    const fund = data.data?.factors?.fundamentals || {};
    console.log('\n💰 FUNDAMENTAL DATA DETAILS:');
    console.log('  P/E Ratio:', fund.pe_ratio);
    console.log('  P/B Ratio:', fund.pb_ratio);
    console.log('  EPS Growth:', fund.eps_growth);
    console.log('  Revenue Growth:', fund.revenue_growth);
    console.log('  ROE:', fund.roe);
    console.log('  Profit Margin:', fund.profit_margin);
    console.log('  Debt/Equity:', fund.debt_to_equity);
    console.log('  Dividend Yield:', fund.dividend_yield);
    console.log('  Score:', fund.score);
    
    if (fund.score === 0) {
      console.warn('\n⚠️ WARNING: Fundamental score is 0!');
      console.warn('This means either:');
      console.warn('1. Data not fetched from Yahoo Finance');
      console.warn('2. All metrics are at neutral/default values');
      console.warn('3. Data not enriched in prepareStockData()');
    }
  })
  .catch(err => console.error('❌ Error:', err));
