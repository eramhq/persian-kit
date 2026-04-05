<?php

namespace PersianKit\Tests\Unit\Utilities;

use PHPUnit\Framework\TestCase;
use PersianKit\Modules\Utilities\PhoneNumber;

class PhoneNumberTest extends TestCase
{
    public function test_valid_with_leading_zero(): void
    {
        $result = PhoneNumber::validate('09121234567');
        $this->assertTrue($result->isValid());
    }

    public function test_valid_international_plus98(): void
    {
        $result = PhoneNumber::validate('+989121234567');
        $this->assertTrue($result->isValid());
    }

    public function test_valid_international_0098(): void
    {
        $result = PhoneNumber::validate('00989121234567');
        $this->assertTrue($result->isValid());
    }

    public function test_valid_without_leading_zero(): void
    {
        $result = PhoneNumber::validate('9121234567');
        $this->assertTrue($result->isValid());
    }

    public function test_with_spaces_and_dashes(): void
    {
        $result = PhoneNumber::validate('0912-123-4567');
        $this->assertTrue($result->isValid());
    }

    public function test_persian_digits(): void
    {
        $result = PhoneNumber::validate('۰۹۱۲۱۲۳۴۵۶۷');
        $this->assertTrue($result->isValid());
    }

    public function test_normalized_format_in_details(): void
    {
        $result = PhoneNumber::validate('+989121234567');
        $this->assertSame('09121234567', $result->details()['normalized']);
    }

    public function test_mci_operator(): void
    {
        $result = PhoneNumber::validate('09121234567');
        $this->assertSame('همراه اول', $result->details()['operator']);
    }

    public function test_irancell_operator(): void
    {
        $result = PhoneNumber::validate('09351234567');
        $this->assertSame('ایرانسل', $result->details()['operator']);
    }

    public function test_rightel_operator(): void
    {
        $result = PhoneNumber::validate('09211234567');
        $this->assertSame('رایتل', $result->details()['operator']);
    }

    public function test_unknown_operator(): void
    {
        $result = PhoneNumber::validate('09401234567');
        $this->assertTrue($result->isValid());
        $this->assertNull($result->details()['operator']);
    }

    public function test_too_short(): void
    {
        $result = PhoneNumber::validate('0912123');
        $this->assertFalse($result->isValid());
    }

    public function test_too_long(): void
    {
        $result = PhoneNumber::validate('091212345678');
        $this->assertFalse($result->isValid());
    }

    public function test_empty_string(): void
    {
        $result = PhoneNumber::validate('');
        $this->assertFalse($result->isValid());
    }

    public function test_landline_rejected(): void
    {
        $result = PhoneNumber::validate('02112345678');
        $this->assertFalse($result->isValid());
    }
}
