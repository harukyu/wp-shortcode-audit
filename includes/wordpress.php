<?php

defined('ABSPATH') || exit;

/** Build a read-only analyzer from public builder metadata, when available. */
function wpsa_analyzer(): \Nakaryu\ShortcodeAudit\Analyzer
{
    $defaults = new \Nakaryu\ShortcodeAudit\Analyzer();
    $options = [];
    $paired = [];
    $known = [];
    if (class_exists('WPBMap') && is_callable(['WPBMap', 'getAllShortCodes'])) {
        $mapping = WPBMap::getAllShortCodes();
        if (is_array($mapping)) {
            foreach ($mapping as $name => $definition) {
                if (!is_string($name) || !is_array($definition)) {
                    continue;
                }
                $known[] = $name;
                if (!empty($definition['is_container'])) {
                    $paired[] = $name;
                }
                foreach (is_array($definition['params'] ?? null) ? $definition['params'] : [] as $param) {
                    if (is_array($param) && ($param['param_name'] ?? '') === 'content' && ($param['type'] ?? '') === 'textarea_html') {
                        $paired[] = $name;
                    }
                }
            }
        }
    }
    // Add inferred paired names to core defaults rather than replacing them.
    $options['known_tags'] = $known;
    $options['additional_paired_tags'] = array_values(array_unique($paired));
    /** Customize classification for theme-specific tags. No content is changed. */
    $options = apply_filters('wp_shortcode_audit_options', $options);
    return is_array($options) ? new \Nakaryu\ShortcodeAudit\Analyzer($options) : $defaults;
}

function wpsa_register_admin_page(): void
{
    add_management_page(
        __('WP Shortcode Audit', 'wp-shortcode-audit'),
        __('Shortcode Audit', 'wp-shortcode-audit'),
        'manage_options',
        'wp-shortcode-audit',
        'wpsa_render_admin_page'
    );
}

function wpsa_enqueue_admin_assets(string $hook): void
{
    if ($hook === 'tools_page_wp-shortcode-audit') {
        wp_enqueue_style('wp-shortcode-audit-admin', plugins_url('assets/admin.css', dirname(__DIR__) . '/wp-shortcode-audit.php'), [], \Nakaryu\ShortcodeAudit\Analyzer::VERSION);
    }
}

