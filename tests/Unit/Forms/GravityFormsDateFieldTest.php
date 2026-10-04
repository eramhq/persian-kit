<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\Forms\GravityFormsDateField;
use PersianKit\Service\Language\ContentLanguage;
use PersianKit\Tests\Unit\Forms\Support\FakeGravityField;
use PersianKit\Tests\Unit\Support\UsesLanguages;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/functions.php';

class GravityFormsDateFieldTest extends TestCase
{
    use UsesLanguages;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        ContentLanguage::reset();

        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('get_option')->alias(fn (string $option) => $option === 'date_format' ? 'Y/m/d' : false);
        Functions\when('esc_html')->alias(fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES));
    }

    protected function tearDown(): void
    {
        \GFAPI::$fields = [];
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_upgrades_date_fields_and_shows_jalali_dates_where_people_read_them(): void
    {
        $field = new GravityFormsDateField(true);
        $field->register();

        $this->assertNotFalse(has_filter('gform_field_content', [$field, 'upgradeMarkup']));
        $this->assertNotFalse(has_action('gform_enqueue_scripts', [$field, 'enqueue']));
        $this->assertNotFalse(has_action('gform_field_standard_settings', [$field, 'renderSetting']));
        $this->assertNotFalse(has_action('gform_editor_js', [$field, 'renderEditorScript']));
        $this->assertNotFalse(has_filter('gform_entries_field_value', [$field, 'formatListValue']));
        $this->assertNotFalse(has_filter('gform_entry_field_value', [$field, 'formatEntryValue']));
        $this->assertNotFalse(has_filter('gform_merge_tag_filter', [$field, 'formatMergeTag']));
    }

    public function test_without_date_conversion_entries_and_emails_stay_gregorian(): void
    {
        $field = new GravityFormsDateField(false);
        $field->register();

        $this->assertNotFalse(has_filter('gform_field_content', [$field, 'upgradeMarkup']));
        $this->assertFalse(has_filter('gform_entries_field_value', [$field, 'formatListValue']));
        $this->assertFalse(has_filter('gform_merge_tag_filter', [$field, 'formatMergeTag']));
    }

    public function test_all_seven_formats_map_to_php_formats_and_their_order(): void
    {
        $expected = [
            'mdy'       => ['m/d/Y', ['m', 'd', 'Y']],
            'dmy'       => ['d/m/Y', ['d', 'm', 'Y']],
            'dmy_dash'  => ['d-m-Y', ['d', 'm', 'Y']],
            'dmy_dot'   => ['d.m.Y', ['d', 'm', 'Y']],
            'ymd_slash' => ['Y/m/d', ['Y', 'm', 'd']],
            'ymd_dash'  => ['Y-m-d', ['Y', 'm', 'd']],
            'ymd_dot'   => ['Y.m.d', ['Y', 'm', 'd']],
        ];

        $this->assertSame(array_keys($expected), array_keys(GravityFormsDateField::FORMATS));

        foreach ($expected as $format => [$php, $order]) {
            $field = $this->date(['dateFormat' => $format]);
            $this->assertSame($php, GravityFormsDateField::format($field), $format);
            $this->assertSame($order, GravityFormsDateField::order($field), $format);
        }

        // Gravity Forms' default.
        $this->assertSame('m/d/Y', GravityFormsDateField::format($this->date(['dateFormat' => ''])));
    }

    public function test_every_date_style_is_jalali_unless_the_field_keeps_the_gregorian_calendar(): void
    {
        $this->assertSame('datepicker', GravityFormsDateField::style($this->date(['dateType' => ''])));
        $this->assertSame('datefield', GravityFormsDateField::style($this->date(['dateType' => 'datefield'])));
        $this->assertSame('datedropdown', GravityFormsDateField::style($this->date(['dateType' => 'datedropdown'])));

        $this->assertTrue(GravityFormsDateField::isJalali($this->date()));
        $this->assertTrue(GravityFormsDateField::isJalali(new FakeGravityField(['type' => 'post_custom_field', 'inputType' => 'date'])));
        $this->assertFalse(GravityFormsDateField::isJalali($this->date([GravityFormsDateField::GREGORIAN_SETTING => true])));
        $this->assertFalse(GravityFormsDateField::isJalali(new FakeGravityField(['type' => 'text'])));
    }

    public function test_markup_is_left_alone_in_the_form_editor_for_gregorian_fields_and_pages_in_other_languages(): void
    {
        $field = new GravityFormsDateField(true);
        $html = "<input id='input_1_3' class='datepicker gform-datepicker mdy' value='10/04/2026'>";

        $editor = $this->date();
        $editor->inFormEditor = true;
        $this->assertSame($html, $field->upgradeMarkup($html, $editor));
        $this->assertSame($html, $field->upgradeMarkup($html, $this->date([GravityFormsDateField::GREGORIAN_SETTING => true])));
        $this->assertSame($html, $field->upgradeMarkup($html, new FakeGravityField(['type' => 'text'])));

        $this->inLanguage('en_US');
        $this->assertSame($html, $field->upgradeMarkup($html, $this->date()));
    }

    public function test_the_setting_sits_under_the_date_format(): void
    {
        Functions\when('esc_attr')->returnArg();
        Functions\when('esc_html__')->returnArg();
        $field = new GravityFormsDateField(true);

        ob_start();
        $field->renderSetting(1200);
        $field->renderSetting(1225);
        $html = (string) ob_get_clean();

        $this->assertSame(1, substr_count($html, 'persian_kit_gregorian_setting'));
        $this->assertStringContainsString("SetFieldProperty('persianKitGregorian', this.checked)", $html);
    }

    public function test_entries_show_the_jalali_date_in_the_sites_format(): void
    {
        $field = new GravityFormsDateField(true);
        $date = $this->date(['id' => 3]);
        \GFAPI::$fields[1]['3'] = $date;
        $entry = ['id' => 7, 'form_id' => 1, '3' => '2026-10-04'];

        $this->assertSame('1405/07/12', $field->formatListValue('10/04/2026', 1, '3', $entry));
        $this->assertSame('1405/07/12', $field->formatEntryValue('10/04/2026', $date, $entry));

        // Gregorian fields, other fields and empty dates stay.
        $gregorian = $this->date(['id' => 4, GravityFormsDateField::GREGORIAN_SETTING => true]);
        \GFAPI::$fields[1]['4'] = $gregorian;
        $this->assertSame('10/04/2026', $field->formatListValue('10/04/2026', 1, '4', ['4' => '2026-10-04']));
        $this->assertSame('10/04/2026', $field->formatEntryValue('10/04/2026', $gregorian, ['4' => '2026-10-04']));
        $this->assertSame('', $field->formatEntryValue('', $date, ['3' => '']));

        // The admin's language.
        $this->inLanguage('en_US', true);
        $this->assertSame('10/04/2026', $field->formatEntryValue('10/04/2026', $date, $entry));
    }

    public function test_merge_tags_show_the_jalali_date_and_modifiers_pick_its_parts(): void
    {
        $field = new GravityFormsDateField(true);
        $date = $this->date();
        $field->startMergeTags('{Date:3}', [], ['source_id' => 0], false);

        $this->assertSame('1405/07/12', $field->formatMergeTag('10/04/2026', '3', '', $date, '2026-10-04', 'html'));
        $this->assertSame('1405', $field->formatMergeTag('2026', '3', 'year', $date, '2026-10-04', 'text'));
        $this->assertSame('07', $field->formatMergeTag('10', '3', 'month', $date, '2026-10-04', 'text'));
        $this->assertSame('12', $field->formatMergeTag('04', '3', 'day', $date, '2026-10-04', 'text'));
        $this->assertSame('12-07-1405', $field->formatMergeTag('04-10-2026', '3', 'dmy_dash', $date, '2026-10-04', 'text'));
        $this->assertSame('1405/07/12', $field->formatMergeTag('2026/10/04', '3', 'ymd', $date, '2026-10-04', 'text'));

        // {all_fields} passes its own options.
        $this->assertSame('1405/07/12', $field->formatMergeTag('10/04/2026', 'all_fields', 'noadmin', $date, '2026-10-04', 'html'));

        // :raw keeps the saved date; other fields stay.
        $this->assertSame('2026-10-04', $field->formatMergeTag('2026-10-04', '3', 'raw', $date, '2026-10-04', 'html'));
        $this->assertSame('Ali', $field->formatMergeTag('Ali', '1', '', new FakeGravityField(['type' => 'text']), 'Ali', 'html'));
        $this->assertFalse($field->formatMergeTag(false, '3', '', $date, '2026-10-04', 'html'));
    }

    public function test_merge_tags_in_a_redirect_url_keep_the_gregorian_date(): void
    {
        $field = new GravityFormsDateField(true);
        $date = $this->date();

        $field->startMergeTags('https://example.org/?d={Date:3}', [], [], true);
        $this->assertSame('10/04/2026', $field->formatMergeTag('10/04/2026', '3', '', $date, '2026-10-04', 'text'));

        // The next text is filled on its own.
        $field->startMergeTags('{Date:3}', [], [], false);
        $this->assertSame('1405/07/12', $field->formatMergeTag('10/04/2026', '3', '', $date, '2026-10-04', 'text'));
    }

    public function test_on_multilingual_sites_emails_follow_the_page_the_form_was_sent_from(): void
    {
        Functions\when('get_post')->justReturn((object) ['ID' => 21]);
        $source = $this->inLanguage('fa_IR', true);
        $source->posts = [21 => 'en_US', 22 => 'fa_IR'];

        $field = new GravityFormsDateField(true);
        $date = $this->date();

        $field->startMergeTags('{Date:3}', [], ['source_id' => '21'], false);
        $this->assertSame('10/04/2026', $field->formatMergeTag('10/04/2026', '3', '', $date, '2026-10-04', 'html'));

        $field->startMergeTags('{Date:3}', [], ['source_id' => '22'], false);
        $this->assertSame('1405/07/12', $field->formatMergeTag('10/04/2026', '3', '', $date, '2026-10-04', 'html'));
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function date(array $settings = []): FakeGravityField
    {
        return new FakeGravityField($settings + ['id' => 3, 'type' => 'date', 'dateType' => 'datepicker', 'dateFormat' => 'mdy']);
    }
}
