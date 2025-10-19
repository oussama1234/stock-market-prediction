# Simple Category Prediction Test
Write-Host "================================"
Write-Host "CATEGORY PREDICTION TESTS - V6"
Write-Host "================================"
Write-Host ""

# Test NVDA (High volatility - Tech Growth)
Write-Host "Testing NVDA (Tech Growth - 2.5x multiplier)..."
$nvda = curl.exe -s "http://localhost:8000/api/predict/NVDA?model=v6" | ConvertFrom-Json
Write-Host "  Label: $($nvda.data.label)"
Write-Host "  Expected Move: $($nvda.data.expected_pct_move)%"
Write-Host "  Probability: $($nvda.data.probability)"
Write-Host ""

# Test AAPL (Medium volatility - Tech Blue Chip)
Write-Host "Testing AAPL (Tech Blue Chip - 1.5x multiplier)..."
$aapl = curl.exe -s "http://localhost:8000/api/predict/AAPL?model=v6" | ConvertFrom-Json
Write-Host "  Label: $($aapl.data.label)"
Write-Host "  Expected Move: $($aapl.data.expected_pct_move)%"
Write-Host "  Probability: $($aapl.data.probability)"
Write-Host ""

# Test PG (Low volatility - Consumer Staples)
Write-Host "Testing PG (Consumer Staples - 0.7x multiplier)..."
$pg = curl.exe -s "http://localhost:8000/api/predict/PG?model=v6" | ConvertFrom-Json
Write-Host "  Label: $($pg.data.label)"
Write-Host "  Expected Move: $($pg.data.expected_pct_move)%"
Write-Host "  Probability: $($pg.data.probability)"
Write-Host ""

# Test AMD (High volatility - Semiconductor)
Write-Host "Testing AMD (Semiconductor - 2.2x multiplier)..."
$amd = curl.exe -s "http://localhost:8000/api/predict/AMD?model=v6" | ConvertFrom-Json
Write-Host "  Label: $($amd.data.label)"
Write-Host "  Expected Move: $($amd.data.expected_pct_move)%"
Write-Host "  Probability: $($amd.data.probability)"
Write-Host ""

Write-Host "================================"
Write-Host "SUMMARY"
Write-Host "================================"

$allLabels = @($nvda.data.label, $aapl.data.label, $pg.data.label, $amd.data.label)
$bullishCount = ($allLabels | Where-Object { $_ -eq "BULLISH" }).Count
$bearishCount = ($allLabels | Where-Object { $_ -eq "BEARISH" }).Count
$neutralCount = ($allLabels | Where-Object { $_ -eq "NEUTRAL" }).Count

Write-Host "Total Tested: 4 stocks"
Write-Host "BULLISH: $bullishCount"
Write-Host "BEARISH: $bearishCount"
Write-Host "NEUTRAL: $neutralCount (should be 0!)"
Write-Host ""

if ($neutralCount -gt 0) {
    Write-Host "ERROR: Found NEUTRAL predictions!" -ForegroundColor Red
} else {
    Write-Host "SUCCESS: No NEUTRAL predictions found!" -ForegroundColor Green
}
