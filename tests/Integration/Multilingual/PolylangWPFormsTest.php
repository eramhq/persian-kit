<?php

namespace PersianKit\Tests\Integration\Multilingual;

use PersianKit\Tests\Integration\Support\UsesPolylang;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * WPForms' Jalali date field follows the page's language: the Jalali
 * picker and dates in Persian, the browser's date input and Gregorian
 * dates in other languages.
 *
 * @group polylang
 */
class PolylangWPFormsTest extends WordPressIntegrationTestCase
{
    use UsesPolylang;

    private int $formId;

    public function set_up(): void
    {
        parent::set_up();
        $this->setUpPolylang();

        if (!function_exists('wpforms_display')) {
            $this->markTestSkipped('WPForms is not installed next to the plugin.');
        }

        $this->formId = (int) wp_insert_post(['post_type' => 'wpforms', 'post_status' => 'publish', 'post_title' => 'PK dates']);
        wp_update_post(['ID' => $this->formId, 'post_content' => wpforms_encode([
            'id'       => (string) $this->formId,
            'field_id' => 2,
            'fields'   => [1 => ['id' => '1', 'type' => 'persian-kit-date', 'label' => 'Visit', 'date_format' => 'd/m/Y', 'size' => 'medium']],
            'settings' => ['form_title' => 'PK dates'],
        ])]);
    }

    public function tear_down(): void
    {
        $_POST = [];
        parent::tear_down();
    }

    public function test_on_an_english_page_the_field_is_the_browsers_date_input(): void
    {
        $this->assertStringContainsString('data-persian-kit-date-format="d/m/Y"', $this->render());

        $this->useLanguage('en');
        $html = $this->render();
        $this->assertStringNotContainsString('data-persian-kit-date', $html);
        $this->assertMatchesRegularExpression('/<input type="date"[^>]*name="wpforms\[fields\]\[1\]"/', $html);
    }

    public function test_the_entry_follows_the_page_the_form_was_sent_from(): void
    {
        // Whatever language the request is in, the page decides.
        $_POST['page_id'] = (string) $this->postIn('en', ['post_type' => 'page']);
        $this->assertSame('04/10/2026', $this->submit('2026-10-04'));

        $_POST['page_id'] = (string) $this->postIn('fa', ['post_type' => 'page']);
        $this->assertSame('12/07/1405', $this->submit('04/10/2026'));
    }

    private function render(): string
    {
        ob_start();
        wpforms_display($this->formId);

        return (string) ob_get_clean();
    }

    private function submit(string $date): string
    {
        $process = wpforms()->obj('process');
        $process->process(['id' => (string) $this->formId, 'fields' => [1 => $date], 'page_id' => $_POST['page_id']]);
        $this->assertSame([], $process->errors[$this->formId] ?? []);

        return (string) $process->fields[1]['value'];
    }
}
