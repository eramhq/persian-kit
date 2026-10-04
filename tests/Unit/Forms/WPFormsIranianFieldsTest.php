<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\Forms\IranianFieldTypes;
use PersianKit\Modules\Forms\WPForms\IranianField;
use PersianKit\Modules\Forms\WPForms\MobileField;
use PersianKit\Modules\Forms\WPForms\NationalIdField;
use PersianKit\Modules\Forms\WPFormsIranianFields;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/functions.php';

class WPFormsIranianFieldsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('__')->returnArg();
        Functions\when('esc_attr')->returnArg();
        Functions\when('sanitize_text_field')->alias('trim');
        \WPForms_Registry::$instance = null;
        IranianField::$checks = false;
    }

    protected function tearDown(): void
    {
        IranianField::$checks = false;
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_each_field_type_maps_to_an_iranian_type(): void
    {
        $this->assertSame(IranianFieldTypes::types(), array_values(WPFormsIranianFields::TYPES));
        $this->assertSame('national_id', WPFormsIranianFields::iranianType(['type' => 'persian-kit-national-id']));
        $this->assertNull(WPFormsIranianFields::iranianType(['type' => 'text']));
    }

    public function test_the_fields_are_created_on_init_after_wpforms_own_and_check_their_values(): void
    {
        $fields = new WPFormsIranianFields();
        $fields->register();

        $this->assertFalse(has_filter('wpforms_fields_get_field_object_persian-kit-mobile'));
        $this->assertNotFalse(has_action('init', [$fields, 'addFields']));

        $fields->addFields();

        foreach (array_keys(WPFormsIranianFields::TYPES) as $type) {
            $this->assertNotFalse(has_filter("wpforms_fields_get_field_object_{$type}"), $type);
        }
        $this->assertTrue(IranianField::$checks);
        $this->assertNotFalse(has_filter('wpforms_builder_fields_buttons', [$fields, 'addGroup']));
    }

    public function test_after_init_the_fields_are_created_at_once(): void
    {
        do_action('init');

        (new WPFormsIranianFields())->register();

        $this->assertNotFalse(has_filter('wpforms_fields_get_field_object_persian-kit-iban'));
    }

    public function test_turned_off_the_fields_stay_without_checks_their_buttons_or_their_group(): void
    {
        do_action('init');

        $fields = new WPFormsIranianFields();
        $fields->registerFallback();

        $this->assertNotFalse(has_filter('wpforms_fields_get_field_object_persian-kit-mobile'));
        $this->assertFalse(IranianField::$checks);
        $this->assertFalse(has_filter('wpforms_builder_fields_buttons', [$fields, 'addGroup']));

        $groups = ['standard' => ['group_name' => 'Standard Fields', 'fields' => []]];
        $this->assertSame($groups, (new MobileField())->field_button($groups));

        (new MobileField())->validate(1, '12', $this->form());
        $this->assertSame([], wpforms()->obj('process')->errors);
    }

    public function test_the_builder_gets_an_iranian_fields_group_the_buttons_go_in(): void
    {
        IranianField::$checks = true;

        $groups = (new WPFormsIranianFields())->addGroup(['standard' => ['group_name' => 'Standard Fields', 'fields' => []]]);
        $this->assertSame(['group_name' => 'Iranian fields', 'fields' => []], $groups['persian_kit']);

        $groups = (new NationalIdField())->field_button($groups);
        $this->assertSame(
            [['order' => 20, 'name' => 'National ID', 'type' => 'persian-kit-national-id', 'icon' => 'fa-id-card-o', 'keywords' => '']],
            $groups['persian_kit']['fields']
        );
    }

    public function test_the_options_are_a_text_fields_without_its_mask_limit_or_default(): void
    {
        $field = new NationalIdField();
        $field->field_options(['id' => 1]);

        $this->assertSame([
            'option:basic-options:open',
            'option:label',
            'option:description',
            'option:required',
            'option:basic-options:close',
            'option:advanced-options:open',
            'option:size',
            'option:placeholder',
            'option:css',
            'option:label_hide',
            'option:advanced-options:close',
        ], $field->printed);
    }

    public function test_the_input_has_the_types_attributes(): void
    {
        ob_start();
        (new MobileField())->field_display($this->displayed(), null, $this->form());
        $html = (string) ob_get_clean();

        $this->assertSame(
            '<input type="tel" id="wpforms-5-field_1" class="wpforms-field-medium" name="wpforms[fields][1]" placeholder="0912" dir="ltr" autocomplete="tel-national" maxlength="20" required>',
            $html
        );

        ob_start();
        (new NationalIdField())->field_display($this->displayed(''), null, $this->form());
        $this->assertStringContainsString('type="text" id="wpforms-5-field_1" class="wpforms-field-medium" name="wpforms[fields][1]" placeholder="0912" dir="ltr" autocomplete="off" maxlength="12" inputmode="numeric" >', (string) ob_get_clean());
    }

    public function test_an_invalid_value_fails_with_the_types_message(): void
    {
        IranianField::$checks = true;

        (new NationalIdField())->validate(1, '1234567890', $this->form());
        $this->assertSame([5 => [1 => IranianFieldTypes::message('national_id')]], wpforms()->obj('process')->errors);

        // Valid values, in any digits, pass, and empty ones are left to the Required check.
        foreach (['0013542419', '۰۰۱۳۵۴۲۴۱۹', ''] as $value) {
            \WPForms_Registry::$instance = null;
            (new NationalIdField())->validate(1, $value, $this->form());
            $this->assertSame([], wpforms()->obj('process')->errors, $value);
        }

        (new NationalIdField())->validate(1, '', $this->form(true));
        $this->assertSame([5 => [1 => 'This field is required.']], wpforms()->obj('process')->errors);
    }

    public function test_valid_values_are_saved_in_their_standard_form(): void
    {
        IranianField::$checks = true;

        (new MobileField())->format(1, ' ۰۹۱۲ ۱۲۳ ۴۵۶۷ ', $this->form());
        (new MobileField())->format(2, 'abc ۱۲', $this->form());

        $this->assertSame([
            1 => ['name' => 'Mobile', 'value' => '09121234567', 'id' => 1, 'type' => 'persian-kit-mobile'],
            2 => ['name' => '', 'value' => 'abc 12', 'id' => 2, 'type' => 'persian-kit-mobile'],
        ], wpforms()->obj('process')->fields);
    }

    public function test_turned_off_values_are_saved_as_typed(): void
    {
        (new MobileField())->format(1, '۰۹۱۲ ۱۲۳ ۴۵۶۷', $this->form());

        $this->assertSame('۰۹۱۲ ۱۲۳ ۴۵۶۷', wpforms()->obj('process')->fields[1]['value']);
    }

    /**
     * @return array<string, mixed>
     */
    private function form(bool $required = false): array
    {
        return ['id' => 5, 'fields' => [1 => ['id' => 1, 'type' => 'persian-kit-mobile', 'label' => 'Mobile', 'required' => $required ? '1' : '']]];
    }

    /**
     * A field with the properties WPForms gives it for display.
     *
     * @return array<string, mixed>
     */
    private function displayed(string $required = 'required'): array
    {
        return ['id' => 1, 'properties' => ['inputs' => ['primary' => [
            'attr'     => ['name' => 'wpforms[fields][1]', 'value' => '', 'placeholder' => '0912'],
            'class'    => ['wpforms-field-medium'],
            'data'     => [],
            'id'       => 'wpforms-5-field_1',
            'required' => $required,
        ]]]];
    }
}
