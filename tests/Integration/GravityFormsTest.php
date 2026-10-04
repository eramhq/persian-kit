<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Core\SettingsManager;
use PersianKit\Modules\Forms\GravityForms\IranianField;
use PersianKit\Modules\Forms\GravityFormsEntryDates;
use PersianKit\Modules\Forms\GravityFormsFieldUsage;
use PersianKit\Modules\Forms\GravityFormsIranianFields;
use PersianKit\Modules\Forms\GravityFormsModule;
use PersianKit\Modules\Forms\IranianFieldTypes;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Gravity Forms with the integration as the plugin booted it. Runs when
 * Gravity Forms is loaded (see tests/bootstrap.php). Forms are rendered
 * with gravity_form() and sent through GFAPI::submit_form(), as a form sent
 * to its page; emails are caught by WordPress's mock mailer.
 */
class GravityFormsTest extends WordPressIntegrationTestCase
{
    private int $formId;

    public function set_up(): void
    {
        parent::set_up();

        if (!class_exists('GFAPI')) {
            $this->markTestSkipped('Gravity Forms is not installed next to the plugin.');
        }

        update_option('date_format', 'Y/m/d');
        // New installs send notifications in a background request.
        add_filter('gform_is_asynchronous_notifications_enabled', '__return_false');

        $id = \GFAPI::add_form([
            'title'  => 'PK test',
            'fields' => [
                ['id' => 1, 'type' => 'date', 'label' => 'Visit', 'dateType' => 'datepicker', 'dateFormat' => 'dmy'],
                ['id' => 2, 'type' => 'date', 'label' => 'Born', 'dateType' => 'datefield', 'dateFormat' => 'ymd_slash'],
                ['id' => 3, 'type' => 'date', 'label' => 'Moved', 'dateType' => 'datedropdown', 'dateFormat' => 'mdy'],
                ['id' => 4, 'type' => 'date', 'label' => 'Abroad', 'dateType' => 'datepicker', 'dateFormat' => 'mdy', 'persianKitGregorian' => true],
                ['id' => 5, 'type' => 'phone', 'label' => 'Phone', 'phoneFormat' => 'international'],
                ['id' => 6, 'type' => 'number', 'label' => 'Guests', 'numberFormat' => 'decimal_dot'],
                ['id' => 7, 'type' => 'time', 'label' => 'Arrival', 'timeFormat' => '24'],
                ['id' => 8, 'type' => 'text', 'label' => 'Note'],
                ['id' => 9, 'type' => 'persian_kit_mobile', 'label' => 'Mobile'],
                ['id' => 10, 'type' => 'persian_kit_national_id', 'label' => 'National ID', 'errorMessage' => 'Check your national ID.'],
                ['id' => 11, 'type' => 'persian_kit_postcode', 'label' => 'Postcode'],
                ['id' => 12, 'type' => 'persian_kit_card', 'label' => 'Card'],
                ['id' => 13, 'type' => 'persian_kit_iban', 'label' => 'IBAN'],
                [
                    'id'          => 14,
                    'type'        => 'address',
                    'label'       => 'Address',
                    'addressType' => 'iran',
                    'inputs'      => [
                        ['id' => '14.1', 'label' => 'Street Address'],
                        ['id' => '14.2', 'label' => 'Address Line 2'],
                        ['id' => '14.3', 'label' => 'City'],
                        ['id' => '14.4', 'label' => 'Province'],
                        ['id' => '14.5', 'label' => 'Postcode'],
                        ['id' => '14.6', 'label' => 'Country'],
                    ],
                ],
            ],
            'notifications' => [
                'n1' => [
                    'id'       => 'n1',
                    'name'     => 'Admin',
                    'isActive' => true,
                    'event'    => 'form_submission',
                    'to'       => 'admin@example.org',
                    'toType'   => 'email',
                    'subject'  => 'Visit on {Visit:1}',
                    'message'  => 'Visit: {Visit:1} raw {Visit:1:raw} year {Visit:1:year}<br>{all_fields}',
                ],
            ],
            'confirmations' => [
                'c1' => ['id' => 'c1', 'name' => 'Default', 'isDefault' => true, 'type' => 'message', 'message' => 'Thanks'],
            ],
        ]);
        $this->assertIsInt($id);
        $this->formId = $id;

        reset_phpmailer_instance();
    }

    public function tear_down(): void
    {
        if (class_exists(IranianField::class, false)) {
            IranianField::$checks = true;
        }
        $_POST = [];
        if (class_exists('GFFormDisplay')) {
            \GFFormDisplay::$submission = [];
        }
        parent::tear_down();
    }