function wpsa_render_admin_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to view this page.', 'wp-shortcode-audit'), '', ['response' => 403]);
    }
    $report = null;
    $error = '';
    $sourceLabel = '';
    $sourceId = 0;
    $snippet = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        check_admin_referer('wpsa_scan');
        $mode = isset($_POST['wpsa_mode']) && is_string($_POST['wpsa_mode']) ? sanitize_key(wp_unslash($_POST['wpsa_mode'])) : '';
        if ($mode === 'post') {
            $sourceId = isset($_POST['wpsa_post_id']) && is_scalar($_POST['wpsa_post_id']) ? absint($_POST['wpsa_post_id']) : 0;
            $post = $sourceId > 0 ? get_post($sourceId) : null;
            if (!$post || !current_user_can('edit_post', $sourceId) || !in_array($post->post_type, ['post', 'page', 'product'], true)) {
                $error = __('Choose an existing post, page, or product you can edit.', 'wp-shortcode-audit');
            } else {
                // No the_content filter, rendering, callbacks, post updates, or option writes.
                $report = wpsa_analyzer()->analyze((string) $post->post_content);
                $sourceLabel = get_the_title($sourceId);
                $report['source'] = ['post_id' => $sourceId, 'post_type' => $post->post_type];
            }
        } elseif ($mode === 'snippet') {
            if (isset($_POST['wpsa_snippet']) && is_string($_POST['wpsa_snippet'])) {
                // Keep exact syntax. It is inspected as data and escaped when displayed.
                $snippet = wp_unslash($_POST['wpsa_snippet']);
                $report = wpsa_analyzer()->analyze($snippet);
                // Avoid reflecting arbitrarily oversized bodies back into the response.
                if (strlen($snippet) > \Nakaryu\ShortcodeAudit\Analyzer::MAX_BYTES) {
                    $snippet = '';
                }
                $sourceLabel = __('Pasted snippet', 'wp-shortcode-audit');
            } else {
                $error = __('Paste shortcode content to inspect.', 'wp-shortcode-audit');
            }
        } else {
            $error = __('Choose a scan source.', 'wp-shortcode-audit');
        }
    }
    $search = isset($_GET['wpsa_search']) && is_string($_GET['wpsa_search']) ? sanitize_text_field(wp_unslash($_GET['wpsa_search'])) : '';
    $posts = get_posts([
        'post_type' => array_values(array_filter(['post', 'page', 'product'], 'post_type_exists')),
        'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
        'numberposts' => 20, 'orderby' => 'modified', 'order' => 'DESC', 's' => $search,
    ]);
    ?>
    <div class="wrap wpsa">
        <h1><?php echo esc_html__('WP Shortcode Audit', 'wp-shortcode-audit'); ?></h1>
        <p class="wpsa-intro"><?php echo esc_html__('Inspect WPBakery shortcode structure before editing or migrating content.', 'wp-shortcode-audit'); ?></p>
        <p><?php echo esc_html__('Scans read saved content only. Reports are not stored, and shortcode callbacks are never executed.', 'wp-shortcode-audit'); ?></p>
        <?php if ($error !== '') : ?>
            <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
        <?php endif; ?>
        <?php if ($report !== null) : ?>
            <?php wpsa_render_report($report, $sourceLabel, $sourceId); ?>
        <?php endif; ?>
        <div class="wpsa-sources">
            <section class="wpsa-card" aria-labelledby="wpsa-existing">
                <h2 id="wpsa-existing"><?php echo esc_html__('Inspect saved content', 'wp-shortcode-audit'); ?></h2>
                <form method="get">
                    <input type="hidden" name="page" value="wp-shortcode-audit">
                    <label for="wpsa-search"><?php echo esc_html__('Find by title or content', 'wp-shortcode-audit'); ?></label>
                    <div class="wpsa-search"><input id="wpsa-search" name="wpsa_search" type="search" value="<?php echo esc_attr($search); ?>"> <?php submit_button(__('Find', 'wp-shortcode-audit'), 'secondary', '', false); ?></div>
                </form>
                <form method="post">
                    <?php wp_nonce_field('wpsa_scan'); ?>
                    <input type="hidden" name="wpsa_mode" value="post">
                    <label for="wpsa-post"><?php echo esc_html__('Recent or matching entries (up to 20)', 'wp-shortcode-audit'); ?></label>
                    <select id="wpsa-post" name="wpsa_post_id" required>
                        <option value=""><?php echo esc_html__('Choose an entry', 'wp-shortcode-audit'); ?></option>
                        <?php foreach ($posts as $entry) : ?>
                            <?php if (!current_user_can('edit_post', $entry->ID)) { continue; } ?>
                            <option value="<?php echo esc_attr((string) $entry->ID); ?>" <?php selected($sourceId, $entry->ID); ?>><?php echo esc_html(($entry->post_title !== '' ? $entry->post_title : __('Untitled', 'wp-shortcode-audit')) . ' — ' . $entry->post_type . ' #' . $entry->ID); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php submit_button(__('Inspect entry', 'wp-shortcode-audit'), 'primary', 'wpsa_scan'); ?>
                </form>
            </section>
            <section class="wpsa-card" aria-labelledby="wpsa-pasted">
                <h2 id="wpsa-pasted"><?php echo esc_html__('Inspect a snippet', 'wp-shortcode-audit'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('wpsa_scan'); ?>
                    <input type="hidden" name="wpsa_mode" value="snippet">
                    <label for="wpsa-snippet"><?php echo esc_html__('Paste raw shortcode content (UTF-8, up to 2 MiB)', 'wp-shortcode-audit'); ?></label>
                    <textarea id="wpsa-snippet" name="wpsa_snippet" rows="9" spellcheck="false" placeholder="[vc_row][vc_column][vc_column_text]Hello[/vc_column_text][/vc_column][/vc_row]"><?php echo esc_textarea($snippet); ?></textarea>
                    <?php submit_button(__('Inspect snippet', 'wp-shortcode-audit'), 'primary', 'wpsa_scan'); ?>
                </form>
            </section>
        </div>
        <p class="wpsa-scope"><?php echo esc_html__('This is a structural aid, not a rendering or layout certification. Unknown tag pairing needs manual review. HTML attributes, comments, script/style bodies, and raw builder payloads are excluded from structural checks.', 'wp-shortcode-audit'); ?></p>
    </div>
    <?php
}

