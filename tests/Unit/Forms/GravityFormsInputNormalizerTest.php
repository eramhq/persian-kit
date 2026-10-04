<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\Forms\GravityFormsInputNormalizer;
use PersianKit\Tests\Unit\Forms\Support\FakeGravityField;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/functions.php';

class GravityFormsInputNormalizerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        unset($_POST['input_1']);
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_fixes_submissions_before_gravity_forms_reads_them(): void
    {
        $normalizer = new GravityFormsInputNormalizer();
        $normalizer->register();

        $this->assertNotFalse(has_filter('gform_pre_process', [$normalizer, 'normalizeSubmission']));
        $this->assertNotFalse(has_filter('gform_pre_validation', [$normalizer, 'normalizeSubmission']));
        $this->assertNotFalse(has_action('gform_pre_entry_detail', [$normalizer, 'normalizeEntryUpdate']));
        $this->assertNotFalse(has_filter('gform_field_validation', [$normalizer, 'rejectInvalidDate']));
        $this->assertNotFalse(has_action('gform_enqueue_scripts', [$normalizer, 'enqueue']));
    }

    public function test_number_like_inputs_get_english_digits_and_text_keeps_its_own(): void
    {
        $post = (new GravityFormsInputNormalizer())->normalizePost([
            'input_1'   => '۰۲۱-۸۸۸۸۱۲۳۴',
            'input_2'   => '١٢',
            'input_3'   => ['۰۹', '۳۰', 'am'],
            'input_4_1' => 'خیابان ۱۲',
            'input_4_5' => '۱۲۳۴۵۶۷۸۹۰',
            'input_5_3' => '۲',
            'input_6'   => '۲۵,۰۰۰',
            'input_7'   => 'پلاک ۱۲',
            'input_8'   => '۳',
        ], $this->form([
            ['id' => 1, 'type' => 'phone'],
            ['id' => 2, 'type' => 'number'],
            ['id' => 3, 'type' => 'time'],
            ['id' => 4, 'type' => 'address'],
            ['id' => 5, 'type' => 'product', 'inputType' => 'singleproduct'],
            ['id' => 6, 'type' => 'product', 'inputType' => 'price'],
            ['id' => 7, 'type' => 'text'],
            ['id' => 8, 'type' => 'quantity'],
        ]));

        $this->assertSame([
            'input_1'   => '021-88881234',
            'input_2'   => '12',
            'input_3'   => ['09', '30', 'am'],
            'input_4_1' => 'خیابان ۱۲',
            'input_4_5' => '1234567890',
            'input_5_3' => '2',
            'input_6'   => '25,000',
            'input_7'   => 'پلاک ۱۲',
            'input_8'   => '3',
        ], $post);
    }

    public function test_inputs_gravity_forms_checks_against_what_it_printed_are_left_alone(): void
    {
        $values = [
            'input_1'   => '۱۲',
            'input_2'   => '۱۲',
            'input_3_1' => 'کالای ۱',
            'input_3_2' => '۱۲۰۰',
            'input_4_4' => 'استان ۱',
            'input_4_6' => 'ایران',
        ];

        $post = (new GravityFormsInputNormalizer())->normalizePost($values, $this->form([
            ['id' => 1, 'type' => 'hidden'],
            ['id' => 2, 'type' => 'select'],
            ['id' => 3, 'type' => 'product', 'inputType' => 'singleproduct'],
            ['id' => 4, 'type' => 'address'],
        ]));

        $this->assertSame($values, $post);
    }

    public function test_a_jalali_date_picker_value_is_sent_as_the_gregorian_date_in_the_fields_format(): void
    {
        $normalizer = new GravityFormsInputNormalizer();
        $form = $this->form([['id' => 1, 'type' => 'date', 'dateType' => 'datepicker', 'dateFormat' => 'dmy']]);

        foreach (['۱۴۰۵/۰۷/۱۲', '12/07/1405', '1405-7-12', '04/10/2026'] as $typed) {
            $this->assertSame(['input_1' => '04/10/2026'], $normalizer->normalizePost(['input_1' => $typed], $form), $typed);
        }

        // Not a date: Gravity Forms reports it.
        $this->assertSame(['input_1' => 'soon'], $normalizer->normalizePost(['input_1' => 'soon'], $form));
        $this->assertSame(['input_1' => ''], $normalizer->normalizePost(['input_1' => ''], $form));
    }

    public function test_date_boxes_and_drop_downs_are_sent_as_gregorian_parts_in_the_fields_order(): void
    {
        $normalizer = new GravityFormsInputNormalizer();
        $cases = [
            'mdy'       => [['۷', '۱۲', '۱۴۰۵'], ['10', '4', '2026']],
            'dmy_dot'   => [['12', '7', '1405'], ['4', '10', '2026']],
            'ymd_slash' => [['1405', '7', '12'], ['2026', '10', '4']],
        ];

        foreach ($cases as $format => [$sent, $saved]) {
            foreach (['datefield', 'datedropdown'] as $style) {
                $form = $this->form([['id' => 1, 'type' => 'date', 'dateType' => $style, 'dateFormat' => $format]]);
                $this->assertSame(['input_1' => $saved], $normalizer->normalizePost(['input_1' => $sent], $form), "$format $style");
            }
        }

        // A Gregorian date stays, and a part left out is Gravity Forms' to report.
        $form = $this->form([['id' => 1, 'type' => 'date', 'dateType' => 'datefield', 'dateFormat' => 'mdy']]);
        $this->assertSame(['input_1' => ['10', '4', '2026']], $normalizer->normalizePost(['input_1' => ['10', '4', '2026']], $form));
        $this->assertSame(['input_1' => ['7', '', '1405']], $normalizer->normalizePost(['input_1' => ['۷', '', '۱۴۰۵']], $form));
    }

    public function test_running_twice_changes_nothing(): void
    {
        $normalizer = new GravityFormsInputNormalizer();
        $form = $this->form([
            ['id' => 1, 'type' => 'date', 'dateType' => 'datefield', 'dateFormat' => 'dmy'],
            ['id' => 2, 'type' => 'date', 'dateType' => 'datepicker', 'dateFormat' => 'ymd_dash'],
        ]);

        $once = $normalizer->normalizePost(['input_1' => ['12', '7', '1405'], 'input_2' => '1405/07/12'], $form);

        $this->assertSame(['input_1' => ['4', '10', '2026'], 'input_2' => '2026-10-04'], $once);
        $this->assertSame($once, $normalizer->normalizePost($once, $form));
    }

    public function test_a_gregorian_date_field_only_gets_english_digits(): void
    {
        $form = $this->form([['id' => 1, 'type' => 'date', 'dateType' => 'datepicker', 'dateFormat' => 'mdy', 'persianKitGregorian' => true]]);

        $this->assertSame(['input_1' => '10/04/2026'], (new GravityFormsInputNormalizer())->normalizePost(['input_1' => '۱۰/۰۴/۲۰۲۶'], $form));
    }

    public function test_a_jalali_date_that_does_not_exist_fails_with_gravity_forms_own_message(): void
    {
        $normalizer = new GravityFormsInputNormalizer();
        $picker = new FakeGravityField(['id' => 1, 'type' => 'date', 'dateType' => 'datepicker', 'dateFormat' => 'dmy']);
        $boxes = new FakeGravityField(['id' => 2, 'type' => 'date', 'dateType' => 'datefield', 'dateFormat' => 'dmy', 'errorMessage' => 'Check the date.']);
        $form = ['id' => 5, 'fields' => [$picker, $boxes]];
        $valid = ['is_valid' => true, 'message' => ''];

        // 31 Mehr does not exist; Gravity Forms would read 1405 as a Gregorian year.
        $post = $normalizer->normalizePost(['input_1' => '31/07/1405', 'input_2' => ['31', '7', '1405']], $form);
        $this->assertSame(['input_1' => '31/07/1405', 'input_2' => ['31', '7', '1405']], $post);

        $this->assertSame(
            ['is_valid' => false, 'message' => 'Please enter a valid date in the format (dd/mm/yyyy).'],
            $normalizer->rejectInvalidDate($valid, '31/07/1405', $form, $picker)
        );
        $this->assertSame(['is_valid' => false, 'message' => 'Check the date.'], $normalizer->rejectInvalidDate($valid, [], $form, $boxes));
        $this->assertFalse($picker->failed_validation, 'the field itself is not touched');

        // A failure Gravity Forms found stays its own; other forms and later submissions pass.
        $required = ['is_valid' => false, 'message' => 'This field is required.'];
        $this->assertSame($required, $normalizer->rejectInvalidDate($required, '', $form, $picker));
        $this->assertSame($valid, $normalizer->rejectInvalidDate($valid, '', ['id' => 6], $picker));

        $normalizer->normalizePost(['input_1' => '12/07/1405'], $form);
        $this->assertSame($valid, $normalizer->rejectInvalidDate($valid, '04/10/2026', $form, $picker));
    }

    public function test_the_submission_in_post_is_fixed_and_the_form_returned(): void
    {
        $_POST['input_1'] = '۰۹۱۲۱۲۳۴۵۶۷';
        $form = $this->form([['id' => 1, 'type' => 'phone']]);

        $this->assertSame($form, (new GravityFormsInputNormalizer())->normalizeSubmission($form));
        $this->assertSame('09121234567', $_POST['input_1']);
    }

    public function test_an_entry_edited_in_the_admin_is_fixed_before_it_is_saved(): void
    {
        Functions\when('wp_unslash')->returnArg();
        Functions\when('sanitize_key')->alias(fn ($key) => strtolower((string) $key));
        $form = $this->form([['id' => 1, 'type' => 'date', 'dateType' => 'datefield', 'dateFormat' => 'ymd_slash']]);
        $normalizer = new GravityFormsInputNormalizer();

        // Showing the entry changes nothing.
        $_POST = ['input_1' => ['1405', '7', '12']];
        $normalizer->normalizeEntryUpdate($form);
        $this->assertSame(['1405', '7', '12'], $_POST['input_1']);

        $_POST['action'] = 'update';
        $normalizer->normalizeEntryUpdate($form);
        $this->assertSame(['2026', '10', '4'], $_POST['input_1']);

        $_POST = [];
    }

    public function test_the_digit_script_is_loaded_with_forms_that_have_such_fields(): void
    {
        if (!defined('PERSIAN_KIT_URL')) {
            define('PERSIAN_KIT_URL', 'https://example.com/wp-content/plugins/persian-kit/');
        }
        if (!defined('PERSIAN_KIT_VERSION')) {
            define('PERSIAN_KIT_VERSION', '1.0.0');
        }

        $enqueued = [];
        Functions\when('wp_register_script')->justReturn(true);
        Functions\when('wp_enqueue_script')->alias(function (string $handle, string $src, array $deps) use (&$enqueued) {
            $enqueued[] = [$handle, basename($src), $deps];
        });

        $normalizer = new GravityFormsInputNormalizer();
        $normalizer->enqueue($this->form([['id' => 1, 'type' => 'text'], ['id' => 2, 'type' => 'email']]));
        $this->assertSame([], $enqueued);

        $normalizer->enqueue($this->form([['id' => 1, 'type' => 'text'], ['id' => 2, 'type' => 'phone']]));
        $this->assertSame([[GravityFormsInputNormalizer::SCRIPT, 'gravityforms-digits.js', ['persian-kit-form-digits']]], $enqueued);
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @return array{id: int, fields: list<FakeGravityField>}
     */
    private function form(array $fields): array
    {
        return ['id' => 1, 'fields' => array_map(static fn (array $settings): FakeGravityField => new FakeGravityField($settings), $fields)];
    }
}
