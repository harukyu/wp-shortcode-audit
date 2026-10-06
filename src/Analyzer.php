<?php

namespace Nakaryu\ShortcodeAudit;

/** Conservative structural analysis; this is not a WordPress renderer or layout validator. */
final class Analyzer
{
    public const VERSION = '0.1.0';
    public const MAX_BYTES = 2097152;

    private array $paired;
    private array $standalone;
    private array $known;
    private array $prefixes;
    private int $maxTokens;
    private int $maxDepth;
    private int $maxIssues;

    public function __construct(array $options = [])
    {
        $paired = $options['paired_tags'] ?? [
            'vc_row', 'vc_row_inner', 'vc_column', 'vc_column_inner', 'vc_section',
            'vc_column_text', 'vc_raw_html', 'vc_raw_js', 'vc_tabs', 'vc_tab',
            'vc_tour', 'vc_accordion', 'vc_accordion_tab', 'vc_toggle',
            'vc_tta_tabs', 'vc_tta_tour', 'vc_tta_accordion', 'vc_tta_section',
        ];
        $standalone = $options['standalone_tags'] ?? [
            'vc_single_image', 'vc_empty_space', 'vc_separator', 'vc_text_separator',
            'vc_btn', 'vc_icon', 'vc_video', 'vc_progress_bar', 'vc_pie',
            'vc_gmaps', 'vc_images_carousel', 'vc_gallery',
        ];
        $paired = array_merge($paired, $options['additional_paired_tags'] ?? []);
        $standalone = array_merge($standalone, $options['additional_standalone_tags'] ?? []);
        $this->paired = array_fill_keys($this->validNames($paired), true);
        $this->standalone = array_fill_keys($this->validNames($standalone), true);
        foreach ($this->validNames($options['additional_standalone_tags'] ?? []) as $name) {
            unset($this->paired[$name]);
        }
        $this->known = array_fill_keys($this->validNames($options['known_tags'] ?? []), true);
        $this->prefixes = $options['prefixes'] ?? ['vc_', 'uncode_'];
        $this->maxTokens = max(1, (int) ($options['max_tokens'] ?? 10000));
        $this->maxDepth = max(1, (int) ($options['max_depth'] ?? 256));
        $this->maxIssues = max(1, (int) ($options['max_issues'] ?? 200));
    }