    public function test_the_date_picker_gets_the_jalali_picker_in_place_of_gravity_forms_own(): void
    {
        $html = $this->render();

        $visit = $this->tag($html, 'input', 'input_1');
        $this->assertStringContainsString('data-persian-kit-date ', $visit);
        $this->assertStringContainsString('data-persian-kit-date-format="d/m/Y"', $visit);
        $this->assertDoesNotMatchRegularExpression('/\bdatepicker\b|\bgform-datepicker\b|data-mask/', $visit);
        // Gravity Forms' calendar button (3.x) and icon (2.9).
        $this->assertStringNotContainsString('datepicker_toggle_input_' . $this->formId . '_1', $html);
        $this->assertStringNotContainsString('gforms_calendar_icon_input_' . $this->formId . '_1', $html);

        // Ticked "Gregorian calendar": Gravity Forms' own.
        $abroad = $this->tag($html, 'input', 'input_4');
        $this->assertStringContainsString('gform-datepicker', $abroad);
        $this->assertStringNotContainsString('data-persian-kit-date', $abroad);

        $this->assertTrue(wp_script_is('persian-kit-date-field', 'enqueued'));
        $this->assertTrue(wp_script_is('persian-kit-gravityforms-digits', 'enqueued'));
    }

    public function test_date_boxes_and_drop_downs_take_jalali_parts(): void
    {
        $html = $this->render();

        $year = $this->tag($html, 'input', 'input_2[]', 'input_' . $this->formId . '_2_3');
        $this->assertMatchesRegularExpression("/\bmin=['\"]1298['\"]/", $year);
        $this->assertMatchesRegularExpression("/\bmax=['\"]" . ((int) gmdate('Y') - 620) . "['\"]/", $year);

        $months = $this->options($html, 'input_' . $this->formId . '_3_1');
        $this->assertSame('فروردین', $months[1]);
        $this->assertSame('اسفند', $months[12]);

        $years = $this->options($html, 'input_' . $this->formId . '_3_3');
        $this->assertSame(1298, min(array_keys($years)));
        $this->assertSame((int) gmdate('Y') - 620, max(array_keys($years)));
    }

    public function test_a_jalali_submission_is_saved_as_the_gregorian_date_with_english_digits(): void
    {
        $result = $this->submit([
            'input_1' => '۱۴۰۵/۰۷/۱۲',
            'input_2' => ['۱۳۷۰', '۶', '۳۱'],
            'input_3' => ['۷', '۱۲', '۱۴۰۵'],
            'input_4' => '10/04/2026',
            'input_5' => '۰۲۱-۸۸۸۸۱۲۳۴',
            'input_6' => '۱۲',
            'input_7' => ['۰۹', '۳۰'],
            'input_8' => 'پلاک ۱۲',
        ]);

        $this->assertTrue($result['is_valid'], wp_json_encode($result['validation_messages'] ?? []));
        $entry = \GFAPI::get_entry($result['entry_id']);

        $this->assertSame('2026-10-04', $entry['1']);
        $this->assertSame('1991-09-22', $entry['2']);
        $this->assertSame('2026-10-04', $entry['3']);
        $this->assertSame('2026-10-04', $entry['4']);
        $this->assertSame('021-88881234', $entry['5']);
        $this->assertSame('12', $entry['6']);
        $this->assertSame('09:30', $entry['7']);
        $this->assertSame('پلاک ۱۲', $entry['8']);
    }

    public function test_a_jalali_date_that_does_not_exist_is_rejected(): void
    {
        $result = $this->submit(['input_1' => '1405/07/31', 'input_2' => ['1405', '7', '31']]);

        $this->assertFalse($result['is_valid']);
        $this->assertSame('Please enter a valid date in the format (dd/mm/yyyy).', $result['validation_messages'][1] ?? null);
        $this->assertSame('Please enter a valid date.', $result['validation_messages'][2] ?? null);
    }

    public function test_the_email_shows_jalali_dates_and_raw_keeps_the_saved_one(): void
    {
        $result = $this->submit(['input_1' => '1405/07/12']);
        $this->assertTrue($result['is_valid']);

        $mail = tests_retrieve_phpmailer_instance()->get_sent();
        $this->assertNotFalse($mail);
        $this->assertSame('Visit on 1405/07/12', $mail->subject);
        $this->assertStringContainsString('Visit: 1405/07/12 raw 04/10/2026 year 1405', $mail->body);
        $this->assertStringContainsString('1405/07/12', strstr($mail->body, '<table') ?: '');
    }

