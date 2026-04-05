<?php

namespace PersianKit\Modules\CharNormalization;

defined('ABSPATH') || exit;

class BatchResult
{
    public readonly int $processed;
    public readonly int $modified;
    public readonly int $lastId;
    public readonly bool $hasMore;

    public function __construct(int $processed, int $modified, int $lastId, bool $hasMore)
    {
        $this->processed = $processed;
        $this->modified  = $modified;
        $this->lastId    = $lastId;
        $this->hasMore   = $hasMore;
    }
}
