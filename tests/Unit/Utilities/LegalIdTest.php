<?php

namespace PersianKit\Tests\Unit\Utilities;

use PHPUnit\Framework\TestCase;
use PersianKit\Modules\Utilities\LegalId;

class LegalIdTest extends TestCase
{
    public function test_valid_legal_id(): void
    {
        $result = LegalId::validate('10380284790');
        $this->assertTrue($result->isValid());
    }

    public function test_persian_digit_input(): void
    {
        $result = LegalId::validate('۱۰۳۸۰۲۸۴۷۹۰');
        $this->assertTrue($result->isValid());
    }

    public function test_arabic_digit_input(): void
    {
        $result = LegalId::validate('١٠٣٨٠٢٨٤٧٩٠');
        $this->assertTrue($result->isValid());
    }

    public function test_invalid_checksum(): void
    {
        $result = LegalId::validate('10380284792');
        $this->assertFalse($result->isValid());
    }

    public function test_too_short(): void
    {
        $result = LegalId::validate('1234567');
        $this->assertFalse($result->isValid());
    }

    public function test_too_long(): void
    {
        $result = LegalId::validate('123456789012');
        $this->assertFalse($result->isValid());
    }

    public function test_empty_string(): void
    {
        $result = LegalId::validate('');
        $this->assertFalse($result->isValid());
    }

    public function test_non_numeric(): void
    {
        $result = LegalId::validate('abcdefghijk');
        $this->assertFalse($result->isValid());
    }

    public function test_all_zero_middle_rejected(): void
    {
        // Construct an ID with zeros in positions 3-8
        // 12300000089 — middle 6 positions are all zero
        $result = LegalId::validate('12300000089');
        $this->assertFalse($result->isValid());
    }

    public function test_whitespace_trimmed(): void
    {
        $result = LegalId::validate('  10380284790  ');
        $this->assertTrue($result->isValid());
    }
}
