<?php

namespace PersianKit\Tests\Unit\DigitConversion;

use PHPUnit\Framework\TestCase;
use PersianKit\Modules\DigitConversion\DigitConverter;

class DigitConverterTest extends TestCase
{
    // ── toPersian ─────────────────────────────────────────────────────

    public function test_to_persian_converts_english_digits(): void
    {
        $this->assertSame('۰۱۲۳۴۵۶۷۸۹', DigitConverter::toPersian('0123456789'));
    }

    public function test_to_persian_converts_arabic_digits(): void
    {
        $this->assertSame('۰۱۲۳۴۵۶۷۸۹', DigitConverter::toPersian('٠١٢٣٤٥٦٧٨٩'));
    }

    public function test_to_persian_mixed_text_and_digits(): void
    {
        $this->assertSame('قیمت: ۱۲,۰۰۰ تومان', DigitConverter::toPersian('قیمت: 12,000 تومان'));
    }

    // ── toEnglish ─────────────────────────────────────────────────────

    public function test_to_english_converts_persian_digits(): void
    {
        $this->assertSame('0123456789', DigitConverter::toEnglish('۰۱۲۳۴۵۶۷۸۹'));
    }

    public function test_to_english_converts_arabic_digits(): void
    {
        $this->assertSame('0123456789', DigitConverter::toEnglish('٠١٢٣٤٥٦٧٨٩'));
    }

    // ── toArabic ──────────────────────────────────────────────────────

    public function test_to_arabic_converts_english_digits(): void
    {
        $this->assertSame('٠١٢٣٤٥٦٧٨٩', DigitConverter::toArabic('0123456789'));
    }

    public function test_to_arabic_converts_persian_digits(): void
    {
        $this->assertSame('٠١٢٣٤٥٦٧٨٩', DigitConverter::toArabic('۰۱۲۳۴۵۶۷۸۹'));
    }

    // ── convertContent ────────────────────────────────────────────────

    public function test_convert_content_empty_string(): void
    {
        $this->assertSame('', DigitConverter::convertContent(''));
    }

    public function test_convert_content_plain_text(): void
    {
        $this->assertSame('۱۲۳ تست', DigitConverter::convertContent('123 تست'));
    }

    public function test_convert_content_skips_html_attributes(): void
    {
        $html = '<a href="page-123" class="item-5">Item 5</a>';
        $expected = '<a href="page-123" class="item-5">Item ۵</a>';
        $this->assertSame($expected, DigitConverter::convertContent($html));
    }

    public function test_convert_content_skips_script_blocks(): void
    {
        $html = '<script>var x = 123;</script><p>456</p>';
        $expected = '<script>var x = 123;</script><p>۴۵۶</p>';
        $this->assertSame($expected, DigitConverter::convertContent($html));
    }

    public function test_convert_content_skips_style_blocks(): void
    {
        $html = '<style>.col-3 { width: 25%; }</style><span>3 columns</span>';
        $expected = '<style>.col-3 { width: 25%; }</style><span>۳ columns</span>';
        $this->assertSame($expected, DigitConverter::convertContent($html));
    }

    public function test_convert_content_skips_tel_links(): void
    {
        $html = '<a href="tel:+989121234567">Call 09121234567</a>';
        $expected = '<a href="tel:+989121234567">Call ۰۹۱۲۱۲۳۴۵۶۷</a>';
        $this->assertSame($expected, DigitConverter::convertContent($html));
    }

    public function test_convert_content_nested_tags(): void
    {
        $html = '<div><p>Price: 100</p><p>Tax: 9</p></div>';
        $expected = '<div><p>Price: ۱۰۰</p><p>Tax: ۹</p></div>';
        $this->assertSame($expected, DigitConverter::convertContent($html));
    }

    public function test_convert_content_multiline_script(): void
    {
        $html = "<script>\nvar count = 42;\nconsole.log(count);\n</script>\n<p>Result: 42</p>";
        $expected = "<script>\nvar count = 42;\nconsole.log(count);\n</script>\n<p>Result: ۴۲</p>";
        $this->assertSame($expected, DigitConverter::convertContent($html));
    }

    public function test_convert_content_data_attributes(): void
    {
        $html = '<div data-id="99" data-count="5">Count: 5</div>';
        $expected = '<div data-id="99" data-count="5">Count: ۵</div>';
        $this->assertSame($expected, DigitConverter::convertContent($html));
    }

    public function test_convert_content_src_attribute(): void
    {
        $html = '<img src="image-200x300.jpg"><span>200x300</span>';
        $expected = '<img src="image-200x300.jpg"><span>۲۰۰x۳۰۰</span>';
        $this->assertSame($expected, DigitConverter::convertContent($html));
    }
}
