# Contributing

Bug reports and small, documented improvements are welcome.

## Reproduce a finding

Provide a **minimal synthetic shortcode sample**, expected behavior, PHP/WordPress versions, and any relevant theme or builder tag configuration. Do not attach a full customer page, production export, credentials, or personal data.

Unknown pairing is a warning by design. Before proposing a default tag classification, link to public documentation or explain the registered element's content model.

## Development

Run `php tests/run.php` and `php tests/wordpress.php`. New parser behavior should include a small regression case. Keep the analyzer usable without WordPress and without executing callbacks or fetching data.

Use PHP 7.4-compatible syntax. Keep reports deterministic. Preserve the explicit incomplete-scan state for resource limits. Core code changes must not introduce writes, automatic repairs, frontend scripts, or background tasks.

Run `php tools/demo.php` after changing report examples, and `php tools/package.php` to build the installable plugin. The ZIP uses an allowlist to exclude development-only files.

Contributions are licensed under GPL-2.0-or-later, the project's license. Report security concerns privately as described in [SECURITY.md](SECURITY.md).
