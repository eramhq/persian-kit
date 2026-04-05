<?php

namespace PersianKit\Tests\Unit\Utilities;

use PHPUnit\Framework\TestCase;
use PersianKit\Modules\Utilities\Slug;

class SlugTest extends TestCase
{
    public function test_basic_persian(): void
    {
        $this->assertSame('سلام-دنیا', Slug::generate('سلام دنیا'));
    }

    public function test_arabic_normalized(): void
    {
        // Arabic kaf ك → Persian kaf ک, Arabic yeh ي → Persian yeh ی
        $result = Slug::generate('كتابي');
        $this->assertSame('کتابی', $result);
    }

    public function test_persian_digits_to_english(): void
    {
        $this->assertSame('محصول-123', Slug::generate('محصول ۱۲۳'));
    }

    public function test_english_lowercased(): void
    {
        $this->assertSame('hello-world', Slug::generate('Hello World'));
    }

    public function test_mixed_persian_english(): void
    {
        $this->assertSame('محصول-product-1', Slug::generate('محصول Product ۱'));
    }

    public function test_special_chars_removed(): void
    {
        $this->assertSame('سلام', Slug::generate('سلام! @#$'));
    }

    public function test_consecutive_spaces_collapsed(): void
    {
        $this->assertSame('سلام-دنیا', Slug::generate('سلام   دنیا'));
    }

    public function test_consecutive_hyphens_collapsed(): void
    {
        $this->assertSame('سلام-دنیا', Slug::generate('سلام---دنیا'));
    }

    public function test_leading_trailing_trimmed(): void
    {
        $this->assertSame('سلام', Slug::generate(' -سلام- '));
    }

    public function test_underscores_replaced(): void
    {
        $this->assertSame('سلام-دنیا', Slug::generate('سلام_دنیا'));
    }

    public function test_empty_string(): void
    {
        $this->assertSame('', Slug::generate(''));
    }

    public function test_only_special_chars(): void
    {
        $this->assertSame('', Slug::generate('!@#$%^&*()'));
    }

    public function test_idempotent(): void
    {
        $slug = 'سلام-دنیا';
        $this->assertSame($slug, Slug::generate($slug));
    }
}
