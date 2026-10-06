# Release checklist

1. Run the parser suite and WordPress adapter harness. Lint all PHP files.
2. Test the admin flow on an isolated WordPress staging installation: activate, inspect an entry, inspect a pasted snippet, export JSON, and deny access for an unauthorized account.
3. Confirm input content remains byte-for-byte unchanged, with no new report/options data or frontend assets.
4. Exercise an active WPBakery installation and a relevant theme before claiming their version compatibility. Basic syntax checking without WPBakery is separate from builder metadata compatibility.
5. Review `README.md`, `readme.txt`, the plugin header, `Analyzer::VERSION`, and `CHANGELOG.md` for consistent version numbers. Set `Tested up to` only after a real WordPress version has been tested.
6. Review the publication allowlist: original code, synthetic examples, license, and public documentation only. No production content or commercial-plugin files.
7. Build with `php tools/package.php`, then inspect the ZIP's contents.
8. Push the repository. GitHub Actions tests the supported PHP matrix. Only claim remote checks passed after the workflow actually completes.
9. Tag `v0.1.0` only after the checks pass. The release workflow creates a **prerelease** with an installable ZIP. Promote a stable release only after appropriate staging/builder QA.

No WordPress.org listing, live website installation, or existing paid-product change is part of this repository's release process.
