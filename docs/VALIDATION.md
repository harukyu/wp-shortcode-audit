# Validation of version 0.1.0

Date: 2026-10-06.

## Completed locally

- PHP syntax lint for all project PHP files.
- 32 structural fixtures and 156 assertions on PHP 8.5.5, including a run with a 32 MiB memory limit.
- WordPress adapter harness: custom container metadata, output escaping, and capability gates.
- Real isolated WordPress 7.1.3 with PHP 8.5.5 and SQLite Database Integration 3.0.2: activation, saved-entry scans, pasted snippets, report form, JSON export, unauthorized access denial, and invalid-nonce denial.
- Query monitoring around scan rendering found no SQL writes. Stored synthetic page content stayed byte-for-byte identical.
- Standalone CLI: file input, stdin, JSON output, and expected exit codes.
- Real WP-CLI 2.12.0: one-entry check and bounded batch scan, JSON output, and expected exit codes.
- Composer manifest validation and plugin ZIP generation from an explicit file allowlist.

## Completed on GitHub

- [Checks #1](https://github.com/harukyu/wp-shortcode-audit/actions/runs/37533797697), commit `4784fbb`: PHP syntax lint, structural/adapter suites, and ZIP packaging passed on PHP 7.4, 8.1, 8.3, and 8.5.

The test site contained synthetic data only. No production website or commercial plugin was involved.

## Still unverified

- Visual inspection of the admin page and demo in a browser.
- Active WPBakery installations, specific addons, and theme-specific container definitions.
- A WordPress.org submission or review.

WP-CLI's bundled dependencies emitted PHP 8.5 deprecation notices during setup; these originated in the external CLI distribution, not this plugin. CLI behavioral checks excluded those deprecation notices while retaining ordinary error reporting. The parser's own tests ran with a strict error handler.

The optional `tests/integration-wordpress.php` script requires an explicitly flagged disposable installation (`WPSA_TEST_ENV === true`) at `http://127.0.0.1:8765`. It creates a synthetic page before checking the read-only scanner. It refuses to run against an ordinary site.

## Version 0.1.1 directory preparation — 2026-10-07

- Official WordPress.org Plugin Check (PCP) 2.1.0, CLI with `--require=plugin-check/cli.php` for runtime checks, default full check set, new-submission mode, including low-severity errors and warnings: no outstanding findings for the installable ZIP contents.
- Existing isolated WordPress integration check rerun after the input-handling and naming changes: saved-entry scan, pasted markup escaping, access/nonce denial, export form, and zero scan writes passed on WordPress 7.1.3 / PHP 8.5.5.
- Raw shortcode input and raw JSON are deliberately not sanitized as text because that would corrupt the analyzed data. Narrow PHPCS annotations explain their nonce/capability checks, bounded validation, and escaped/JSON-encoded output. Two namespace annotations document the unique `Nakaryu\ShortcodeAudit` namespace, which the automatic prefix detector did not recognize. No checks or result codes were excluded from the Plugin Check command.
- The standalone CLI is source-only and rejects web requests; the ZIP contains the WordPress admin plugin and WP-CLI adapter.
- Directory submission and acceptance are separate steps; this report does not claim approval.
