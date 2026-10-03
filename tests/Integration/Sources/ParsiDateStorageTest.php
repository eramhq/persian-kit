<?php

namespace PersianKit\Tests\Integration\Sources;

use PersianKit\Service\Import\Sources\ParsiDate\BlockRewriter;
use PersianKit\Service\Import\Sources\ParsiDate\ParsiDateSource;
use PersianKit\Service\Import\Sources\ParsiDate\WidgetTask;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * What the real Parsi Date stores for its widgets and blocks is what the
 * switch reads, and it is found while active.
 *
 * @group sources
 */
class ParsiDateStorageTest extends WordPressIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('WP_PARSI_ROOT')) {
            $this->markTestSkipped('Parsi Date is not loaded.');
        }
    }

    public function test_it_is_found_while_active(): void
    {
        $source = new ParsiDateSource();

        $this->assertTrue($source->isActive());
        $this->assertTrue($source->hasData());
        $this->assertNotNull($source->readyToImport());
    }

    public function test_its_widgets_store_what_the_switch_reads(): void
    {
        $widgets = [];
        foreach (['WPParsidate\Widget\ArchiveWidget', 'WPParsidate\Widget\CalendarWidget'] as $class) {
            $widget = new $class();
            $widgets[$widget->id_base] = $widget;
        }

        foreach (['wp_parsidate_archive', 'wp_parsidate_calendar'] as $base) {
            $this->assertArrayHasKey($base, $widgets);
            $this->assertArrayHasKey($base, WidgetTask::BASES);
        }

        // As the Widgets screen saves the form.
        $archive = $widgets['wp_parsidate_archive']->update(['title' => 'بایگانی', 'post_type' => 'post', 'type' => 'yearly', 'display_select' => '1', 'display_count' => '1'], []);
        $settings = WidgetTask::settings(['kind' => 'archive', 'format' => WidgetTask::BASES['wp_parsidate_archive'][1], 'instance' => $archive]);
        $this->assertSame(['title' => 'بایگانی', 'post_type' => 'post', 'type' => 'yearly', 'count' => true, 'dropdown' => true, 'theme' => ''], $settings);

        $calendar = $widgets['wp_parsidate_calendar']->update(['title' => 'تقویم', 'post_type' => 'post', 'theme' => 'dark'], []);
        $this->assertSame('تقویم', WidgetTask::settings(['kind' => 'calendar', 'format' => '64', 'instance' => $calendar])['title']);
    }

    public function test_its_blocks_have_the_attributes_the_rewriter_reads(): void
    {
        $registry = \WP_Block_Type_Registry::get_instance();
        $archive = $registry->get_registered('wp-parsidate/archive');
        $calendar = $registry->get_registered('wp-parsidate/calendar');

        $this->assertNotNull($archive);
        $this->assertNotNull($calendar);
        foreach (['title', 'postType', 'type', 'displaySelect', 'displayCount'] as $attribute) {
            $this->assertArrayHasKey($attribute, $archive->attributes);
        }
        $this->assertArrayHasKey('theme', $calendar->attributes);

        // As the editor serializes it.
        $block = serialize_block(['blockName' => 'wp-parsidate/archive', 'attrs' => ['title' => 'سال‌ها', 'type' => 'yearly', 'displayCount' => true], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]);
        $this->assertStringContainsString('<!-- wp:archives {"type":"yearly","showPostCounts":true} /-->', (new BlockRewriter())->rewrite($block));
    }
}
