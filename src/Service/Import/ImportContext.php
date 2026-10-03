<?php

namespace PersianKit\Service\Import;

defined('ABSPATH') || exit;

/**
 * What a task needs while it runs: the run, the source, the choices made in
 * Review, the snapshot taken while the source was active, and the log.
 */
final class ImportContext
{
    /**
     * @param array<string, mixed> $options  Choices from Review, such as the status map.
     * @param array<string, mixed> $snapshot Saved while the source was active.
     */
    public function __construct(
        public readonly string $runId,
        public readonly string $source,
        public readonly ImportLog $log,
        public readonly array $options = [],
        public readonly array $snapshot = [],
        public readonly bool $dryRun = false,
    ) {
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
