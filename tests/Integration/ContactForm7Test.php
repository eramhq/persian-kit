<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Contact Form 7 with the Forms module as the plugin booted it. Runs when
 * Contact Form 7 is loaded (see tests/bootstrap.php). Submissions run
 * through CF7 itself, and emails are caught by WordPress's mock mailer.
 */
class ContactForm7Test extends WordPressIntegrationTestCase
{
    private const FORM = <<<'FORM'
        <label for="visit">Visit</label> [date* visit id:visit min:2026-01-01]
        [date born gregorian]
        [mobile_ir* mobile]
        [national_id code]
        [card_ir card]
        [iban_ir iban]
        [tel phone]
        [submit]
        FORM;

    private \WPCF7_ContactForm $form;

    public function set_up(): void
    {
        parent::set_up();

        if (!class_exists('WPCF7_ContactForm')) {
            $this->markTestSkipped('Contact Form 7 is not installed next to the plugin.');
        }

        $this->form = \WPCF7_ContactForm::get_template(['title' => 'Persian Kit']);
        $this->form->set_properties([
            'form' => self::FORM,
            'mail' => [
                'active'             => true,
                'subject'            => 'Visit',
                'sender'             => 'WordPress <wordpress@example.org>',
                'recipient'          => 'admin@example.org',
                'body'               => "Visit: [visit]\nISO: [_format_visit \"Y-m-d\"]\nRaw: [_raw_visit]\nMobile: [mobile]\nCard: [card]\nIBAN: [iban]\nPhone: [phone]",
                'additional_headers' => '',
                'attachments'        => '',
                'use_html'           => false,
                'exclude_blank'      => false,
            ],
        ]);
        $this->form->save();

        // CF7's bot checks expect a browser's request; they are not tested here.
        add_filter('wpcf7_skip_spam_check', '__return_true');

        reset_phpmailer_instance();
        $this->resetSubmission();
    }

    public function tear_down(): void
    {
        $_POST = [];
        $this->resetSubmission();
        parent::tear_down();
    }

    public function test_date_fields_get_the_jalali_picker(): void
    {
        $html = $this->form->form_html();

        $this->assertMatchesRegularExpression('/<input[^>]*name="visit"[^>]*>/', $html);
        preg_match('/<input[^>]*name="visit"[^>]*>/', $html, $visit);
        $this->assertStringContainsString('data-persian-kit-date', $visit[0]);
        $this->assertStringContainsString('type="text"', $visit[0]);
        $this->assertStringContainsString('min="2026-01-01"', $visit[0]);

        // [date born gregorian] keeps CF7's date input.
        preg_match('/<input[^>]*name="born"[^>]*>/', $html, $born);
        $this->assertStringNotContainsString('data-persian-kit-date', $born[0]);
        $this->assertStringContainsString('type="date"', $born[0]);

        $this->assertTrue(wp_script_is('persian-kit-date-field', 'enqueued'));
        $this->assertTrue(wp_style_is('persian-kit-date-field', 'enqueued'));
    }

    public function test_iranian_fields_render_as_left_to_right_inputs(): void
    {
        $html = $this->form->form_html();

        preg_match('/<input[^>]*name="mobile"[^>]*>/', $html, $mobile);
        $this->assertStringContainsString('type="tel"', $mobile[0]);
        $this->assertStringContainsString('dir="ltr"', $mobile[0]);
        $this->assertStringContainsString('aria-required="true"', $mobile[0]);

        preg_match('/<input[^>]*name="code"[^>]*>/', $html, $code);
        $this->assertStringContainsString('inputmode="numeric"', $code[0]);
    }

    public function test_a_typed_jalali_date_is_sent_as_gregorian_and_mailed_as_jalali(): void
    {
        $submission = $this->submit([
            'visit'  => '۱۴۰۵/۰۷/۱۰',
            'mobile' => '۰۹۱۲ ۱۲۳ ۴۵۶۷',
            'card'   => '6037-9975-9940-4952',
            'iban'   => 'ir۷۳ ۰۰۱۸ ۰۰۰۰ ۰۰۰۰ ۰۰۱۲ ۳۴۵۶ ۷۸',
            'phone'  => '۰۲۱ ۸۸۷۷ ۶۶۵۵',
        ]);

        $this->assertSame('mail_sent', $submission->get_status(), print_r($submission->get_invalid_fields(), true));
        $this->assertSame('2026-10-02', $submission->get_posted_data('visit'));
        $this->assertSame('09121234567', $submission->get_posted_data('mobile'));
        $this->assertSame('6037997599404952', $submission->get_posted_data('card'));
        $this->assertSame('IR730018000000000012345678', $submission->get_posted_data('iban'));
        $this->assertSame('021 8877 6655', $submission->get_posted_data('phone'));

        $body = tests_retrieve_phpmailer_instance()->get_sent()->body;
        $this->assertStringContainsString('Visit: ' . persian_kit_date(get_option('date_format'), '2026-10-02 00:00:00'), $body);
        $this->assertStringContainsString('ISO: 2026-10-02', $body);
        $this->assertStringContainsString('Raw: 2026-10-02', $body);
        $this->assertStringContainsString('Mobile: 09121234567', $body);
        $this->assertStringContainsString('IBAN: IR730018000000000012345678', $body);
    }

    public function test_a_date_from_the_picker_is_sent_unchanged(): void
    {
        $submission = $this->submit(['visit' => '2026-10-02', 'mobile' => '09121234567']);

        $this->assertSame('mail_sent', $submission->get_status(), print_r($submission->get_invalid_fields(), true));
        $this->assertSame('2026-10-02', $submission->get_posted_data('visit'));
    }

    public function test_cf7_still_checks_the_converted_date(): void
    {
        // 1 Farvardin 1404 is 2025-03-21, before the field's min.
        $submission = $this->submit(['visit' => '۱۴۰۴/۰۱/۰۱', 'mobile' => '09121234567']);

        $this->assertSame('validation_failed', $submission->get_status());
        $this->assertArrayHasKey('visit', $submission->get_invalid_fields());
    }

    public function test_invalid_iranian_values_and_missing_required_ones_are_reported(): void
    {
        $submission = $this->submit([
            'visit' => '2026-10-02',
            'code'  => '1234567890',
            'card'  => '6037997599404955',
            'iban'  => 'IR740018000000000012345678',
        ]);

        $this->assertSame('validation_failed', $submission->get_status());
        $invalid = $submission->get_invalid_fields();
        $this->assertSame(wpcf7_get_message('invalid_required'), $invalid['mobile']['reason']);
        $this->assertSame(wpcf7_get_message('invalid_national_id'), $invalid['code']['reason']);
        $this->assertSame(wpcf7_get_message('invalid_card_ir'), $invalid['card']['reason']);
        $this->assertSame(wpcf7_get_message('invalid_iban_ir'), $invalid['iban']['reason']);
        $this->assertArrayNotHasKey('visit', $invalid);
    }

    /**
     * @param array<string, string> $fields
     */
    private function submit(array $fields): \WPCF7_Submission
    {
        $_POST = wp_slash($fields);

        return \WPCF7_Submission::get_instance($this->form);
    }

    /**
     * CF7 keeps one submission per request.
     */
    private function resetSubmission(): void
    {
        if (!class_exists('WPCF7_Submission')) {
            return;
        }

        $instance = new \ReflectionProperty(\WPCF7_Submission::class, 'instance');
        $instance->setValue(null, null);
    }
}
