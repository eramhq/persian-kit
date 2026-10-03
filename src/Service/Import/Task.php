<?php

namespace PersianKit\Service\Import;

defined('ABSPATH') || exit;

/**
 * One kind of data the import converts, such as customer addresses. Every
 * task is idempotent: it selects only the rows that still need converting,
 * so running it again picks up what changed since and nothing else.
 */
interface Task
{
    public function key(): string;

    public function label(): string;

    /** Whether it waits until the source plugin is inactive. */
    public function requiresInactive(): bool;

    /** Null when it can run here; otherwise why not, such as "Activate ACF". */
    public function available(): ?string;

    /** How many items still need converting. */
    public function count(ImportContext $context): int;

    /**
     * A few items as they are and as they would be.
     *
     * @return list<array{label: string, before: string, after: string}>
     */
    public function preview(ImportContext $context, int $limit): array;

    /**
     * Converts up to $limit items after $cursor, logging each.
     */
    public function run(ImportContext $context, mixed $cursor, int $limit): TaskBatch;

    /**
     * Puts one logged item back while it still holds what the import wrote.
     * Returns the outcome: ImportLog::RESTORED or ImportLog::KEPT.
     */
    public function undo(ImportContext $context, LogRow $row): string;
}
