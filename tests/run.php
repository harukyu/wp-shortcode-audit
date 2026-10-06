<?php

require_once dirname(__DIR__) . '/src/Lexer.php';
require_once dirname(__DIR__) . '/src/Analyzer.php';

use Nakaryu\ShortcodeAudit\Analyzer;

set_error_handler(static function (int $severity, string $message, string $file, int $line): void {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$assertions = 0;
function expect($actual, $expected, string $label): void
{
    global $assertions;
    $assertions++;
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
function codes(array $report): array
{
    return array_column($report['issues'], 'code');
}

$cases = [
    'valid nested' => ['[vc_row][vc_column][vc_column_text]Hello[/vc_column_text][/vc_column][/vc_row]', []],
    'missing close' => ['[vc_row][vc_column][/vc_column]', ['unclosed_shortcode']],
    'orphan closing' => ['[/vc_row]', ['unexpected_closing_shortcode']],
    'crossed pairs' => ['[vc_row][vc_column][/vc_row][/vc_column]', ['misnested_shortcode', 'unexpected_closing_shortcode']],
    'same-name nesting' => ['[vc_row][vc_row]Hello[/vc_row][/vc_row]', []],
    'standalone elements' => ['[vc_row][vc_single_image image="7"][vc_empty_space][/vc_row]', []],
    'explicit self-close' => ['[vc_row /]', []],
    'quoted syntax stays in attribute' => ['[vc_row label="[vc_column] and ]"]ok[/vc_row]', []],
    'single quoted syntax' => ["[vc_row label='[vc_column]']ok[/vc_row]", []],
    'escaped standalone' => ['[[vc_row]]', []],
    'escaped enclosed content' => ['[[vc_column_text][vc_row]literal[/vc_column_text]]', []],
    'raw html opaque' => ['[vc_raw_html][vc_row]<script>[vc_column]</script>[/vc_raw_html]', []],
    'raw JS opaque' => ['[vc_raw_js]"[vc_row]"[/vc_raw_js]', []],
    'raw block missing close' => ['[vc_raw_html][vc_row]', ['unclosed_shortcode']],
    'html comment ignored' => ['<!-- [vc_row] -->', []],
    'unclosed comment ignored' => ['<!-- [vc_row]', []],
    'script ignored' => ['<script data-x=">">const x="[vc_row]";</script>', []],
    'style ignored' => ['<STYLE>.x::before{content:"[vc_row]"}</STYLE>', []],
    'html attributes ignored' => ['<div title="[vc_row]">Text</div>', []],
    'ordinary html content inspected' => ['<div>[vc_row]</div>', ['unclosed_shortcode']],
    'non-builder syntax ignored' => ['[ordinary]text[/ordinary] [word without a final bracket', []],
    'unknown builder element' => ['[uncode_custom thing="1"]', ['unknown_pairing']],
    'unknown pairs inferred' => ['[uncode_custom]text[/uncode_custom]', []],
    'unknown repeated notices coalesced' => ['[uncode_custom][uncode_custom]', ['unknown_pairing']],
    'custom close overrides standalone' => ['[vc_single_image]text[/vc_single_image]', []],
    'empty text block' => ['[vc_column_text]  [/vc_column_text]', ['empty_text_block']],
    'missing bracket' => ['[vc_row', ['missing_closing_bracket']],
    'missing bracket recovery' => ['[vc_row [vc_column][/vc_column]', ['missing_closing_bracket']],
    'unclosed attribute' => ['[vc_row title="broken]', ['unterminated_attribute']],
    'malformed close' => ['[vc_row][/vc_row invalid]', ['malformed_closing_shortcode']],
    'tags are case sensitive' => ['[VC_ROW]', []],
    'ordinary square brackets' => ['Array [1,2,3] and [not a builder', []],
];
$analyzer = new Analyzer();
foreach ($cases as $label => [$content, $expected]) {
    $before = $content;
    $result = $analyzer->analyze($content);
    expect(codes($result), $expected, $label);
    expect($content, $before, $label . ' does not mutate input');
    expect(json_last_error(), JSON_ERROR_NONE, $label . ' starts with clean JSON state');
    json_encode($result, JSON_THROW_ON_ERROR);
    expect(isset($result['content']), false, $label . ' never exports content');
}

$unicode = $analyzer->analyze("First line\r\n🦊ä [/vc_row]");
expect($unicode['issues'][0]['line'], 2, 'CRLF line');
expect($unicode['issues'][0]['column'], 4, 'Unicode code-point column');
expect($unicode['issues'][0]['offset'], strlen("First line\r\n🦊ä "), 'byte offset');
expect(codes((new Analyzer(['additional_paired_tags' => ['my_box']]))->analyze('[my_box]')), ['unclosed_shortcode'], 'custom paired tag');
expect(codes((new Analyzer(['additional_standalone_tags' => ['vc_column_text']]))->analyze('[vc_column_text]')), [], 'standalone override');
expect(codes((new Analyzer(['known_tags' => ['vc_custom_widget']]))->analyze('[vc_custom_widget]')), [], 'known mapped element');
expect($analyzer->analyze(file_get_contents(dirname(__DIR__) . '/examples/valid.txt'))['summary']['errors'], 0, 'valid example');
expect($analyzer->analyze(file_get_contents(dirname(__DIR__) . '/examples/broken.txt'))['summary']['errors'], 2, 'broken example');

$limited = (new Analyzer(['max_tokens' => 2]))->analyze('[vc_row][vc_column][/vc_column][/vc_row]');
expect(codes($limited), ['token_limit_exceeded'], 'token limit does not invent unclosed tags');
expect($limited['complete'], false, 'token limit incomplete');
$malformedLimit = (new Analyzer(['max_tokens' => 2]))->analyze('[vc_row [vc_row [vc_row');
expect(in_array('token_limit_exceeded', codes($malformedLimit), true), true, 'malformed tokens also bounded');
$depthLimited = (new Analyzer(['max_depth' => 1]))->analyze('[vc_row][vc_column][/vc_column][/vc_row]');
expect(codes($depthLimited), ['nesting_limit_exceeded'], 'depth limit does not invent unmatched tags');
expect($depthLimited['complete'], false, 'depth limit incomplete');
$issueLimited = (new Analyzer(['max_issues' => 1]))->analyze('[/vc_row][/vc_row][/vc_row]');
expect(codes($issueLimited), ['unexpected_closing_shortcode', 'issue_limit_exceeded'], 'issue suppression explicit');
expect($issueLimited['complete'], false, 'issue limit incomplete');
$oversize = $analyzer->analyze(str_repeat('x', Analyzer::MAX_BYTES + 1));
expect(codes($oversize), ['input_limit_exceeded'], 'input size bounded');
expect($oversize['complete'], false, 'oversize incomplete');
expect(codes($analyzer->analyze("\xFF[vc_row]")), ['invalid_encoding'], 'UTF-8 validation');
expect(Analyzer::exitCode($oversize), 2, 'incomplete fails checks');
expect(Analyzer::exitCode($oversize, 'none'), 0, 'explicit informational mode');
expect(Analyzer::exitCode($analyzer->analyze('[uncode_custom]')), 0, 'unknown pairing is not an error');
expect(Analyzer::exitCode($analyzer->analyze('[uncode_custom]'), 'warning'), 1, 'strict warning mode');

// Repeated escapes and nested syntax remain bounded; no timing threshold is used.
$stress = $analyzer->analyze(str_repeat('[[vc_row]', 4000) . '[/vc_row]]');
expect($stress['summary']['errors'], 0, 'large escaped block');
$balanced = str_repeat('[vc_row]', 200) . 'hello' . str_repeat('[/vc_row]', 200);
expect($analyzer->analyze($balanced)['summary']['max_nesting_depth'], 200, 'deep balanced structure');
$manyLines = $analyzer->analyze(str_repeat("\n", 1000000) . '[/vc_row]');
expect($manyLines['issues'][0]['line'], 1000001, 'newline-heavy input avoids per-line storage');
$longName = $analyzer->analyze('[vc_' . str_repeat('x', 200000) . ']');
expect(codes($longName), ['tag_name_limit_exceeded'], 'oversized tag name is an explicit limit');
expect(strlen($longName['issues'][0]['tag']), 128, 'oversized name does not bloat report');
$longAttributes = $analyzer->analyze('[vc_row data="' . str_repeat('x', 70000) . '"][/vc_row]');
expect($longAttributes['issues'][0]['code'], 'tag_syntax_limit_exceeded', 'long attributes are not misreported as a missing bracket');

echo count($cases) . ' structural cases and ' . $assertions . " assertions passed.\n";