    public function test_iranian_fields_read_left_to_right_with_the_right_keyboard(): void
    {
        $html = $this->render();

        $mobile = $this->tag($html, 'input', 'input_9');
        $this->assertMatchesRegularExpression('/\btype="tel"/', $mobile);
        $this->assertStringContainsString('dir="ltr"', $mobile);
        $this->assertStringContainsString('autocomplete="tel-national"', $mobile);

        $nationalId = $this->tag($html, 'input', 'input_10');
        $this->assertStringContainsString('inputmode="numeric"', $nationalId);
        $this->assertStringContainsString('maxlength="12"', $nationalId);

        // Styled as a text field by Gravity Forms' themes.
        $this->assertMatchesRegularExpression('/class="[^"]*ginput_container_text[^"]*ginput_container_persian_kit/', $html);
    }

    public function test_iranian_fields_are_saved_in_their_standard_form(): void
    {
        $result = $this->submit([
            'input_9'  => '+98 912 123 4567',
            'input_10' => '۰۰۱۳۵۴۲۴۱۹',
            'input_11' => '۱۲۳۴۵-۶۷۸۹۰',
            'input_12' => '6037 9918 9919 7283',
            'input_13' => 'ir 82 0540 1026 8002 0817 9090 02',
        ]);

        $this->assertTrue($result['is_valid'], wp_json_encode($result['validation_messages'] ?? []));
        $entry = \GFAPI::get_entry($result['entry_id']);

        $this->assertSame('09121234567', $entry['9']);
        $this->assertSame('0013542419', $entry['10']);
        $this->assertSame('1234567890', $entry['11']);
        $this->assertSame('6037991899197283', $entry['12']);
        $this->assertSame('IR820540102680020817909002', $entry['13']);
    }

    public function test_an_invalid_iranian_value_is_rejected_with_its_message(): void
    {
        $result = $this->submit(['input_9' => '0912', 'input_10' => '1234567890']);

        $this->assertFalse($result['is_valid']);
        $this->assertSame(IranianFieldTypes::message('mobile_ir'), $result['validation_messages'][9] ?? null);
        // The field's own validation message.
        $this->assertSame('Check your national ID.', $result['validation_messages'][10] ?? null);
    }

    public function test_an_iran_address_lists_the_provinces_and_checks_the_postcode(): void
    {
        $html = $this->render();
        $provinces = $this->stateOptions($html);
        $this->assertCount(31, $provinces);
        $this->assertContains('تهران', $provinces);

        // Iran is the country: its input is hidden, as for Gravity Forms' United States type.
        $this->assertMatchesRegularExpression("#<input type='hidden' class='gform_hidden' name='input_14\\.6'#", $html);

        $result = $this->submit(['input_14_1' => 'ولیعصر', 'input_14_3' => 'تهران', 'input_14_4' => 'تهران', 'input_14_5' => '۱۲۳۴۵-۶۷۸۹۰']);
        $this->assertTrue($result['is_valid'], wp_json_encode($result['validation_messages'] ?? []));
        $entry = \GFAPI::get_entry($result['entry_id']);
        $this->assertSame('1234567890', $entry['14.5']);
        $this->assertSame('تهران', $entry['14.4']);

        $result = $this->submit(['input_14_1' => 'ولیعصر', 'input_14_4' => 'تهران', 'input_14_5' => '12345']);
        $this->assertFalse($result['is_valid']);
        $this->assertSame(IranianFieldTypes::message('postcode_ir'), $result['validation_messages'][14] ?? null);
    }

    public function test_entries_show_jalali_dates_and_exports_keep_the_saved_ones(): void
    {
        $result = $this->submit(['input_1' => '1405/07/12', 'input_4' => '10/04/2026']);
        $entry = \GFAPI::get_entry($result['entry_id']);
        $visit = \GFAPI::get_field($this->formId, 1);
        $abroad = \GFAPI::get_field($this->formId, 4);

        // The list's column and the entry's page.
        $this->assertSame('1405/07/12', apply_filters('gform_entries_field_value', $visit->get_value_entry_list($entry['1'], $entry, '1', [], []), $this->formId, '1', $entry));
        $this->assertSame('1405/07/12', apply_filters('gform_entry_field_value', $visit->get_value_entry_detail($entry['1']), $visit, $entry, \GFAPI::get_form($this->formId)));
        $this->assertSame('10/04/2026', apply_filters('gform_entry_field_value', $abroad->get_value_entry_detail($entry['4']), $abroad, $entry, \GFAPI::get_form($this->formId)));

        // The export writes the saved date.
        $this->assertSame('2026-10-04', $visit->get_value_export($entry));
    }

