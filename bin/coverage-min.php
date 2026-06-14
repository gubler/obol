#!/usr/bin/env php
<?php

// ABOUTME: Enforces a minimum line-coverage percentage from a PHPUnit Clover report.
// ABOUTME: PHPUnit has no native --min (Pest did); this restores the coverage gate for mise/CI.

declare(strict_types=1);

$cloverPath = $argv[1] ?? null;
$minimum = (float) ($argv[2] ?? '0');

if (!is_string($cloverPath) || !is_file($cloverPath)) {
    fwrite(\STDERR, 'Clover report not found: ' . (string) ($cloverPath ?? '(none)') . \PHP_EOL);
    exit(2);
}

$xml = simplexml_load_file($cloverPath);

if (!$xml instanceof SimpleXMLElement) {
    fwrite(\STDERR, 'Could not parse Clover report: ' . $cloverPath . \PHP_EOL);
    exit(2);
}

$metrics = $xml->project->metrics;
$statements = (int) (string) $metrics['statements'];
$covered = (int) (string) $metrics['coveredstatements'];
$percentage = $statements > 0 ? ($covered / $statements) * 100.0 : 100.0;

fwrite(\STDOUT, sprintf(
    'Line coverage: %.2f%% (%d/%d statements), minimum %.2f%%' . \PHP_EOL,
    $percentage,
    $covered,
    $statements,
    $minimum,
));

if ($percentage < $minimum) {
    fwrite(\STDERR, sprintf('Coverage %.2f%% is below the required %.2f%%' . \PHP_EOL, $percentage, $minimum));
    exit(1);
}

exit(0);
