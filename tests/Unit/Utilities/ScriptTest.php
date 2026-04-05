<?php

namespace PersianKit\Tests\Unit\Utilities;

use PHPUnit\Framework\TestCase;
use PersianKit\Modules\Utilities\Script;

class ScriptTest extends TestCase
{
    // --- isPersian ---

    public function test_is_persian_pure_text(): void
    {
        $this->assertTrue(Script::isPersian('سلام دنیا'));
    }

    public function test_is_persian_with_persian_digits(): void
    {
        $this->assertTrue(Script::isPersian('شماره ۱۲۳'));
    }

    public function test_is_persian_mixed_with_english_returns_false(): void
    {
        $this->assertFalse(Script::isPersian('Hello سلام'));
    }

    public function test_is_persian_english_only_returns_false(): void
    {
        $this->assertFalse(Script::isPersian('Hello World'));
    }

    public function test_is_persian_empty_returns_false(): void
    {
        $this->assertFalse(Script::isPersian(''));
    }

    public function test_is_persian_whitespace_only_returns_false(): void
    {
        $this->assertFalse(Script::isPersian('   '));
    }

    public function test_is_persian_with_zwnj(): void
    {
        $this->assertTrue(Script::isPersian("می‌خواهم"));
    }

    public function test_is_persian_arabic_exclusive_chars_rejected_in_basic(): void
    {
        // ي (Arabic yaa) should fail basic mode
        $this->assertFalse(Script::isPersian('كتاب'));
    }

    public function test_is_persian_complex_mode_accepts_arabic_overlap(): void
    {
        $this->assertTrue(Script::isPersian('كتاب', complex: true));
    }

    // --- hasPersian ---

    public function test_has_persian_in_mixed_text(): void
    {
        $this->assertTrue(Script::hasPersian('Hello سلام World'));
    }

    public function test_has_persian_english_only_returns_false(): void
    {
        $this->assertFalse(Script::hasPersian('Hello World'));
    }

    public function test_has_persian_empty_returns_false(): void
    {
        $this->assertFalse(Script::hasPersian(''));
    }

    // --- isArabic ---

    public function test_is_arabic_with_exclusive_chars(): void
    {
        $this->assertTrue(Script::isArabic('كتاب عربي'));
    }

    public function test_is_arabic_persian_text_returns_false(): void
    {
        // Pure Persian (no Arabic-exclusive chars) should return false
        $this->assertFalse(Script::isArabic('سلام دنیا'));
    }

    public function test_is_arabic_empty_returns_false(): void
    {
        $this->assertFalse(Script::isArabic(''));
    }

    // --- hasArabic ---

    public function test_has_arabic_detects_exclusive_chars(): void
    {
        $this->assertTrue(Script::hasArabic('متن فارسی با كلمة عربي'));
    }

    public function test_has_arabic_persian_only_returns_false(): void
    {
        $this->assertFalse(Script::hasArabic('سلام دنیا'));
    }

    public function test_has_arabic_empty_returns_false(): void
    {
        $this->assertFalse(Script::hasArabic(''));
    }
}
