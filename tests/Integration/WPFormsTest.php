<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Core\SettingsManager;
use PersianKit\Modules\Forms\IranianFieldTypes;
use PersianKit\Modules\Forms\WPForms\IranianField;
use PersianKit\Modules\Forms\WPForms\JalaliDateField;
use PersianKit\Modules\Forms\WPFormsFieldUsage;
use PersianKit\Modules\Forms\WPFormsModule;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * WPForms Lite with the integration as the plugin booted it. Runs when
 * WPForms is loaded (see tests/bootstrap.php). Forms are rendered and sent
 * through WPForms itself, as a form sent to its page, and the notification
 * is caught by WordPress's mock mailer.
 */
class WPFormsTest extends WordPressIntegrationTestCase
{
    private int $formId;

    public function set_up(): void
    {
        parent::set_up();

        if (!function_exists('wpforms_display')) {
            $this->markTestSkipped('WPForms is not installed next to the plugin.');
        }

        $this->formId = $this->createForm([
            1 => ['type' => 'persian-kit-mobile', 'label' => 'Mobile', 'required' => '1'],
            2 => ['type' => 'persian-kit-national-id', 'label' => 'National ID'],
            3 => ['type' => 'persian-kit-postcode', 'label' => 'Postcode'],
            4 => ['type' => 'persian-kit-date', 'label' => 'Visit', 'date_format' => 'd/m/Y', 'required' => '1'],
            5 => ['type' => 'number', 'label' => 'Guests'],
            6 => ['type' => 'text', 'label' => 'Address'],
        ]);

        reset_phpmailer_instance();
    }

    public function tear_down(): void
    {
        $_POST = [];
        IranianField::$checks = true;
        JalaliDateField::$enabled = true;
        parent::tear_down();
    }

    public function test_the_builder_offers_the_fields_in_an_iranian_fields_group(): void
    {
        $group = wpforms_get_builder_fields()['persian_kit'] ?? [];

        $this->assertSame(IranianFieldTypes::groupLabel(), $group['group_name'] ?? null);
        $this->assertSame(
            ['persian-kit-mobile', 'persian-kit-national-id', 'persian-kit-postcode', 'persian-kit-card', 'persian-kit-iban', 'persian-kit-date'],
            array_column($group['fields'] ?? [], 'type')
        );
    }

    public function test_the_form_shows_the_iranian_inputs_and_the_jalali_picker(): void
    {
        $html = $this->render();

        $mobile = $this->input($html, 1);
        $this->assertStringContainsString('type="tel"', $mobile);
        $this->assertStringContainsString('dir="ltr"', $mobile);
        $this->assertStringContainsString('required', $mobile);
        $this->assertStringContainsString('inputmode="numeric"', $this->input($html, 2));
        $this->assertStringNotContainsString('dir="ltr"', $this->input($html, 6));

        $visit = $this->input($html, 4);
        $this->assertStringContainsString('data-persian-kit-date ', $visit);
        $this->assertStringContainsString('data-persian-kit-date-format="d/m/Y"', $visit);

        $this->assertTrue(wp_script_is('persian-kit-date-field', 'enqueued'));

        // WPForms loads its scripts, and ours with them, in the footer.
        wpforms()->obj('frontend')->assets_js();
        $this->assertTrue(wp_script_is('persian-kit-wpforms-digits', 'enqueued'));
    }

    public function test_a_submission_is_saved_with_standard_values_and_mailed_with_the_jalali_date(): void
    {
        $process = $this->submit([
            1 => '۰۹۱۲ ۱۲۳ ۴۵۶۷',
            2 => '۰۰۱۳۵۴۲۴۱۹',
            3 => '۱۲۳۴۵-۶۷۸۹۰',
            4 => '04/10/2026',
            5 => '۳',
            6 => 'پلاک ۱۲',
        ]);

        $this->assertSame([], $process->errors[$this->formId] ?? [], wp_json_encode($process->errors));
        $this->assertSame('09121234567', $process->fields[1]['value']);
        $this->assertSame('0013542419', $process->fields[2]['value']);
        $this->assertSame('1234567890', $process->fields[3]['value']);
        $this->assertSame('3', $process->fields[5]['value']);
        $this->assertSame('پلاک ۱۲', $process->fields[6]['value']);
        $this->assertSame(
            ['value' => '12/07/1405', 'date' => '2026-10-04', 'unix' => 1791072000],
            array_intersect_key($process->fields[4], array_flip(['value', 'date', 'unix']))
        );

        $mail = tests_retrieve_phpmailer_instance()->get_sent();
        $this->assertNotFalse($mail, 'The notification was sent');
        $this->assertSame('Visit on 12/07/1405', $mail->subject);
        $body = quoted_printable_decode($mail->body);
        $this->assertStringContainsString('12/07/1405', $body);
        $this->assertStringNotContainsString('04/10/2026', $body, '{all_fields} shows the Jalali date');
        $this->assertStringContainsString('09121234567', $body);
    }

    public function test_a_typed_jalali_date_is_read_and_one_that_does_not_exist_fails(): void
    {
        $process = $this->submit([1 => '09121234567', 4 => '۱۴۰۵/۰۷/۱۲']);
        $this->assertSame([], $process->errors[$this->formId] ?? []);
        $this->assertSame('2026-10-04', $process->fields[4]['date']);

        $process = $this->submit([1 => '09121234567', 4 => '31/07/1405']);
        $this->assertSame('Enter a valid date, such as 25/07/1405.', $process->errors[$this->formId][4] ?? null);
        $this->assertFalse(tests_retrieve_phpmailer_instance()->get_sent(1));
    }

