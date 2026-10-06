<?php
/**
 * Plugin Name: WP Shortcode Audit
 * Description: Read-only structural checks for WPBakery shortcode content, with JSON reports and WP-CLI support.
 * Version: 0.1.0
 * Requires at least: 6.3
 * Requires PHP: 7.4
 * Author: Nakaryu GmbH
 * Author URI: https://nakaryu.de
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: wp-shortcode-audit
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/src/Lexer.php';
require_once __DIR__ . '/src/Analyzer.php';
require_once __DIR__ . '/includes/wordpress.php';

add_action('admin_menu', 'wpsa_register_admin_page');
add_action('admin_enqueue_scripts', 'wpsa_enqueue_admin_assets');
add_action('admin_post_wpsa_export', 'wpsa_export_report');

if (defined('WP_CLI') && WP_CLI) {
    require_once __DIR__ . '/includes/cli.php';
    WP_CLI::add_command('shortcode-audit', 'WPSA_CLI_Command');
}
