<?php

namespace PersianKit\Tests\Integration\Import;

use PersianKit\Bootstrap;
use PersianKit\Service\Import\ImportLog;
use PersianKit\Service\Import\ImportReport;
use PersianKit\Service\Import\ImportRunner;
use PersianKit\Service\Import\Sources\ParsiDate\ParsiDateSource;
use PersianKit\Tests\Integration\Support\UsesImportLog;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Parsi Date's widgets and blocks become WordPress's own.
 */
class ImportParsiDateTest extends WordPressIntegrationTestCase
{
    use UsesImportLog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpImportLog();
        update_option('wp_parsidate', ['persian_date' => true]);
        update_option('persian_kit_settings', []);
    }

    public function test_widgets_of_every_version_take_their_place(): void
    {
        update_option('widget_wp_parsidate_archive', [2 => ['title' => 'بایگانی', 'post_type' => 'post', 'type' => 'monthly', 'display_count' => 1, 'display_select' => 0], '_multiwidget' => 1]);
        update_option('widget_parsidate_archive', [3 => ['parsidate_archive_title' => 'سال‌ها', 'parsidate_archive_type' => 'yearly', 'parsidate_archive_count' => 1, 'parsidate_archive_list' => 1], '_multiwidget' => 1]);
        update_option('widget_wpparsidate\widget\parsidatecalendarwidget', [4 => ['parsidate_calendar_title' => 'تقویم', 'theme_color' => 'blue'], '_multiwidget' => 1]);
        update_option('widget_wp_parsidate_calendar', [5 => ['title' => 'Products', 'post_type' => 'product'], '_multiwidget' => 1]);
        update_option('widget_archives', [2 => ['title' => 'Existing'], '_multiwidget' => 1]);
        wp_set_sidebars_widgets([
            'wp_inactive_widgets' => [],
            'sidebar-1'           => ['search-2', 'wp_parsidate_archive-2', 'parsidate_archive-3'],
            'sidebar-2'           => ['wpparsidate\widget\parsidatecalendarwidget-4', 'wp_parsidate_calendar-5'],
        ]);

        $this->switchFrom();

        $sidebars = wp_get_sidebars_widgets();
        $this->assertSame(['search-2', 'archives-3'], array_slice($sidebars['sidebar-1'], 0, 2));
        $this->assertMatchesRegularExpression('/^block-\d+$/', $sidebars['sidebar-1'][2]);
        $block = (int) substr($sidebars['sidebar-1'][2], 6);
        $this->assertSame(['calendar-2', 'wp_parsidate_calendar-5'], $sidebars['sidebar-2']);

        $this->assertSame(['title' => 'بایگانی', 'count' => 1, 'dropdown' => 0], get_option('widget_archives')[3]);
        $this->assertStringContainsString('<!-- wp:archives {"type":"yearly","displayAsDropdown":true,"showPostCounts":true} /-->', get_option('widget_block')[$block]['content']);
        $this->assertStringContainsString('سال‌ها', get_option('widget_block')[$block]['content']);
        $this->assertSame(['title' => 'تقویم'], get_option('widget_calendar')[2]);
        // The old settings are kept.
        $this->assertSame('بایگانی', get_option('widget_wp_parsidate_archive')[2]['title']);

        $attention = Bootstrap::get(ImportReport::class)->page(new ParsiDateSource(), ImportLog::ATTENTION, 1)['rows'];
        $this->assertStringContainsString('product', $attention[0]['reason']);

        // Undo puts them back.
        Bootstrap::get(ImportRunner::class)->undo(new ParsiDateSource(), 'test', 0, '', 30.0);
        $this->assertSame(['search-2', 'wp_parsidate_archive-2', 'parsidate_archive-3'], wp_get_sidebars_widgets()['sidebar-1']);
    }

    public function test_widgets_wordpress_moved_to_inactive_go_back_where_they_were(): void
    {
        update_option('widget_wp_parsidate_archive', [2 => ['title' => 'A', 'type' => 'monthly'], 3 => ['title' => 'B', 'type' => 'monthly'], '_multiwidget' => 1]);
        wp_set_sidebars_widgets(['wp_inactive_widgets' => [], 'sidebar-1' => ['search-2', 'wp_parsidate_archive-2', 'wp_parsidate_archive-3']]);

        // Review takes the snapshot while Parsi Date is active.
        $runner = Bootstrap::get(ImportRunner::class);
        $source = new class extends ParsiDateSource {
            public bool $active = true;

            public function isActive(): bool
            {
                return $this->active;
            }
        };
        $runner->start($source, ['rows' => []]);

        // Deactivated; someone opened the Widgets screen.
        $source->active = false;
        wp_set_sidebars_widgets(['wp_inactive_widgets' => ['wp_parsidate_archive-2', 'wp_parsidate_archive-3'], 'sidebar-1' => ['search-2']]);
        $runner->start($source);
        $this->assertTrue($runner->run('test', 30.0)->isFinished());

        $sidebars = wp_get_sidebars_widgets();
        $this->assertSame(['search-2', 'archives-2', 'archives-3'], $sidebars['sidebar-1']);
        $this->assertSame([], $sidebars['wp_inactive_widgets']);
    }

    public function test_blocks_in_posts_template_parts_and_block_widgets(): void
    {
        $post = self::factory()->post->create(['post_content' => "<!-- wp:paragraph -->\n<p>Hi</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:wp-parsidate/archive {\"title\":\"بایگانی\",\"displayCount\":true} /-->"]);
        $part = self::factory()->post->create(['post_type' => 'wp_template_part', 'post_content' => '<!-- wp:wp-parsidate/calendar {"title":""} /-->']);
        $revision = self::factory()->post->create(['post_type' => 'revision', 'post_status' => 'inherit', 'post_content' => '<!-- wp:wp-parsidate/calendar /-->']);
        update_option('widget_block', [2 => ['content' => '<!-- wp:wp-parsidate/archive {"type":"yearly","title":""} /-->'], 3 => ['content' => '<!-- wp:search /-->'], '_multiwidget' => 1]);

        $this->switchFrom();

        $this->assertSame(
            "<!-- wp:paragraph -->\n<p>Hi</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">بایگانی</h2>\n<!-- /wp:heading -->\n\n<!-- wp:archives {\"showPostCounts\":true} /-->",
            get_post_field('post_content', $post)
        );
        $this->assertSame('<!-- wp:calendar /-->', get_post_field('post_content', $part));
        $this->assertSame('<!-- wp:wp-parsidate/calendar /-->', get_post_field('post_content', $revision));
        $this->assertSame('<!-- wp:archives {"type":"yearly"} /-->', get_option('widget_block')[2]['content']);
        // No revision was made.
        $this->assertSame([], wp_get_post_revisions($post));

        // The rendered archives list is WordPress's.
        $this->assertStringContainsString('wp-block-archives', do_blocks(get_post_field('post_content', $post)));

        Bootstrap::get(ImportRunner::class)->undo(new ParsiDateSource(), 'test', 0, '', 30.0);
        $this->assertSame('<!-- wp:wp-parsidate/calendar {"title":""} /-->', get_post_field('post_content', $part));
        $this->assertSame('<!-- wp:wp-parsidate/archive {"type":"yearly","title":""} /-->', get_option('widget_block')[2]['content']);
    }

    private function switchFrom(): void
    {
        $runner = Bootstrap::get(ImportRunner::class);
        $runner->start(new ParsiDateSource(), ['rows' => []]);
        $this->assertTrue($runner->run('test', 30.0)->isFinished());
    }
}
