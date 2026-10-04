<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\Forms\ForminatorDateField;
use PersianKit\Service\Language\ContentLanguage;
use PersianKit\Tests\Unit\Forms\Support\FakeForminatorForm;
use PersianKit\Tests\Unit\Support\UsesLanguages;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/functions.php';

class ForminatorDateFieldTest extends TestCase
{
    use UsesLanguages;

    private const CALENDAR = ['element_id' => 'date-1', 'type' => 'date', 'field_type' => 'picker', 'date_format' => 'dd/mm/yy'];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        ContentLanguage::reset();

        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('get_option')->alias(fn (string $option) => $option === 'date_format' ? 'Y/m/d' : false);
        Functions\when('esc_html')->alias(fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES));
        Functions\when('wp_strip_all_tags')->alias(fn ($text) => strip_tags((string) $text));

        \Forminator_Mail::$writing = true;
        \Forminator_CForm_Front_Action::$module_object = new FakeForminatorForm([
            self::CALENDAR,
            ['element_id' => 'date-2', 'type' => 'date', 'field_type' => 'select', 'date_format' => 'dd/mm/yy'],
            ['element_id' => 'date-3', 'type' => 'date', 'field_type' => 'picker', 'date_format' => 'yy-mm-dd', 'custom-class' => 'wide persian-kit-gregorian'],
            ['element_id' => 'date-4', 'type' => 'date', 'field_type' => 'picker', 'date_format' => 'mm.dd.yy', 'parent_group' => 'group-1'],
        ]);
    }

    protected function tearDown(): void
    {
        \Forminator_Mail::$writing = false;
        \Forminator_CForm_Front_Action::$module_object = null;
        \Forminator_Base_Form_Model::$models = [];
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_upgrades_calendars_and_formats_dates_where_people_read_them(): void
    {
        $field = new ForminatorDateField(true);
        $field->register();

        $this->assertNotFalse(has_filter('forminator_field_date_markup', [$field, 'upgradeMarkup']));
        $this->assertNotFalse(has_action('forminator_custom_forms_enqueue_scripts', [$field, 'enqueue']));
        $this->assertNotFalse(has_filter('forminator_replace_form_data', [$field, 'formatMailMacros']));
        $this->assertNotFalse(has_filter('forminator_custom_form_after_render_value', [$field, 'formatMailValue']));
        $this->assertNotFalse(has_filter('forminator_get_entry_field_value', [$field, 'formatEntryValue']));
    }

    public function test_without_jalali_dates_emails_and_submissions_stay_gregorian(): void
    {
        $field = new ForminatorDateField(false);
        $field->register();

        $this->assertNotFalse(has_filter('forminator_field_date_markup', [$field, 'upgradeMarkup']));
        $this->assertFalse(has_filter('forminator_replace_form_data', [$field, 'formatMailMacros']));
        $this->assertFalse(has_filter('forminator_get_entry_field_value', [$field, 'formatEntryValue']));
    }

    public function test_only_calendars_get_the_picker(): void
    {
        $this->assertSame('d/m/Y', ForminatorDateField::calendarFormat(self::CALENDAR));
        $this->assertTrue(ForminatorDateField::isJalali(self::CALENDAR));

        foreach (['select', 'input'] as $style) {
            $this->assertNull(ForminatorDateField::calendarFormat(['field_type' => $style] + self::CALENDAR), $style);
        }
        $this->assertNull(ForminatorDateField::calendarFormat(['type' => 'text'] + self::CALENDAR));
        $this->assertNull(ForminatorDateField::calendarFormat(['date_format' => 'd M Y'] + self::CALENDAR));

        // persian-kit-gregorian keeps Forminator's calendar; its dates are still read.
        $gregorian = ['custom-class' => 'wide  persian-kit-gregorian'] + self::CALENDAR;
        $this->assertFalse(ForminatorDateField::isJalali($gregorian));
        $this->assertSame('d/m/Y', ForminatorDateField::calendarFormat($gregorian));
    }

    public function test_all_nine_formats_map_to_php_formats_and_back(): void
    {
        $expected = [
            'mm/dd/yy' => '10/02/2026',
            'mm.dd.yy' => '10.02.2026',
            'mm-dd-yy' => '10-02-2026',
            'yy-mm-dd' => '2026-10-02',
            'yy.mm.dd' => '2026.10.02',
            'yy/mm/dd' => '2026/10/02',
            'dd/mm/yy' => '02/10/2026',
            'dd.mm.yy' => '02.10.2026',
            'dd-mm-yy' => '02-10-2026',
        ];

        $this->assertSame(array_keys($expected), array_keys(ForminatorDateField::FORMATS));

        foreach ($expected as $forminator => $value) {
            $format = ForminatorDateField::FORMATS[$forminator];
            $this->assertSame($value, ForminatorDateField::inFormat('2026-10-02', $format), $forminator);
            $this->assertSame('2026-10-02', ForminatorDateField::toGregorian($value, $format), $forminator);
        }
    }

    public function test_typed_jalali_dates_are_read_in_the_fields_format_or_year_first(): void
    {
        $this->assertSame('2026-10-02', ForminatorDateField::toGregorian('۱۰/۰۷/۱۴۰۵', 'd/m/Y'));
        $this->assertSame('2026-10-02', ForminatorDateField::toGregorian('7/10/1405', 'm/d/Y'));
        $this->assertSame('2026-10-02', ForminatorDateField::toGregorian('١٤٠٥.٧.١٠', 'd.m.Y'));
        $this->assertSame('2026-10-02', ForminatorDateField::toGregorian(' 1405/07/10 ', 'm-d-Y'));
        $this->assertSame('2026-10-02', ForminatorDateField::toGregorian('2026-10-02', 'd/m/Y'));

        foreach (['', 'soon', '10/02', '31/02/2026', '1405/13/01', '10/02/26'] as $value) {
            $this->assertNull(ForminatorDateField::toGregorian($value, 'd/m/Y'), $value);
        }
    }

    public function test_the_picker_is_left_off_dropdowns_gregorian_calendars_and_pages_in_other_languages(): void
    {
        $field = new ForminatorDateField(true);
        $html = '<input type="text" name="date-1" class="forminator-input forminator-datepicker" data-format="dd/mm/yy">';

        $this->assertSame($html, $field->upgradeMarkup($html, ['field_type' => 'select'] + self::CALENDAR));
        $this->assertSame($html, $field->upgradeMarkup($html, ['custom-class' => 'persian-kit-gregorian'] + self::CALENDAR));
        $this->assertSame($html, $field->upgradeMarkup($html, 'date-1'));

        $this->inLanguage('en_US');
        $this->assertSame($html, $field->upgradeMarkup($html, self::CALENDAR));
    }

    public function test_the_picker_is_loaded_with_forms_that_have_a_jalali_calendar(): void
    {
        if (!defined('PERSIAN_KIT_URL')) {
            define('PERSIAN_KIT_URL', 'https://example.com/wp-content/plugins/persian-kit/');
        }
        if (!defined('PERSIAN_KIT_VERSION')) {
            define('PERSIAN_KIT_VERSION', '1.0.0');
        }
        Functions\when('wp_script_is')->justReturn(false);
        Functions\when('wp_register_script')->justReturn(true);
        Functions\when('wp_register_style')->justReturn(true);
        Functions\when('wp_add_inline_script')->justReturn(true);
        Functions\when('wp_json_encode')->alias('json_encode');
        Functions\when('determine_locale')->justReturn('fa_IR');
        Functions\when('__')->returnArg();
        Functions\when('wp_enqueue_style')->justReturn(null);

        $enqueued = [];
        Functions\when('wp_enqueue_script')->alias(function (string $handle) use (&$enqueued) {
            $enqueued[] = $handle;
        });

        $field = new ForminatorDateField(true);
        $field->enqueue($this->render([['field_type' => 'select'] + self::CALENDAR, ['custom-class' => 'persian-kit-gregorian'] + self::CALENDAR]));
        $this->assertSame([], $enqueued);

        $field->enqueue($this->render([['type' => 'text', 'element_id' => 'text-1'], self::CALENDAR]));
        $this->assertSame(['persian-kit-date-field'], $enqueued);
    }

    public function test_date_tags_in_emails_show_the_jalali_date(): void
    {
        $field = new ForminatorDateField(true);
        $data = ['date-1' => '02/10/2026', 'date-2' => '02/10/2026', 'date-3' => '2026-10-02', 'date-4-2' => '10.02.2026'];

        $this->assertSame(
            'Visit: 1405/07/10 (<b>1405/07/10</b>)',
            $field->formatMailMacros('Visit: 02/10/2026 (<b>02/10/2026</b>)', $data, 'Visit: {date-1} (<b>{date-1}</b>)')
        );

        // A repeated group row.
        $this->assertSame('1405/07/10', $field->formatMailMacros('10.02.2026', $data, '{date-4-2}'));

        // Dropdowns and persian-kit-gregorian calendars stay Gregorian.
        $this->assertSame('02/10/2026', $field->formatMailMacros('02/10/2026', $data, '{date-2}'));
        $this->assertSame('2026-10-02', $field->formatMailMacros('2026-10-02', $data, '{date-3}'));

        // Text without date tags is left alone.
        $this->assertSame('02/10/2026', $field->formatMailMacros('02/10/2026', $data, '02/10/2026'));
    }

    public function test_date_tags_outside_emails_keep_the_submitted_value(): void
    {
        \Forminator_Mail::$writing = false;

        // Such as a redirect URL or user meta.
        $this->assertSame(
            'https://example.org/?d=02/10/2026',
            (new ForminatorDateField(true))->formatMailMacros('https://example.org/?d=02/10/2026', ['date-1' => '02/10/2026'], 'https://example.org/?d={date-1}')
        );
    }

    public function test_all_fields_in_emails_show_jalali_calendar_dates(): void
    {
        $field = new ForminatorDateField(true);
        $form = \Forminator_CForm_Front_Action::$module_object;

        $this->assertSame('1405/07/10', $field->formatMailValue('02/10/2026', $form, 'date-1', []));
        $this->assertSame('1405/07/10', $field->formatMailValue('10.02.2026', $form, 'date-4-3', []));
        $this->assertSame('02/10/2026', $field->formatMailValue('02/10/2026', $form, 'date-2', []));
        $this->assertSame('02/10/2026', $field->formatMailValue('02/10/2026', $form, 'text-1', []));

        \Forminator_Mail::$writing = false;
        $this->assertSame('02/10/2026', $field->formatMailValue('02/10/2026', $form, 'date-1', []));
    }

    public function test_emails_follow_the_language_of_the_page_the_form_was_sent_from(): void
    {
        $source = $this->inLanguage('fa_IR');
        $source->posts = [11 => 'en_US', 12 => 'fa_IR', 13 => 'ps_AF'];
        $field = new ForminatorDateField(true);

        $this->assertSame('02/10/2026', $field->formatMailMacros('02/10/2026', ['date-1' => '02/10/2026', 'page_id' => '11'], '{date-1}'));
        $this->assertSame('1405/07/10', $field->formatMailMacros('02/10/2026', ['date-1' => '02/10/2026', 'page_id' => '12'], '{date-1}'));
        // A Pashto page shows the Jalali picker too.
        $this->assertSame('1405/07/10', $field->formatMailMacros('02/10/2026', ['date-1' => '02/10/2026', 'page_id' => '13'], '{date-1}'));

        $form = \Forminator_CForm_Front_Action::$module_object;
        $this->assertSame('02/10/2026', $field->formatMailValue('02/10/2026', $form, 'date-1', ['page_id' => 11]));

        // Without a page, the current language decides.
        $this->assertSame('1405/07/10', $field->formatMailMacros('02/10/2026', ['date-1' => '02/10/2026'], '{date-1}'));
    }

    public function test_submissions_show_jalali_calendar_dates_in_the_admins_language(): void
    {
        $field = new ForminatorDateField(true);
        $mapper = ['type' => 'date', 'meta_key' => 'date-1', 'field' => self::CALENDAR];

        $this->assertSame('1405/07/10', $field->formatEntryValue('02/10/2026', null, $mapper, ''));
        $this->assertSame('', $field->formatEntryValue('', null, $mapper, ''));
        $this->assertSame('02/10/2026', $field->formatEntryValue('02/10/2026', null, ['field' => ['field_type' => 'select'] + self::CALENDAR] + $mapper, ''));
        $this->assertSame('Ali', $field->formatEntryValue('Ali', null, ['type' => 'text', 'meta_key' => 'text-1'], ''));

        $this->inLanguage('en_US', true);
        $this->assertSame('02/10/2026', $field->formatEntryValue('02/10/2026', null, $mapper, ''));
    }

    public function test_submissions_read_the_field_from_the_entrys_form_when_the_column_lacks_it(): void
    {
        \Forminator_Base_Form_Model::$models[30] = \Forminator_CForm_Front_Action::$module_object;
        $entry = (object) ['form_id' => 30];
        $field = new ForminatorDateField(true);

        // Releases before 1.54 leave the field settings out.
        $this->assertSame('1405/07/10', $field->formatEntryValue('02/10/2026', $entry, ['type' => 'date', 'meta_key' => 'date-1'], ''));

        // A date in a repeated group row.
        $this->assertSame('1405/07/10', $field->formatEntryValue('10.02.2026', $entry, ['type' => 'group', 'meta_key' => 'group-1'], 'date-4-2'));
        $this->assertSame('Ali', $field->formatEntryValue('Ali', $entry, ['type' => 'group', 'meta_key' => 'group-1'], 'text-1-2'));
    }

    /**
     * Forminator_CForm_Front, as passed to forminator_custom_forms_enqueue_scripts.
     *
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
