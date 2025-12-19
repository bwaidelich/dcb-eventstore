<?php

declare(strict_types=1);

/**
 * Check code coverage and fail if below 100%
 */

$coverageFile = __DIR__ . '/coverage.xml';

if (!file_exists($coverageFile)) {
    echo "Coverage file not found: {$coverageFile}\n";
    echo "Run: composer run-script test:unit:coverage\n";
    exit(1);
}

$xml = simplexml_load_string(file_get_contents($coverageFile));
if ($xml === false) {
    echo "Failed to parse coverage file\n";
    exit(1);
}

$metrics = $xml->project->metrics;
$statements = (int) $metrics['statements'];
$coveredStatements = (int) $metrics['coveredstatements'];

if ($statements === 0) {
    echo "No statements found in coverage report\n";
    exit(1);
}

$coverage = ($coveredStatements / $statements) * 100;

if ($coverage < 100) {
    echo sprintf(
        "✗ Code coverage is %.2f%%, which is below the required 100%%\n",
        $coverage,
    );
    echo sprintf(
        "  Covered: %d/%d statements\n",
        $coveredStatements,
        $statements,
    );
    exit(1);
}

echo sprintf("✓ Code coverage is 100%% (%d/%d statements)\n", $coveredStatements, $statements);
exit(0);
