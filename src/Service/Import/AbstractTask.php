<?php

namespace PersianKit\Service\Import;

defined('ABSPATH') || exit;

abstract class AbstractTask implements Task
{
    public function requiresInactive(): bool
    {
        return true;
    }

    public function available(): ?string
    {
        return null;
    }

    /** How many items one batch converts. */
    public function batchSize(): int
    {
        return 50;
    }

    public function preview(ImportContext $context, int $limit): array
    {
        return [];
    }

    /**
     * Runs $convert for one item and logs a failure as needing attention,
     * so one bad row never stops the import.
     *
     * @param callable(): void $convert
     */
    protected function guarded(ImportContext $context, string $objectType, int $objectId, callable $convert): void
    {
        try {
            $convert();
        } catch (\Throwable $error) {
            $context->log->attention(
                $context,
                $this->key(),
                $objectType,
                $objectId,
                '',
                /* translators: %s: error message. */
                sprintf(__('Could not convert: %s', 'persian-kit'), $error->getMessage())
            );
        }
    }
}
