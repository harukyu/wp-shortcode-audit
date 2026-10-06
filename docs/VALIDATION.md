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

The test site contained synthetic data only. No production website or commercial plugin was involved.

## Still unverified

- Visual inspection of the admin page and demo in a browser.
- The GitHub Actions PHP 7.4/8.1/8.3/8.5 matrix, until a remote workflow actually runs.
- Active WPBakery installations, specific addons, and theme-specific container definitions.
- A WordPress.org submission or review.

WP-CLI's bundled dependencies emitted PHP 8.5 deprecation notices during setup; these originated in the external CLI distribution, not this plugin. CLI behavioral checks excluded those deprecation notices while retaining ordinary error reporting. The parser's own tests ran with a strict error handler.

The optional `tests/integration-wordpress.php` script requires an explicitly flagged disposable installation (`WPSA_TEST_ENV === true`) at `http://127.0.0.1:8765`. It creates a synthetic page before checking the read-only scanner. It refuses to run against an ordinary site.
