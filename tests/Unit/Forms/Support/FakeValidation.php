<?php

namespace PersianKit\Tests\Unit\Forms\Support;

/**
 * Contact Form 7's WPCF7_Validation: the first error of a field wins.
 */
class FakeValidation
{
    /** @var array<string, string> */
    public array $invalid = [];

    public function invalidate(FakeFormTag $tag, string $message): void
    {
        $this->invalid[$tag->name] ??= $message;
    }
}
