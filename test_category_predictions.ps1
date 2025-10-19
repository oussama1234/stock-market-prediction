# Category-Based Stock Prediction Test Script
# Tests predictions for different stock categories with v6 model

Write-Host ""
Write-Host "================================" -ForegroundColor Cyan
Write-Host "CATEGORY-BASED PREDICTION TESTS" -ForegroundColor Cyan
Write-Host "================================" -ForegroundColor Cyan
Write-Host ""

# Function to test a stock prediction
function Test-StockPrediction {
    param(
        [string]$Symbol,
        [string]$ExpectedCategory,
        [decimal]$ExpectedMultiplier
    )
    
    Write-Host ""
    Write-Host "Testing $Symbol ($ExpectedCategory)..." -ForegroundColor Yellow
    
    $response = curl.exe -s "http://localhost:8000/api/predict/$Symbol?model=v6" | ConvertFrom-Json
    
    if ($response.success) {
        $data = $response.data
        Write-Host "  ✓ Label: $($data.label)" -ForegroundColor $(if ($data.label -eq 'NEUTRAL') { 'Red' } else { 'Green' })
        Write-Host "  ✓ Expected Move: $([math]::Round($data.expected_pct_move, 2))%" -ForegroundColor Green
        Write-Host "  ✓ Probability: $([math]::Round($data.probability, 2))" -ForegroundColor Green
        Write-Host "  ✓ Current Price: $$($data.current_price)" -ForegroundColor Green
        Write-Host "  ✓ Final Score: $([math]::Round($data.final_score, 3))" -ForegroundColor Green
        
        # Check if NEUTRAL (should never happen)
        if ($data.label -eq 'NEUTRAL') {
            Write-Host "  ✗ ERROR: Model returned NEUTRAL (should be BULLISH or BEARISH only!)" -ForegroundColor Red
        }
        
        return @{
            Symbol = $Symbol
            Label = $data.label
            ExpectedMove = [math]::Round($data.expected_pct_move, 2)
            Probability = [math]::Round($data.probability, 2)
            FinalScore = [math]::Round($data.final_score, 3)
        }
    } else {
        Write-Host "  ✗ ERROR: $($response.message)" -ForegroundColor Red
        return $null
    }
}

# Test high-volatility stocks
Write-Host ""
Write-Host "=== HIGH VOLATILITY STOCKS ===" -ForegroundColor Magenta
$highVol = @(
    @{ Symbol = "NVDA"; Category = "Tech Growth"; Multiplier = 2.50 },
    @{ Symbol = "TSLA"; Category = "Tech Growth"; Multiplier = 2.50 },
    @{ Symbol = "AMD"; Category = "Semiconductor"; Multiplier = 2.20 }
)

$highVolResults = @()
foreach ($stock in $highVol) {
    $result = Test-StockPrediction -Symbol $stock.Symbol -ExpectedCategory $stock.Category -ExpectedMultiplier $stock.Multiplier
    if ($result) { $highVolResults += $result }
}

# Test medium-volatility stocks
Write-Host ""
Write-Host ""
Write-Host "=== MEDIUM VOLATILITY STOCKS ===" -ForegroundColor Magenta
$medVol = @(
    @{ Symbol = "AAPL"; Category = "Tech Blue Chip"; Multiplier = 1.50 },
    @{ Symbol = "MSFT"; Category = "Tech Blue Chip"; Multiplier = 1.50 }
)

$medVolResults = @()
foreach ($stock in $medVol) {
    $result = Test-StockPrediction -Symbol $stock.Symbol -ExpectedCategory $stock.Category -ExpectedMultiplier $stock.Multiplier
    if ($result) { $medVolResults += $result }
}

# Test low-volatility stocks
Write-Host ""
Write-Host ""
Write-Host "=== LOW VOLATILITY STOCKS ===" -ForegroundColor Magenta
$lowVol = @(
    @{ Symbol = "PG"; Category = "Consumer Staples"; Multiplier = 0.70 },
    @{ Symbol = "KO"; Category = "Consumer Staples"; Multiplier = 0.70 }
)

$lowVolResults = @()
foreach ($stock in $lowVol) {
    $result = Test-StockPrediction -Symbol $stock.Symbol -ExpectedCategory $stock.Category -ExpectedMultiplier $stock.Multiplier
    if ($result) { $lowVolResults += $result }
}

# Summary
Write-Host ""
Write-Host ""
Write-Host "================================" -ForegroundColor Cyan
Write-Host "SUMMARY" -ForegroundColor Cyan
Write-Host "================================" -ForegroundColor Cyan
Write-Host ""

$allResults = $highVolResults + $medVolResults + $lowVolResults

Write-Host "Total Stocks Tested: $($allResults.Count)" -ForegroundColor White

$bullishCount = ($allResults | Where-Object { $_.Label -eq 'BULLISH' }).Count
$bearishCount = ($allResults | Where-Object { $_.Label -eq 'BEARISH' }).Count
$neutralCount = ($allResults | Where-Object { $_.Label -eq 'NEUTRAL' }).Count

Write-Host "  BULLISH: $bullishCount" -ForegroundColor Green
Write-Host "  BEARISH: $bearishCount" -ForegroundColor Red
Write-Host "  NEUTRAL: $neutralCount $(if ($neutralCount -gt 0) { '(ERROR!)' } else { '✓' })" -ForegroundColor $(if ($neutralCount -gt 0) { 'Red' } else { 'Green' })

# Check expected move ranges
Write-Host ""
Write-Host "Expected Move Ranges:" -ForegroundColor White

if ($highVolResults.Count -gt 0) {
    $highVolAvg = ($highVolResults | Measure-Object -Property ExpectedMove -Average).Average
    Write-Host "  High Volatility Avg: $([math]::Round([math]::Abs($highVolAvg), 2))% (Expected: 2-8%)" -ForegroundColor Yellow
}

if ($medVolResults.Count -gt 0) {
    $medVolAvg = ($medVolResults | Measure-Object -Property ExpectedMove -Average).Average
    Write-Host "  Medium Volatility Avg: $([math]::Round([math]::Abs($medVolAvg), 2))% (Expected: 1-4%)" -ForegroundColor Yellow
}

if ($lowVolResults.Count -gt 0) {
    $lowVolAvg = ($lowVolResults | Measure-Object -Property ExpectedMove -Average).Average
    Write-Host "  Low Volatility Avg: $([math]::Round([math]::Abs($lowVolAvg), 2))% (Expected: 0.3-1.5%)" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "✓ Category-based prediction testing complete!" -ForegroundColor Green
Write-Host ""
