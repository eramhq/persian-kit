<?php

namespace PersianKit\Tests\Integration\Import;

use PersianKit\Bootstrap;
use PersianKit\Service\Import\ImportLog;
use PersianKit\Service\Import\ImportReport;
use PersianKit\Service\Import\ImportReview;
use PersianKit\Service\Import\ImportRunner;
use PersianKit\Service\Import\Sources\ParsiDate\ParsiDateSource;
use PersianKit\Tests\Integration\Support\UsesImportLog;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Parsi Date's ACF date fields and their values become ACF's date picker.
 */
class ImportAcfTest extends WordPressIntegrationTestCase
{
    use UsesImportLog;

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('acf_update_field_group')) {
            $this->markTestSkipped('ACF is not loaded.');
        }

        $this->setUpImportLog();
        update_option('wp_parsidate', ['persian_date' => true]);
        update_option('persian_kit_settings', []);

        $group = acf_update_field_group(['key' => 'group_pd', 'title' => 'Dates', 'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'post']]]]);
        acf_update_field(['key' => 'field_pd_date', 'label' => 'Date', 'name' => 'pd_date', 'type' => 'jalali_datepicker', 'parent' => $group['ID'], 'placeholder' => 'YYYY-MM-DD']);
        $inner = acf_update_field(['key' => 'field_pd_group', 'label' => 'Group', 'name' => 'pd_group', 'type' => 'group', 'parent' => $group['ID']]);
        acf_update_field(['key' => 'field_pd_sub', 'label' => 'Sub', 'name' => 'sub', 'type' => 'jalali_datepicker', 'parent' => $inner['ID']]);
        acf_update_field(['key' => 'field_pd_picker', 'label' => 'Picker', 'name' => 'pd_picker', 'type' => 'date_picker', 'parent' => $group['ID']]);

        // A group only in PHP.
        acf_add_local_field_group(['key' => 'group_local', 'title' => 'Local', 'fields' => [['key' => 'field_local', 'label' => 'Local date', 'name' => 'local_date', 'type' => 'jalali_datepicker']], 'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'page']]]]);
    }

    public function test_values_everywhere_acf_keeps_them_and_the_fields(): void
    {
        $post = self::factory()->post->create();
        $this->value('post', $post, 'pd_date', '2024-08-02', 'field_pd_date');
        $this->value('post', $post, 'pd_group_sub', '1403-05-12', 'field_pd_sub');
        // A repeater row, as ACF Pro saves it.
        $this->value('post', $post, 'rep_0_sub', '۱۴۰۳/۰۶/۳۱', 'field_pd_sub');
        $this->value('post', $post, 'pd_picker', '26460321', 'field_pd_picker');
        $this->value('post', $post, 'pd_bad', '1650-01-01', 'field_pd_date');
        $term = self::factory()->term->create();
        $this->value('term', $term, 'pd_date', '1399-12-30', 'field_pd_date');
        $user = self::factory()->user->create();
        $this->value('user', $user, 'pd_date', '20240802', 'field_pd_date');
        update_option('options_pd_date', '1403-01-01');
        update_option('_options_pd_date', 'field_pd_date');

        $review = Bootstrap::get(ImportReview::class)->review(new ParsiDateSource());
        $tasks = array_column($review['tasks'], null, 'key');
        // The already-Ymd value is not counted.
        $this->assertSame(7, $tasks['acf_values']['count']);
        $this->assertSame('fix_double_dates', $tasks['acf_values']['options']['type']);
        // Two in the database, one in PHP.
        $this->assertSame(3, $tasks['acf_fields']['count']);

        $runner = Bootstrap::get(ImportRunner::class);
        $runner->start(new ParsiDateSource(), ['rows' => []]);
        $this->assertTrue($runner->run('test', 30.0)->isFinished());

        $this->assertSame('20240802', get_post_meta($post, 'pd_date', true));
        $this->assertSame('20240802', get_post_meta($post, 'pd_group_sub', true));
        $this->assertSame('20240921', get_post_meta($post, 'rep_0_sub', true));
        $this->assertSame('26460321', get_post_meta($post, 'pd_picker', true));
        $this->assertSame('1650-01-01', get_post_meta($post, 'pd_bad', true));
        $this->assertSame('20210320', get_term_meta($term, 'pd_date', true));
        // Nowruz 1403 fell on 20 March 2024.
        $this->assertSame('20240320', get_option('options_pd_date'));

        $field = acf_get_field('field_pd_date');
        $this->assertSame('date_picker', $field['type']);
        $this->assertSame('d/m/Y', $field['display_format']);
        $this->assertSame('Y/m/d', $field['return_format']);
        $this->assertSame('date_picker', acf_get_field('field_pd_sub')['type']);
        // ACF reads it back, in Jalali through Persian Kit: 1403/05/12 where
        // Parsi Date gave templates 1403-05-12.
        $this->assertSame('1403/05/12', get_field('pd_date', $post));

        $attention = Bootstrap::get(ImportReport::class)->page(new ParsiDateSource(), ImportLog::ATTENTION, 1)['rows'];
        $reasons = implode("\n", array_column($attention, 'reason'));
        $this->assertStringContainsString('PHP code', $reasons);
        $this->assertStringContainsString('2100', $reasons);
        $this->assertStringContainsString('1650-01-01', $reasons);

        // A value edited since is kept by undo.
        update_post_meta($post, 'pd_group_sub', '20250101');
        Bootstrap::get(ImportRunner::class)->undo(new ParsiDateSource(), 'test', 0, '', 30.0);
        $this->assertSame('2024-08-02', get_post_meta($post, 'pd_date', true));
        $this->assertSame('20250101', get_post_meta($post, 'pd_group_sub', true));
        $this->assertSame('jalali_datepicker', acf_get_field('field_pd_date')['type']);
    }

    public function test_dates_saved_twice_are_fixed_when_asked(): void
    {
        $post = self::factory()->post->create();
        $this->value('post', $post, 'pd_picker', '26460321', 'field_pd_picker');

        $runner = Bootstrap::get(ImportRunner::class);
        $runner->start(new ParsiDateSource(), ['rows' => [], 'options' => ['fix_double_dates' => true]]);
        $runner->run('test', 30.0);

        $this->assertSame('20250101', get_post_meta($post, 'pd_picker', true));
    }

    private function value(string $type, int $id, string $name, string $value, string $key): void
    {
        update_metadata($type, $id, $name, $value);
        update_metadata($type, $id, '_' . $name, $key);
    }
}
