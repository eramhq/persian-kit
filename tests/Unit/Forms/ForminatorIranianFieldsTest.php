<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\Forms\ForminatorIranianFields;
use PersianKit\Modules\Forms\IranianFieldTypes;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/functions.php';

class ForminatorIranianFieldsTest extends TestCase
{
    private const NATIONAL_ID = ['element_id' => 'text-1', 'type' => 'text', 'custom-class' => 'persian-kit-national-id'];

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

    public function test_register_checks_submissions_and_marks_inputs(): void
    {
        $fields = new ForminatorIranianFields();
        $fields->register();

        $this->assertNotFalse(has_filter('forminator_custom_form_submit_errors', [$fields, 'validate']));
        $this->assertNotFalse(has_filter('forminator_field_markup', [$fields, 'addInputAttributes']));
    }

    public function test_each_class_names_an_iranian_type(): void
    {
        $this->assertSame(['mobile_ir', 'national_id', 'postcode_ir', 'card_ir', 'iban_ir'], array_values(ForminatorIranianFields::MARKERS));
        $this->assertSame(IranianFieldTypes::types(), array_values(ForminatorIranianFields::MARKERS));

        foreach (ForminatorIranianFields::MARKERS as $class => $type) {
            $this->assertSame($type, ForminatorIranianFields::markerType(['type' => 'text', 'custom-class' => $class]), $class);
        }
    }

    public function test_classes_count_on_text_phone_and_number_fields_only(): void
    {
        foreach (['text', 'phone', 'number'] as $type) {
            $this->assertSame('mobile_ir', ForminatorIranianFields::markerType(['type' => $type, 'custom-class' => 'persian-kit-mobile']), $type);
        }

        foreach (['select', 'textarea', 'email', 'hidden', 'date'] as $type) {
            $this->assertNull(ForminatorIranianFields::markerType(['type' => $type, 'custom-class' => 'persian-kit-mobile']), $type);
        }

        $this->assertNull(ForminatorIranianFields::markerType(['type' => 'text']));
        $this->assertNull(ForminatorIranianFields::markerType(['type' => 'text', 'custom-class' => 'persian-kit-mobiles wide']));
    }

    public function test_with_several_classes_the_first_marker_wins(): void
    {
        $this->assertSame('postcode_ir', ForminatorIranianFields::markerType(['type' => 'text', 'custom-class' => "  wide\tpersian-kit-postcode persian-kit-card "]));
    }

    public function test_an_invalid_value_is_rejected_under_the_fields_id(): void
    {
        $errors = (new ForminatorIranianFields())->validate([], 12, [
            $this->entry('text-1', '1234567890', self::NATIONAL_ID),
            $this->entry('text-2', '0013542419', ['element_id' => 'text-2'] + self::NATIONAL_ID),
            $this->entry('text-3', 'anything', ['element_id' => 'text-3', 'type' => 'text']),
        ]);

        $this->assertSame([['text-1' => IranianFieldTypes::message('national_id')]], $errors);
    }

    public function test_a_repeated_group_row_is_rejected_under_the_id_it_was_rendered_with(): void
    {
        $errors = (new ForminatorIranianFields())->validate([], 12, [
            $this->entry('text-1-2', '1234567890', ['element_id' => 'text-1-2', 'original_id' => 'text-1-mfk2a9'] + self::NATIONAL_ID),
        ]);

        $this->assertSame([['text-1-mfk2a9' => IranianFieldTypes::message('national_id')]], $errors);
    }

    public function test_empty_values_and_fields_forminator_already_rejected_are_left_to_forminator(): void
    {
        $existing = [['phone-1' => 'Please enter a valid phone number.']];
        $mobile = ['element_id' => 'phone-1', 'type' => 'phone', 'custom-class' => 'persian-kit-mobile'];

        $this->assertSame($existing, (new ForminatorIranianFields())->validate($existing, 12, [
            $this->entry('phone-1', '12', $mobile),
            $this->entry('text-1', '  ', self::NATIONAL_ID),
        ]));
    }

    public function test_the_phone_fields_country_code_is_accepted(): void
    {
        $mobile = ['element_id' => 'phone-1', 'type' => 'phone', 'custom-class' => 'persian-kit-mobile'];

        $this->assertSame([], (new ForminatorIranianFields())->validate([], 12, [$this->entry('phone-1', '+98 912 123 4567', $mobile)]));
        $this->assertSame([['phone-1' => IranianFieldTypes::message('mobile_ir')]], (new ForminatorIranianFields())->validate([], 12, [$this->entry('phone-1', '+98 21 8888 1234', $mobile)]));
    }

    public function test_only_the_fields_forminator_checked_are_checked(): void
    {
        // A field hidden by a condition is not in the list Forminator passes.
        $this->assertSame([], (new ForminatorIranianFields())->validate([], 12, []));
        $this->assertSame('not an array', (new ForminatorIranianFields())->validate('not an array', 12, []));
    }

    public function test_inputs_of_other_fields_are_not_parsed(): void
    {
        $html = '<input type="text" name="text-1">';
        $fields = new ForminatorIranianFields();

        $this->assertSame($html, $fields->addInputAttributes($html, ['element_id' => 'text-1', 'type' => 'text']));
        $this->assertSame($html, $fields->addInputAttributes($html, ['custom-class' => 'persian-kit-mobile', 'type' => 'select', 'element_id' => 'text-1']));
        $this->assertSame($html, $fields->addInputAttributes($html, 'text-1'));
    }

    /**
     * An item of Forminator's field_data_array.
     *
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    private function entry(string $name, string $value, array $field): array
    {
        return ['name' => $name, 'value' => $value, 'field_type' => $field['type'], 'field_array' => $field];
    }
}
