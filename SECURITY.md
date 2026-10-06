# Security

For a suspected security issue, contact **hey@nakaryu.de** with the affected version and a minimal synthetic reproduction. Please avoid publishing credentials, production reports, or customer content in a public issue.

The intended boundary is read-only analysis. Shortcode callbacks are never executed, and no external services are contacted. The WordPress admin page and report export require administrator capability and nonces; saved entries also require edit permission.

Standalone PHP and WP-CLI commands run with the operator's filesystem/database access. JSON reports may contain entry IDs, types, tag names, and positions. Treat your own exported reports according to the sensitivity of that metadata.

Version 0.1.x is an early release. This tool does not perform a security audit of the inspected page, its theme, or its shortcodes.
