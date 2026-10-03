<?php

namespace PersianKit\Service\Import;

defined('ABSPATH') || exit;

/**
 * A task with a choice to make in Review, such as where order statuses go.
 */
interface HasReviewOptions
{
    /**
     * @return array<string, mixed>|null {type, default, ...}, or null for none here.
     */
    public function reviewOptions(ImportContext $context): ?array;
}
