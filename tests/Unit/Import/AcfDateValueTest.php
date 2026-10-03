<?php

namespace PersianKit\Tests\Unit\Import;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Service\Import\Sources\ParsiDate\AcfDateValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AcfDateValueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('__')->returnArg();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function values(): array
    {
        return [
            'Gregorian Y-m-d, as Parsi Date saved it'  => ['2024-08-02', 'changed', '20240802'],
            'already Ymd'                               => ['20240802', 'unchanged', '20240802'],
            'Jalali, saved as typed'                    => ['1403-05-12', 'changed', '20240802'],
            'Jalali with slashes and one-digit parts'   => ['1403/5/12', 'changed', '20240802'],
            'Jalali in Persian digits'                  => ['۱۴۰۳-۰۵-۱۲', 'changed', '20240802'],
            'Jalali as 8 digits'                        => ['14030512', 'changed', '20240802'],
            '31 Shahrivar'                              => ['1403-06-31', 'changed', '20240921'],
            'empty'                                     => ['', 'unchanged', ''],
            'a year between the calendars'              => ['1650-01-01', 'attention', '1650-01-01'],
            'not a date'                                => ['tomorrow', 'attention', 'tomorrow'],
            'an invalid Jalali day'                     => ['1403-12-31', 'attention', '1403-12-31'],
            'an invalid Gregorian day'                  => ['2024-02-30', 'attention', '2024-02-30'],
            'converted twice, not asked to fix'         => ['26460321', 'attention', '26460321'],
        ];
    }

    #[DataProvider('values')]
    public function test_values(string $value, string $outcome, string $expected): void
    {
        $result = AcfDateValue::convert($value);

        $this->assertSame($outcome, $result['outcome']);
        $this->assertSame($expected, $result['value']);
    }

    public function test_a_date_converted_twice_is_fixed_when_asked(): void
    {
        // 2025-01-01 read as Jalali 2025/01/01 is Gregorian 2646-03-21.
        $result = AcfDateValue::convert('26460321', true);

        $this->assertSame('changed', $result['outcome']);
        $this->assertSame('20250101', $result['value']);
    }
}
