<?php

namespace PersianKit\Service\Import;

defined('ABSPATH') || exit;

/**
 * One thing to sort out before the source is deactivated: theme code that
 * calls its functions, payment gateways that will stop, shipping zones left
 * empty. A blocking item keeps the Deactivate step closed until it is
 * handled; an item to acknowledge, until it is ticked.
 */
final class ChecklistItem
{
    /**
     * @param list<array{label: string, detail?: string, url?: string}> $entries
     * @param array<string, mixed>                                     $extra   Item-specific data, such as a code snippet.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $description,
        public readonly array $entries = [],
        public readonly bool $blocking = false,
        public readonly bool $acknowledge = false,
        public readonly array $extra = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key'         => $this->key,
            'title'       => $this->title,
            'description' => $this->description,
            'entries'     => $this->entries,
            'blocking'    => $this->blocking,
            'acknowledge' => $this->acknowledge,
            'extra'       => $this->extra,
        ];
    }
}
