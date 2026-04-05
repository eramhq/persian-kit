<?php

namespace PersianKit\Tests\Unit\Utilities;

use PHPUnit\Framework\TestCase;
use PersianKit\Modules\Utilities\NationalId;

class NationalIdTest extends TestCase
{
    public function test_valid_national_id(): void
    {
        // 0012345678: checksum = 0*10+0*9+1*8+2*7+3*6+4*5+5*4+6*3+7*2 = 0+0+8+14+18+20+20+18+14 = 112
        // 112 % 11 = 2, check digit = 11-2 = 9... let me use a known-valid ID
        // 1234567891: sum = 1*10+2*9+3*8+4*7+5*6+6*5+7*4+8*3+9*2 = 10+18+24+28+30+30+28+24+18 = 210
        // 210 % 11 = 1, check = 1
        $result = NationalId::validate('1234567891');
        $this->assertTrue($result->isValid());
    }

    public function test_valid_id_with_leading_zeros(): void
    {
        // 0013542419: sum = 0*10+0*9+1*8+3*7+5*6+4*5+2*4+4*3+1*2 = 0+0+8+21+30+20+8+12+2 = 101
        // 101 % 11 = 2, check = 11-2 = 9
        $result = NationalId::validate('0013542419');
        $this->assertTrue($result->isValid());
    }

    public function test_valid_id_returns_city_and_province(): void
    {
        $result = NationalId::validate('1234567891');
        $this->assertTrue($result->isValid());
        $details = $result->details();
        $this->assertArrayHasKey('city_code', $details);
        $this->assertArrayHasKey('city', $details);
        $this->assertArrayHasKey('province', $details);
    }

    public function test_persian_digit_input(): void
    {
        $result = NationalId::validate('۱۲۳۴۵۶۷۸۹۱');
        $this->assertTrue($result->isValid());
    }

    public function test_arabic_digit_input(): void
    {
        $result = NationalId::validate('١٢٣٤٥٦٧٨٩١');
        $this->assertTrue($result->isValid());
    }

    public function test_all_same_digits_rejected(): void
    {
        for ($d = 0; $d <= 9; $d++) {
            $result = NationalId::validate(str_repeat((string) $d, 10));
            $this->assertFalse($result->isValid(), "All {$d}s should be rejected");
        }
    }

    public function test_too_short(): void
    {
        $result = NationalId::validate('12345');
        $this->assertFalse($result->isValid());
    }

    public function test_too_long(): void
    {
        $result = NationalId::validate('123456789012');
        $this->assertFalse($result->isValid());
    }

    public function test_non_numeric(): void
    {
        $result = NationalId::validate('12345abcde');
        $this->assertFalse($result->isValid());
    }

    public function test_empty_string(): void
    {
        $result = NationalId::validate('');
        $this->assertFalse($result->isValid());
    }

    public function test_invalid_checksum(): void
    {
        // 1234567890: sum = 210, 210%11=1, check should be 1 but got 0
        $result = NationalId::validate('1234567890');
        $this->assertFalse($result->isValid());
    }

    public function test_8_digit_input_padded(): void
    {
        // 8-digit input left-padded to 10
        // '13542419' -> '0013542419'
        $result = NationalId::validate('13542419');
        $this->assertTrue($result->isValid());
    }

    public function test_unknown_city_code(): void
    {
        // Prefix 775 is in VALID_PREFIXES but not in CITY_CODES
        // Need to construct a valid checksum for a 775 prefix
        // 7750000001: sum = 7*10+7*9+5*8+0*7+0*6+0*5+0*4+0*3+0*2 = 70+63+40 = 173
        // 173 % 11 = 8, check = 11-8 = 3
        // 7750000003: let's verify sum = 70+63+40+0+0+0+0+0+0 = 173, 173%11 = 8, 11-8 = 3
        // But middle digits 000000 check would fail. Let's use 7751000003:
        // sum = 7*10+7*9+5*8+1*7+0*6+0*5+0*4+0*3+0*2 = 70+63+40+7 = 180
        // 180 % 11 = 4, check = 11-4 = 7 -> 7751000007
        $result = NationalId::validate('7751000007');
        $this->assertTrue($result->isValid());
        $this->assertNull($result->details()['city']);
        $this->assertNull($result->details()['province']);
    }
}
