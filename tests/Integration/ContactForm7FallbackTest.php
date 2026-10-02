<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Bootstrap;
use PersianKit\Modules\Forms\Cf7IranianFields;
use PersianKit\Modules\Forms\IranianFieldTypes;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * A form that uses the Iranian fields while the Contact Form 7 integration
 * is off: the fields are still text inputs, not raw [national_id …] text,
 * and they accept any text. Runs when Contact Form 7 is loaded.
 */
class ContactForm7FallbackTest extends WordPressIntegrationTestCase
{
    private const FORM = <<<'FORM'
        [national_id* code]
        [mobile_ir mobile]
        [submit]
        FORM;

    private \WPCF7_ContactForm $form;

    public function set_up(): void
    {
        parent::set_up();

        if (!class_exists('WPCF7_ContactForm')) {
            $this->markTestSkipped('Contact Form 7 is not installed next to the plugin.');
        }

        // The plugin booted with the integration on. Take its checks off
        // again, leaving what the fallback registers (WordPress restores
        // hooks after each test).
        $fields = Bootstrap::get(Cf7IranianFields::class);
        remove_filter('wpcf7_messages', [$fields, 'addMessages']);
        remove_action('wpcf7_swv_create_schema', [$fields, 'addRequiredRules']);
        foreach (IranianFieldTypes::types() as $type) {
            foreach ([$type, "{$type}*"] as $tag) {
                remove_filter("wpcf7_validate_{$tag}", [$fields, 'validate']);
                remove_filter("wpcf7_posted_data_{$tag}", [$fields, 'normalizePostedValue']);
            }
        }
        (new Cf7IranianFields())->registerFallback();

        $this->form = \WPCF7_ContactForm::get_template(['title' => 'Fallback']);
        $this->form->set_properties(['form' => self::FORM]);
        $this->form->save();

        add_filter('wpcf7_skip_spam_check', '__return_true');
        $this->resetSubmission();
    }

    public function tear_down(): void
    {
        $_POST = [];
        $this->resetSubmission();
        parent::tear_down();
    }

    public function test_the_fields_still_render_as_inputs(): void
    {
        $html = $this->form->form_html();

        $this->assertStringNotContainsString('[national_id', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="code"/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="mobile"/', $html);
    }

    public function test_the_fields_accept_any_text(): void
    {
        $_POST = wp_slash(['code' => 'not an ID', 'mobile' => '۱۲۳']);
        $submission = \WPCF7_Submission::get_instance($this->form);

        $this->assertNotSame('validation_failed', $submission->get_status(), print_r($submission->get_invalid_fields(), true));
        $this->assertSame('not an ID', $submission->get_posted_data('code'));
        $this->assertSame('۱۲۳', $submission->get_posted_data('mobile'));
    }

    private function resetSubmission(): void
    {
        if (!class_exists('WPCF7_Submission')) {
            return;
        }

        $instance = new \ReflectionProperty(\WPCF7_Submission::class, 'instance');
        $instance->setValue(null, null);
    }
}
