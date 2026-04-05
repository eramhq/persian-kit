<?php

namespace PersianKit\Tests\Unit\Utilities;

use PHPUnit\Framework\TestCase;
use PersianKit\Modules\Utilities\NumberToWords;

class NumberToWordsTest extends TestCase
{
    public function test_zero(): void
    {
        $this->assertSame('صفر', NumberToWords::convert(0));
    }

    public function test_single_digits(): void
    {
        $this->assertSame('یک', NumberToWords::convert(1));
        $this->assertSame('پنج', NumberToWords::convert(5));
        $this->assertSame('نه', NumberToWords::convert(9));
    }

    public function test_teens(): void
    {
        $this->assertSame('ده', NumberToWords::convert(10));
        $this->assertSame('یازده', NumberToWords::convert(11));
        $this->assertSame('پانزده', NumberToWords::convert(15));
        $this->assertSame('نوزده', NumberToWords::convert(19));
    }

    public function test_tens(): void
    {
        $this->assertSame('بیست', NumberToWords::convert(20));
        $this->assertSame('سی', NumberToWords::convert(30));
        $this->assertSame('نود', NumberToWords::convert(90));
    }

    public function test_hundreds(): void
    {
        $this->assertSame('یکصد', NumberToWords::convert(100));
        $this->assertSame('دویست', NumberToWords::convert(200));
        $this->assertSame('نهصد', NumberToWords::convert(900));
    }

    public function test_composite(): void
    {
        $this->assertSame('یکصد و بیست و سه', NumberToWords::convert(123));
    }

    public function test_thousand(): void
    {
        $this->assertSame('یک هزار', NumberToWords::convert(1000));
    }

    public function test_million(): void
    {
        $this->assertSame('یک میلیون', NumberToWords::convert(1000000));
    }

    public function test_complex_large(): void
    {
        $this->assertSame(
            'یک میلیون و دویست و سی و چهار هزار و پانصد و شصت و هفت',
            NumberToWords::convert(1234567)
        );
    }

    public function test_billion(): void
    {
        $this->assertSame('یک میلیارد', NumberToWords::convert(1000000000));
    }

    public function test_negative(): void
    {
        $this->assertSame('منفی پنج', NumberToWords::convert(-5));
    }

    public function test_decimal(): void
    {
        $this->assertSame('یک ممیز پنج', NumberToWords::convert(1.5));
    }

    public function test_decimal_multi_digits(): void
    {
        $this->assertSame('سه ممیز بیست و پنج', NumberToWords::convert(3.25));
    }

    public function test_integer_as_float(): void
    {
        $this->assertSame('یک', NumberToWords::convert(1.0));
    }
}
