<?php

namespace PersianKit\Service\Import;

use PersianKit\Core\SettingsManager;
use PersianKit\Service\Import\Settings\SettingRow;
use PersianKit\Service\Import\Settings\SettingsImporter;
use PersianKit\Service\Import\Settings\SettingStatus;

defined('ABSPATH') || exit;

/**
 * Runs the switch: starts the job from Review's choices, converts in
 * batches that fit a time budget, and undoes it.
 *
 * Only one request works on the job at a time (the lock), so a second tab
 * or WP-CLI waits. Before every batch the source must still be ready, so
 * reactivating it pauses the job instead of converting under it.
 */
class ImportRunner
{
    public const LOCK_OPTION = 'persian_kit_import_lock';

    /** Seconds each run request works for, by default. */
    public const TIME_BUDGET = 8;

    private SourceRegistry $sources;
    private SettingsManager $settings;
    private SettingsImporter $importer;
    private ImportLog $log;
    private ImportState $state;

    public function __construct(SourceRegistry $sources, SettingsManager $settings, SettingsImporter $importer, ImportLog $log, ImportState $state)
    {
        $this->sources = $sources;
        $this->settings = $settings;
        $this->importer = $importer;
        $this->log = $log;
        $this->state = $state;
    }

    public function source(string $key): Source
    {
        $source = $this->sources->get($key);
        if ($source === null) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- REST and WP-CLI show it as text.
            throw new ImportException(ImportException::UNKNOWN_SOURCE, __('No such plugin to switch from.', 'persian-kit'), 404);
        }

