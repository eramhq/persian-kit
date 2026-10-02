<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\Forms\Cf7IranianFields;
use PersianKit\Tests\Unit\Forms\Support\FakeFormTag;
use PersianKit\Tests\Unit\Forms\Support\FakeValidation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/functions.php';

class Cf7IranianFieldsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('__')->returnArg();
        Functions\when('wpcf7_get_message')->alias(fn (string $status) => "message:{$status}");
        Functions\when('sanitize_text_field')->alias('trim');
        Functions\when('wp_unslash')->alias('stripslashes');
    }

    protected function tearDown(): void
    {
        unset($_POST['field']);
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_adds_tags_messages_and_checks_for_both_variants(): void
    {
        Functions\when('did_action')->justReturn(0);

        $fields = new Cf7IranianFields();
        $fields->register();

        $this->assertNotFalse(has_action('wpcf7_init', [$fields, 'addFormTags']));
        $this->assertNotFalse(has_filter('wpcf7_messages', [$fields, 'addMessages']));
        $this->assertNotFalse(has_action('wpcf7_swv_create_schema', [$fields, 'addRequiredRules']));

        foreach (['mobile_ir', 'national_id', 'postcode_ir', 'card_ir', 'iban_ir'] as $type) {
            $this->assertNotFalse(has_filter("wpcf7_validate_{$type}", [$fields, 'validate']), $type);
            $this->assertNotFalse(has_filter("wpcf7_validate_{$type}*", [$fields, 'validate']), $type);
            $this->assertNotFalse(has_filter("wpcf7_posted_data_{$type}*", [$fields, 'normalizePostedValue']), $type);
        }
    }

    public function test_the_fallback_adds_the_tags_without_checks_or_messages(): void
    {
        Functions\when('did_action')->justReturn(0);

        $fields = new Cf7IranianFields();
        $fields->registerFallback();

        $this->assertNotFalse(has_action('wpcf7_init', [$fields, 'addFormTags']));
        $this->assertFalse(has_filter('wpcf7_messages', [$fields, 'addMessages']));
        $this->assertFalse(has_action('wpcf7_swv_create_schema', [$fields, 'addRequiredRules']));

        foreach (['mobile_ir', 'national_id', 'postcode_ir', 'card_ir', 'iban_ir'] as $type) {
            $this->assertFalse(has_filter("wpcf7_validate_{$type}*", [$fields, 'validate']), $type);
            $this->assertFalse(has_filter("wpcf7_posted_data_{$type}", [$fields, 'normalizePostedValue']), $type);
        }
    }

    public function test_form_tags_are_added_when_cf7_already_ran_its_init(): void
    {
        Functions\when('did_action')->justReturn(1);
        $added = [];
        Functions\when('wpcf7_add_form_tag')->alias(function (array $types) use (&$added) {
            $added = array_merge($added, $types);
        });

        (new Cf7IranianFields())->register();

        $this->assertSame([
            'mobile_ir', 'mobile_ir*', 'national_id', 'national_id*', 'postcode_ir', 'postcode_ir*',
            'card_ir', 'card_ir*', 'iban_ir', 'iban_ir*',
        ], $added);
    }

    public function test_each_tag_has_an_editable_error_message(): void
    {
        $messages = (new Cf7IranianFields())->addMessages(['invalid_required' => ['description' => '', 'default' => '']]);

        foreach (['mobile_ir', 'national_id', 'postcode_ir', 'card_ir', 'iban_ir'] as $type) {
            $this->assertArrayHasKey("invalid_{$type}", $messages);
            $this->assertNotSame('', $messages["invalid_{$type}"]['default']);
        }
        $this->assertArrayHasKey('invalid_required', $messages);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function validValues(): array
    {
        return [
            'mobile, Persian digits' => ['mobile_ir', '۰۹۱۲ ۱۲۳ ۴۵۶۷', '09121234567'],
            'mobile, +98'            => ['mobile_ir', '+98 912 123 4567', '09121234567'],
            'national ID'            => ['national_id', '۰۰۱۳۵۴۲۴۱۹', '0013542419'],
            'postcode with dash'     => ['postcode_ir', '۱۳۸۹۷-۵۸۶۴۸', '1389758648'],
            'card with spaces'       => ['card_ir', '6037 9975 9940 4952', '6037997599404952'],
            'IBAN, Persian digits'   => ['iban_ir', 'ir۷۳ ۰۰۱۸ ۰۰۰۰ ۰۰۰۰ ۰۰۱۲ ۳۴۵۶ ۷۸', 'IR730018000000000012345678'],
            'IBAN without IR'        => ['iban_ir', '730018000000000012345678', 'IR730018000000000012345678'],
        ];
    }

    #[DataProvider('validValues')]
    public function test_valid_values_are_sent_in_their_standard_form(string $type, string $typed, string $standard): void
    {
        $this->assertSame($standard, Cf7IranianFields::standardValue($type, $typed));
        $this->assertSame($standard, (new Cf7IranianFields())->normalizePostedValue($typed, $typed, new FakeFormTag("{$type}*", 'field')));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidValues(): array
    {
        return [
            'landline as mobile' => ['mobile_ir', '02188776655'],
            'short mobile'       => ['mobile_ir', '0912123'],
            'national ID'        => ['national_id', '1234567890'],
            'postcode'           => ['postcode_ir', '0123456789'],
            'card checksum'      => ['card_ir', '6037997599404955'],
            'IBAN checksum'      => ['iban_ir', 'IR740018000000000012345678'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_values_are_reported(string $type, string $value): void
    {
        $_POST['field'] = $value;
        $result = (new Cf7IranianFields())->validate(new FakeValidation(), new FakeFormTag($type, 'field'));

        $this->assertNull(Cf7IranianFields::standardValue($type, $value));
        $this->assertSame(['field' => "message:invalid_{$type}"], $result->invalid);
    }

    public function test_invalid_values_are_sent_with_english_digits(): void
    {
        $this->assertSame('09121', (new Cf7IranianFields())->normalizePostedValue(' ۰۹۱۲۱ ', '', new FakeFormTag('mobile_ir', 'field')));
    }

    public function test_valid_values_pass(): void
    {
        $_POST['field'] = '۰۹۱۲۱۲۳۴۵۶۷';
        $result = (new Cf7IranianFields())->validate(new FakeValidation(), new FakeFormTag('mobile_ir*', 'field'));

        $this->assertSame([], $result->invalid);
    }

    public function test_an_empty_field_fails_only_when_required(): void
    {
        $_POST['field'] = '  ';
        $fields = new Cf7IranianFields();

        $this->assertSame([], $fields->validate(new FakeValidation(), new FakeFormTag('card_ir', 'field'))->invalid);
        $this->assertSame(
            ['field' => 'message:invalid_required'],
            $fields->validate(new FakeValidation(), new FakeFormTag('card_ir*', 'field'))->invalid
        );
    }

    public function test_other_values_are_left_alone(): void
    {
        $fields = new Cf7IranianFields();

        $this->assertSame(['a'], $fields->normalizePostedValue(['a'], ['a'], new FakeFormTag('mobile_ir', 'field')));
        $this->assertSame('', $fields->normalizePostedValue('', '', new FakeFormTag('mobile_ir', 'field')));
        $this->assertNull(Cf7IranianFields::standardValue('text', '09121234567'));
    }
}
