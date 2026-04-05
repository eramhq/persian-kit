<?php

namespace PersianKit\Modules\CharNormalization\CLI;

use PersianKit\Modules\CharNormalization\BatchMigrator;

defined('ABSPATH') || exit;

class NormalizeCommand
{
    private BatchMigrator $migrator;

    public function __construct(BatchMigrator $migrator)
    {
        $this->migrator = $migrator;
    }

    /**
     * Normalize Arabic characters to Persian in post content.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Count affected posts without modifying.
     *
     * [--post-type=<types>]
     * : Comma-separated post types.
     * ---
     * default: post,page
     * ---
     *
     * [--batch-size=<size>]
     * : Posts per batch.
     * ---
     * default: 100
     * ---
     *
     * [--restart]
     * : Clear saved cursor and start fresh.
     *
     * ## EXAMPLES
     *
     *     wp persian-kit normalize --dry-run
     *     wp persian-kit normalize --post-type=post,page,product --batch-size=50
     *     wp persian-kit normalize --restart
     *
     * @param array $args       Positional arguments.
     * @param array $assocArgs  Associative arguments.
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $dryRun    = \WP_CLI\Utils\get_flag_value($assocArgs, 'dry-run', false);
        $postTypes = explode(',', \WP_CLI\Utils\get_flag_value($assocArgs, 'post-type', 'post,page'));
        $batchSize = (int) \WP_CLI\Utils\get_flag_value($assocArgs, 'batch-size', 100);
        $restart   = \WP_CLI\Utils\get_flag_value($assocArgs, 'restart', false);

        if ($restart) {
            $this->migrator->clearCursor();
            \WP_CLI::log('Cursor cleared. Starting fresh.');
        }

        if ($dryRun) {
            $this->dryRun($postTypes);
            return;
        }

        $this->run($postTypes, $batchSize);
    }

    private function dryRun(array $postTypes): void
    {
        \WP_CLI::log('Counting posts with Arabic characters...');

        $counts = $this->migrator->countAffected($postTypes);
        $total  = array_sum($counts);

        $tableData = [];
        foreach ($counts as $type => $count) {
            $tableData[] = ['Post Type' => $type, 'Affected' => $count];
        }

        \WP_CLI\Utils\format_items('table', $tableData, ['Post Type', 'Affected']);
        \WP_CLI::log(sprintf('Total: %d posts need normalization.', $total));
    }

    private function run(array $postTypes, int $batchSize): void
    {
        $cursor = $this->migrator->getCursor();
        if ($cursor > 0) {
            \WP_CLI::log(sprintf('Resuming from post ID %d...', $cursor));
        }

        $totalProcessed = 0;
        $totalModified  = 0;

        $progress = \WP_CLI\Utils\make_progress_bar('Normalizing posts', 0);

        do {
            $result = $this->migrator->processBatch($postTypes, $batchSize);

            $totalProcessed += $result->processed;
            $totalModified  += $result->modified;

            $progress->tick($result->processed, sprintf(
                'Processed %d posts (%d modified)...',
                $totalProcessed,
                $totalModified
            ));
        } while ($result->hasMore);

        $progress->finish();

        $this->migrator->clearCursor();

        \WP_CLI::success(sprintf(
            'Done! %d posts processed, %d modified.',
            $totalProcessed,
            $totalModified
        ));
    }
}
