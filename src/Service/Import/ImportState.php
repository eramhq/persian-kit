<?php

namespace PersianKit\Service\Import;

defined('ABSPATH') || exit;

/**
 * What was imported from each source, kept after the job is cleared: when,
 * by which runs, and how many of its parts finished.
 */
class ImportState
{
    public const OPTION = 'persian_kit_imports';

    /**
     * @return array{imported_at: int, run_ids: list<string>, parts_done: int, parts_total: int, undone_at: int}
     */
    public function get(string $source): array
    {
        $all = $this->load();
        $record = is_array($all['sources'][$source] ?? null) ? $all['sources'][$source] : [];

        return [
            'imported_at' => (int) ($record['imported_at'] ?? 0),
            'run_ids'     => array_values(array_filter((array) ($record['run_ids'] ?? []), 'is_string')),
            'parts_done'  => (int) ($record['parts_done'] ?? 0),
            'parts_total' => (int) ($record['parts_total'] ?? 0),
            'undone_at'   => (int) ($record['undone_at'] ?? 0),
        ];
    }

    public function record(string $source, string $runId, int $partsDone, int $partsTotal): void
    {
        $all = $this->load();
        $record = $this->get($source);

        if (!in_array($runId, $record['run_ids'], true)) {
            $record['run_ids'][] = $runId;
        }
        $record['imported_at'] = time();
        $record['parts_done'] = $partsDone;
        $record['parts_total'] = $partsTotal;
        $record['undone_at'] = 0;

        $all['sources'][$source] = $record;
        $this->save($all);
    }

    public function markUndone(string $source): void
    {
        $all = $this->load();
        $record = $this->get($source);
        $record['undone_at'] = time();
        $record['imported_at'] = 0;
        $record['parts_done'] = 0;

        $all['sources'][$source] = $record;
        $this->save($all);
    }

    public function forget(string $source): void
    {
        $all = $this->load();
        unset($all['sources'][$source]);
        $this->save($all);
    }

    /**
     * Whether a source gave posts Jalali links. Persian Kit keeps them
     * working either way; this keeps the links themselves Jalali.
     */
    public function jalaliUrls(): bool
    {
        return !empty($this->load()['jalali_urls']);
    }

    public function setJalaliUrls(bool $value): void
    {
        $all = $this->load();
        $all['jalali_urls'] = $value;
        $this->save($all);
    }

    public function stateOf(Source $source): SourceState
    {
        if ($source->isActive()) {
            return SourceState::Active;
        }

        $record = $this->get($source->key());
        if ($record['imported_at'] > 0) {
            return $record['parts_total'] > 0 && $record['parts_done'] < $record['parts_total']
                ? SourceState::PartlyImported
                : SourceState::Imported;
        }

        return $source->hasData() ? SourceState::InactiveWithData : SourceState::NoData;
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        $value = get_option(self::OPTION, []);

        return is_array($value) ? $value : [];
    }

    /**
     * @param array<string, mixed> $value
     */
    private function save(array $value): void
    {
        update_option(self::OPTION, $value, false);
    }
}
