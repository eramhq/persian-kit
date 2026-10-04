<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\Forms\ForminatorInputNormalizer;
use PersianKit\Tests\Unit\Forms\Support\FakeForminatorForm;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/functions.php';

class ForminatorInputNormalizerTest extends TestCase
{
    private const FIELDS = [
        'phone-1'    => ['element_id' => 'phone-1', 'type' => 'phone'],
        'phone-2'    => ['element_id' => 'phone-2', 'type' => 'phone', 'custom-class' => 'persian-kit-mobile'],
        'number-1'   => ['element_id' => 'number-1', 'type' => 'number'],
        'currency-1' => ['element_id' => 'currency-1', 'type' => 'currency'],
        'text-1'     => ['element_id' => 'text-1', 'type' => 'text'],
        'text-2'     => ['element_id' => 'text-2', 'type' => 'text', 'custom-class' => 'persian-kit-national-id', 'parent_group' => 'group-1'],
        'date-1'     => ['element_id' => 'date-1', 'type' => 'date', 'field_type' => 'picker', 'date_format' => 'dd/mm/yy'],
        'date-2'     => ['element_id' => 'date-2', 'type' => 'date', 'field_type' => 'select', 'date_format' => 'dd/mm/yy'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        \Forminator_CForm_Front_Action::$prepared_data = [];
        \Forminator_CForm_Front_Action::$module_object = null;
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_runs_before_forminator_reads_the_submission(): void
    {
        $normalizer = new ForminatorInputNormalizer();
        $normalizer->register();

        foreach (['forminator_form_before_save_entry', 'forminator_form_draft_before_save_entry', 'forminator_form_before_handle_submit'] as $hook) {
            $this->assertNotFalse(has_action($hook, [$normalizer, 'normalizeSubmission']), $hook);
        }
        $this->assertNotFalse(has_action('forminator_custom_forms_enqueue_scripts', [$normalizer, 'enqueue']));
    }

    public function test_phone_number_and_currency_get_english_digits_and_text_keeps_its_own(): void
    {
        $data = (new ForminatorInputNormalizer())->normalizeData([
            'phone-1'    => '۰۲۱-۸۸۸۸۱۲۳۴',
            'number-1'   => '١٢',
            'currency-1' => '۲۵۰۰۰',
            'text-1'     => 'پلاک ۱۲',
        ], self::FIELDS);

        $this->assertSame(['phone-1' => '021-88881234', 'number-1' => '12', 'currency-1' => '25000', 'text-1' => 'پلاک ۱۲'], $data);
    }

    public function test_a_typed_jalali_date_is_saved_as_the_gregorian_date_in_the_fields_format(): void
    {
        $normalizer = new ForminatorInputNormalizer();

        $this->assertSame('02/10/2026', $normalizer->normalize(self::FIELDS['date-1'], '۱۰/۰۷/۱۴۰۵'));
        $this->assertSame('02/10/2026', $normalizer->normalize(self::FIELDS['date-1'], '1405/7/10'));
        $this->assertSame('02/10/2026', $normalizer->normalize(self::FIELDS['date-1'], '02/10/2026'));

        foreach (['mm/dd/yy' => '10/02/2026', 'yy.mm.dd' => '2026.10.02', 'dd-mm-yy' => '02-10-2026'] as $format => $expected) {
            $this->assertSame($expected, $normalizer->normalize(['date_format' => $format] + self::FIELDS['date-1'], '۱۴۰۵/۰۷/۱۰'), $format);
        }

        // Not a date: English digits, for Forminator to report.
        $this->assertSame('31/02/2026', $normalizer->normalize(self::FIELDS['date-1'], '۳۱/۰۲/۲۰۲۶'));
    }

    public function test_iranian_fields_are_saved_in_their_standard_form(): void
    {
        $data = (new ForminatorInputNormalizer())->normalizeData([
            'phone-2' => '+98 912 123 4567',
            'text-2'  => '۰۰۱۳۵۴۲۴۱۹',
        ], self::FIELDS);

        $this->assertSame(['phone-2' => '09121234567', 'text-2' => '0013542419'], $data);

        // Not valid: English digits, for the check to reject.
        $this->assertSame('12345', (new ForminatorInputNormalizer())->normalize(self::FIELDS['text-2'], '۱۲۳۴۵'));
    }

    public function test_repeated_group_rows_are_fixed_too(): void
    {
        $data = (new ForminatorInputNormalizer())->normalizeData([
            'text-2-2' => '۰۰۱۳۵۴۲۴۱۹',
            // Not in a group: a field of its own Forminator doesn't know.
            'number-1-2' => '۱۲',
        ], self::FIELDS);

        $this->assertSame(['text-2-2' => '0013542419', 'number-1-2' => '۱۲'], $data);
    }

    public function test_other_values_are_left_alone(): void
    {
        $data = [
            'date-2'       => ['day' => '۱۰', 'month' => '۷', 'year' => '۱۴۰۵'],
            'phone-1'      => '',
            'form_id'      => '12',
            'current_url'  => 'https://example.org/۱۲',
        ];

        $this->assertSame($data, (new ForminatorInputNormalizer())->normalizeData($data, self::FIELDS));
    }

    public function test_the_submission_forminator_reads_is_fixed(): void
    {
        \Forminator_CForm_Front_Action::$module_object = new FakeForminatorForm(array_values(self::FIELDS));
        \Forminator_CForm_Front_Action::$prepared_data = ['form_id' => '12', 'phone-2' => '۰۹۱۲۱۲۳۴۵۶۷', 'date-1' => '۱۴۰۵/۰۷/۱۰'];

        (new ForminatorInputNormalizer())->normalizeSubmission();

        $this->assertSame(['form_id' => '12', 'phone-2' => '09121234567', 'date-1' => '02/10/2026'], \Forminator_CForm_Front_Action::$prepared_data);
    }

    public function test_without_a_form_nothing_changes(): void
    {
        \Forminator_CForm_Front_Action::$prepared_data = ['phone-2' => '۰۹۱۲۱۲۳۴۵۶۷'];

        (new ForminatorInputNormalizer())->normalizeSubmission();

        $this->assertSame(['phone-2' => '۰۹۱۲۱۲۳۴۵۶۷'], \Forminator_CForm_Front_Action::$prepared_data);
    }

    public function test_the_digit_script_loads_with_forms_that_have_phone_number_or_iranian_fields(): void
    {
        if (!defined('PERSIAN_KIT_URL')) {
            define('PERSIAN_KIT_URL', 'https://example.com/wp-content/plugins/persian-kit/');
        }
        if (!defined('PERSIAN_KIT_VERSION')) {
            define('PERSIAN_KIT_VERSION', '1.0.0');
        }

        $enqueued = [];
        Functions\when('wp_register_script')->justReturn(true);
        Functions\when('wp_enqueue_script')->alias(function (string $handle) use (&$enqueued) {
            $enqueued[] = $handle;
        });

        $normalizer = new ForminatorInputNormalizer();
        $normalizer->enqueue($this->render([self::FIELDS['text-1'], self::FIELDS['date-1']]));
        $this->assertSame([], $enqueued);

        $normalizer->enqueue($this->render([self::FIELDS['text-1'], self::FIELDS['currency-1']]));
        $normalizer->enqueue($this->render([self::FIELDS['text-2']]));
        $this->assertSame([ForminatorInputNormalizer::SCRIPT, ForminatorInputNormalizer::SCRIPT], $enqueued);
    }

    /**
     * @param list<array<string, mixed>> $fields
     */
    private function render(array $fields): object
    {
        return new class ($fields) {
            /** @param list<array<string, mixed>> $fields */
            public function __construct(private array $fields)
            {
            }

            /** @return list<array<string, mixed>> */
            public function get_fields(): array
            {
                return $this->fields;
            }
        };
    }
}
