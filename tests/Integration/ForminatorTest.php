<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Core\SettingsManager;
use PersianKit\Modules\Forms\ForminatorFieldUsage;
use PersianKit\Modules\Forms\ForminatorModule;
use PersianKit\Modules\Forms\IranianFieldTypes;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Forminator with the integration as the plugin booted it. Runs when
 * Forminator is loaded (see tests/bootstrap.php). Forms are rendered and
 * sent through Forminator itself, as a form sent to its page without
 * JavaScript, and emails are caught by WordPress's mock mailer.
 */
class ForminatorTest extends WordPressIntegrationTestCase
{
    /** A form with a second guest row, as Forminator's script sends it. */
    private const GUESTS = [
        'date-1'         => '02/10/2026',
        'text-1'         => '0013542419',
        'group-1-copies' => ['x7'],
        'text-3'         => '0013542419',
        'date-3'         => '10/02/2026',
        'date-3-x7'      => '۱۴۰۵/۰۷/۱۱',
    ];

    private int $formId;

    public function set_up(): void
    {
        parent::set_up();

        if (!class_exists('Forminator_API')) {
            $this->markTestSkipped('Forminator is not installed next to the plugin.');
        }

        update_option('date_format', 'Y/m/d');

        $id = \Forminator_API::add_form('PK test', [
            $this->row('date-1', [
                'type'                => 'date',
                'field_type'          => 'picker',
                'date_format'         => 'dd/mm/yy',
                'field_label'         => 'Visit',
                'start-date'          => 'specific',
                'start-specific-date' => '2026-01-01',
            ]),
            $this->row('date-2', ['type' => 'date', 'field_type' => 'select', 'date_format' => 'dd/mm/yy', 'field_label' => 'Born']),
            $this->row('text-1', ['type' => 'text', 'field_label' => 'National ID', 'custom-class' => 'persian-kit-national-id']),
            $this->row('phone-1', ['type' => 'phone', 'field_label' => 'Mobile', 'custom-class' => 'persian-kit-mobile', 'validation' => 'none']),
            $this->row('text-2', ['type' => 'text', 'field_label' => 'Address']),
            $this->row('group-1', ['type' => 'group', 'field_label' => 'Guests', 'is_repeater' => 'true', 'max_type' => 'custom', 'max_limit' => '5']),
            ['parent_group' => 'group-1'] + $this->row('text-3', ['type' => 'text', 'field_label' => 'Guest ID', 'custom-class' => 'persian-kit-national-id']),
            ['parent_group' => 'group-1'] + $this->row('date-3', ['type' => 'date', 'field_type' => 'picker', 'date_format' => 'mm/dd/yy', 'field_label' => 'Arrival']),
        ], ['formName' => 'PK test']);
        $this->assertIsInt($id);
        $this->formId = $id;

        $model = \Forminator_Base_Form_Model::get_model($id);
        $model->notifications[0]['email-subject'] = 'Visit on {date-1}';
        $model->notifications[0]['email-editor'] = 'Visit: {date-1}<br/>{all_fields}';
        $model->save();

        reset_phpmailer_instance();
    }

    public function tear_down(): void
    {
        $_POST = [];
        $_REQUEST = [];
        if (class_exists('Forminator_CForm_Front_Action')) {
            \Forminator_CForm_Front_Action::$prepared_data = [];
        }
        parent::tear_down();
    }

    public function test_the_calendar_gets_the_jalali_picker_and_dropdowns_stay_gregorian(): void
    {
        $html = do_shortcode('[forminator_form id="' . $this->formId . '"]');

        $visit = $this->tag($html, 'input', 'date-1');
        $this->assertStringContainsString('data-persian-kit-date ', $visit);
        $this->assertStringContainsString('data-persian-kit-date-format="d/m/Y"', $visit);
        $this->assertStringContainsString('data-persian-kit-date-min="2026-01-01"', $visit);
        $this->assertMatchesRegularExpression('/class="[^"]*\bforminator-datepicker\b[^"]*\bdo-validate\b/', $visit);
        // Forminator's own format stays for its checks and conditions.
        $this->assertStringContainsString('data-format="dd/mm/yy"', $visit);

        $this->assertStringNotContainsString('data-persian-kit-date', $this->tag($html, 'select', 'date-2-day'));

        $this->assertTrue(wp_script_is('persian-kit-date-field', 'enqueued'));
        $this->assertTrue(wp_script_is('persian-kit-forminator-digits', 'enqueued'));
    }

    public function test_iranian_fields_read_left_to_right(): void
    {
        $html = do_shortcode('[forminator_form id="' . $this->formId . '"]');

        $code = $this->tag($html, 'input', 'text-1');
        $this->assertStringContainsString('dir="ltr"', $code);
        $this->assertStringContainsString('inputmode="numeric"', $code);
        $this->assertStringNotContainsString('dir="ltr"', $this->tag($html, 'input', 'text-2'));
    }