        return $source;
    }

    /**
     * The setting rows Review shows, with the module rows they need.
     *
     * @return list<SettingRow>
     */
    public function settingRows(Source $source): array
    {
        $rows = $source->settingRows($this->settings);

        return array_merge($rows, $this->importer->moduleRows($rows, $this->settings->all()));
    }

    /**
     * Saves Review's choices into the job, with a snapshot while the source
     * is still active, and moves to the import once the source is ready.
     * Until then the job waits at the Deactivate step.
     *
     * @param array{rows?: list<string>, tasks?: list<string>, options?: array<string, mixed>, backup?: bool, acknowledged?: list<string>} $choices
     */
    public function start(Source $source, array $choices = []): ImportJob
    {
        $job = ImportJob::load();

        if ($job !== null && $job->source !== $source->key() && !$job->isFinished()) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- REST and WP-CLI show it as text.
            throw new ImportException(ImportException::OTHER_JOB, $this->otherJobMessage($job), 409);
        }

        $isNew = $job === null || $job->source !== $source->key() || $job->isFinished();
        if ($isNew) {
            $job = new ImportJob(ImportJob::newRunId(), $source->key(), startedAt: time());
        }

        $this->applyChoices($job, $source, $choices, $isNew);

        // Taken while the source runs: WordPress and the plugin lose some of
        // this once it is inactive. Later snapshots only add to it.
        if ($source->isActive() || $job->snapshot === []) {
            $job->snapshot = array_replace($job->snapshot, $source->snapshot());
        }

        if ($job->step === ImportJob::STEP_DEACTIVATE && $source->readyToImport() === null) {
            $this->beginImport($job, $source);
        }

        $job->save();

        return $job;
    }

    /**
     * Works on the job until the time budget runs out or it finishes.
     */
    public function run(string $owner, ?float $budget = null): ImportJob
    {
        $job = ImportJob::load();
        if ($job === null) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- REST and WP-CLI show it as text.
            throw new ImportException(ImportException::NO_JOB, __('There is no switch in progress.', 'persian-kit'), 404);
        }

        $source = $this->source($job->source);

        if ($job->isFinished()) {
            return $job;
        }

        if (!$this->acquireLock($owner)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- REST and WP-CLI show it as text.
            throw new ImportException(ImportException::LOCKED, __('The switch is running in another tab or in WP-CLI. Try again in a minute.', 'persian-kit'), 409);
        }

        try {
            if ($job->step === ImportJob::STEP_DEACTIVATE) {
                $reason = $source->readyToImport();
                if ($reason !== null) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- REST and WP-CLI show it as text.
                    throw new ImportException(ImportException::NOT_READY, $reason, 409);
                }
                $this->beginImport($job, $source);
                $job->save();
            }

            $this->work($job, $source, microtime(true) + ($budget ?? $this->timeBudget()));
        } finally {
            $this->releaseLock($owner);
        }

        return $job;
    }

    public function pause(string $reason = ''): ?ImportJob
    {
        $job = ImportJob::load();
        if ($job === null || $job->isFinished()) {
            return $job;
        }

        $job->status = ImportJob::PAUSED;
        $job->pausedReason = $reason;
        $job->save();

        return $job;
    }

    /**
     * Drops the job. What it converted stays, with its log, so undo still works.
     */
    public function cancel(): void
    {
        $job = ImportJob::load();

        if ($job !== null && $job->step === ImportJob::STEP_IMPORT) {
            $this->recordState($job, $this->source($job->source));
        }

        ImportJob::clear();
        delete_option(self::LOCK_OPTION);
    }

    /**
     * Puts back what the source's imports changed, newest first, while each
     * value is still what the import wrote. Call again with the returned
     * cursor until done.
     *
     * @return array{done: bool, cursor: int, restored: int, kept: int, run_id: string}
     */
    public function undo(Source $source, string $owner, int $cursor = 0, string $runId = '', ?float $budget = null): array
    {
        if (!$this->acquireLock($owner)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- REST and WP-CLI show it as text.
            throw new ImportException(ImportException::LOCKED, __('The switch is running in another tab or in WP-CLI. Try again in a minute.', 'persian-kit'), 409);
        }

        $runId = $runId !== '' ? $runId : 'undo-' . ImportJob::newRunId();
        $context = new ImportContext($runId, $source->key(), $this->log);
        $tasks = $this->tasksByKey($source);
        $deadline = microtime(true) + ($budget ?? $this->timeBudget());
        $restored = 0;
        $kept = 0;
        $done = false;

        try {
            do {
                $rows = $this->log->changedRows($source->key(), $cursor, 100);
                if ($rows === []) {
                    $done = true;
                    break;
                }

                foreach ($rows as $row) {
                    $cursor = $row->id;

                    // A later run changed the same field; undoing that one
                    // went back to the first original value already.
                    if ($this->log->hasNewerChange($row)) {
                        continue;
                    }

                    $outcome = $this->undoRow($context, $row, $tasks);
                    $this->log->undone($context, $row, $outcome, $outcome === ImportLog::KEPT ? __('Changed since the import; kept.', 'persian-kit') : '');
                    $outcome === ImportLog::RESTORED ? $restored++ : $kept++;
                }
            } while (microtime(true) < $deadline);
        } finally {
            $this->releaseLock($owner);
        }

        if ($done) {
            $this->log->retire($source->key());
            $this->state->markUndone($source->key());

            $job = ImportJob::load();
            if ($job !== null && $job->source === $source->key()) {
                ImportJob::clear();
            }
        }

        return ['done' => $done, 'cursor' => $cursor, 'restored' => $restored, 'kept' => $kept, 'run_id' => $runId];
    }

    /**
     * Deletes the source's undo data. The report goes with it.
     */
    public function forget(Source $source): void
    {
        $this->log->forget($source->key());
    }

    public function timeBudget(): float
    {
        /**
         * Seconds each import request works for before answering.
         *
         * @param float $seconds
         */
        return max(1.0, (float) apply_filters('persian_kit_import_time_budget', self::TIME_BUDGET));
    }

    /**
     * @return array<string, Task>
     */
    public function tasksByKey(Source $source): array
    {
        $tasks = [];
        foreach ($source->tasks() as $task) {
            $tasks[$task->key()] = $task;
        }

        return $tasks;
    }

    public function context(ImportJob $job, bool $dryRun = false): ImportContext
    {
        return new ImportContext($job->runId, $job->source, $this->log, $job->options, $job->snapshot, $dryRun);
    }

    /**
     * Choices left out keep what the job has; a new job gets Review's defaults.
     *
     * @param array{rows?: list<string>, tasks?: list<string>, options?: array<string, mixed>, backup?: bool, acknowledged?: list<string>} $choices
     */
    private function applyChoices(ImportJob $job, Source $source, array $choices, bool $isNew): void
    {
        if (isset($choices['rows'])) {
            $ids = array_map(static fn (SettingRow $row): string => $row->id, array_filter(
                $this->settingRows($source),
                static fn (SettingRow $row): bool => $row->imports()
            ));
            $job->rows = array_values(array_intersect($choices['rows'], $ids));
        } elseif ($isNew) {
            // Nothing chosen yet: the rows Review ticks by default.
            $job->rows = array_map(static fn (SettingRow $row): string => $row->id, array_values(array_filter(
                $this->settingRows($source),
                static fn (SettingRow $row): bool => $row->imports() && $row->ticked
            )));
        }

        $taskKeys = array_keys($this->tasksByKey($source));
        if (isset($choices['tasks'])) {
            $job->taskKeys = array_values(array_intersect($taskKeys, $choices['tasks']));
        } elseif ($isNew) {
            $job->taskKeys = $taskKeys;
        }

        if (isset($choices['options'])) {
            $job->options = array_replace($job->options, $choices['options']);
        }
        if (isset($choices['backup'])) {
            $job->backup = (bool) $choices['backup'];
        }
        if (isset($choices['acknowledged'])) {
            $job->acknowledged = array_values(array_unique(array_filter($choices['acknowledged'], 'is_string')));
        }
    }

    private function beginImport(ImportJob $job, Source $source): void
    {
        $this->log->install();

        $context = $this->context($job);
        $tasks = $this->tasksByKey($source);

        $progress = [SettingsImporter::TASK => ['status' => ImportJob::TASK_PENDING, 'cursor' => null, 'total' => 1, 'processed' => 0, 'reason' => '']];
        foreach ($job->taskKeys as $key) {
            if (!isset($tasks[$key])) {
                continue;
            }

            $reason = $tasks[$key]->available();
            $progress[$key] = [
                'status'    => $reason === null ? ImportJob::TASK_PENDING : ImportJob::TASK_UNAVAILABLE,
                'cursor'    => null,
                'total'     => $reason === null ? $tasks[$key]->count($context) : 0,
                'processed' => 0,
                'reason'    => (string) $reason,
            ];
        }

        $job->tasks = $progress;
        $job->step = ImportJob::STEP_IMPORT;
        $job->status = ImportJob::RUNNING;
        $job->pausedReason = '';
    }

    private function work(ImportJob $job, Source $source, float $deadline): void
    {
        $tasks = $this->tasksByKey($source);
        $context = $this->context($job);
        $job->status = ImportJob::RUNNING;
        $job->pausedReason = '';

        foreach ($job->tasks as $key => $progress) {
            if (($progress['status'] ?? '') !== ImportJob::TASK_PENDING) {
                continue;
            }

            while (true) {
                $task = $tasks[$key] ?? null;

                // Settings and most tasks wait for the source to be inactive,
                // so the two plugins never convert at once.
                if ($task === null || $task->requiresInactive()) {
                    $reason = $source->readyToImport();
                    if ($reason !== null) {
                        $job->status = ImportJob::PAUSED;
                        $job->pausedReason = $reason;
                        $job->save();

                        return;
                    }
                }

                if (microtime(true) >= $deadline) {
                    $job->save();

                    return;
                }

                try {
                    if ($key === SettingsImporter::TASK) {
                        $this->importSettings($job, $source, $context);
                        $job->tasks[$key]['processed'] = 1;
                        $job->tasks[$key]['status'] = ImportJob::TASK_DONE;
                    } elseif ($task !== null) {
                        $size = $task instanceof AbstractTask ? $task->batchSize() : 50;
                        $batch = $task->run($context, $job->tasks[$key]['cursor'] ?? null, $size);
                        $job->tasks[$key]['cursor'] = $batch->cursor;
                        $job->tasks[$key]['processed'] = (int) $job->tasks[$key]['processed'] + $batch->processed;
                        if ($batch->done) {
                            $job->tasks[$key]['status'] = ImportJob::TASK_DONE;
                            $job->tasks[$key]['total'] = max((int) $job->tasks[$key]['total'], (int) $job->tasks[$key]['processed']);
                        }
                    }
                } catch (\Throwable $error) {
                    $this->log->attention(
                        $context,
                        $key,
                        'task',
                        0,
                        '',
                        /* translators: %s: error message. */
                        sprintf(__('Stopped with an error: %s', 'persian-kit'), $error->getMessage())
                    );
                    $job->tasks[$key]['status'] = ImportJob::TASK_FAILED;
                    $job->tasks[$key]['reason'] = $error->getMessage();
                }

                $job->save();

                if (($job->tasks[$key]['status'] ?? '') !== ImportJob::TASK_PENDING) {
                    break;
                }
            }
        }

        $job->step = ImportJob::STEP_REPORT;
        $job->status = ImportJob::DONE;
        $this->recordState($job, $source);
        $job->save();
    }

    private function importSettings(ImportJob $job, Source $source, ImportContext $context): void
    {
        $rows = $this->settingRows($source);
        $chosen = array_values(array_filter($rows, static fn (SettingRow $row): bool => in_array($row->id, $job->rows, true)));

        $this->importer->apply($chosen, $context);

        foreach ($rows as $row) {
            if ($row->status === SettingStatus::NotYet) {
                $this->log->notImported($context, SettingsImporter::TASK, $row->sourceLabel, $row->reason);
            }
        }

        foreach ($chosen as $row) {
            if (array_key_exists('date_conversion.jalali_permalinks', $row->changes)) {
                $this->state->setJalaliUrls(true);
            }
        }
    }

    private function recordState(ImportJob $job, Source $source): void
    {
        $total = count($job->tasks);
        $done = count(array_filter($job->tasks, static fn (array $task): bool => ($task['status'] ?? '') === ImportJob::TASK_DONE));

        $this->state->record($source->key(), $job->runId, $done, $total);
    }

    /**
     * @param array<string, Task> $tasks
     */
    private function undoRow(ImportContext $context, LogRow $row, array $tasks): string
    {
        try {
            if ($row->task === SettingsImporter::TASK) {
                return $this->importer->undo($row) ? ImportLog::RESTORED : ImportLog::KEPT;
            }

            $task = $tasks[$row->task] ?? null;

            return $task === null ? ImportLog::KEPT : $task->undo($context, $row);
        } catch (\Throwable $error) {
            return ImportLog::KEPT;
        }
    }

    private function otherJobMessage(ImportJob $job): string
    {
        $other = $this->sources->get($job->source);

        return sprintf(
            /* translators: %s: plugin name, such as Parsi Date. */
            __('Finish or cancel the switch from %s first.', 'persian-kit'),
            $other !== null ? $other->name() : $job->source
        );
    }

    private function acquireLock(string $owner): bool
    {
        $lock = ['owner' => $owner, 'expires' => time() + (int) ceil($this->timeBudget()) + 30];

        if (add_option(self::LOCK_OPTION, $lock, '', false)) {
            return true;
        }

        wp_cache_delete(self::LOCK_OPTION, 'options');
        $current = get_option(self::LOCK_OPTION);
        if (!is_array($current) || (int) ($current['expires'] ?? 0) < time() || ($current['owner'] ?? '') === $owner) {
            update_option(self::LOCK_OPTION, $lock, false);

            return true;
        }

        return false;
    }

    private function releaseLock(string $owner): void
    {
        wp_cache_delete(self::LOCK_OPTION, 'options');
        $current = get_option(self::LOCK_OPTION);
        if (is_array($current) && ($current['owner'] ?? '') === $owner) {
            delete_option(self::LOCK_OPTION);
        }
    }
}
