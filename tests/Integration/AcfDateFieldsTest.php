<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * ACF's date fields with the Forms module as the plugin booted it, and Date
 * Conversion on (the default). Runs when ACF is loaded (see
 * tests/bootstrap.php).
 */
class AcfDateFieldsTest extends WordPressIntegrationTestCase
{
    private int $postId;

    public function set_up(): void
    {
        parent::set_up();

        if (!function_exists('acf_add_local_field_group')) {
            $this->markTestSkipped('ACF is not installed next to the plugin.');
        }

        acf_add_local_field_group([
            'key'      => 'group_persian_kit',
            'title'    => 'Persian Kit',
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'post']]],
            'fields'   => [
                ['key' => 'field_pk_event', 'name' => 'event', 'label' => 'Event', 'type' => 'date_picker', 'display_format' => 'd/m/Y', 'return_format' => 'Y/m/d'],
                ['key' => 'field_pk_iso', 'name' => 'iso', 'label' => 'ISO', 'type' => 'date_picker', 'return_format' => 'Ymd'],
                ['key' => 'field_pk_meeting', 'name' => 'meeting', 'label' => 'Meeting', 'type' => 'date_time_picker', 'display_format' => 'd/m/Y g:i a', 'return_format' => 'Y/m/d H:i'],
            ],
        ]);

        $this->postId = self::factory()->post->create();
    }

    public function tear_down(): void
    {
        $_POST = [];
        if (function_exists('acf_remove_local_field_group')) {
            acf_remove_local_field_group('group_persian_kit');
        }
        parent::tear_down();
    }

    public function test_date_fields_render_for_the_jalali_picker(): void
    {
        update_field('event', '20261002', $this->postId);
        update_field('meeting', '2026-10-02 09:30:00', $this->postId);

        $event = $this->renderField('field_pk_event');
        $this->assertStringContainsString('<input type="hidden" id="acf-field_pk_event" name="acf[field_pk_event]" value="20261002" data-persian-kit-date data-persian-kit-date-format="Ymd">', $event);
        $this->assertStringNotContainsString('acf-date-picker', $event, 'ACF\'s own picker is not rendered');

        $meeting = $this->renderField('field_pk_meeting');
        $this->assertStringContainsString('value="2026-10-02 09:30:00"', $meeting);
        $this->assertStringContainsString('data-persian-kit-date-format="Y-m-d H:i:s"', $meeting);

        $this->assertTrue(wp_script_is('persian-kit-date-field', 'enqueued'));
    }

    public function test_a_saved_date_is_stored_as_acf_stores_it(): void
    {
        // As the hidden field submits it.
        $_POST['acf'] = [
            'field_pk_event'   => '20261002',
            'field_pk_meeting' => '2026-10-02 09:30:00',
        ];
        acf_save_post($this->postId);

        $this->assertSame('20261002', get_post_meta($this->postId, 'event', true));
        $this->assertSame('2026-10-02 09:30:00', get_post_meta($this->postId, 'meeting', true));
        $this->assertSame('20261002', get_field('event', $this->postId, false));
    }

    public function test_templates_get_jalali_dates_in_the_return_format(): void
    {
        update_field('event', '20261002', $this->postId);
        update_field('iso', '20261002', $this->postId);
        update_field('meeting', '2026-10-02 09:30:00', $this->postId);

        $this->assertSame('1405/07/10', get_field('event', $this->postId));
        $this->assertSame('1405/07/10 09:30', get_field('meeting', $this->postId));
        // A format code reads stays Gregorian.
        $this->assertSame('20261002', get_field('iso', $this->postId));
        // Only ACF's own formatting is changed.
        $this->assertSame(date_i18n('Y/m/d', strtotime('2026-10-02')), '2026/10/02');
    }

    public function test_the_filter_keeps_a_field_gregorian(): void
    {
        update_field('event', '20261002', $this->postId);
        add_filter('persian_kit_acf_jalali_value', fn (bool $jalali, array $field) => $field['name'] !== 'event', 10, 2);

        $this->assertSame('2026/10/02', get_field('event', $this->postId));
    }

    private function renderField(string $key): string
    {
        $field = acf_get_field($key);
        $field['value'] = acf_get_value($this->postId, $field);
        $field['name'] = 'acf[' . $key . ']';
        $field = acf_prepare_field($field);

        ob_start();
        acf_render_field($field);

        return (string) ob_get_clean();
    }
}
