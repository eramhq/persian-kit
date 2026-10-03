<?php

namespace PersianKit\Tests\Integration\Support;

use PersianKit\Service\Import\AbstractTask;
use PersianKit\Service\Import\ImportContext;
use PersianKit\Service\Import\ImportLog;
use PersianKit\Service\Import\LogRow;
use PersianKit\Service\Import\TaskBatch;

/**
 * Converts the items in an option from old-N to new-N, two at a time.
 */
class FakeImportTask extends AbstractTask
{
    public const OPTION = 'persian_kit_test_items';

    public bool $inactiveOnly = true;

    /** Item ids that throw when converted. */
    public array $failing = [];

    public function key(): string
    {
        return 'items';
    }

    public function label(): string
    {
        return 'Items';
    }

    public function requiresInactive(): bool
    {
        return $this->inactiveOnly;
    }

    public function batchSize(): int
    {
        return 2;
    }

    public function count(ImportContext $context): int
    {
        return count($this->pending(0));
    }

    public function preview(ImportContext $context, int $limit): array
    {
        return array_map(
            static fn (int $id, string $value): array => ['label' => "Item {$id}", 'before' => $value, 'after' => str_replace('old', 'new', $value)],
            array_slice(array_keys($this->pending(0)), 0, $limit),
            array_slice($this->pending(0), 0, $limit)
        );
    }

    public function run(ImportContext $context, mixed $cursor, int $limit): TaskBatch
    {
        $items = get_option(self::OPTION, []);
        $batch = array_slice($this->pending((int) $cursor), 0, $limit, true);

        foreach ($batch as $id => $value) {
            $this->guarded($context, 'item', $id, function () use ($context, &$items, $id, $value): void {
                if (in_array($id, $this->failing, true)) {
                    throw new \RuntimeException('broken item');
                }
                $items[$id] = str_replace('old', 'new', $value);
                $context->log->changed($context, $this->key(), 'item', $id, 'value', $value, $items[$id]);
            });
        }
        update_option(self::OPTION, $items);

        $last = $batch === [] ? (int) $cursor : (int) array_key_last($batch);

        return new TaskBatch(count($batch), $last, count($this->pending($last)) === 0);
    }

    public function undo(ImportContext $context, LogRow $row): string
    {
        $items = get_option(self::OPTION, []);
        if (($items[$row->objectId] ?? null) !== $row->newValue) {
            return ImportLog::KEPT;
        }

        $items[$row->objectId] = $row->oldValue;
        update_option(self::OPTION, $items);

        return ImportLog::RESTORED;
    }

    /**
     * @return array<int, string>
     */
    private function pending(int $after): array
    {
        $items = get_option(self::OPTION, []);

        return array_filter(
            $items,
            fn (string $value, int $id): bool => $id > $after && str_starts_with($value, 'old'),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
