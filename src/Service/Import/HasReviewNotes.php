<?php

namespace PersianKit\Service\Import;

defined('ABSPATH') || exit;

/**
 * A source with more to say in Review than its settings and data, such as
 * how many old links keep working.
 */
interface HasReviewNotes
{
    /**
     * @param array<string, mixed> $snapshot
     * @return list<array{key: string, title: string, text: string, example?: array{before: string, after: string}, choice?: array<string, mixed>}>
     */
    public function reviewNotes(array $snapshot): array;
}
