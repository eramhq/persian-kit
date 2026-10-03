<?php

namespace PersianKit\Service\Import\Cli;

use PersianKit\Bootstrap;
use PersianKit\Service\Import\ImportException;
use PersianKit\Service\Import\ImportJob;
use PersianKit\Service\Import\ImportReport;
use PersianKit\Service\Import\ImportReview;
use PersianKit\Service\Import\ImportRunner;
use PersianKit\Service\Import\Source;

defined('ABSPATH') || exit;

/**
 * Switches from Parsi Date, Persian WooCommerce or its shipping plugin, as
 * the Tools tab does: for sites whose REST API is blocked, and big stores.
 */
class ImportCommand
{
    private const OWNER = 'cli';

    private ImportRunner $runner;
    private ImportReview $review;
    private ImportReport $report;

    /**
     * WP-CLI constructs the command with no arguments when it runs, after
     * the plugin's services are registered.
     */
    public function __construct(?ImportRunner $runner = null, ?ImportReview $review = null, ?ImportReport $report = null)
    {
        $this->runner = $runner ?? Bootstrap::get(ImportRunner::class);
        $this->review = $review ?? Bootstrap::get(ImportReview::class);
        $this->report = $report ?? Bootstrap::get(ImportReport::class);
    }

    /**
     * Switch to Persian Kit from another Persian plugin.
     *
     * Imports its settings and converts its data. Deactivate the plugin
     * first; nothing changes while it is active.
     *
     * ## OPTIONS
     *
     * <source>
     * : The plugin to switch from: wp-parsidate, persian-woocommerce or
     * persian-woocommerce-shipping. Or "list" to list them.
     *
     * [--dry-run]
     * : Show what would be imported and converted, without changing anything.
     *
     * [--tasks=<tasks>]
     * : Comma-separated data to convert. Default: all of it.
     *
     * [--skip-settings]
     * : Convert data only; leave Persian Kit's settings as they are.
     *
     * [--status-map=<map>]
     * : Where the shipping plugin's order statuses go, as
     * wc-pws-packaged:processing,wc-pws-courier:completed. Default: processing.
     *
     * [--no-district-line]
     * : Don't put the district into the empty second address line.
     *
     * [--yes]
     * : Don't ask for confirmation.
     *
     * [--undo]
     * : Put back what the imports from this plugin changed.
     *
     * [--report=<file>]
     * : Write the report of this plugin's imports to a CSV file.
     *
     * ## EXAMPLES
     *
     *     wp persian-kit import list
     *     wp persian-kit import wp-parsidate --dry-run
     *     wp persian-kit import persian-woocommerce-shipping --status-map=wc-pws-packaged:processing --yes
     *     wp persian-kit import persian-woocommerce-shipping --report=switch.csv
     *     wp persian-kit import wp-parsidate --undo
     *
     * @param array<int, string>   $args      Positional arguments.
     * @param array<string, mixed> $assocArgs Associative arguments.
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $key = $args[0] ?? 'list';

        if ($key === 'list') {
            $this->listSources();
            return;
        }

        try {
            $source = $this->runner->source($key);

            $report = \WP_CLI\Utils\get_flag_value($assocArgs, 'report', null);
            if (is_string($report) && $report !== '') {
                $this->writeReport($source, $report);
                return;
            }

            if (\WP_CLI\Utils\get_flag_value($assocArgs, 'undo', false)) {
                \WP_CLI::confirm(sprintf('Put back what the imports from %s changed?', $source->name()), $assocArgs);
                $this->undo($source);
                return;
            }

            if (\WP_CLI\Utils\get_flag_value($assocArgs, 'dry-run', false)) {
                $this->dryRun($source);
                return;
            }

            $this->import($source, $assocArgs);
        } catch (ImportException $error) {
            \WP_CLI::error($error->getMessage());
        }
    }

    private function listSources(): void
    {
        $items = array_map(static fn (array $source): array => [
            'source' => $source['key'],
            'name'   => $source['name'],
            'state'  => $source['state_label'],
        ], $this->review->sources());

        if ($items === []) {
            \WP_CLI::log('No other Persian plugin left settings or data on this site.');
            return;
        }

        \WP_CLI\Utils\format_items('table', $items, ['source', 'name', 'state']);
    }

    private function dryRun(Source $source): void
    {
        $review = $this->review->review($source);

        \WP_CLI::log('Settings:');
        \WP_CLI\Utils\format_items('table', array_map(static fn (array $row): array => [
            'setting'     => $row['source_label'],
            'persian kit' => $row['target_label'],
            'status'      => $row['status_label'],
            'import'      => $row['imports'] ? ($row['no_change'] ? 'no change' : ($row['ticked'] ? 'yes' : 'no')) : '-',
            'reason'      => $row['reason'],
        ], $review['rows']), ['setting', 'persian kit', 'status', 'import', 'reason']);

        \WP_CLI::log('Data:');
        \WP_CLI\Utils\format_items('table', array_map(static fn (array $task): array => [
            'task'  => $task['key'],
            'items' => $task['available'] ? (string) $task['count'] : $task['reason'],
        ], $review['tasks']), ['task', 'items']);

        foreach ($review['checklist'] as $item) {
            \WP_CLI::warning($item['title'] . ': ' . $item['description']);
            foreach ($item['entries'] as $entry) {
                \WP_CLI::log('  - ' . $entry['label'] . (isset($entry['detail']) ? ' (' . $entry['detail'] . ')' : ''));
            }
        }

        $ready = $source->readyToImport();
        if ($ready !== null) {
            \WP_CLI::warning($ready);
        }
    }

    /**
     * @param array<string, mixed> $assocArgs
     */
    private function import(Source $source, array $assocArgs): void
    {
        $choices = [];

        if (\WP_CLI\Utils\get_flag_value($assocArgs, 'skip-settings', false)) {
            $choices['rows'] = [];
        }

        $tasks = \WP_CLI\Utils\get_flag_value($assocArgs, 'tasks', null);
        if (is_string($tasks) && $tasks !== '') {
            $choices['tasks'] = array_values(array_filter(array_map('trim', explode(',', $tasks))));
        }

        $options = [];
        $map = \WP_CLI\Utils\get_flag_value($assocArgs, 'status-map', null);
        if (is_string($map) && $map !== '') {
            $options['status_map'] = self::parseStatusMap($map);
        }
        // WP-CLI passes --no-district-line as is, or as district-line=false.
        if (\WP_CLI\Utils\get_flag_value($assocArgs, 'no-district-line', false) || \WP_CLI\Utils\get_flag_value($assocArgs, 'district-line', true) === false) {
            $options['district_line'] = false;
        }
        if ($options !== []) {
            $choices['options'] = $options;
        }

        $ready = $source->readyToImport();
        if ($ready !== null) {
            \WP_CLI::error($ready);
        }

        \WP_CLI::confirm(sprintf('Switch from %s now? Back up the database first.', $source->name()), $assocArgs);

        $job = $this->runner->start($source, $choices + ['backup' => true]);
        $progress = \WP_CLI\Utils\make_progress_bar('Switching', max(1, $job->progress()['total']));
        $done = 0;

        while (!$job->isFinished()) {
            $job = $this->runner->run(self::OWNER);
            $progress->tick(max(0, $job->progress()['processed'] - $done));
            $done = $job->progress()['processed'];

            if ($job->status === ImportJob::PAUSED) {
                $progress->finish();
                \WP_CLI::error($job->pausedReason !== '' ? $job->pausedReason : 'Paused.');
            }
        }
        $progress->finish();

        $counts = $this->report->page($source, null, 1, 1)['counts'];
        \WP_CLI::success(sprintf(
            'Done: %d converted, %d not imported, %d need attention. See: wp persian-kit import %s --report=switch.csv',
            $counts['changed'],
            $counts['not_imported'],
            $counts['attention'],
            $source->key()
        ));
    }

