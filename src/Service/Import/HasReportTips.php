<?php

namespace PersianKit\Service\Import;

defined('ABSPATH') || exit;

/**
 * A source with advice for after its import, such as clearing the page
 * cache once widgets changed.
 */
interface HasReportTips
{
    /**
     * @param array<string, mixed> $job The finished job's public data, or empty.
     * @return list<string>
     */
    public function reportTips(array $job): array;
}
