<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\Forms\WPForms\IranianField;
use PersianKit\Modules\Forms\WPForms\JalaliDateField;
use PersianKit\Modules\Forms\WPFormsDateField;
use PersianKit\Service\Language\ContentLanguage;
use PersianKit\Tests\Unit\Support\UsesLanguages;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/functions.php';

class WPFormsDateFieldTest extends TestCase
{
    use UsesLanguages;

    /** @var list<string> */
    private array $enqueued = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        ContentLanguage::reset();
        \WPForms_Registry::$instance = null;
        JalaliDateField::$enabled = true;
        $_POST = [];

        if (!defined('PERSIAN_KIT_URL')) {
            define('PERSIAN_KIT_URL', 'https://example.com/wp-content/plugins/persian-kit/');
        }
        if (!defined('PERSIAN_KIT_VERSION')) {
            define('PERSIAN_KIT_VERSION', '1.0.0');
        }
        Functions\when('__')->returnArg();
        Functions\when('esc_attr')->returnArg();
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_html__')->returnArg();
        Functions\when('sanitize_text_field')->alias('trim');
        Functions\when('absint')->alias(fn ($value) => abs((int) $value));
        Functions\when('wp_unslash')->returnArg();
        Functions\when('wp_script_is')->justReturn(false);
        Functions\when('wp_register_script')->justReturn(true);
        Functions\when('wp_register_style')->justReturn(true);
        Functions\when('wp_add_inline_script')->justReturn(true);
        Functions\when('wp_json_encode')->alias('json_encode');
        Functions\when('determine_locale')->justReturn('fa_IR');
        Functions\when('wp_enqueue_style')->justReturn(null);
        $this->enqueued = [];
        Functions\when('wp_enqueue_script')->alias(function (string $handle) {
            $this->enqueued[] = $handle;
        });
    }

    protected function tearDown(): void
    {
        JalaliDateField::$enabled = false;
        $_POST = [];
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_the_field_is_created_on_init_in_the_iranian_fields_group(): void
    {
        $registrar = new WPFormsDateField();
        $registrar->register();
        $this->assertNotFalse(has_action('init', [$registrar, 'addField']));
        $this->assertFalse(has_filter('wpforms_fields_get_field_object_persian-kit-date'));

        do_action('init');
        $registrar->register();
        $this->assertNotFalse(has_filter('wpforms_fields_get_field_object_persian-kit-date'));
        $this->assertTrue(JalaliDateField::$enabled);

        $field = new JalaliDateField();
        $this->assertSame(['type' => 'persian-kit-date', 'name' => 'Jalali date', 'group' => IranianField::GROUP], ['type' => $field->type, 'name' => $field->name, 'group' => $field->group]);
        $this->assertSame([['order' => 60, 'name' => 'Jalali date', 'type' => 'persian-kit-date', 'icon' => 'fa-calendar', 'keywords' => '']], $field->field_button([])[IranianField::GROUP]['fields']);
    }

    public function test_turned_off_the_field_stays_without_its_button(): void
    {
        do_action('init');
        (new WPFormsDateField())->registerFallback();

        $this->assertNotFalse(has_filter('wpforms_fields_get_field_object_persian-kit-date'));
        $this->assertFalse(JalaliDateField::$enabled);
        $this->assertSame([], (new JalaliDateField())->field_button([]));
    }

    public function test_formats_are_day_month_and_year_in_each_order_and_default_to_year_first(): void
    {
        $this->assertSame('d.m.Y', JalaliDateField::dateFormat(['date_format' => 'd.m.Y']));
        $this->assertSame('Y/m/d', JalaliDateField::dateFormat(['date_format' => 'Y-m-d H:i:s']));
        $this->assertSame('Y/m/d', JalaliDateField::dateFormat([]));

        $field = new JalaliDateField();
        $field->field_options(['id' => 1, 'date_format' => 'd/m/Y']);
        $this->assertContains('element:select:date_format', $field->printed);
        $this->assertContains('option:required', $field->printed);
        $this->assertNotContains('option:default_value', $field->printed);
    }

    public function test_the_builder_shows_the_format_with_a_jalali_date(): void
    {
        $field = new JalaliDateField();

        ob_start();
        $field->field_preview(['id' => 1, 'date_format' => 'd-m-Y']);
        $this->assertStringContainsString('placeholder="25-07-1405"', (string) ob_get_clean());
    }

    public function test_the_input_gets_the_jalali_picker_in_the_fields_format(): void
    {
        ob_start();
        (new JalaliDateField())->field_display($this->displayed(['date_format' => 'd/m/Y']), null, $this->form());

        $this->assertSame(
            '<input type="text" id="wpforms-5-field_3" class="wpforms-field-medium" name="wpforms[fields][3]" data-persian-kit-date data-persian-kit-date-format="d/m/Y" required>',
            (string) ob_get_clean()
        );
        $this->assertSame(['persian-kit-date-field'], $this->enqueued);
    }

    public function test_on_pages_in_another_language_it_is_the_browsers_date_input(): void
    {
        $this->inLanguage('en_US');

        ob_start();
        (new JalaliDateField())->field_display($this->displayed(['date_format' => 'd/m/Y'], '04/10/2026'), null, $this->form());

        $this->assertSame(
            '<input type="date" id="wpforms-5-field_3" class="wpforms-field-medium" name="wpforms[fields][3]" value="2026-10-04" required>',
            (string) ob_get_clean()
        );
        $this->assertSame([], $this->enqueued);
    }

    public function test_turned_off_it_is_a_text_input(): void
    {
        JalaliDateField::$enabled = false;

        ob_start();
        (new JalaliDateField())->field_display($this->displayed([], '1405/07/12', ''), null, $this->form());

        $this->assertSame('<input type="text" id="wpforms-5-field_3" class="wpforms-field-medium" name="wpforms[fields][3]" value="1405/07/12" >', (string) ob_get_clean());
    }

    public function test_a_date_that_does_not_exist_fails(): void
    {
        $field = new JalaliDateField();

        // The picker's Gregorian date, a typed Jalali one, in any digits, and an empty one pass.
        foreach (['2026/10/04', '1405/07/12', '۱۴۰۵/۰۷/۳۰', ''] as $value) {
            $field->validate(3, $value, $this->form());
            $this->assertSame([], wpforms()->obj('process')->errors, $value);
        }

        $field->validate(3, '1405/07/31', $this->form());
        $this->assertSame([5 => [3 => 'Enter a valid date, such as 1405/07/25.']], wpforms()->obj('process')->errors);

        \WPForms_Registry::$instance = null;
        $field->validate(3, '', $this->form(['required' => '1']));
        $this->assertSame([5 => [3 => 'This field is required.']], wpforms()->obj('process')->errors);
    }

    public function test_turned_off_any_text_passes(): void
    {
        JalaliDateField::$enabled = false;

        (new JalaliDateField())->validate(3, 'tomorrow', $this->form());

        $this->assertSame([], wpforms()->obj('process')->errors);
    }

    public function test_the_entry_keeps_the_jalali_date_in_the_fields_format_and_the_gregorian_date(): void
    {
        (new JalaliDateField())->format(3, '04/10/2026', $this->form(['date_format' => 'd/m/Y']));
        (new JalaliDateField())->format(4, '', $this->form());

        $this->assertSame([
            3 => ['name' => 'Birthday', 'value' => '12/07/1405', 'date' => '2026-10-04', 'unix' => 1791072000, 'id' => 3, 'type' => 'persian-kit-date'],
            4 => ['name' => '', 'value' => '', 'date' => '', 'unix' => '', 'id' => 4, 'type' => 'persian-kit-date'],
        ], wpforms()->obj('process')->fields);
    }

    public function test_on_multilingual_sites_the_entry_follows_the_page_the_form_was_sent_from(): void
    {
        Functions\when('get_post')->justReturn((object) ['ID' => 21]);
        $source = $this->inLanguage('fa_IR', true);
        $source->posts = [21 => 'en_US', 22 => 'fa_IR'];

        $_POST['page_id'] = '21';
        (new JalaliDateField())->format(3, '2026/10/04', $this->form());
        $this->assertSame('2026/10/04', wpforms()->obj('process')->fields[3]['value']);

        $_POST['page_id'] = '22';
        (new JalaliDateField())->format(3, '2026/10/04', $this->form());
        $this->assertSame('1405/07/12', wpforms()->obj('process')->fields[3]['value']);
    }

    public function test_turned_off_the_entry_keeps_what_was_typed(): void
    {
        JalaliDateField::$enabled = false;

        (new JalaliDateField())->format(3, '۱۴۰۵/۰۷/۱۲', $this->form());

        $this->assertSame(['۱۴۰۵/۰۷/۱۲', '', ''], array_values(array_intersect_key(wpforms()->obj('process')->fields[3], array_flip(['value', 'date', 'unix']))));
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function form(array $settings = []): array
    {
        return ['id' => 5, 'fields' => [3 => $settings + ['id' => 3, 'type' => 'persian-kit-date', 'label' => 'Birthday']]];
    }

    /**
     * A field with the properties WPForms gives it for display.
     *
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function displayed(array $settings = [], string $value = '', string $required = 'required'): array
    {
        return $settings + ['id' => 3, 'properties' => ['inputs' => ['primary' => [
            'attr'     => ['name' => 'wpforms[fields][3]', 'value' => $value, 'placeholder' => ''],
            'class'    => ['wpforms-field-medium'],
            'data'     => [],
            'id'       => 'wpforms-5-field_3',
            'required' => $required,
        ]]]];
    }
}
