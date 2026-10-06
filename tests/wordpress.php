<?php

// A small adapter harness, not a substitute for staging QA with real WordPress.
define('ABSPATH', __DIR__);
$GLOBALS['wpsa_test_hooks'] = [];
$GLOBALS['wpsa_test_permission'] = true;
function add_action($hook, $callback) { $GLOBALS['wpsa_test_hooks'][$hook] = $callback; }
function apply_filters($hook, $value) { return $value; }
function current_user_can($capability, ...$args) { return $GLOBALS['wpsa_test_permission']; }
function wp_die($message, $title = '', $args = []) { throw new RuntimeException((string) ($args['response'] ?? 500)); }
function __($text, $domain = '') { return $text; }
function esc_html__($text, $domain = '') { return esc_html($text); }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return esc_html($text); }
function esc_url($text) { return esc_html($text); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags | JSON_THROW_ON_ERROR); }
function wp_nonce_field($action) { echo '<input type="hidden" name="_wpnonce" value="test">'; }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function submit_button($label, $class, $name, $wrap) { echo '<button>' . esc_html($label) . '</button>'; }
function get_edit_post_link($id, $context) { return 'https://example.test/wp-admin/post.php?post=' . (int) $id; }
final class WPBMap {
    public static function getAllShortCodes() {
        return [
            'my_container' => ['is_container' => true],
            'my_text' => ['params' => [['param_name' => 'content', 'type' => 'textarea_html']]],
            'my_image' => ['params' => []],
            'bad_mapping' => ['params' => false],
        ];
    }
}
require dirname(__DIR__) . '/wp-shortcode-audit.php';

function check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
check(count($GLOBALS['wpsa_test_hooks']) === 3, 'Expected only admin menu, assets, and authenticated export hooks.');
$report = wpsa_analyzer()->analyze('[my_container][my_text]Hello[/my_text]');
check($report['summary']['errors'] === 1 && $report['issues'][0]['tag'] === 'my_container', 'Builder metadata adds custom paired containers.');
check(wpsa_analyzer()->analyze('[vc_row]')['summary']['errors'] === 1, 'Metadata preserves default paired tags.');
check(wpsa_analyzer()->analyze('[my_image]')['summary']['errors'] === 0, 'Mapped leaf elements are not missing-close errors.');

ob_start();
wpsa_render_report($report, '<script>alert("label")</script>', 42);
$html = ob_get_clean();
check(strpos($html, '<script>') === false, 'Report label must be escaped.');
check(strpos($html, '&lt;script&gt;') !== false, 'Escaped label is visible as text.');
check(strpos($html, 'Download JSON report') !== false, 'Report export is present.');
$GLOBALS['wpsa_test_permission'] = false;
foreach (['wpsa_render_admin_page', 'wpsa_export_report'] as $function) {
    try { $function(); throw new RuntimeException('Permission gate missing.'); }
    catch (RuntimeException $error) { check($error->getMessage() === '403', 'Unauthorized access is denied.'); }
}
echo "WordPress adapter metadata, escaping, and access checks passed (stub harness).\n";
