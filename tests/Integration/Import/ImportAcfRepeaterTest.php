<?php

namespace PersianKit\Tests\Integration\Import;

use PersianKit\Bootstrap;
use PersianKit\Service\Import\ImportRunner;
use PersianKit\Service\Import\Sources\ParsiDate\ParsiDateSource;
use PersianKit\Tests\Integration\Support\UsesImportLog;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Parsi Date's date fields inside repeaters and flexible content, with ACF
 * Pro or Secure Custom Fields. Skipped with the free ACF, which has neither:
 * PERSIAN_KIT_TESTS_ACF_DIR can point at Secure Custom Fields, whose main
 * file is acf.php too.
 */
class ImportAcfRepeaterTest extends WordPressIntegrationTestCase
{
    use UsesImportLog;

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('acf_get_field_type') || !acf_get_field_type('repeater') || !acf_get_field_type('flexible_content')) {
            $this->markTestSkipped('Needs ACF Pro or Secure Custom Fields for repeaters.');
        }

        $this->setUpImportLog();
        update_option('wp_parsidate', ['persian_date' => true]);
        update_option('persian_kit_settings', []);

        $group = acf_update_field_group(['key' => 'group_pd_rep', 'title' => 'Events', 'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'post']]]]);
        $repeater = acf_update_field(['key' => 'field_pd_rep', 'label' => 'Events', 'name' => 'events', 'type' => 'repeater', 'parent' => $group['ID']]);
        acf_update_field(['key' => 'field_pd_rep_date', 'label' => 'When', 'name' => 'when', 'type' => 'jalali_datepicker', 'parent' => $repeater['ID']]);
        $flexible = acf_update_field([
            'key'     => 'field_pd_flex',
            'label'   => 'Blocks',
            'name'    => 'blocks',
            'type'    => 'flexible_content',
            'parent'  => $group['ID'],
            'layouts' => ['layout_pd_day' => ['key' => 'layout_pd_day', 'name' => 'day', 'label' => 'Day', 'display' => 'block']],
        ]);
        acf_update_field(['key' => 'field_pd_flex_date', 'label' => 'Day', 'name' => 'day_date', 'type' => 'jalali_datepicker', 'parent' => $flexible['ID'], 'parent_layout' => 'layout_pd_day']);
    }

    public function test_dates_in_repeater_rows_and_layouts(): void
    {
        $post = self::factory()->post->create();
        // As ACF saves them: the row count, then each row's value and reference.
        $this->meta($post, 'events', '2', 'field_pd_rep');
        $this->meta($post, 'events_0_when', '2024-08-02', 'field_pd_rep_date');
        $this->meta($post, 'events_1_when', '1403-06-31', 'field_pd_rep_date');
        $this->meta($post, 'blocks', ['day'], 'field_pd_flex');
        $this->meta($post, 'blocks_0_day_date', '۱۴۰۳/۰۱/۰۱', 'field_pd_flex_date');

        $runner = Bootstrap::get(ImportRunner::class);
        $runner->start(new ParsiDateSource(), ['rows' => []]);
        $this->assertTrue($runner->run('test', 30.0)->isFinished());

        $this->assertSame('20240802', get_post_meta($post, 'events_0_when', true));
        $this->assertSame('20240921', get_post_meta($post, 'events_1_when', true));
        $this->assertSame('20240320', get_post_meta($post, 'blocks_0_day_date', true));

        // The sub-fields are date pickers now, inside their repeater and layout.
        $this->assertSame('date_picker', acf_get_field('field_pd_rep_date')['type']);
        $this->assertSame('date_picker', acf_get_field('field_pd_flex_date')['type']);

        // ACF reads the rows back, in Jalali through Persian Kit, on the next
        // request: in this one it still holds the fields as they were loaded.
        foreach (['fields', 'values', 'local-fields'] as $store) {
            if (acf_get_store($store)) {
                acf_get_store($store)->reset();
            }
        }
        wp_cache_flush();
        $rows = get_field('events', $post);
        $this->assertSame(['1403/05/12', '1403/06/31'], array_column($rows, 'when'));
        $this->assertSame('1403/01/01', get_field('blocks', $post)[0]['day_date']);
    }

    /**
     * @param string|list<string> $value
     */
    private function meta(int $post, string $name, string|array $value, string $key): void
    {
        update_post_meta($post, $name, $value);
        update_post_meta($post, '_' . $name, $key);
    }
}
