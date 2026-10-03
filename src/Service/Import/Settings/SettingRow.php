<?php

namespace PersianKit\Service\Import\Settings;

defined('ABSPATH') || exit;

/**
 * One of a source's settings, next to the Persian Kit settings it maps to.
 *
 * $changes are Persian Kit settings by path ("woocommerce.city_select") and
 * the value the import gives them. Booleans only ever turn on.
 */
final class SettingRow
{
    /**
     * @param array<string, mixed> $changes
     */
    public function __construct(
        public readonly string $id,
        public readonly string $sourceLabel,
        public readonly SettingStatus $status,
        public readonly string $targetLabel = '',
        public readonly array $changes = [],
        public readonly string $reason = '',
        public readonly bool $ticked = true,
    ) {
    }

    public function imports(): bool
    {
        return $this->status->imports() && $this->changes !== [];
    }

    public function withTicked(bool $ticked): self
    {
        return new self($this->id, $this->sourceLabel, $this->status, $this->targetLabel, $this->changes, $this->reason, $ticked);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'source_label' => $this->sourceLabel,
            'target_label' => $this->targetLabel,
            'status'       => $this->status->value,
            'status_label' => $this->status->label(),
            'reason'       => $this->reason,
            'changes'      => $this->changes,
            'imports'      => $this->imports(),
            'ticked'       => $this->imports() && $this->ticked,
        ];
    }
}
