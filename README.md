# Nakaryu Shortcode Audit

[![Checks](https://github.com/harukyu/wp-shortcode-audit/actions/workflows/ci.yml/badge.svg)](https://github.com/harukyu/wp-shortcode-audit/actions/workflows/ci.yml)

**Read-only structural checks for WPBakery content.** Find missing closing tags, crossed nesting, and malformed shortcode syntax before editing or migrating a page.

Built by [Nakaryu GmbH](https://nakaryu.de). This project is independently implemented and has no code dependency on Nakaryu's commercial plugins. It is not an official WPBakery product.

> Early release, version 0.1.1. Passing a scan means no issue was found within the supported structural checks. It does not certify rendering, visual layout, security, or compatibility with a particular theme.

[Deutsche Anleitung](docs/DE.md) · [Example report](docs/demo.html) · [Changelog](CHANGELOG.md) · [Contributing](CONTRIBUTING.md)

## Example

```text
[vc_row]
  [vc_column]
    [vc_column_text]Hello
  [/vc_column]
[/vc_row]
```

```text
ERROR 4:3 misnested_shortcode — Close [/vc_column_text] before [/vc_column].
```

The tool does not repair this automatically. It shows the location so you can review the source in your usual editor.

## What it checks

| Finding | Level | Meaning |
| --- | --- | --- |
| `unclosed_shortcode` | Error | A known container or text block has no matching closing tag. |
| `unexpected_closing_shortcode` | Error | No matching opening tag was found. |
| `misnested_shortcode` | Error | Enclosed tags close in the wrong order. |
| `missing_closing_bracket` | Error | A builder shortcode's syntax is missing `]`. |
| `unterminated_attribute` | Error | A quoted builder attribute is not closed. |
| `malformed_closing_shortcode` | Error | A closing tag contains extra parameters. |
| `unknown_pairing` | Warning | A custom builder tag's content model is unknown. Review or configure it. |
| `empty_text_block` | Notice | A `vc_column_text` block contains only whitespace; it may be intentional. |
| Resource/encoding limits | Error | The scan is incomplete. Split or convert the input and run again. |

Reports also show element counts, maximum inspected nesting depth, Unicode line/column positions, and byte offsets. Only opening tags count as elements.

## Install in WordPress

1. Download `nakaryu-shortcode-audit-0.1.1.zip` from [GitHub releases](https://github.com/harukyu/wp-shortcode-audit/releases), or build it locally.
2. Upload it under **Plugins → Add New Plugin → Upload Plugin**, then activate it.
3. Open **Tools → Shortcode Audit**.
4. Select a saved post, page, or WooCommerce product, or paste a raw snippet.
5. Inspect the findings and optionally download the JSON report.

WordPress 6.3+ and PHP 7.4+ are required. WPBakery is optional: the basic scan works without it. When available, WPBakery's public element metadata adds registered custom containers and content elements to the classification.

The admin page requires `manage_options`. Inspecting a saved entry also requires permission to edit that entry. JSON exports require an authenticated administrator and a nonce. The plugin adds no frontend assets, shortcodes, database tables, scheduled tasks, or external requests. It does not save reports or run `the_content`/`do_shortcode`.

## Use without WordPress

No Composer install or API key is needed:

```sh
php bin/shortcode-audit.php examples/valid.txt
php bin/shortcode-audit.php examples/broken.txt --format=json
cat page-content.txt | php bin/shortcode-audit.php -
php bin/shortcode-audit.php page-content.txt --paired=my_container,uncode_custom
```

Exit codes: **0** no selected failure, **1** warnings when using `--fail-on=warning`, **2** structural errors or an incomplete scan, **3** a CLI usage or input error. `--fail-on=none` creates an informational report without failing on findings.

For PHP usage, include `src/Lexer.php` and `src/Analyzer.php` or use the provided Composer PSR-4 autoload definition:

```php
$analyzer = new \Nakaryu\ShortcodeAudit\Analyzer([
    'additional_paired_tags' => ['my_container'],
]);
$report = $analyzer->analyze($rawContent);
```

## WP-CLI

With the WordPress plugin activated:

```sh
wp shortcode-audit check 42 --format=json
wp shortcode-audit scan --post_type=page,product --limit=50 --offset=0
wp shortcode-audit scan --post_type=page --status=publish,draft --format=json
```

Batch scans are bounded to 1–200 entries, ordered by ID. Continue with `--offset`; these commands do not imply that the entire site was inspected. WP-CLI assumes the operator is authorized to access the site's database. Reports include entry IDs and types, but omit titles and original content.

## Custom builder tags

By default, the analyzer inspects `vc_` and `uncode_` names, known WPBakery containers/text blocks, and an explicit set of common leaf elements. Names are case sensitive, as WordPress shortcode names are.

An explicit closing tag in a document allows inspection of an otherwise unknown pair. An unknown opening tag without such evidence gets a warning, not an invented missing-close error. Explicit self-closing tags do not need a closing tag.

Configure your content model if a theme or addon behaves differently:

```php
add_filter('wpsa_shortcode_audit_options', static function (array $options): array {
    $options['additional_paired_tags'][] = 'my_container';
    $options['additional_standalone_tags'][] = 'vc_custom_leaf';
    return $options;
});
```

`paired_tags` and `standalone_tags` replace the corresponding defaults; `additional_*` extends them. An explicit `additional_standalone_tags` entry overrides a default paired classification. Options also support `known_tags`, `prefixes`, `max_tokens`, `max_depth`, and `max_issues`. A `known_tags` entry suppresses the unknown-pairing warning; use an explicit paired tag when missing-close detection is needed.

## Scope and limitations

- This is a syntax aid, not a replacement for WordPress's shortcode renderer. Attribute syntax is scanned with quote awareness; it is not fully validated against WordPress's own parsing rules.
- Double-bracket escaped shortcodes, HTML comments, HTML tag attributes, script/style bodies, and `vc_raw_html`/`vc_raw_js` payloads are excluded from structural checks. An unterminated comment or script/style body can hide text until end of input; HTML validity is outside this tool's scope.
- Raw builder blocks are opaque. Their stored payloads are not decoded or executed.
- The tool does not verify images, links, CSS, allowed parent/child combinations, callbacks, serialized attributes, or block-editor content.
- Maximum input is 2 MiB, up to 10,000 syntax tokens, depth 256, and 200 reported findings by default. Individual tag names are limited to 128 bytes and opening/closing syntax to 64 KiB. Limits are visible in the result; truncated scans are marked incomplete.
- Findings should be reviewed with the relevant theme/addon documentation. No page is changed or automatically repaired.

## Development and release

```sh
php tests/run.php
php tests/wordpress.php
php tools/demo.php
php tools/package.php
```

The parser suite covers malformed input, nesting, escapes, opaque content, Unicode positions, and resource limits. The WordPress adapter suite uses stubs to check metadata integration, escaping, and capability gates. It is not a full real-WordPress integration test. See [the release checklist](docs/RELEASE.md) for staging QA.

Version 0.1.0 was additionally checked in an isolated **WordPress 7.1.3 / PHP 8.5.5** installation using the WordPress Performance Team's SQLite integration. Saved-entry scans, pasted markup escaping, access/nonce denial, JSON export, and WP-CLI single/batch scans passed. The scan was monitored for database writes and preserved the fixture content byte-for-byte. Active WPBakery/theme-specific metadata compatibility has not yet been tested. Details are in [VALIDATION.md](docs/VALIDATION.md).

GitHub Actions runs lint/tests and packaging on PHP 7.4, 8.1, 8.3, and 8.5. All four jobs passed for the initial implementation in [Checks #1](https://github.com/harukyu/wp-shortcode-audit/actions/runs/37533797697). A `v*` tag builds a plugin ZIP and creates a prerelease, or attaches the package to an existing release for that tag.

## Licensing and attribution

Copyright 2026 Nakaryu GmbH. Licensed under **GPL-2.0-or-later**; see [LICENSE](LICENSE). All examples are synthetic. No paid plugin, customer content, credentials, or third-party WPBakery implementation is bundled.

The classification uses publicly documented shortcode/container concepts: [WPBakery container documentation](https://kb.wpbakery.com/devs/developer-tutorials/nested-shortcodes-container/), [content parameters](https://kb.wpbakery.com/devs/element-development/params-array/), and [WordPress escaping rules](https://developer.wordpress.org/reference/functions/get_shortcode_regex/). WPBakery is a third-party product name; this project is independently maintained.

The installable plugin ZIP excludes the standalone `bin/` CLI. Clone or download this repository to use that CLI. WordPress administrators can use the included WP-CLI command.