    public function test_invalid_iranian_values_are_rejected_with_their_messages(): void
    {
        $process = $this->submit([1 => '12345', 2 => '1234567890', 3 => '۱۲', 4 => '04/10/2026']);

        $this->assertSame(IranianFieldTypes::message('mobile_ir'), $process->errors[$this->formId][1] ?? null);
        $this->assertSame(IranianFieldTypes::message('national_id'), $process->errors[$this->formId][2] ?? null);
        $this->assertSame(IranianFieldTypes::message('postcode_ir'), $process->errors[$this->formId][3] ?? null);
        $this->assertFalse(tests_retrieve_phpmailer_instance()->get_sent());

        // The form shown again has the errors under the fields.
        $this->assertStringContainsString(IranianFieldTypes::message('national_id'), $this->render());
    }

    public function test_required_fields_keep_wpforms_own_check(): void
    {
        $process = $this->submit([2 => '0013542419']);

        $this->assertSame(wpforms_get_required_label(), $process->errors[$this->formId][1] ?? null);
        $this->assertSame(wpforms_get_required_label(), $process->errors[$this->formId][4] ?? null);
    }

    public function test_turned_off_the_fields_are_text_inputs_that_save_what_was_typed(): void
    {
        // As the module's bootDisabled() leaves them.
        IranianField::$checks = false;
        JalaliDateField::$enabled = false;

        $html = $this->render();
        $this->assertStringNotContainsString('data-persian-kit-date', $this->input($html, 4));
        $this->assertStringContainsString('type="text"', $this->input($html, 4));
        $this->assertSame([], wpforms_get_builder_fields('persian_kit'), 'The builder no longer offers them');

        $process = $this->submit([1 => '12345', 2 => 'unknown', 4 => 'next week']);
        $this->assertSame([], $process->errors[$this->formId] ?? []);
        $this->assertSame('unknown', $process->fields[2]['value']);
        $this->assertSame('next week', $process->fields[4]['value']);
    }

    public function test_the_card_lists_the_forms_with_the_fields_and_saving_a_form_clears_the_list(): void
    {
        $plain = $this->createForm([1 => ['type' => 'text', 'label' => 'Name']], 'Plain form');

        $module = new WPFormsModule(new SettingsManager());
        $forms = $module->formsUsingFields();

        $this->assertSame(['PK WPForms test'], array_column($forms, 'title'));
        $this->assertStringContainsString('page=wpforms-builder&view=fields&form_id=' . $this->formId, $forms[0]['url']);
        $this->assertNotFalse(get_transient(WPFormsFieldUsage::TRANSIENT));

        // Saved in the builder with a Jalali date field.
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $data = wpforms()->obj('form')->get($plain, ['content_only' => true]);
        $data['fields'][2] = ['id' => 2, 'type' => 'persian-kit-date', 'label' => 'Born'];
        wpforms()->obj('form')->update($plain, $data);

        $this->assertFalse(get_transient(WPFormsFieldUsage::TRANSIENT));
        $this->assertSame(['PK WPForms test', 'Plain form'], array_column($module->formsUsingFields(), 'title'));
    }

    /**
     * A published form with these fields and a notification to the admin.
     *
     * @param array<int, array<string, mixed>> $fields
     */
    private function createForm(array $fields, string $title = 'PK WPForms test'): int
    {
        $id = (int) wp_insert_post(['post_type' => 'wpforms', 'post_status' => 'publish', 'post_title' => $title]);

        foreach ($fields as $fieldId => $field) {
            $fields[$fieldId] = ['id' => (string) $fieldId, 'size' => 'medium'] + $field;
        }

        wp_update_post(['ID' => $id, 'post_content' => wpforms_encode([
            'id'       => (string) $id,
            'field_id' => count($fields) + 1,
            'fields'   => $fields,
            'settings' => [
                'form_title'          => $title,
                'submit_text'         => 'Submit',
                'notification_enable' => '1',
                'notifications'       => [1 => [
                    'email'          => '{admin_email}',
                    'subject'        => 'Visit on {field_id="4"}',
                    'sender_name'    => 'Persian Kit',
                    'sender_address' => '{admin_email}',
                    'message'        => '{all_fields}',
                ]],
                'confirmations'       => [1 => ['type' => 'message', 'message' => 'Thanks']],
            ],
        ])]);

        return $id;
    }

    private function render(): string
    {
        ob_start();
        wpforms_display($this->formId);

        return (string) ob_get_clean();
    }

    /**
     * Sends the form as its page does.
     *
     * @param array<int, string> $fields
     */
    private function submit(array $fields): \WPForms_Process
    {
        $process = wpforms()->obj('process');
        $process->process(['id' => (string) $this->formId, 'fields' => $fields]);

        return $process;
    }

    private function input(string $html, int $fieldId): string
    {
        $name = preg_quote('wpforms[fields][' . $fieldId . ']', '/');
        $this->assertMatchesRegularExpression('/<input[^>]*name="' . $name . '"[^>]*>/', $html);
        preg_match('/<input[^>]*name="' . $name . '"[^>]*>/', $html, $matches);

        return $matches[0];
    }
}
