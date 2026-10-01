<?php

namespace PersianKit\Tests\Unit;

use Brain\Monkey;
use PersianKit\Dependencies\Eram\Abzar\Validation\BillId;
use PersianKit\Dependencies\Eram\Abzar\Validation\CardNumber;
use PersianKit\Dependencies\Eram\Abzar\Validation\Iban;
use PersianKit\Dependencies\Eram\Abzar\Validation\LegalId;
use PersianKit\Dependencies\Eram\Abzar\Validation\NationalId;
use PersianKit\Dependencies\Eram\Abzar\Validation\PhoneNumber;
use PersianKit\Dependencies\Eram\Abzar\Validation\PlateNumber;
use PersianKit\Dependencies\Eram\Abzar\Validation\PostalCode;
use PersianKit\Dependencies\Eram\Abzar\Validation\ValidationResult;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/functions.php';

class FunctionsTest extends TestCase
{
    /** 2024-03-26 00:00:00 UTC = 1403/01/07. */
    private const TIMESTAMP = 1711411200;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_digit_helpers(): void
    {
        $this->assertSame('۱۲۳', persian_kit_to_persian_digits('123'));
        $this->assertSame('1234', persian_kit_to_english_digits('۱۲۳٤'));
        $this->assertSame('١٢', persian_kit_to_arabic_digits('12'));
    }

    public function test_text_helpers(): void
    {
        $this->assertSame('کتاب', persian_kit_normalize_persian('كتاب'));
        $this->assertSame('سلام-دنیا', persian_kit_slug('سلام دنیا'));
        $this->assertSame("می\u{200C}خواهم", persian_kit_half_space_fix('می خواهم'));
    }

    public function test_script_detection_helpers(): void
    {
        $this->assertTrue(persian_kit_is_persian('سلام'));
        $this->assertFalse(persian_kit_is_persian('hello'));
        $this->assertTrue(persian_kit_has_persian('hello سلام'));
        $this->assertFalse(persian_kit_is_arabic('hello'));
        $this->assertFalse(persian_kit_has_arabic('abc'));
    }

    public function test_keyboard_fix_switches_layout_both_ways(): void
    {
        $this->assertSame('سلام', persian_kit_keyboard_fix('sghl'));
        $this->assertSame('sghl', persian_kit_keyboard_fix('سلام'));
    }

    public function test_keyboard_fix_maps_uppercase_through_shift_layer(): void
    {
        $this->assertSame('آب', persian_kit_keyboard_fix('Hf'));
    }

    public function test_persian_sort_orders_by_persian_alphabet(): void
    {
        $this->assertSame(['آ', 'الف', 'ب', 'پ'], array_values(persian_kit_persian_sort(['پ', 'ب', 'الف', 'آ'])));
    }

    public function test_persian_sort_with_key_extractor(): void
    {
        $items = [['name' => 'پ'], ['name' => 'ب'], ['name' => 'آ']];

        $sorted = persian_kit_persian_sort($items, static fn (array $item): string => $item['name']);

        $this->assertSame(['آ', 'ب', 'پ'], array_column(array_values($sorted), 'name'));
    }

    public function test_persian_sort_falls_back_without_intl(): void
    {
        if (extension_loaded('intl')) {
            $this->markTestSkipped('The fallback only runs when ext-intl is missing.');
        }

        $this->assertSame(['a', 'b', 'c'], persian_kit_persian_sort(['c', 'a', 'b']));
        $this->assertSame(
            [['k' => 'a'], ['k' => 'b']],
            persian_kit_persian_sort([['k' => 'b'], ['k' => 'a']], static fn (array $i): string => $i['k'])
        );
    }

    public function test_date_helpers(): void
    {
        $utc = new \DateTimeZone('UTC');

        $this->assertSame('1403/01/07', persian_kit_date('Y/m/d', self::TIMESTAMP, $utc));
        $this->assertSame('2024-03-26', persian_kit_gregorian_date('Y-m-d', self::TIMESTAMP, $utc));
    }

    public function test_number_helpers(): void
    {
        $this->assertSame('1,234,567', persian_kit_number_format(1234567));
        $this->assertSame('بیست و یک', persian_kit_number_to_words(21));
        $this->assertSame(21, persian_kit_words_to_number('بیست و یک'));
        $this->assertNull(persian_kit_words_to_number('سلام'));
    }

    public function test_ordinal_word_joins_suffix_with_zwnj(): void
    {
        $this->assertSame("سی\u{200C}ام", persian_kit_ordinal_word(30));
    }

    public function test_ordinal_short_accepts_bool_and_legacy_strings(): void
    {
        $this->assertSame('۳ام', persian_kit_ordinal_short(3));
        $this->assertSame('3ام', persian_kit_ordinal_short(3, false));
        $this->assertSame('۳ام', persian_kit_ordinal_short(3, 'persian'));
        $this->assertSame('3ام', persian_kit_ordinal_short(3, 'english'));
        $this->assertSame('3ام', persian_kit_ordinal_short(3, 'English'));
    }

    public function test_time_ago(): void
    {
        $this->assertSame('۱ ساعت پیش', persian_kit_time_ago(self::TIMESTAMP - 3600, self::TIMESTAMP));
    }

    public function test_currency_helpers(): void
    {
        $this->assertSame('۱،۵۰۰،۰۰۰ تومان', persian_kit_currency_format(1500000));
        $this->assertSame('1،500،000', persian_kit_currency_format(1500000, 'RIAL', false, false));
        $this->assertSame(1000, persian_kit_currency_convert(100, 'toman', 'rial'));
        $this->assertSame(10, persian_kit_currency_convert(100, 'rial', 'toman'));
    }

    public function test_currency_rejects_unknown_unit(): void
    {
        \Brain\Monkey\Functions\when('esc_html')->returnArg();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown currency unit "dollar"');

        persian_kit_currency_format(100, 'dollar');
    }

    /**
     * @dataProvider validators
     */
    public function test_validators_accept_valid_input(callable $helper, string $input): void
    {
        $result = $helper($input);

        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertTrue($result->isValid());
    }

    public static function validators(): array
    {
        return [
            'national id'  => ['persian_kit_validate_national_id', NationalId::fake()],
            'phone'        => ['persian_kit_validate_phone', PhoneNumber::fake()],
            'card number'  => ['persian_kit_validate_card_number', CardNumber::fake()],
            'iban'         => ['persian_kit_validate_iban', Iban::fake()],
            'legal id'     => ['persian_kit_validate_legal_id', LegalId::fake()],
            'postal code'  => ['persian_kit_validate_postal_code', PostalCode::fake()],
            'plate number' => ['persian_kit_validate_plate_number', PlateNumber::fake()],
            'bill id'      => ['persian_kit_validate_bill_id', BillId::fake()],
        ];
    }

    public function test_validators_reject_invalid_input(): void
    {
        $this->assertFalse(persian_kit_validate_national_id('1111111111')->isValid());
        $this->assertFalse(persian_kit_validate_phone('123')->isValid());
        $this->assertFalse(persian_kit_validate_card_number('1234')->isValid());
    }

    public function test_bill_id_validates_payment_pair(): void
    {
        $billId = BillId::fake();

        $this->assertTrue(persian_kit_validate_bill_id($billId, BillId::fakePaymentId($billId))->isValid());
        $this->assertFalse(persian_kit_validate_bill_id($billId, '1')->isValid());
    }
}
