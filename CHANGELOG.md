# Changelog

## 0.1.1 — 2026-10-07

- Prefix the analyzer options filter consistently as `wpsa_shortcode_audit_options`.
- Use Nakaryu branding and the `nakaryu-shortcode-audit` plugin slug/text domain for directory submission.
- Sanitize the request method and document deliberate preservation of raw shortcode and JSON input behind nonce/capability checks.
- Ship only the WordPress plugin and WP-CLI adapter in the installable ZIP; the standalone CLI remains in the source repository and now rejects web requests.

## 0.1.0 — 2026-10-06

Initial early release.

- Standalone, read-only PHP structural analyzer for WPBakery and configured builder tags.
- Quote-aware syntax tokenization, nested pairing, escaped/raw content handling, Unicode positions, and explicit resource limits.
- Administrator interface for saved entries and pasted snippets, with authenticated JSON downloads.
- Standalone PHP CLI and bounded WP-CLI scans.
- Synthetic fixtures, parser tests, WordPress adapter harness, demo report, and release packaging.
