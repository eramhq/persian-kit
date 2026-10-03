<?php

namespace PersianKit\Service\Import;

defined('ABSPATH') || exit;

final class TaskBatch
{
    public function __construct(
        public readonly int $processed,
        public readonly mixed $cursor,
        public readonly bool $done,
    ) {
    }
}
