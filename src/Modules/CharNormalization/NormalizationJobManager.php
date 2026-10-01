<?php

namespace PersianKit\Modules\CharNormalization;

defined('ABSPATH') || exit;

class NormalizationJobManager
{
    public const STATE_OPTION = 'persian_kit_normalize_job';

    private BatchMigrator $migrator;

    public function __construct(BatchMigrator $migrator)
    {
        $this->migrator = $migrator;
    }

    /**
     * @param array<mixed> $postTypes
     * @return array<string, mixed>
     */
    public function runBatch(array $postTypes, int $batchSize): array
    {
        $postTypes = $this->normalizePostTypes($postTypes);
        $batchSize = $this->normalizeBatchSize($batchSize);

        $state = $this->getState();
        $cursor = $this->migrator->getCursor();
        $wasRunning = ($state['status'] ?? '') === 'running';
        $isResuming = $cursor > 0 || $wasRunning;

        // The cursor belongs to the post types the job started with.
        if ($wasRunning && is_array($state['post_types'] ?? null) && $state['post_types'] !== []) {
            $postTypes = $this->normalizePostTypes($state['post_types']);
        }

        if (($state['status'] ?? '') !== 'running') {
            $state = [
                'status'      => 'running',
                'post_types'  => $postTypes,
                'batch_size'  => $batchSize,
                'processed'   => $isResuming ? (int) ($state['processed'] ?? 0) : 0,
                'modified'    => $isResuming ? (int) ($state['modified'] ?? 0) : 0,
                'last_id'     => $cursor,
                'counts'      => $this->migrator->countAffected($postTypes),
                'started_at'  => $isResuming ? (int) ($state['started_at'] ?? time()) : time(),
                'updated_at'  => time(),
            ];
        }

        $result = $this->migrator->processBatch($postTypes, $batchSize);

        $state['processed'] = (int) ($state['processed'] ?? 0) + $result->processed;
        $state['modified'] = (int) ($state['modified'] ?? 0) + $result->modified;
        $state['last_id'] = $result->lastId;
        $state['updated_at'] = time();
        $state['post_types'] = $postTypes;
        $state['batch_size'] = $batchSize;

        if ($result->hasMore) {
            $state['status'] = 'running';
            $this->saveState($state);
        } else {
            $this->migrator->clearCursor();
            $state['status'] = 'completed';
            $this->saveState($state);
        }

        return [
            'cursor'      => $this->migrator->getCursor(),
            'is_resuming' => $isResuming,
            'counts'      => $state['counts'] ?? [],
            'job'         => $state,
            'has_more'    => $result->hasMore,
        ];
    }

    /**
     * One dry-run batch after $cursor: how many posts in it the current
     * settings would change, by post type. Stateless, so it never touches a
     * running job; the caller passes back last_id until has_more is false.
     *
     * @param array<mixed> $postTypes
     * @return array<string, mixed>
     */
    public function preview(array $postTypes, int $cursor, int $batchSize): array
    {
        $postTypes = $this->normalizePostTypes($postTypes);
        $result = $this->migrator->processBatch($postTypes, $this->normalizeBatchSize($batchSize), true, max(0, $cursor));

        return [
            'counts'    => array_replace(array_fill_keys($postTypes, 0), $result->modifiedByType),
            'processed' => $result->processed,
            'last_id'   => $result->lastId,
            'has_more'  => $result->hasMore,
        ];
    }

    /**
     * The saved job, without counting posts: counting scans every post, so it
     * runs only when a job starts or the user asks for a preview.
     *
     * @param array<mixed> $postTypes
     * @return array<string, mixed>
     */
    public function status(array $postTypes): array
    {
        $state = $this->getState();

        $counts = $state['counts'] ?? [];
        $cursor = $this->migrator->getCursor();
        $job = $state;

        if ($job === []) {
            $job = [
                'status'     => 'idle',
                'processed'  => 0,
                'modified'   => 0,
                'last_id'    => $cursor,
                'counts'     => $counts,
            ];
        }

        return [
            'cursor'      => $cursor,
            'is_resuming' => $cursor > 0 || (($job['status'] ?? '') === 'running'),
            'counts'      => $counts,
            'job'         => $job,
        ];
    }

    public function restart(): void
    {
        $this->migrator->clearCursor();
        $this->deleteState();
    }

    /**
     * @return array<string, mixed>
     */
    private function getState(): array
    {
        $state = get_option(self::STATE_OPTION, []);

        return is_array($state) ? $state : [];
    }

    /**
     * @param array<string, mixed> $state
     */
    private function saveState(array $state): void
    {
        update_option(self::STATE_OPTION, $state, false);
    }

    private function deleteState(): void
    {
        delete_option(self::STATE_OPTION);
    }

    /**
     * Post types arrive from REST and WP-CLI input, so non-strings are dropped.
     *
     * @param array<mixed> $postTypes
     * @return array<string>
     */
    private function normalizePostTypes(array $postTypes): array
    {
        $postTypes = array_values(array_filter(array_map(
            static fn ($postType) => is_string($postType) ? $postType : null,
            $postTypes
        )));

        return $postTypes !== [] ? $postTypes : array_values(get_post_types(['public' => true]));
    }

    private function normalizeBatchSize(int $batchSize): int
    {
        return max(1, min(500, $batchSize));
    }
}
