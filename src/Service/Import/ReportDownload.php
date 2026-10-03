<?php

namespace PersianKit\Service\Import;

defined('ABSPATH') || exit;

/**
 * Downloads a source's import report as CSV, from admin-post.php.
 */
class ReportDownload
{
    public const ACTION = 'persian_kit_import_report';

    private SourceRegistry $sources;
    private ImportReport $report;

    public function __construct(SourceRegistry $sources, ImportReport $report)
    {
        $this->sources = $sources;
        $this->report = $report;
    }

    public function register(): void
    {
        add_action('admin_post_' . self::ACTION, [$this, 'handle']);
    }

    /**
     * The link, without the source, which the page appends.
     */
    public static function url(): string
    {
        return add_query_arg(['action' => self::ACTION, '_wpnonce' => wp_create_nonce(self::ACTION)], admin_url('admin-post.php'));
    }

    public function handle(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'persian-kit'), '', ['response' => 403]);
        }

        check_admin_referer(self::ACTION);

        $key = isset($_GET['source']) ? sanitize_key(wp_unslash($_GET['source'])) : '';
        $source = $this->sources->get($key);
        if ($source === null) {
            wp_die(esc_html__('No such plugin to switch from.', 'persian-kit'), '', ['response' => 404]);
        }

        $this->send($source->key() . '-switch-' . gmdate('Y-m-d') . '.csv', $this->report->csv($source));
    }

    /**
     * @codeCoverageIgnore Sends headers and exits.
     */
    protected function send(string $filename, string $csv): void
    {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a CSV file, not HTML.
        exit;
    }
}
