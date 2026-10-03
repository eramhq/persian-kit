<?php

namespace PersianKit\Tests\Unit\Import;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Service\Import\Sources\ParsiDate\WidgetTask;
use PHPUnit\Framework\TestCase;

/**
 * Parsi Date's widget settings in each version's keys.
 */
class WidgetSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('wp_strip_all_tags')->alias(static fn (string $text): string => strip_tags($text));
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_every_id_base_is_an_archive_or_calendar(): void
    {
        $this->assertCount(6, WidgetTask::BASES);
        $this->assertSame(['archive', 'legacy'], WidgetTask::BASES['wpparsidate\widget\parsidatearchivewidget']);
        $this->assertSame(['calendar', '64'], WidgetTask::BASES['wp_parsidate_calendar']);
    }

    public function test_6_4_keys(): void
    {
        $settings = WidgetTask::settings(['kind' => 'archive', 'format' => '64', 'instance' => [
            'title' => '<b>بایگانی</b>', 'post_type' => 'post', 'type' => 'yearly', 'display_count' => '1', 'display_select' => 0,
        ]]);

        $this->assertSame(['title' => 'بایگانی', 'post_type' => 'post', 'type' => 'yearly', 'count' => true, 'dropdown' => false, 'theme' => ''], $settings);
    }

    public function test_6_0_and_5_x_keys(): void
    {
        $archive = WidgetTask::settings(['kind' => 'archive', 'format' => 'legacy', 'instance' => [
            'parsidate_archive_title' => 'Archive', 'parsidate_archive_type' => 'daily', 'parsidate_archive_count' => 0, 'parsidate_archive_list' => 1,
        ]]);
        $calendar = WidgetTask::settings(['kind' => 'calendar', 'format' => 'legacy', 'instance' => ['parsidate_calendar_title' => 'تقویم', 'theme_color' => 'blue']]);

        $this->assertSame(['title' => 'Archive', 'post_type' => 'post', 'type' => 'daily', 'count' => false, 'dropdown' => true, 'theme' => ''], $archive);
        $this->assertSame('تقویم', $calendar['title']);
        $this->assertSame('blue', $calendar['theme']);
    }

    public function test_an_unknown_type_is_monthly(): void
    {
        $this->assertSame('monthly', WidgetTask::settings(['kind' => 'archive', 'format' => '64', 'instance' => ['type' => 'hourly']])['type']);
    }
}
