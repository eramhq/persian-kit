<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\Forms\IranianFieldTypes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/functions.php';

class IranianFieldTypesTest extends TestCase
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

    public function test_the_five_types(): void
    {
        $this->assertSame(['mobile_ir', 'national_id', 'postcode_ir', 'card_ir', 'iban_ir'], IranianFieldTypes::types());
        $this->assertTrue(IranianFieldTypes::exists('iban_ir'));
        $this->assertFalse(IranianFieldTypes::exists('text'));
    }

    public function test_each_type_has_a_label_a_message_and_left_to_right_input_attributes(): void
    {
        foreach (IranianFieldTypes::types() as $type) {
            $this->assertNotSame($type, IranianFieldTypes::label($type), $type);
            $this->assertNotSame('', IranianFieldTypes::message($type), $type);

            $attributes = IranianFieldTypes::inputAttributes($type);
            $this->assertSame('ltr', $attributes['dir'], $type);
            $this->assertGreaterThan(0, $attributes['maxlength'], $type);
        }

        $this->assertSame('tel', IranianFieldTypes::inputAttributes('mobile_ir')['type']);
        $this->assertSame('numeric', IranianFieldTypes::inputAttributes('national_id')['inputmode']);
        $this->assertSame('Iranian fields', IranianFieldTypes::groupLabel());
    }

    /**
     * @return array<string, array{string, string, ?string}>
     */
    public static function values(): array
    {
        return [
            'mobile with Persian digits' => ['mobile_ir', '۰۹۱۲ ۱۲۳ ۴۵۶۷', '09121234567'],
            'mobile with +98'            => ['mobile_ir', '+98 912 123 4567', '09121234567'],
            'landline is not a mobile'   => ['mobile_ir', '02112345678', null],
            'national ID'                => ['national_id', '۰۰۱۳۵۴۲۴۱۹', '0013542419'],
            'bad national ID'            => ['national_id', '1234567890', null],
            'IBAN without IR'            => ['iban_ir', 'IR820540102680020817909002', 'IR820540102680020817909002'],
            'unknown type'               => ['text', '123', null],
        ];
    }

    #[DataProvider('values')]
    public function test_standard_value(string $type, string $value, ?string $expected): void
    {
        $this->assertSame($expected, IranianFieldTypes::standardValue($type, $value));
    }

    public function test_normalize_keeps_an_invalid_value_with_english_digits(): void
    {
        $this->assertSame('09121234567', IranianFieldTypes::normalize('mobile_ir', '۰۹۱۲۱۲۳۴۵۶۷'));
        $this->assertSame('12abc', IranianFieldTypes::normalize('national_id', ' ۱۲abc '));
    }
}
