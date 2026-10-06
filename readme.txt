=== WP Shortcode Audit ===
Contributors: nakaryu
Tags: shortcode, wpbakery, audit, developer-tools, migration
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Read-only structural checks for WPBakery shortcode content, with JSON reports and WP-CLI support.

== Description ==

Inspect existing WPBakery shortcode structure before editing or migrating content.

Find missing closing tags, crossed nesting, missing brackets, and unclosed attribute quotes. Unknown custom pairing is flagged for review instead of treated as a confirmed missing-close error. Reports include element counts and source positions.

Open Tools > Shortcode Audit to inspect a saved post, page, product, or pasted snippet. Download the report as JSON. Administrators can also run bounded batch checks with WP-CLI.

The plugin reads saved content without rendering it. It never executes shortcode callbacks, updates posts, stores reports, makes external requests, or loads frontend assets. Exported reports omit the original content.

WPBakery is optional. Known shortcode types work without it; public builder metadata, when available, helps classify custom elements. This is an independently maintained tool from Nakaryu GmbH, not an official WPBakery product.

This early release is a structural aid, not a rendering, layout, security, or compatibility certification. HTML attributes, comments, script/style bodies, escaped shortcodes, and raw builder payloads are excluded. Default limits are 2 MiB, 10,000 tokens, depth 256, and 200 findings. Incomplete scans are explicitly reported.

== Installation ==

1. Upload the plugin ZIP via Plugins > Add New Plugin > Upload Plugin.
2. Activate WP Shortcode Audit.
3. Open Tools > Shortcode Audit.

== Frequently Asked Questions ==

= Does it change content? =
No. It reads and reports only.

= Does it require WPBakery or WooCommerce? =
No. WooCommerce is only needed to create products to inspect. WPBakery metadata improves custom tag classification if loaded.

= Is a clean report proof that a page will render correctly? =
No. It only means the supported structural checks found no errors. Theme behavior, callbacks, HTML validity, and visual layout need separate review.

= Where are reports stored? =
They are generated for the current request only. Downloaded JSON files are saved wherever the operator chooses.

== Changelog ==

= 0.1.0 =
* Initial early release: structural analyzer, authenticated admin UI, JSON export, standalone PHP CLI, and WP-CLI checks.