    private function undo(Source $source): void
    {
        $cursor = 0;
        $runId = '';
        $restored = 0;
        $kept = 0;

        do {
            $result = $this->runner->undo($source, self::OWNER, $cursor, $runId);
            $cursor = $result['cursor'];
            $runId = $result['run_id'];
            $restored += $result['restored'];
            $kept += $result['kept'];
        } while (!$result['done']);

        \WP_CLI::success(sprintf('Undone: %d put back, %d kept because they changed after the import.', $restored, $kept));
    }

    private function writeReport(Source $source, string $file): void
    {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- WP-CLI writes the file its user named.
        if (file_put_contents($file, $this->report->csv($source)) === false) {
            \WP_CLI::error(sprintf('Could not write %s.', $file));
        }

        \WP_CLI::success(sprintf('Report written to %s.', $file));
    }

    /**
     * "wc-pws-packaged:processing,wc-pws-courier:completed" as a map. A
     * target without the wc- prefix gets it.
     *
     * @return array<string, string>
     */
    public static function parseStatusMap(string $map): array
    {
        $parsed = [];
        foreach (explode(',', $map) as $pair) {
            $parts = array_map('trim', explode(':', $pair, 2));
            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                continue;
            }

            $from = str_starts_with($parts[0], 'wc-') ? $parts[0] : 'wc-' . $parts[0];
            $to = str_starts_with($parts[1], 'wc-') ? $parts[1] : 'wc-' . $parts[1];
            $parsed[sanitize_key($from)] = sanitize_key($to);
        }

        return $parsed;
    }
}