function wpsa_render_report(array $report, string $label, int $postId = 0): void
{
    $summary = $report['summary'];
    ?>
    <section class="wpsa-card wpsa-report" aria-labelledby="wpsa-report-heading">
        <h2 id="wpsa-report-heading"><?php echo esc_html__('Report', 'wp-shortcode-audit') . ': ' . esc_html($label); ?></h2>
        <?php if (!$report['complete']) : ?>
            <p class="wpsa-alert"><strong><?php echo esc_html__('Scan incomplete — review the findings below.', 'wp-shortcode-audit'); ?></strong></p>
        <?php elseif ($summary['errors'] === 0) : ?>
            <p class="wpsa-success"><?php echo esc_html__('No structural errors detected within the supported checks.', 'wp-shortcode-audit'); ?></p>
        <?php endif; ?>
        <dl class="wpsa-stats">
            <?php foreach ([__('Builder elements', 'wp-shortcode-audit') => 'builder_shortcode_count', __('Errors', 'wp-shortcode-audit') => 'errors', __('Warnings', 'wp-shortcode-audit') => 'warnings', __('Notices', 'wp-shortcode-audit') => 'notices', __('Maximum depth', 'wp-shortcode-audit') => 'max_nesting_depth'] as $name => $key) : ?>
                <div><dt><?php echo esc_html($name); ?></dt><dd><?php echo esc_html((string) $summary[$key]); ?></dd></div>
            <?php endforeach; ?>
        </dl>
        <?php if ($summary['builder_shortcode_count'] === 0 && $report['complete']) : ?>
            <p><?php echo esc_html__('No supported builder elements were found.', 'wp-shortcode-audit'); ?></p>
        <?php endif; ?>
        <?php if ($report['issues']) : ?>
            <div class="wpsa-table-wrap"><table class="widefat striped">
                <caption class="screen-reader-text"><?php echo esc_html__('Findings with source positions', 'wp-shortcode-audit'); ?></caption>
                <thead><tr><th scope="col"><?php echo esc_html__('Level', 'wp-shortcode-audit'); ?></th><th scope="col"><?php echo esc_html__('Position', 'wp-shortcode-audit'); ?></th><th scope="col"><?php echo esc_html__('Finding', 'wp-shortcode-audit'); ?></th></tr></thead>
                <tbody><?php foreach ($report['issues'] as $issue) : ?>
                    <tr><td><strong><?php echo esc_html(ucfirst($issue['severity'])); ?></strong></td><td><?php echo esc_html($issue['line'] . ':' . $issue['column']); ?></td><td><?php echo esc_html($issue['message']); ?><br><code><?php echo esc_html($issue['code'] . ($issue['tag'] !== '' ? ' · ' . $issue['tag'] : '')); ?></code></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
        <?php if ($summary['shortcode_counts']) : ?>
            <details><summary><?php echo esc_html__('Element inventory', 'wp-shortcode-audit'); ?></summary><ul>
                <?php foreach ($summary['shortcode_counts'] as $tag => $count) : ?>
                    <li><code><?php echo esc_html($tag); ?></code> × <?php echo esc_html((string) $count); ?></li>
                <?php endforeach; ?>
            </ul></details>
        <?php endif; ?>
        <form class="wpsa-export" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('wpsa_export'); ?>
            <input type="hidden" name="action" value="wpsa_export">
            <input type="hidden" name="wpsa_report" value="<?php echo esc_attr(wp_json_encode($report)); ?>">
            <?php submit_button(__('Download JSON report', 'wp-shortcode-audit'), 'secondary', '', false); ?>
            <?php if ($postId > 0) : ?>
                <a href="<?php echo esc_url(get_edit_post_link($postId, 'raw')); ?>"><?php echo esc_html__('Open entry in editor', 'wp-shortcode-audit'); ?></a>
            <?php endif; ?>
        </form>
        <p><?php echo esc_html__('The report contains tag names and positions, not the original content. Reports for saved entries include their ID and type.', 'wp-shortcode-audit'); ?></p>
    </section>
    <?php
}

function wpsa_export_report(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to export reports.', 'wp-shortcode-audit'), '', ['response' => 403]);
    }
    check_admin_referer('wpsa_export');
    $json = isset($_POST['wpsa_report']) && is_string($_POST['wpsa_report']) ? wp_unslash($_POST['wpsa_report']) : '';
    $report = strlen($json) <= 524288 ? json_decode($json, true) : null;
    if (!is_array($report) || ($report['schema_version'] ?? null) !== 1 || !isset($report['summary'], $report['issues'])) {
        wp_die(esc_html__('Invalid report.', 'wp-shortcode-audit'), '', ['response' => 400]);
    }
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="shortcode-audit.json"');
    echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
