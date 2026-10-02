<?php

namespace PersianKit\Tests\Unit\Forms\Support;

/**
 * Contact Form 7's WPCF7_MailTag options.
 */
class FakeMailTag
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(private array $options = ['do_not_heat' => false, 'format' => ''])
    {
    }

    public function get_option(string $option): mixed
    {
        return $this->options[$option] ?? null;
    }
}
