<?php

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- Both classes use the unique Nakaryu company namespace and ShortcodeAudit project namespace.
namespace Nakaryu\ShortcodeAudit;

/** Tokenizes syntax only. Never calls WordPress or runs shortcode callbacks. */
final class Lexer
{
    /** @return array{tokens:array,issues:array,complete:bool} */
    public function scan(string $content, int $maxTokens): array
    {
        $tokens = [];
        $issues = [];
        $length = strlen($content);
        $complete = true;
        $escapedClosures = [];
        preg_match_all('~\[/([A-Za-z_][A-Za-z0-9_.:-]*)\]\]~', $content, $escapedMatches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($escapedMatches as $escapedMatch) {
            $escapedClosures[$escapedMatch[1][0]][] = $escapedMatch[0][1] + strlen($escapedMatch[0][0]);
        }

        for ($i = 0; $i < $length;) {
            if ($content[$i] === '<') {
                $htmlEnd = $this->skipHtml($content, $i);
                if ($htmlEnd > $i) {
                    $i = $htmlEnd;
                    continue;
                }
            }
            if ($content[$i] !== '[') {
                $i++;
                continue;
            }

            if (isset($content[$i + 1]) && $content[$i + 1] === '[') {
                $escaped = $this->readTag($content, $i + 1);
                if ($escaped && !isset($escaped['error'])) {
                    if (isset($content[$escaped['end']]) && $content[$escaped['end']] === ']') {
                        $i = $escaped['end'] + 1;
                        continue;
                    }
                    if (!$escaped['closing'] && !$escaped['self_closing']) {
                        $ends = $escapedClosures[$escaped['name']] ?? [];
                        $escapedEnd = $this->nextEnd($ends, $escaped['end']);
                        if ($escapedEnd !== null) {
                            $i = $escapedEnd;
                            continue;
                        }
                    }
                }
                $i++;
                continue;
            }

            $tag = $this->readTag($content, $i);
            if (!$tag) {
                $i++;
                continue;
            }
            if (count($tokens) + count($issues) >= $maxTokens) {
                $issues[] = ['name' => '', 'offset' => $i, 'error' => 'token_limit_exceeded'];
                $complete = false;
                break;
            }
            if (isset($tag['error'])) {
                $issues[] = $tag;
                $i++;
                continue;
            }
            $tokens[] = $tag;
            $i = $tag['end'];

            // WPBakery raw blocks are opaque: their payload is not builder structure.
            if (!$tag['closing'] && !$tag['self_closing'] && in_array($tag['name'], ['vc_raw_html', 'vc_raw_js'], true)) {
                $pattern = '~\[/' . preg_quote($tag['name'], '~') . '\s*\]~';
                if (preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE, $i)) {
                    $closing = $this->readTag($content, $match[0][1]);
                    if (count($tokens) + count($issues) >= $maxTokens) {
                        $issues[] = ['name' => '', 'offset' => $i, 'error' => 'token_limit_exceeded'];
                        $complete = false;
                        break;
                    }
                    $tokens[] = $closing;
                    $i = $closing['end'];
                } else {
                    $i = $length;
                }
            }
        }

        return ['tokens' => $tokens, 'issues' => $issues, 'complete' => $complete];
    }

    private function nextEnd(array $ends, int $after): ?int
    {
        $low = 0;
        $high = count($ends);
        while ($low < $high) {
            $mid = (int) floor(($low + $high) / 2);
            if ($ends[$mid] <= $after) {
                $low = $mid + 1;
            } else {
                $high = $mid;
            }
        }
        return $ends[$low] ?? null;
    }

    private function readTag(string $content, int $start): ?array
    {
        if (!preg_match('~\G\[(/?)([A-Za-z_][A-Za-z0-9_.:-]*)(?=[\s/\]]|$)~', $content, $match, 0, $start)) {
            return null;
        }
        $token = [
            'name' => $match[2],
            'offset' => $start,
            'closing' => $match[1] === '/',
            'self_closing' => false,
        ];
        if (strlen($token['name']) > 128) {
            $token['name'] = substr($token['name'], 0, 128);
            $token['error'] = 'tag_name_limit_exceeded';
            return $token;
        }
        $attributesStart = $start + strlen($match[0]);
        $quote = '';
        $length = strlen($content);
        for ($i = $attributesStart; $i < $length && $i - $start < 65536; $i++) {
            $char = $content[$i];
            if ($quote !== '') {
                if ($char === $quote) {
                    $quote = '';
                }
                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }
            if ($char === '[') {
                $token['error'] = 'missing_closing_bracket';
                return $token;
            }
            if ($char === ']') {
                $attributes = trim(substr($content, $attributesStart, $i - $attributesStart));
                if ($token['closing'] && $attributes !== '') {
                    $token['error'] = 'malformed_closing_shortcode';
                    return $token;
                }
                $token['end'] = $i + 1;
                $token['self_closing'] = !$token['closing'] && substr($attributes, -1) === '/';
                return $token;
            }
        }
        $token['error'] = $i < $length && $i - $start >= 65536
            ? 'tag_syntax_limit_exceeded'
            : ($quote !== '' ? 'unterminated_attribute' : 'missing_closing_bracket');
        return $token;
    }

    /** Ignore HTML syntax, comments, and script/style bodies. Inspect ordinary HTML text. */
    private function skipHtml(string $content, int $start): int
    {
        if (substr($content, $start, 4) === '<!--') {
            $end = strpos($content, '-->', $start + 4);
            return $end === false ? strlen($content) : $end + 3;
        }
        if (!preg_match('~\G</?([A-Za-z][A-Za-z0-9:-]*)(?=[\s/>])~', $content, $match, 0, $start)) {
            return $start;
        }
        $quote = '';
        $length = strlen($content);
        for ($i = $start + strlen($match[0]); $i < $length; $i++) {
            $char = $content[$i];
            if ($quote !== '') {
                if ($char === $quote) {
                    $quote = '';
                }
                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '>') {
                if ($content[$start + 1] !== '/' && in_array(strtolower($match[1]), ['script', 'style'], true)) {
                    $pattern = '~</' . preg_quote($match[1], '~') . '\s*>~i';
                    if (preg_match($pattern, $content, $closing, PREG_OFFSET_CAPTURE, $i + 1)) {
                        return $closing[0][1] + strlen($closing[0][0]);
                    }
                    return $length;
                }
                return $i + 1;
            }
        }
        // Malformed HTML is outside our scope; keep examining the remaining text.
        return $start;
    }
}
