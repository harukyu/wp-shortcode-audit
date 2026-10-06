#!/usr/bin/env php
<?php

require_once dirname(__DIR__) . '/src/Lexer.php';
require_once dirname(__DIR__) . '/src/Analyzer.php';

use Nakaryu\ShortcodeAudit\Analyzer;

function wpsa_cli_error(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(3);
}

$path = null;
$format = 'text';
$failOn = 'error';
$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        echo "Usage: php bin/shortcode-audit.php <file|-> [--format=text|json] [--fail-on=error|warning|none] [--paired=tag1,tag2] [--standalone=tag1,tag2]\n";
        echo "Use - to read UTF-8 content from stdin. Exit: 0 pass, 1 warnings, 2 findings/incomplete, 3 usage/I/O error.\n";
        exit(0);
    }
    if (strpos($argument, '--format=') === 0) {
        $format = substr($argument, 9);
    } elseif (strpos($argument, '--fail-on=') === 0) {
        $failOn = substr($argument, 10);
    } elseif (strpos($argument, '--paired=') === 0) {
        $options['additional_paired_tags'] = explode(',', substr($argument, 9));
    } elseif (strpos($argument, '--standalone=') === 0) {
        $options['additional_standalone_tags'] = explode(',', substr($argument, 13));
    } elseif ($argument !== '-' && substr($argument, 0, 1) === '-') {
        wpsa_cli_error('Unknown option: ' . $argument);
    } elseif ($path === null) {
        $path = $argument;
    } else {
        wpsa_cli_error('Provide exactly one input file.');
    }
}
if ($path === null || !in_array($format, ['text', 'json'], true) || !in_array($failOn, ['error', 'warning', 'none'], true)) {
    wpsa_cli_error('Provide an input file and valid options. Use --help for usage.');
}
$handle = $path === '-' ? STDIN : @fopen($path, 'rb');
if ($handle === false) {
    wpsa_cli_error('Cannot open input file.');
}
// Read one byte beyond the limit to detect oversized input without loading it all.
$content = stream_get_contents($handle, Analyzer::MAX_BYTES + 1);
if ($path !== '-') {
    fclose($handle);
}
if ($content === false) {
    wpsa_cli_error('Cannot read input.');
}
$report = (new Analyzer($options))->analyze($content);
if ($format === 'json') {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} else {
    $s = $report['summary'];
    echo sprintf("%d builder elements · %d errors · %d warnings · %d notices · scan %s\n", $s['builder_shortcode_count'], $s['errors'], $s['warnings'], $s['notices'], $report['complete'] ? 'complete' : 'incomplete');
    foreach ($report['issues'] as $issue) {
        echo sprintf("%s %d:%d %s — %s\n", strtoupper($issue['severity']), $issue['line'], $issue['column'], $issue['code'], $issue['message']);
    }
    if ($s['errors'] === 0 && $report['complete']) {
        echo "No structural errors detected within the supported checks.\n";
    }
}
exit(Analyzer::exitCode($report, $failOn));