    public function analyze(string $content): array
    {
        $report = [
            'schema_version' => 1,
            'analyzer_version' => self::VERSION,
            'complete' => true,
            'bytes' => strlen($content),
            'summary' => [
                'builder_shortcode_count' => 0,
                'shortcode_counts' => [],
                'max_nesting_depth' => 0,
                'errors' => 0, 'warnings' => 0, 'notices' => 0,
            ],
            'issues' => [],
        ];
        $issueLimit = false;
        $add = function (string $code, string $severity, string $message, int $offset = 0, string $tag = '') use (&$report, &$issueLimit, $content): void {
            if (count($report['issues']) >= $this->maxIssues) {
                if ($issueLimit) {
                    return;
                }
                $issueLimit = true;
                $report['complete'] = false;
                $code = 'issue_limit_exceeded';
                $severity = 'error';
                $message = 'The issue limit was reached. Some findings are omitted; split the document and scan again.';
                $tag = '';
            }
            $position = $this->position($content, $offset);
            $report['issues'][] = array_merge([
                'code' => $code, 'severity' => $severity, 'message' => $message, 'tag' => $tag,
            ], $position);
            $key = $severity === 'info' ? 'notices' : ($severity === 'error' ? 'errors' : 'warnings');
            $report['summary'][$key]++;
        };

        if (strlen($content) > self::MAX_BYTES) {
            $report['complete'] = false;
            $add('input_limit_exceeded', 'error', 'Input exceeds 2 MiB. Split the document before scanning.');
            return $report;
        }
        if (preg_match('//u', $content) !== 1) {
            $report['complete'] = false;
            $add('invalid_encoding', 'error', 'Input is not valid UTF-8. Convert the file to UTF-8 and scan again.');
            return $report;
        }

        $lexed = (new Lexer())->scan($content, $this->maxTokens);
        $report['complete'] = $lexed['complete'];
        foreach ($lexed['issues'] as $issue) {
            if ($issue['name'] !== '' && !$this->inScope($issue['name'])) {
                continue;
            }
            $messages = [
                'missing_closing_bracket' => 'Shortcode syntax is missing a closing bracket ].',
                'unterminated_attribute' => 'Shortcode attribute has an unclosed quote.',
                'malformed_closing_shortcode' => 'A closing shortcode must contain only the tag name.',
                'token_limit_exceeded' => 'The token limit was reached. Split the document and scan again.',
                'tag_name_limit_exceeded' => 'A tag name exceeds 128 bytes. Its reported name is truncated and the scan is incomplete.',
                'tag_syntax_limit_exceeded' => 'A shortcode opening or closing tag exceeds 64 KiB. The scan is incomplete.',
            ];
            $add($issue['error'], 'error', $messages[$issue['error']], $issue['offset'], $issue['name']);
            $report['complete'] = false;
        }

        // Explicit closing tags let us inspect custom paired tags without guessing
        // that every builder element needs a closing tag.
        $observedPaired = [];
        foreach ($lexed['tokens'] as $token) {
            if ($token['closing'] && $this->inScope($token['name'])) {
                $observedPaired[$token['name']] = true;
            }
        }

        $stack = [];
        $unknownSeen = [];
        foreach ($lexed['tokens'] as $token) {
            $name = $token['name'];
            if (!$this->inScope($name)) {
                continue;
            }
            if (!$token['closing']) {
                $report['summary']['builder_shortcode_count']++;
                $report['summary']['shortcode_counts'][$name] = ($report['summary']['shortcode_counts'][$name] ?? 0) + 1;
            }
            if ($token['closing']) {
                $match = null;
                for ($i = count($stack) - 1; $i >= 0; $i--) {
                    if ($stack[$i]['name'] === $name) {
                        $match = $i;
                        break;
                    }
                }
                if ($match === null) {
                    $add('unexpected_closing_shortcode', 'error', 'Closing tag has no matching opening tag.', $token['offset'], $name);
                } else {
                    if ($match !== count($stack) - 1) {
                        $expected = $stack[count($stack) - 1]['name'];
                        $add('misnested_shortcode', 'error', 'Close [/' . $expected . '] before [/' . $name . '].', $token['offset'], $name);
                    }
                    $opening = $stack[$match];
                    if ($name === 'vc_column_text' && trim(substr($content, $opening['end'], $token['offset'] - $opening['end'])) === '') {
                        $add('empty_text_block', 'info', 'Text block is empty. This may be intentional.', $opening['offset'], $name);
                    }
                    $stack = array_slice($stack, 0, $match);
                }
                continue;
            }

            if ($token['self_closing']) {
                continue;
            }
            // An explicit pair in this document takes precedence over a standalone
            // default because theme integrations can change a tag's content model.
            if (isset($this->paired[$name]) || isset($observedPaired[$name])) {
                if (count($stack) >= $this->maxDepth) {
                    $add('nesting_limit_exceeded', 'error', 'Nesting exceeds the configured limit. Remaining structure was not inspected.', $token['offset'], $name);
                    $report['complete'] = false;
                    break;
                }
                $stack[] = $token;
                $report['summary']['max_nesting_depth'] = max($report['summary']['max_nesting_depth'], count($stack));
            } elseif (!isset($this->standalone[$name]) && !isset($this->known[$name]) && !isset($unknownSeen[$name])) {
                $unknownSeen[$name] = true;
                $add('unknown_pairing', 'warning', 'Pairing is unknown for this builder tag. No missing-closing-tag claim is made; configure the tag if it should wrap content.', $token['offset'], $name);
            }
        }

        // If the lexer/depth limit stopped the scan, open tags might close later.
        if ($report['complete']) {
            foreach (array_reverse($stack) as $opening) {
                $add('unclosed_shortcode', 'error', 'Opening tag has no closing tag [/' . $opening['name'] . '].', $opening['offset'], $opening['name']);
            }
        }
        ksort($report['summary']['shortcode_counts']);
        usort($report['issues'], static function (array $a, array $b): int {
            return ($a['offset'] <=> $b['offset']) ?: strcmp($a['code'], $b['code']);
        });
        return $report;
    }

    public static function exitCode(array $report, string $failOn = 'error'): int
    {
        if ($failOn === 'none') {
            return 0;
        }
        if (!$report['complete'] || $report['summary']['errors'] > 0) {
            return 2;
        }
        return $failOn === 'warning' && $report['summary']['warnings'] > 0 ? 1 : 0;
    }

    private function inScope(string $name): bool
    {
        if (isset($this->paired[$name]) || isset($this->standalone[$name]) || isset($this->known[$name])) {
            return true;
        }
        foreach ($this->prefixes as $prefix) {
            if (is_string($prefix) && $prefix !== '' && strpos($name, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    private function validNames(array $names): array
    {
        return array_values(array_filter($names, static function ($name): bool {
            return is_string($name) && preg_match('/^[A-Za-z_][A-Za-z0-9_.:-]{0,127}$/', $name) === 1;
        }));
    }

    private function position(string $content, int $offset): array
    {
        // Count only when a finding needs a position. Avoid a potentially huge
        // per-line array for inputs made almost entirely of newline characters.
        $prefix = substr($content, 0, $offset);
        $newlines = substr_count($prefix, "\n");
        $lastNewline = strrpos($prefix, "\n");
        $linePrefix = $lastNewline === false ? $prefix : substr($prefix, $lastNewline + 1);
        $characters = function_exists('mb_strlen') ? mb_strlen($linePrefix, 'UTF-8') : preg_match_all('/./us', $linePrefix);
        return ['offset' => $offset, 'line' => $newlines + 1, 'column' => ($characters === false ? strlen($linePrefix) : $characters) + 1];
    }
}
