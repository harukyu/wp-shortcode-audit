<?php

defined('ABSPATH') || exit;

/** Read-only WP-CLI commands. Run under an account with access to the database. */
final class WPSA_CLI_Command
{
    /**
     * Inspect one saved post, page, or product without rendering it.
     *
     * ## OPTIONS
     * <post-id>
     * : ID of the entry to inspect.
     * [--format=<format>]
     * : json or table. Default: table.
     * [--fail-on=<level>]
     * : error, warning, or none. Default: error.
     *
     * ## EXAMPLES
     *     wp shortcode-audit check 42 --format=json
     */
    public function check(array $args, array $assoc): void
    {
        $this->validate($assoc);
        if (count($args) !== 1 || !ctype_digit((string) $args[0]) || (int) $args[0] < 1) {
            WP_CLI::error('Provide one positive post ID.');
        }
        $post = get_post((int) $args[0]);
        if (!$post || !in_array($post->post_type, ['page', 'post', 'product'], true)) {
            WP_CLI::error('Post, page, or product not found.');
        }
        $report = wpsa_analyzer()->analyze((string) $post->post_content);
        $report['source'] = ['post_id' => (int) $post->ID, 'post_type' => $post->post_type];
        $this->display([$report], $assoc, true);
    }

    /**
     * Inspect a bounded batch of saved entries; paginate with --offset.
     *
     * ## OPTIONS
     * [--post_type=<types>]
     * : Comma-separated post,page,product. Default: page.
     * [--status=<statuses>]
     * : Comma-separated publish,draft,private,pending,future. Default: publish.
     * [--limit=<number>]
     * : 1 to 200 entries. Default: 50.
     * [--offset=<number>]
     * : Skip this many entries ordered by ID. Default: 0.
     * [--format=<format>]
     * : json or table. Default: table.
     * [--fail-on=<level>]
     * : error, warning, or none. Default: error.
     *
     * ## EXAMPLES
     *     wp shortcode-audit scan --post_type=page,product --limit=50 --format=json
     */
    public function scan(array $args, array $assoc): void
    {
        $this->validate($assoc);
        if ($args) {
            WP_CLI::error('Use named options for batch scans.');
        }
        $limit = $assoc['limit'] ?? '50';
        $offset = $assoc['offset'] ?? '0';
        if (!ctype_digit((string) $limit) || (int) $limit < 1 || (int) $limit > 200 || !ctype_digit((string) $offset)) {
            WP_CLI::error('--limit must be 1–200 and --offset must be a nonnegative integer.');
        }
        $types = explode(',', $assoc['post_type'] ?? 'page');
        $statuses = explode(',', $assoc['status'] ?? 'publish');
        if (array_diff($types, ['post', 'page', 'product']) || array_diff($statuses, ['publish', 'draft', 'private', 'pending', 'future'])) {
            WP_CLI::error('Unsupported post type or status.');
        }
        $query = new WP_Query([
            'post_type' => $types, 'post_status' => $statuses,
            'posts_per_page' => (int) $limit, 'offset' => (int) $offset,
            'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true,
        ]);
        $reports = [];
        $analyzer = wpsa_analyzer();
        foreach ($query->posts as $post) {
            $report = $analyzer->analyze((string) $post->post_content);
            $report['source'] = ['post_id' => (int) $post->ID, 'post_type' => $post->post_type];
            $reports[] = $report;
        }
        $this->display($reports, $assoc, false);
    }

    private function validate(array $assoc): void
    {
        if (!in_array($assoc['format'] ?? 'table', ['table', 'json'], true) || !in_array($assoc['fail-on'] ?? 'error', ['error', 'warning', 'none'], true)) {
            WP_CLI::error('Use --format=table|json and --fail-on=error|warning|none.');
        }
    }

    private function display(array $reports, array $assoc, bool $single): void
    {
        if (($assoc['format'] ?? 'table') === 'json') {
            WP_CLI::line(wp_json_encode($single ? $reports[0] : $reports, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $rows = [];
            foreach ($reports as $report) {
                $rows[] = [
                    'ID' => $report['source']['post_id'], 'type' => $report['source']['post_type'],
                    'elements' => $report['summary']['builder_shortcode_count'],
                    'errors' => $report['summary']['errors'], 'notices' => $report['summary']['notices'],
                    'complete' => $report['complete'] ? 'yes' : 'no',
                ];
            }
            \WP_CLI\Utils\format_items('table', $rows, ['ID', 'type', 'elements', 'errors', 'notices', 'complete']);
            foreach ($reports as $report) {
                foreach ($report['issues'] as $issue) {
                    WP_CLI::line(sprintf('#%d %s %d:%d %s — %s', $report['source']['post_id'], strtoupper($issue['severity']), $issue['line'], $issue['column'], $issue['code'], $issue['message']));
                }
            }
        }
        $exit = 0;
        foreach ($reports as $report) {
            $exit = max($exit, \Nakaryu\ShortcodeAudit\Analyzer::exitCode($report, $assoc['fail-on'] ?? 'error'));
        }
        if ($exit !== 0) {
            WP_CLI::halt($exit);
        }
    }
}
