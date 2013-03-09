<?php
if (php_sapi_name() == "cli" || php_sapi_name() == "embed")
{
    echo PHP_EOL . "Lingoes Converter v0.1" . PHP_EOL;
    echo "> by WindyLea" . PHP_EOL;
    echo "---" . PHP_EOL;

    $input = isset($_SERVER["argv"][1]) ? trim($_SERVER["argv"][1]) : "";
    $output = isset($_SERVER["argv"][2]) ? trim($_SERVER["argv"][2]) : "";
    if (empty($input))
    {
        $line = false;
        while(!$line)
        {
            echo "+ Input file: ";
            $cmdHandle = fopen("php://stdin", "r");
            $line = trim(fgets($cmdHandle));
        }
        $input = trim($line, '"');
    }

    echo "+ Output file (Optional): ";
    $line = trim(fgets($cmdHandle));
    $output = trim($line, '"');

    echo "+ Entry word encoding (Optional / Default is UTF-8): ";
    $line = trim(fgets($cmdHandle));
    $encodingWord = trim($line, '"');

    echo "+ Entry definition encoding (Optional / Default is UTF-16LE): ";
    $line = trim(fgets($cmdHandle));
    $encodingDef = trim($line, '"');

} else
{
    $input = isset($_GET["input"]) ? trim($_GET["input"]) : "";
    $output = isset($_GET["output"]) ? trim($_GET["output"]) : "";
    $encodingWord = isset($_GET["encodingWord"]) ? trim($_GET["encodingWord"]) : "UTF-8";
    $encodingDef = isset($_GET["encodingDef"]) ? trim($_GET["encodingDef"]) : "UTF-16LE";
}

set_time_limit(0);
ini_set("memory_limit", "128M");
include("LingoesConverter.php");

echo PHP_EOL . "Converting..." . PHP_EOL;

$timeStart = microtime(true);
$plc = new LingoesConverter;
$plc->input = $input;
$plc->output = $output;
$plc->encodingDef = $encodingDef;
$plc->encodingWord = $encodingWord;
$convert = $plc->convert();
if (!$convert)
{
    $lastMessage = end($plc->logs);
    echo "* " . $lastMessage[1] . PHP_EOL;
}

$timeEnd = microtime(true);

echo PHP_EOL . "# Execution time: " . round(($timeEnd - $timeStart), 2) . " (s)";
echo PHP_EOL . "# Memory usage: " . (memory_get_usage(true) / 1024) . " KB"; 
echo PHP_EOL . "# Peak memory usage: " . (memory_get_peak_usage(true) / 1024) . " KB"; 
?>