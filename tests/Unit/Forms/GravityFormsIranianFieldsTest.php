<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use PersianKit\Modules\Forms\GravityForms\IranianField;
use PersianKit\Modules\Forms\GravityForms\MobileField;
use PersianKit\Modules\Forms\GravityForms\NationalIdField;
use PersianKit\Modules\Forms\GravityFormsInputNormalizer;
use PersianKit\Modules\Forms\GravityFormsIranianFields;
use PersianKit\Modules\Forms\IranianFieldTypes;
use PersianKit\Tests\Unit\Forms\Support\FakeGravityField;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/functions.php';

class GravityFormsIranianFieldsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('__')->returnArg();
        Functions\when('esc_attr')->returnArg();
        Functions\when('esc_attr__')->returnArg();
        \GF_Fields::$fields = [];
        IranianField::$checks = false;
    }

    protected function tearDown(): void
    {
        \GF_Fields::$fields = [];
        IranianField::$checks = false;
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_each_field_type_maps_to_an_iranian_type(): void
    {
        $this->assertSame(IranianFieldTypes::types(), array_values(GravityFormsIranianFields::TYPES));
        $this->assertSame('national_id', GravityFormsIranianFields::iranianType(new FakeGravityField(['type' => 'persian_kit_national_id'])));
        $this->assertNull(GravityFormsIranianFields::iranianType(new FakeGravityField(['type' => 'text'])));
    }

    public function test_the_fields_are_registered_once_gravity_forms_has_loaded_and_check_their_values(): void
    {
        Actions\expectDone('gform_loaded');
        do_action('gform_loaded');

        $fields = new GravityFormsIranianFields();
        $fields->register();

        $this->assertSame(array_keys(GravityFormsIranianFields::TYPES), array_keys(\GF_Fields::$fields));
        $this->assertTrue(IranianField::$checks);
        $this->assertNotFalse(has_filter('gform_field_groups_form_editor', [$fields, 'addGroup']));

        // Registering again keeps the first ones.
        $fields->addFields();
        $this->assertCount(5, \GF_Fields::$fields);
    }

    public function test_turned_off_the_fields_stay_without_checks_or_their_group(): void
    {
        do_action('gform_loaded');

        $fields = new GravityFormsIranianFields();
        $fields->registerFallback();

        $this->assertCount(5, \GF_Fields::$fields);
        $this->assertFalse(IranianField::$checks);
        $this->assertFalse(has_filter('gform_field_groups_form_editor', [$fields, 'addGroup']));

        $field = new MobileField();
        $field->validate('12', []);
        $this->assertNull($field->failed_validation);
    }

    public function test_the_editor_gets_an_iranian_fields_group_the_buttons_go_in(): void
    {
        $groups = (new GravityFormsIranianFields())->addGroup(['standard_fields' => ['name' => 'standard_fields', 'label' => 'Standard Fields', 'fields' => []]]);

        $this->assertSame(['name' => 'persian_kit_fields', 'label' => 'Iranian fields', 'fields' => []], $groups['persian_kit_fields']);
        $this->assertSame(['group' => 'persian_kit_fields', 'text' => 'National ID'], (new NationalIdField())->get_form_editor_button());
        $this->assertNotContains('input_mask_setting', (new NationalIdField())->get_form_editor_field_settings());
        $this->assertContains('error_message_setting', (new NationalIdField())->get_form_editor_field_settings());
    }

    public function test_an_invalid_value_fails_with_the_types_message_or_the_fields_own(): void
    {
        IranianField::$checks = true;

        $field = new NationalIdField();
        $field->validate('1234567890', []);
        $this->assertTrue($field->failed_validation);
        $this->assertSame(IranianFieldTypes::message('national_id'), $field->validation_message);

        $custom = new NationalIdField();
        $custom->errorMessage = 'کد ملی درست نیست.';
        $custom->validate('1234567890', []);
        $this->assertSame('کد ملی درست نیست.', $custom->validation_message);

        // Valid values, in any digits, and empty ones (Gravity Forms' Required check) pass.
        foreach (['0013542419', '۰۰۱۳۵۴۲۴۱۹', ''] as $value) {
            $valid = new NationalIdField();
            $valid->validate($value, []);
            $this->assertNull($valid->failed_validation, $value);
        }
    }

    public function test_valid_values_are_saved_in_their_standard_form(): void
    {
        $post = (new GravityFormsInputNormalizer())->normalizePost(
            ['input_1' => '۰۹۱۲ ۱۲۳ ۴۵۶۷', 'input_2' => '+98 912 123 4567', 'input_3' => 'abc ۱۲'],
            ['id' => 1, 'fields' => [
                new FakeGravityField(['id' => 1, 'type' => 'persian_kit_mobile']),
                new FakeGravityField(['id' => 2, 'type' => 'persian_kit_mobile']),
                new FakeGravityField(['id' => 3, 'type' => 'persian_kit_postcode']),
            ]]
        );

        $this->assertSame(['input_1' => '09121234567', 'input_2' => '09121234567', 'input_3' => 'abc 12'], $post);
    }
}
