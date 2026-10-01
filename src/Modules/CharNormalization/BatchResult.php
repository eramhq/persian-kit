<?php

namespace PersianKit\Modules\CharNormalization;

defined('ABSPATH') || exit;

class BatchResult
{
    public readonly int $processed;
    public readonly int $modified;
    public readonly int $lastId;
    public readonly bool $hasMore;

    /** @var array<string, int> Changed (or, in a dry run, changeable) posts by post type. */
    public readonly array $modifiedByType;

    /**
     * @param array<string, int> $modifiedByType
     */
    public function __construct(int $processed, int $modified, int $lastId, bool $hasMore, array $modifiedByType = [])
    {
        $this->processed      = $processed;
        $this->modified       = $modified;
        $this->lastId         = $lastId;
        $this->hasMore        = $hasMore;
        $this->modifiedByType = $modifiedByType;
    }
}
