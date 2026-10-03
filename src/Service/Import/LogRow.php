<?php

namespace PersianKit\Service\Import;

defined('ABSPATH') || exit;

/**
 * One row of the import log: what happened to one field of one object.
 */
final class LogRow
{
    public function __construct(
        public readonly int $id,
        public readonly string $runId,
        public readonly string $source,
        public readonly string $task,
        public readonly string $objectType,
        public readonly int $objectId,
        public readonly string $field,
        public readonly string $outcome,
        public readonly string $reason,
        public readonly mixed $oldValue,
        public readonly mixed $newValue,
        public readonly string $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row A row as $wpdb returns it.
     */
    public static function fromDatabase(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['run_id'],
            (string) $row['source'],
            (string) $row['task'],
            (string) $row['object_type'],
            (int) $row['object_id'],
            (string) $row['field'],
            (string) $row['outcome'],
            (string) $row['reason'],
            ImportLog::decode($row['old_value']),
            ImportLog::decode($row['new_value']),
            (string) $row['created_at'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'task'        => $this->task,
            'object_type' => $this->objectType,
            'object_id'   => $this->objectId,
            'field'       => $this->field,
            'outcome'     => $this->outcome,
            'reason'      => $this->reason,
            'old_value'   => $this->oldValue,
            'new_value'   => $this->newValue,
            'created_at'  => $this->createdAt,
        ];
    }
}
