<?php

namespace PersianKit\Service\Import;

use PersianKit\Service\Import\Settings\SettingsImporter;

defined('ABSPATH') || exit;

/**
 * What the Tools tab shows: each source's state, and Review's rows, links,
 * data and checklist. Read-only; it works while the source is active.
 */
class ImportReview
{
    private const SAMPLES = 3;

    private SourceRegistry $sources;
    private ImportRunner $runner;
    private SettingsImporter $importer;
    private ImportState $state;
    private ImportLog $log;

    public function __construct(SourceRegistry $sources, ImportRunner $runner, SettingsImporter $importer, ImportState $state, ImportLog $log)
    {
        $this->sources = $sources;
        $this->runner = $runner;
        $this->importer = $importer;
        $this->state = $state;
        $this->log = $log;
    }

    /**
     * Every source this site used, with its state. Sources with no data
     * are left out.
     *
     * @return list<array<string, mixed>>
     */
    public function sources(): array
    {
        $job = ImportJob::load();
        $list = [];

        foreach ($this->sources->all() as $source) {
            $state = $this->state->stateOf($source);
            $hasJob = $job !== null && $job->source === $source->key();
            if ($state === SourceState::NoData && !$hasJob) {
                continue;
            }

            $list[] = $this->summary($source, $state, $hasJob ? $job : null);
        }

        return $list;
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Source $source, ?SourceState $state = null, ?ImportJob $job = null): array
    {
        $state ??= $this->state->stateOf($source);
        $record = $this->state->get($source->key());

        return [
            'key'            => $source->key(),
            'name'           => $source->name(),
            'state'          => $state->value,
            'state_label'    => $this->stateLabel($state, $record),
            'active'         => $source->isActive(),
            'network_active' => $source->isNetworkActive(),
            'ready'          => $source->readyToImport(),
            'imported_at'    => $record['imported_at'],
            'parts'          => ['done' => $record['parts_done'], 'total' => $record['parts_total']],
            'can_undo'       => $this->log->hasChanges($source->key()),
            'deactivate'     => $this->deactivate($source),
            'job'            => $job?->toPublic(),
            'cli'            => 'wp persian-kit import ' . $source->key(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function review(Source $source, bool $rescan = false): array
    {
        $job = ImportJob::load();
        $job = $job !== null && $job->source === $source->key() ? $job : null;
        $snapshot = $job !== null && $job->snapshot !== [] ? $job->snapshot : $source->snapshot();
        $context = new ImportContext('review', $source->key(), $this->log, $job->options ?? [], $snapshot, true);

        $tasks = [];
        foreach ($source->tasks() as $task) {
            $reason = $task->available();
            $tasks[] = [
                'key'               => $task->key(),
                'label'             => $task->label(),
                'available'         => $reason === null,
                'reason'            => (string) $reason,
                'requires_inactive' => $task->requiresInactive(),
                'count'             => $reason === null ? $task->count($context) : 0,
                'samples'           => $reason === null ? $task->preview($context, self::SAMPLES) : [],
                'options'           => $reason === null && $task instanceof HasReviewOptions ? $task->reviewOptions($context) : null,
            ];
        }

        return [
            'source'    => $this->summary($source, null, $job),
            'rows'      => $this->importer->review($this->runner->settingRows($source)),
            'notes'     => $source instanceof HasReviewNotes ? $source->reviewNotes($snapshot) : [],
            'tasks'     => $tasks,
            'checklist'    => array_map(static fn (ChecklistItem $item): array => $item->toArray(), $source->checklist($snapshot + ['rescan' => $rescan])),
            'undo_warning' => $source instanceof HasUndoWarning ? $source->undoWarning() : '',
        ];
    }

    /**
     * Count or preview one task, as Review loads them on demand.
     *
     * @return array<string, mixed>
     */
    public function task(Source $source, string $key, int $samples): array
    {
        $task = $this->runner->tasksByKey($source)[$key] ?? null;
        if ($task === null) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- REST and WP-CLI show it as text.
            throw new ImportException('persian_kit_import_unknown_task', __('No such data to convert.', 'persian-kit'), 404);
        }

        $job = ImportJob::load();
        $job = $job !== null && $job->source === $source->key() ? $job : null;
        $snapshot = $job !== null && $job->snapshot !== [] ? $job->snapshot : $source->snapshot();
        $context = new ImportContext('review', $source->key(), $this->log, $job->options ?? [], $snapshot, true);
        $reason = $task->available();

        return [
            'key'     => $key,
            'reason'  => (string) $reason,
            'count'   => $reason === null ? $task->count($context) : 0,
            'samples' => $reason === null && $samples > 0 ? $task->preview($context, $samples) : [],
        ];
    }

    /**
     * WordPress's deactivate link, or why this user can't use it.
     *
     * @return array{url: ?string, network: bool, message: string}
     */
    private function deactivate(Source $source): array
    {
        $url = $source instanceof AbstractSource && $source->isActive() ? $source->deactivateUrl() : null;
        $network = $source->isNetworkActive();
        $message = '';

        if ($source->isActive() && $url === null) {
            $message = $network
                ? __('It is active for the whole network. Ask your network admin to deactivate it.', 'persian-kit')
                : __('You can not deactivate plugins on this site. Ask an administrator.', 'persian-kit');
        }

        return ['url' => $url, 'network' => $network, 'message' => $message];
    }

    /**
     * @param array{imported_at: int, run_ids: list<string>, parts_done: int, parts_total: int, undone_at: int} $record
     */
    private function stateLabel(SourceState $state, array $record): string
    {
        return match ($state) {
            SourceState::Active           => __('Active', 'persian-kit'),
            SourceState::InactiveWithData => __('Inactive, its data is still here', 'persian-kit'),
            SourceState::PartlyImported   => sprintf(
                /* translators: 1: parts imported, 2: all parts. */
                __('Partly imported (%1$s of %2$s parts)', 'persian-kit'),
                number_format_i18n($record['parts_done']),
                number_format_i18n($record['parts_total'])
            ),
            /* translators: %s: date of the import. */
            SourceState::Imported         => sprintf(__('Imported on %s', 'persian-kit'), wp_date((string) get_option('date_format'), $record['imported_at'])),
            SourceState::NoData           => __('No data', 'persian-kit'),
        };
    }
}
