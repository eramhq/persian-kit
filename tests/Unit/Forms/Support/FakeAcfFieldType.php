<?php

namespace PersianKit\Tests\Unit\Forms\Support;

/**
 * An ACF field type (acf_field) with its renderer.
 */
class FakeAcfFieldType
{
    /**
     * @param array<string, mixed> $field
     */
    public function render_field(array $field): void
    {
        echo 'acf';
    }
}
