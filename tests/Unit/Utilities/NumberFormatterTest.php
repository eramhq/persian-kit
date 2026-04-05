<?php

namespace PersianKit\Tests\Unit\Utilities;

use PHPUnit\Framework\TestCase;
use PersianKit\Modules\Utilities\NumberFormatter;

class NumberFormatterTest extends TestCase
{
    public function test_basic_thousands(): void
    {
        $this->assertSame('1,234,567', NumberFormatter::withSeparators(1234567));
    }

    public function test_small_number_unchanged(): void
    {
        $this->assertSame('999', NumberFormatter::withSeparators(999));
    }

    public function test_zero(): void
    {
        $this->assertSame('0', NumberFormatter::withSeparators(0));
    }

    public function test_single_digit(): void
    {
        $this->assertSame('5', NumberFormatter::withSeparators(5));
    }

    public function test_negative(): void
    {
        $this->assertSame('-1,234,567', NumberFormatter::withSeparators(-1234567));
    }

    public function test_decimal_preserved(): void
    {
        $this->assertSame('30,000,000.02', NumberFormatter::withSeparators(30000000.02));
    }

    public function test_custom_separator(): void
    {
        $this->assertSame('1٬234٬567', NumberFormatter::withSeparators(1234567, '٬'));
    }

    public function test_string_input(): void
    {
        $this->assertSame('1,234,567', NumberFormatter::withSeparators('1234567'));
    }

    public function test_persian_digit_input(): void
    {
        $this->assertSame('1,234,567', NumberFormatter::withSeparators('۱۲۳۴۵۶۷'));
    }

    public function test_idempotent_already_formatted(): void
    {
        $this->assertSame('1,234,567', NumberFormatter::withSeparators('1,234,567'));
    }

    public function test_large_string_number(): void
    {
        $this->assertSame(
            '999,999,999,999,999',
            NumberFormatter::withSeparators('999999999999999')
        );
    }

    public function test_string_decimal(): void
    {
        $this->assertSame('1,234.56', NumberFormatter::withSeparators('1234.56'));
    }

    public function test_invalid_string_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NumberFormatter::withSeparators('abc');
    }
}
