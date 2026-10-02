<?php

namespace PersianKit\Tests\Unit\Forms\Support;

/**
 * The parts of Contact Form 7's WPCF7_FormTag the integration uses.
 */
class FakeFormTag
{
    public string $basetype;

    /**
     * @param list<string> $options
     * @param list<string> $values
     */
    public function __construct(public string $type, public string $name, public array $options = [], public array $values = [])
    {
        $this->basetype = rtrim($type, '*');
    }

    public function is_required(): bool
    {
        return str_ends_with($this->type, '*');
    }

    public function has_option(string $option): bool
    {
        return in_array($option, $this->options, true);
    }
}