    public function test_submitted_on_dates_are_jalali_on_the_entries_screens_and_the_export_stays_gregorian(): void
    {
        // Booted in the admin only.
        $dates = new GravityFormsEntryDates();
        $dates->register();
        set_current_screen('forms_page_gf_entries');
        $local = gmmktime(13, 7, 0, 10, 4, 2026);

        $this->assertSame('1405/07/12', \GFCommon::format_date('2026-10-04 09:37:00', false, 'Y/m/d', false));
        $this->assertSame('1405/07/12', date_i18n('Y/m/d', $local, true));
        // As the export writes dates.
        $this->assertSame('2026-10-04 13:07:00', date_i18n('Y-m-d H:i:s', $local, true));

        remove_filter('date_i18n', [$dates, 'jalaliDate']);
        remove_action('current_screen', [$dates, 'detectScreen']);
        unset($GLOBALS['current_screen']);
    }

    public function test_turned_off_the_fields_and_address_type_stay_without_checks(): void
    {
        (new GravityFormsIranianFields())->registerFallback();

        $this->assertTrue(\GF_Fields::exists('persian_kit_mobile'));
        $this->assertArrayHasKey('iran', (new \GF_Field_Address())->get_address_types($this->formId));

        $result = $this->submit(['input_9' => '0912']);
        $this->assertTrue($result['is_valid'], wp_json_encode($result['validation_messages'] ?? []));
    }

    public function test_prices_in_toman_and_rial(): void
    {
        $this->assertSame('250,000 تومان', \GFCommon::to_money('250000', 'IRT'));
        $this->assertSame('2,500,000 ریال', \GFCommon::to_money('2500000', 'IRR'));
        $this->assertSame(250000.0, (float) \GFCommon::to_number('250,000 تومان', 'IRT'));
    }

    public function test_the_card_lists_the_forms_with_iranian_fields_and_saving_a_form_clears_the_list(): void
    {
        $plain = \GFAPI::add_form(['title' => 'Plain', 'fields' => [['id' => 1, 'type' => 'text', 'label' => 'Name']]]);
        $module = new GravityFormsModule(new SettingsManager());

        $titles = array_column($module->formsUsingFields(), 'title');
        $this->assertContains('PK test', $titles);
        $this->assertNotContains('Plain', $titles);
        $this->assertStringContainsString('page=gf_edit_forms&id=' . $this->formId, $module->formsUsingFields()[0]['url']);

        $form = \GFAPI::get_form($plain);
        $form['fields'][] = \GF_Fields::create(['id' => 2, 'type' => 'persian_kit_postcode', 'label' => 'Postcode', 'formId' => $plain]);
        \GFAPI::update_form($form);
        do_action('gform_after_save_form', $form, false, []);

        $this->assertContains('Plain', array_column($module->formsUsingFields(), 'title'));
        $this->assertNotFalse(get_transient(GravityFormsFieldUsage::TRANSIENT));
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function submit(array $values): array
    {
        $_POST = [];
        \GFFormDisplay::$submission = [];

        $result = \GFAPI::submit_form($this->formId, $values);
        $this->assertIsArray($result);

        return $result;
    }

    /**
     * The Iran address's provinces, without the empty choice.
     *
     * @return list<string>
     */
    private function stateOptions(string $html): array
    {
        $this->assertMatchesRegularExpression("#<select\\b[^>]*name='input_14\\.4'[^>]*>(.*?)</select>#s", $html);
        preg_match("#<select\\b[^>]*name='input_14\\.4'[^>]*>(.*?)</select>#s", $html, $select);
        preg_match_all("#<option value='([^']+)'#", $select[1], $options);

        return $options[1];
    }

    private function render(): string
    {
        return (string) gravity_form($this->formId, false, false, false, null, false, 0, false);
    }

    /**
     * The first tag with that name, or id.
     */
    private function tag(string $html, string $tag, string $name, string $id = ''): string
    {
        $attribute = $id === '' ? 'name' : 'id';
        $pattern = sprintf('#<%s\b[^>]*\b%s=([\'"])%s\1[^>]*>#', $tag, $attribute, preg_quote($id === '' ? $name : $id, '#'));
        $this->assertMatchesRegularExpression($pattern, $html, "$tag $name $id");
        preg_match($pattern, $html, $matches);

        return $matches[0];
    }

    /**
     * A drop-down's options: value => label, without the placeholder.
     *
     * @return array<int, string>
     */
    private function options(string $html, string $id): array
    {
        $this->assertMatchesRegularExpression('#<select\b[^>]*\bid=([\'"])' . $id . '\1[^>]*>(.*?)</select>#s', $html);
        preg_match('#<select\b[^>]*\bid=([\'"])' . $id . '\1[^>]*>(.*?)</select>#s', $html, $select);
        preg_match_all('#<option\b[^>]*\bvalue=([\'"])(\d+)\1[^>]*>(.*?)</option>#s', $select[2], $options, PREG_SET_ORDER);

        $byValue = [];
        foreach ($options as $option) {
            $byValue[(int) $option[2]] = html_entity_decode($option[3]);
        }

        return $byValue;
    }
}
