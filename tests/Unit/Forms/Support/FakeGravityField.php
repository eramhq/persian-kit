<?php

namespace PersianKit\Tests\Unit\Forms\Support;

/**
 * A Gravity Forms field as the integration reads it: settings as properties
 * (missing ones read as null, as GF_Field's do), its input type, and a date
 * check that fails as Gravity Forms' does.
 */
class FakeGravityField
{
    /** @var array<string, mixed> */
    private array $settings;

    public bool $inFormEditor = false;

    /** @param array<string, mixed> $settings */
    public function __construct(array $settings)
    {
        $this->settings = $settings + ['failed_validation' => false, 'validation_message' => ''];
    }

    public function __get(string $name): mixed
    {
        return $this->settings[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->settings[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->settings[$name]);
    }

    public function get_input_type(): string
    {
        return (string) ($this->settings['inputType'] ?? $this->settings['type'] ?? '');
    }

    public function is_form_editor(): bool
    {
        return $this->inFormEditor;
    }

    /**
     * @param mixed                $value
     * @param array<string, mixed> $form
     */
    public function validate($value, array $form): void
    {
        $this->failed_validation = true;
        $this->validation_message = $this->errorMessage ?: (($this->dateType ?? 'datepicker') === 'datepicker' ? 'Please enter a valid date in the format (dd/mm/yyyy).' : 'Please enter a valid date.');
    }
}
