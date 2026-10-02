<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\Forms\AcfDateFields;
use PersianKit\Service\Language\ContentLanguage;
use PersianKit\Tests\Unit\Forms\Support\FakeAcfFieldType;
use PersianKit\Tests\Unit\Support\UsesLanguages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AcfDateFieldsTest extends TestCase
{
    use UsesLanguages;

    /** @var list<string> */
    private array $enqueued = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        ContentLanguage::reset();

        if (!defined('PERSIAN_KIT_URL')) {
            define('PERSIAN_KIT_URL', 'https://example.com/wp-content/plugins/persian-kit/');
        }

        if (!defined('PERSIAN_KIT_VERSION')) {
            define('PERSIAN_KIT_VERSION', '1.0.0');
        }

        Functions\when('wp_script_is')->justReturn(true);
        Functions\when('wp_enqueue_script')->alias(function (string $handle) {
            $this->enqueued[] = $handle;
        });
        Functions\when('wp_enqueue_style')->justReturn(null);
        Functions\when('esc_attr')->alias(fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES));
        Functions\when('wp_is_serving_rest_request')->justReturn(false);
        Functions\when('current_time')->alias(fn (string $format) => $format === 'Ymd' ? '20261002' : '2026-10-02 09:30:00');
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_waits_for_acf_to_create_its_field_types(): void
    {
        Functions\when('did_action')->justReturn(0);

        $fields = new AcfDateFields(true);
        $fields->register();

        $this->assertNotFalse(has_action('acf/init', [$fields, 'replaceRenderers']));
        $this->assertNotFalse(has_action('acf/input/admin_enqueue_scripts', 'PersianKit\Modules\DateConversion\DatePicker::enqueue'));
        $this->assertSame(9, has_filter('acf/format_value/type=date_picker', [$fields, 'startJalaliValue']));
        $this->assertSame(11, has_filter('acf/format_value/type=date_time_picker', [$fields, 'stopJalaliValue']));
    }

    public function test_values_stay_gregorian_without_date_conversion(): void
    {
        Functions\when('did_action')->justReturn(0);

        $fields = new AcfDateFields(false);
        $fields->register();

        $this->assertFalse(has_filter('acf/format_value/type=date_picker', [$fields, 'startJalaliValue']));
    }

    public function test_acf_renderers_are_replaced(): void
    {
        $datePicker = new FakeAcfFieldType();
        Functions\when('acf_get_field_type')->alias(fn (string $type) => $type === 'date_picker' ? $datePicker : null);
        add_action('acf/render_field/type=date_picker', [$datePicker, 'render_field'], 9);

        $fields = new AcfDateFields(true);
        $fields->replaceRenderers();

        $this->assertFalse(has_action('acf/render_field/type=date_picker', [$datePicker, 'render_field']));
        $this->assertSame(9, has_action('acf/render_field/type=date_picker', [$fields, 'render']));
        $this->assertFalse(has_action('acf/render_field/type=date_time_picker', [$fields, 'render']));
    }

    public function test_a_date_field_renders_a_hidden_input_for_the_picker(): void
    {
        ob_start();
        (new AcfDateFields(true))->render([
            'type'  => 'date_picker',
            'id'    => 'acf-field_1',
            'class' => '',
            'name'  => 'acf[field_1]',
            'value' => '20260321',
        ]);
        $html = (string) ob_get_clean();

        $this->assertSame(
            '<div class="acf-input-wrap persian-kit-acf-date"><input type="hidden" id="acf-field_1" name="acf[field_1]" value="20260321" data-persian-kit-date data-persian-kit-date-format="Ymd"></div>',
            $html
        );
        $this->assertSame(['persian-kit-date-field'], $this->enqueued);
    }

    public function test_a_date_time_field_keeps_its_format_and_states(): void
    {
        ob_start();
        (new AcfDateFields(true))->render([
            'type'     => 'date_time_picker',
            'id'       => 'acf-field_2',
            'name'     => 'acf[field_2]',
            'value'    => '',
            'disabled' => 1,
            'default_to_current_date' => 1,
        ]);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('value="2026-10-02 09:30:00"', $html);
        $this->assertStringContainsString('disabled="disabled"', $html);
        $this->assertStringContainsString('data-persian-kit-date-format="Y-m-d H:i:s"', $html);
    }

    public function test_an_empty_field_has_an_empty_value(): void
    {
        ob_start();
        (new AcfDateFields(true))->render(['type' => 'date_picker', 'name' => 'acf[field_1]', 'value' => '']);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString(' value ', $html);
        $this->assertStringNotContainsString('id=', $html);
    }

    public function test_fields_with_an_old_save_format_keep_acfs_renderer(): void
    {
        $datePicker = new FakeAcfFieldType();
        Functions\when('acf_get_field_type')->justReturn($datePicker);
        add_action('acf/render_field/type=date_picker', [$datePicker, 'render_field'], 9);
        add_action('acf/render_field/type=date_time_picker', [$datePicker, 'render_field'], 9);

        $fields = new AcfDateFields(true);
        $fields->replaceRenderers();

        ob_start();
        $fields->render(['type' => 'date_picker', 'name' => 'x', 'value' => '15/03/2026', 'save_format' => 'dd/mm/yy']);
        $this->assertSame('acf', ob_get_clean());
    }

    /**
     * @return array<string, array{mixed, string, string}>
     */
    public static function storedValues(): array
    {
        return [
            'Ymd'             => ['20260321', 'Ymd', '20260321'],
            'Ymd as int'      => [20260321, 'Ymd', '20260321'],
            'ISO date'        => ['2026-03-21', 'Ymd', '20260321'],
            'timestamp'       => ['1774051200', 'Ymd', '20260321'],
            'date and time'   => ['2026-03-21 08:15:00', 'Y-m-d H:i:s', '2026-03-21 08:15:00'],
            'ISO date time'   => ['2026-03-21T08:15:00', 'Y-m-d H:i:s', '2026-03-21 08:15:00'],
            'empty'           => ['', 'Ymd', ''],
            'unreadable'      => ['soon', 'Ymd', ''],
            'not a string'    => [['20260321'], 'Ymd', ''],
        ];
    }

    #[DataProvider('storedValues')]
    public function test_stored_values_are_read_without_date_conversion(mixed $value, string $format, string $expected): void
    {
        $this->assertSame($expected, AcfDateFields::storedValue($value, $format));
    }

    public function test_template_values_are_jalali_in_the_return_format(): void
    {
        $fields = new AcfDateFields(true);
        // ACF: date_i18n($format, strtotime('20260321')), read as UTC.
        $timestamp = (int) strtotime('2026-03-21 00:00:00 UTC');

        $this->assertSame('1405/01/01', $fields->jalaliDate('21/03/2026', 'Y/m/d', $timestamp));
        $this->assertSame('01/01/1405 8:15', $fields->jalaliDate('', 'd/m/Y G:i', $timestamp + 8 * 3600 + 15 * 60));
    }

    public function test_formats_code_reads_stay_gregorian(): void
    {
        $fields = new AcfDateFields(true);
        $timestamp = (int) strtotime('2026-03-21 00:00:00 UTC');

        foreach (['Ymd', 'Y-m-d', 'Y-m-d H:i:s', 'U', 'c'] as $format) {
            $this->assertSame('gregorian', $fields->jalaliDate('gregorian', $format, $timestamp), $format);
        }
    }

    public function test_the_jalali_filter_runs_only_around_acfs_own_formatting(): void
    {
        $fields = new AcfDateFields(true);

        $this->assertSame('20260321', $fields->startJalaliValue('20260321', 1, ['type' => 'date_picker']));
        $this->assertSame(10, has_filter('date_i18n', [$fields, 'jalaliDate']));

        $this->assertSame('x', $fields->stopJalaliValue('x'));
        $this->assertFalse(has_filter('date_i18n', [$fields, 'jalaliDate']));
    }

    public function test_rest_responses_and_opted_out_fields_stay_gregorian(): void
    {
        $fields = new AcfDateFields(true);

        Functions\when('wp_is_serving_rest_request')->justReturn(true);
        $fields->startJalaliValue('20260321', 1, ['type' => 'date_picker']);
        $this->assertFalse(has_filter('date_i18n', [$fields, 'jalaliDate']));

        Functions\when('wp_is_serving_rest_request')->justReturn(false);
        Monkey\Filters\expectApplied('persian_kit_acf_jalali_value')->once()->andReturn(false);
        $fields->startJalaliValue('20260321', 1, ['type' => 'date_picker']);
        $this->assertFalse(has_filter('date_i18n', [$fields, 'jalaliDate']));
    }

    public function test_pages_and_admins_not_in_persian_get_acfs_own_picker_and_values(): void
    {
        $datePicker = new FakeAcfFieldType();
        Functions\when('acf_get_field_type')->justReturn($datePicker);
        add_action('acf/render_field/type=date_picker', [$datePicker, 'render_field'], 9);
        add_action('acf/render_field/type=date_time_picker', [$datePicker, 'render_field'], 9);

        $fields = new AcfDateFields(true);
        $fields->replaceRenderers();
        $this->inLanguage('en_US', true);

        ob_start();
        $fields->render(['type' => 'date_picker', 'name' => 'x', 'value' => '20260321']);
        $this->assertSame('acf', ob_get_clean());
        $this->assertSame([], $this->enqueued);

        $this->inLanguage('en_US');
        $fields->startJalaliValue('20260321', 1, ['type' => 'date_picker']);
        $this->assertFalse(has_filter('date_i18n', [$fields, 'jalaliDate']));
    }
}
