<?php

$pythonPath = 'D:\\Stock-market-predection\\backend\\python\\models\\quick_model_v6.py';
echo "Python Path: $pythonPath\n\n";

$input = [
    'current_price' => 351.33,
    'volume_ratio' => 1.2,
    'pe_percentile' => 55,
    'intraday_volume_ratio' => 1.2,
    'price_change_1d' => 0.5,
    'asian_market_sentiment' => 'positive'
];

$inputJson = json_encode($input, JSON_NUMERIC_CHECK);
echo "Input JSON: $inputJson\n\n";

$pythonExecutable = 'python';
$command = sprintf(
    '%s "%s" predict --features %s',
    $pythonExecutable,
    $pythonPath,
    escapeshellarg($inputJson)
);

echo "Command: $command\n\n";

$output = shell_exec($command . ' 2>&1');

echo "Output:\n";
echo $output;
echo "\n\nOutput length: " . strlen($output) . " bytes\n";

if ($output) {
    $result = json_decode($output, true);
    if ($result) {
        echo "\nParsed JSON successfully!\n";
        echo "Model Version: " . ($result['model_version'] ?? 'N/A') . "\n";
        echo "Scores: " . json_encode($result['scores'] ?? []) . "\n";
    } else {
        echo "\nFailed to parse JSON\n";
    }
}
?>
