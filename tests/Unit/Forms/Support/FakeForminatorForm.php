<?php

namespace PersianKit\Tests\Unit\Forms\Support;

/**
 * The parts of Forminator's form model the integration uses: its fields as
 * models, and one field's settings by id, also a repeated group row's.
 */
class FakeForminatorForm
{
    /**
     * @param list<array<string, mixed>> $fields Field settings, each with element_id.
     */
    public function __construct(public array $fields)
    {
    }

    /**
     * @return list<object>
     */
    public function get_fields(): array
    {
        return array_map(static fn (array $settings): object => new class ($settings) {
            /** @param array<string, mixed> $settings */
            public function __construct(private array $settings)
            {
            }

            /** @return array<string, mixed> */
            public function to_formatted_array(): array
            {
                return $this->settings;
            }
        }, $this->fields);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_field(string $id, bool $toArray = true): ?array
    {
        foreach ($this->fields as $field) {
            if ($field['element_id'] === $id) {
                return $field;
            }
        }

        return preg_match('/^(.+)-\d+$/', $id, $matches) ? $this->get_field($matches[1]) : null;
    }
}
