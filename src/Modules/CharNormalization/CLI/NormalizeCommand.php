<?php

namespace PersianKit\Modules\CharNormalization\CLI;

use PersianKit\Bootstrap;
use PersianKit\Modules\CharNormalization\NormalizationJobManager;

defined('ABSPATH') || exit;

class NormalizeCommand
{
    private NormalizationJobManager $jobs;

    /**
     * WP-CLI constructs the command with no arguments when it runs, after
     * the plugin's services are registered.
     */
    public function __construct(?NormalizationJobManager $jobs = null)
    {
        $this->jobs = $jobs ?? Bootstrap::get(NormalizationJobManager::class);
    }

    /**
     * Normalize Arabic characters to Persian in post content.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Count the posts the current settings would change, without saving.
     *
     * [--post-type=<types>]
     * : Comma-separated post types.
     * ---
     * default: post,page
     * ---
     *
     * [--batch-size=<size>]
     * : Posts per batch (1–500).
     * ---
     * default: 100
     * ---
     *
     * [--restart]
     * : Clear the saved job and cursor and start fresh.
     *
     * ## EXAMPLES
     *
     *     wp persian-kit normalize --dry-run
     *     wp persian-kit normalize --post-type=post,page,product --batch-size=50
     *     wp persian-kit normalize --restart
     *
     * @param array<int, string>    $args      Positional arguments.
     * @param array<string, mixed> $assocArgs Associative arguments.
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $dryRun    = \WP_CLI\Utils\get_flag_value($assocArgs, 'dry-run', false);
        $postTypes = explode(',', \WP_CLI\Utils\get_flag_value($assocArgs, 'post-type', 'post,page'));
        $batchSize = max(1, (int) \WP_CLI\Utils\get_flag_value($assocArgs, 'batch-size', 100));
        $restart   = \WP_CLI\Utils\get_flag_value($assocArgs, 'restart', false);

        if ($restart) {
            $this->jobs->restart();
            \WP_CLI::log('Job cleared. Starting fresh.');
        }

        if ($dryRun) {
            $this->dryRun($postTypes, $batchSize);
            return;
        }

        $this->run($postTypes, $batchSize);
    }

    /**
     * Counts the posts the current settings would change, without saving.
     *
     * @param array<int, string> $postTypes
     */
    private function dryRun(array $postTypes, int $batchSize): void
    {
        \WP_CLI::log('Counting posts the current settings would change...');

        $counts = [];
        $cursor = 0;

        do {
            $data = $this->jobs->preview($postTypes, $cursor, $batchSize);
            foreach ($data['counts'] as $type => $count) {
                $counts[$type] = ($counts[$type] ?? 0) + $count;
            }
            $cursor = (int) $data['last_id'];
        } while ($data['has_more']);

        $total = array_sum($counts);

        $tableData = [];
        foreach ($counts as $type => $count) {
            $tableData[] = ['Post Type' => $type, 'Affected' => $count];
        }

        \WP_CLI\Utils\format_items('table', $tableData, ['Post Type', 'Affected']);
        \WP_CLI::log(sprintf('Total: %d posts need normalization.', $total));
    }

    /**
     * Runs the batches through the job manager, so the settings screen shows
     * the same progress and can resume a run the CLI left unfinished.
     *
     * @param array<int, string> $postTypes
     */
    private function run(array $postTypes, int $batchSize): void
    {
        $status = $this->jobs->status($postTypes);
        $processed = ($status['job']['status'] ?? '') === 'running' ? (int) ($status['job']['processed'] ?? 0) : 0;

        if ($status['is_resuming']) {
            \WP_CLI::log(sprintf('Resuming from post ID %d...', $status['cursor']));
        }

        $progress = \WP_CLI\Utils\make_progress_bar('Normalizing posts', 0);

        do {
            $data = $this->jobs->runBatch($postTypes, $batchSize);
            $job = $data['job'];

            $progress->tick(max(0, (int) $job['processed'] - $processed));
            $processed = (int) $job['processed'];
        } while ($data['has_more']);

        $progress->finish();

        \WP_CLI::success(sprintf(
            'Done! %d posts processed, %d modified.',
            (int) $job['processed'],
            (int) $job['modified']
        ));
    }
}