    public function test_a_submission_is_saved_in_gregorian_with_english_digits_and_mailed_in_jalali(): void
    {
        $response = $this->submit([
            'date-1'  => '۱۰/۰۷/۱۴۰۵',
            'text-1'  => '۰۰۱۳۵۴۲۴۱۹',
            'phone-1' => '۰۹۱۲ ۱۲۳ ۴۵۶۷',
            'text-2'  => 'پلاک ۱۲',
        ]);

        $this->assertTrue($response['success'] ?? false, wp_json_encode($response));

        $entry = $this->latestEntry();
        $this->assertSame('02/10/2026', $entry->get_meta('date-1'));
        $this->assertSame('0013542419', $entry->get_meta('text-1'));
        $this->assertSame('09121234567', $entry->get_meta('phone-1'));
        $this->assertSame('پلاک ۱۲', $entry->get_meta('text-2'));
        // Dropdowns stay Gregorian.
        $this->assertSame('1990', $entry->get_meta('date-2')['year'] ?? null);

        $mail = tests_retrieve_phpmailer_instance()->get_sent();
        $this->assertNotFalse($mail);
        $this->assertSame('Visit on 1405/07/10', $mail->subject);
        $this->assertStringContainsString('Visit: 1405/07/10', $mail->body);
        $this->assertStringNotContainsString('02/10/2026', $mail->body, '{all_fields} shows the Jalali date too');
        $this->assertStringContainsString('09121234567', $mail->body);

        // The Submissions screen, for an admin who reads Persian.
        $form = \Forminator_Base_Form_Model::get_model($this->formId);
        $mapper = ['type' => 'date', 'meta_key' => 'date-1', 'field' => $form->get_field('date-1', true)];
        $this->assertSame('1405/07/10', forminator_get_entry_field_value($entry, $mapper, '', false, 100));
    }

    public function test_a_repeated_group_row_is_checked_on_its_own(): void
    {
        $response = $this->submit(['text-3-x7' => '1234567890'] + self::GUESTS);

        $this->assertFalse($response['success'] ?? true);
        // Under the id the copied row was rendered with.
        $this->assertSame([['text-3-x7' => IranianFieldTypes::message('national_id')]], $response['errors'] ?? null);
    }

    public function test_repeated_group_rows_are_saved_fixed(): void
    {
        $response = $this->submit(['text-3-x7' => '۰۰۱۳۵۴۲۴۱۹'] + self::GUESTS);
        $this->assertTrue($response['success'] ?? false, wp_json_encode($response));

        $entry = $this->latestEntry();
        $this->assertSame('0013542419', $entry->get_meta('text-3-2'));
        $this->assertSame('10/03/2026', $entry->get_meta('date-3-2'));
    }

    public function test_an_invalid_iranian_value_is_rejected_with_its_message(): void
    {
        $response = $this->submit(['date-1' => '02/10/2026', 'text-1' => '1234567890', 'phone-1' => '09121234567']);

        $this->assertFalse($response['success'] ?? true);
        $this->assertContains(['text-1' => IranianFieldTypes::message('national_id')], $response['errors'] ?? []);
        $this->assertNull($this->latestEntry());
    }

    public function test_a_date_before_the_start_date_is_rejected_by_forminator(): void
    {
        $response = $this->submit(['date-1' => '1404/01/01', 'text-1' => '0013542419']);

        $this->assertFalse($response['success'] ?? true, 'Forminator checks the converted date against its limits');
        $this->assertArrayHasKey('date-1', array_merge(...($response['errors'] ?? [[]])));
    }

    public function test_the_card_lists_the_forms_with_iranian_fields_and_saving_a_form_clears_the_list(): void
    {
        $module = new ForminatorModule(new SettingsManager());
        $forms = $module->formsUsingFields();

        $this->assertSame(['PK test'], array_column($forms, 'title'));
        $this->assertStringContainsString('page=forminator-cform-wizard&id=' . $this->formId, $forms[0]['url']);
        $this->assertNotFalse(get_transient(ForminatorFieldUsage::TRANSIENT));

        \Forminator_API::update_form_field($this->formId, 'text-1', ['custom-class' => '']);
        \Forminator_API::update_form_field($this->formId, 'phone-1', ['custom-class' => '']);
        \Forminator_API::update_form_field($this->formId, 'text-3', ['custom-class' => '']);
        $this->assertFalse(get_transient(ForminatorFieldUsage::TRANSIENT));
        $this->assertSame([], $module->formsUsingFields());
    }

    /**
     * Sends the form to its page, as without JavaScript.
     *
     * @param array<string, mixed> $fields
     * @return array<string, mixed> Forminator's response.
     */
    private function submit(array $fields): array
    {
        $response = [];
        $capture = static function ($value) use (&$response) {
            $response = $value;

            return $value;
        };
        add_filter('forminator_form_submit_response', $capture);

        // Forminator keeps a submission's errors and fields for the request.
        \Forminator_CForm_Front_Action::$submit_errors = [];
        \Forminator_CForm_Front_Action::$hidden_fields = [];
        \Forminator_CForm_Front_Action::$info['field_data_array'] = [];

        $_POST = $_REQUEST = wp_slash($fields + [
            'date-2-day'       => '21',
            'date-2-month'     => '3',
            'date-2-year'      => '1990',
            'form_id'          => (string) $this->formId,
            'action'           => 'forminator_submit_form_custom-forms',
            'forminator_nonce' => wp_create_nonce('forminator_submit_form' . $this->formId),
            'render_id'        => '0',
            'page_id'          => '0',
        ]);

        \Forminator_CForm_Front_Action::get_instance()->handle_submit();
        remove_filter('forminator_form_submit_response', $capture);

        return is_array($response) ? $response : [];
    }

    private function latestEntry(): ?\Forminator_Form_Entry_Model
    {
        $entries = \Forminator_API::get_entries($this->formId);

        return is_array($entries) && $entries !== [] ? reset($entries) : null;
    }

    /**
     * A builder row with one field.
     *
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function row(string $elementId, array $settings): array
    {
        return [
            'wrapper_id' => 'wrapper-' . $elementId,
            'fields'     => [['element_id' => $elementId, 'id' => $elementId, 'cols' => '12'] + $settings],
        ];
    }

    private function tag(string $html, string $tag, string $name): string
    {
        $this->assertMatchesRegularExpression('/<' . $tag . '[^>]*name="' . preg_quote($name, '/') . '"[^>]*>/', $html);
        preg_match('/<' . $tag . '[^>]*name="' . preg_quote($name, '/') . '"[^>]*>/', $html, $matches);

        return $matches[0];
    }
}
