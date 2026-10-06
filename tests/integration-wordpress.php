<?php

/** Run with `wp eval-file` in a disposable, explicitly flagged test installation. */
if (!defined('WP_CLI') || !WP_CLI || !defined('WPSA_TEST_ENV') || WPSA_TEST_ENV !== true || get_option('siteurl') !== 'http://127.0.0.1:8765') {
    throw new RuntimeException('This integration check requires the isolated loopback test installation.');
}
wp_set_current_user(1);
if (!current_user_can('manage_options') || !function_exists('wpsa_analyzer')) {
    throw new RuntimeException('Test administrator or activated plugin is missing.');
}
$root = dirname(__DIR__);
$content = file_get_contents($root . '/examples/broken.txt');
$id = wp_insert_post([
    'post_type' => 'page', 'post_status' => 'publish',
    'post_title' => 'Synthetic shortcode QA page', 'post_content' => wp_slash($content),
], true);
if (is_wp_error($id)) {
    throw new RuntimeException('Cannot create synthetic fixture.');
}
get_post($id);
wp_create_nonce('wpsa_scan');
$writes = [];
$monitor = static function (string $query) use (&$writes): string {
    if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP|TRUNCATE)\b/i', $query, $match)) {
        $writes[] = strtoupper($match[1]);
    }
    return $query;
};
add_filter('query', $monitor);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET = [];
$_POST = ['wpsa_mode' => 'post', 'wpsa_post_id' => (string) $id, '_wpnonce' => wp_create_nonce('wpsa_scan')];
$_REQUEST = $_POST;
ob_start();
wpsa_render_admin_page();
$html = ob_get_clean();
if (strpos($html, 'misnested_shortcode') === false || strpos($html, 'Download JSON report') === false) {
    throw new RuntimeException('Saved-entry report or export action missing.');
}
if (get_post_field('post_content', $id, 'raw') !== $content) {
    throw new RuntimeException('Saved content changed.');
}
$_POST = ['wpsa_mode' => 'snippet', 'wpsa_snippet' => wp_slash('[vc_row]<script>alert("synthetic")</script>[/vc_row]'), '_wpnonce' => wp_create_nonce('wpsa_scan')];
$_REQUEST = $_POST;
ob_start();
wpsa_render_admin_page();
$snippetHtml = ob_get_clean();
if (strpos($snippetHtml, '<script>alert("synthetic")</script>') !== false || strpos($snippetHtml, '&lt;script&gt;') === false) {
    throw new RuntimeException('Pasted markup is not escaped.');
}
remove_filter('query', $monitor);
if ($writes !== []) {
    throw new RuntimeException('Scan issued a database write: ' . implode(', ', $writes));
}
$dieHandler = static function () {
    return static function ($message, $title = '', $args = []) {
        $response = is_int($args) ? $args : ($args['response'] ?? 500);
        throw new RuntimeException('denied', (int) $response);
    };
};
add_filter('wp_die_handler', $dieHandler, 999);
wp_set_current_user(0);
foreach (['wpsa_render_admin_page', 'wpsa_export_report'] as $function) {
    try {
        $function();
        throw new RuntimeException('Unauthorized access was allowed.');
    } catch (RuntimeException $error) {
        if ($error->getCode() !== 403) {
            throw $error;
        }
    }
}
wp_set_current_user(1);
$_REQUEST['_wpnonce'] = 'invalid-synthetic-nonce';
try {
    wpsa_render_admin_page();
    throw new RuntimeException('Invalid nonce was accepted.');
} catch (RuntimeException $error) {
    if ($error->getCode() !== 403) {
        throw $error;
    }
}
remove_filter('wp_die_handler', $dieHandler, 999);
$report = wpsa_analyzer()->analyze($content);
$report['source'] = ['post_id' => (int) $id, 'post_type' => 'page'];
$directory = getenv('WPSA_QA_OUTPUT');
if ($directory !== false && is_dir($directory)) {
    $css = file_get_contents($root . '/assets/admin.css');
    file_put_contents($directory . '/admin.html', '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>WordPress adapter QA preview</title><style>body{background:#f0f0f1;color:#1d2327;font:14px/1.5 system-ui;padding:24px}.wrap{margin:auto}input,select,textarea,button{font:inherit;padding:8px}button{cursor:pointer}table{border-collapse:collapse;width:100%}td,th{padding:10px;text-align:left;border-bottom:1px solid #ddd}' . $css . '</style>' . $html . '</html>');
    file_put_contents($directory . '/report.json', wp_json_encode($report, JSON_PRETTY_PRINT));
    file_put_contents($directory . '/fixture-id.txt', (string) $id);
}
global $wp_version;
echo 'Real WordPress ' . $wp_version . ': saved-entry scan, pasted markup escaping, access/nonce denial, export form, and zero scan writes passed.' . PHP_EOL;
