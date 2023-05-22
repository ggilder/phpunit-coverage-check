<?php

declare(strict_types=1);

// Inspired by: https://ocramius.github.io/blog/automated-code-coverage-check-for-github-pull-requests-with-travis/

const XPATH_METRICS = '//project/metrics';
const STATUS_OK = 0;
const STATUS_ERROR = 1;

function formatCoverage(float $number): string
{
    return sprintf('%0.2f %%', $number);
}

function loadMetrics(string $file, array $fileFilter): array
{
    $xml = new SimpleXMLElement(file_get_contents($file));

    if (!empty($fileFilter)) {
        $metrics = [];
        $errors = [];
        foreach ($fileFilter as $file) {
            $fileMetrics = $xml->xpath('//file[@name="' . $file . '"]/metrics');
            if (count($fileMetrics) === 1) {
                $metrics[] = $fileMetrics[0];
            } else {
                $errors[] = $file;
            }
        }
        if (!empty($errors)) {
            printStatus(
                'The following file names were not found in the coverage report: ' . implode(', ', $errors),
                STATUS_ERROR
            );
        }
        return $metrics;
    } else {
        return $xml->xpath(XPATH_METRICS);
    }
}

function printStatus(string $msg, int $exitCode = STATUS_OK)
{
    echo $msg.PHP_EOL;
    exit($exitCode);
}

if (! isset($argv[1]) || ! file_exists($argv[1])) {
    printStatus("Invalid input file {$argv[1]} provided.", STATUS_ERROR);
}

if (! isset($argv[2])) {
    printStatus(
        'An integer checked percentage must be given as second parameter.',
        STATUS_ERROR
    );
}

$remainingArgs = array_slice($argv, 3);
$onlyEchoPercentage = false;
$calculateByLine = false;

foreach (array_keys($remainingArgs, '--only-percentage', true) as $key) {
    $onlyEchoPercentage = true;
    unset($remainingArgs[$key]);
}

foreach (array_keys($remainingArgs, '--coverage-by-lines', true) as $key) {
    $calculateByLine = true;
    unset($remainingArgs[$key]);
}

// Interpret the rest of the lines as a file filter. These must match exactly
// with files listed in the coverage report or an error will be raised.
$fileFilter = $remainingArgs;

$inputFile = $argv[1];
$percentage = min(100, max(0, (float) $argv[2]));

$conditionals = 0;
$coveredconditionals = 0;
$statements = 0;
$coveredstatements = 0;
$methods = 0;
$coveredmethods = 0;

foreach (loadMetrics($inputFile, $fileFilter) as $metric) {
    $conditionals += (int) $metric['conditionals'];
    $coveredconditionals += (int) $metric['coveredconditionals'];
    $statements += (int) $metric['statements'];
    $coveredstatements += (int) $metric['coveredstatements'];
    $methods += (int) $metric['methods'];
    $coveredmethods += (int) $metric['coveredmethods'];
}

// See calculation: https://confluence.atlassian.com/pages/viewpage.action?pageId=79986990
// User may specify --coverage-by-lines to only calculate coverage by line (called "statements" in the XML)
$coveredMetrics = $coveredstatements;
$totalMetrics = $statements;

if (!$calculateByLine) {
    $coveredMetrics += $coveredmethods + $coveredconditionals;
    $totalMetrics += $methods + $conditionals;
}

if ($totalMetrics === 0) {
    printStatus('Insufficient data for calculation. Please add more code.', STATUS_ERROR);
}

$totalPercentageCoverage = $coveredMetrics / $totalMetrics * 100;

if ($totalPercentageCoverage < $percentage && ! $onlyEchoPercentage) {
    printStatus(
        'Total code coverage is '.formatCoverage($totalPercentageCoverage).' which is below the accepted '.$percentage.'%',
        STATUS_ERROR
    );
}

if ($totalPercentageCoverage < $percentage && $onlyEchoPercentage) {
    printStatus(formatCoverage($totalPercentageCoverage), STATUS_ERROR);
}

if ($onlyEchoPercentage) {
    printStatus(formatCoverage($totalPercentageCoverage));
}

printStatus('Total code coverage is '.formatCoverage($totalPercentageCoverage).' – OK!');
