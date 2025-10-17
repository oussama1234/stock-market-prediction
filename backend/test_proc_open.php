<?php

$pythonExecutable = 'python';
$pythonPath = 'D:\\Stock-market-predection\\backend\\python\\models\\quick_model_v6.py';

$input = [
    'current_price' => 351.33,
    'volume_ratio' => 1.2,
    'pe_percentile' => 55,
    'intraday_volume_ratio' => 1.2,
    'price_change_1d' => 0.5,
    'asian_market_sentiment' => 'positive'
];

$inputJson = json_encode($input, JSON_NUMERIC_CHECK);

echo "Command: $pythonExecutable \"$pythonPath\" predict\n";
echo "Input JSON:\n$inputJson\n\n";

$descriptors = [
    0 => ['pipe', 'r'],  // stdin
    1 => ['pipe', 'w'],  // stdout
    2 => ['pipe', 'w'],  // stderr
];

$process = proc_open(
    sprintf('%s "%s" predict', $pythonExecutable, $pythonPath),
    $descriptors,
    $pipes,
    null,
    null
);

if (!is_resource($process)) {
    echo "FAILED to start process\n";
    exit(1);
}

echo "Process started: " . ($process ? 'YES' : 'NO') . "\n";

// Send JSON via stdin
fwrite($pipes[0], $inputJson);
fclose($pipes[0]);

echo "JSON sent via stdin\n\n";

// Read output
$output = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);

$exitCode = proc_close($process);

echo "Exit Code: $exitCode\n\n";

if ($stderr) {
    echo "STDERR:\n$stderr\n\n";
}

echo "STDOUT:\n$output\n\n";

if ($output) {
    $result = json_decode($output, true);
    if ($result) {
        echo "Successfully parsed JSON!\n";
        echo "Scores:\n";
        echo "  Volume: " . ($result['scores']['volume'] ?? 'N/A') . "\n";
        echo "  Fundamentals: " . ($result['scores']['fundamentals'] ?? 'N/A') . "\n";
        echo "  Intraday: " . ($result['scores']['intraday'] ?? 'N/A') . "\n";
    }
}
?>
