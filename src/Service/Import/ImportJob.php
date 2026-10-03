<?php

namespace PersianKit\Service\Import;

defined('ABSPATH') || exit;

/**
 * The switch in progress: the choices made in Review, the snapshot taken
 * while the source was active, and each task's progress. Kept in one
 * option, not autoloaded, so a closed browser or a reload resumes it.
 */
final class ImportJob
{
    public const OPTION = 'persian_kit_import_job';

    /** Waiting for the source to be deactivated. */
    public const STEP_DEACTIVATE = 'deactivate';
    public const STEP_IMPORT = 'import';
    public const STEP_REPORT = 'report';

    public const RUNNING = 'running';
    public const PAUSED = 'paused';
    public const WAITING = 'waiting';
    public const DONE = 'done';

    public const TASK_PENDING = 'pending';
    public const TASK_DONE = 'done';
    public const TASK_UNAVAILABLE = 'unavailable';
    public const TASK_FAILED = 'failed';

    /**
     * @param list<string>                        $rows         Setting rows ticked in Review.
     * @param list<string>                        $taskKeys     Data tasks chosen in Review, in run order.
     * @param array<string, mixed>                $options      Other choices from Review.
     * @param array<string, array<string, mixed>> $tasks        Progress by task key: status, cursor, total, processed, reason.
     * @param array<string, mixed>                $snapshot
     * @param list<string>                        $acknowledged Checklist items ticked.
     */
    public function __construct(
        public string $runId,
        public string $source,
        public string $step = self::STEP_DEACTIVATE,
        public string $status = self::WAITING,
        public string $pausedReason = '',
        public array $rows = [],
        public array $taskKeys = [],
        public array $options = [],
        public array $tasks = [],
        public array $snapshot = [],
        public bool $backup = false,
        public array $acknowledged = [],
        public int $startedAt = 0,
        public int $updatedAt = 0,
    ) {
    }

    public static function load(): ?self
    {
        $data = get_option(self::OPTION, null);
        if (!is_array($data) || !is_string($data['run_id'] ?? null) || !is_string($data['source'] ?? null)) {
            return null;
        }

        return new self(
            $data['run_id'],
            $data['source'],
            (string) ($data['step'] ?? self::STEP_DEACTIVATE),
            (string) ($data['status'] ?? self::WAITING),
            (string) ($data['paused_reason'] ?? ''),
            array_values(array_filter((array) ($data['rows'] ?? []), 'is_string')),
            array_values(array_filter((array) ($data['task_keys'] ?? []), 'is_string')),
            (array) ($data['options'] ?? []),
            (array) ($data['tasks'] ?? []),
            (array) ($data['snapshot'] ?? []),
            !empty($data['backup']),
            array_values(array_filter((array) ($data['acknowledged'] ?? []), 'is_string')),
            (int) ($data['started_at'] ?? 0),
            (int) ($data['updated_at'] ?? 0),
        );
    }

    public static function newRunId(): string
    {
        return gmdate('Ymd') . '-' . strtolower(wp_generate_password(8, false, false));
    }

    public function save(): void
    {
        $this->updatedAt = time();
        update_option(self::OPTION, $this->toArray(), false);
    }

    public static function clear(): void
    {
        delete_option(self::OPTION);
    }

    public function isFinished(): bool
    {
        return $this->step === self::STEP_REPORT;
    }

    /**
     * Items converted so far and in all, over every task with a known total.
     *
     * @return array{processed: int, total: int, percent: int}
     */
    public function progress(): array
    {
        $processed = 0;
        $total = 0;
        foreach ($this->tasks as $task) {
            $taskTotal = max(0, (int) ($task['total'] ?? 0));
            $total += $taskTotal;
            $processed += min($taskTotal, (int) ($task['processed'] ?? 0));
        }

        $percent = $total === 0 ? ($this->isFinished() ? 100 : 0) : (int) floor($processed * 100 / $total);

        return ['processed' => $processed, 'total' => $total, 'percent' => $this->isFinished() ? 100 : min(99, $percent)];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'run_id'        => $this->runId,
            'source'        => $this->source,
            'step'          => $this->step,
            'status'        => $this->status,
            'paused_reason' => $this->pausedReason,
            'rows'          => $this->rows,
            'task_keys'     => $this->taskKeys,
            'options'       => $this->options,
            'tasks'         => $this->tasks,
            'snapshot'      => $this->snapshot,
            'backup'        => $this->backup,
            'acknowledged'  => $this->acknowledged,
            'started_at'    => $this->startedAt,
            'updated_at'    => $this->updatedAt,
        ];
    }

    /**
     * What the screen needs; the snapshot stays on the server.
     *
     * @return array<string, mixed>
     */
    public function toPublic(): array
    {
        $data = $this->toArray();
        unset($data['snapshot']);
        foreach ($data['tasks'] as $key => $task) {
            unset($data['tasks'][$key]['cursor']);
        }

        return $data + ['progress' => $this->progress()];
    }
}
