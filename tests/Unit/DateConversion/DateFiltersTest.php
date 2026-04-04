<?php

namespace PersianKit\Tests\Unit\DateConversion;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Brain\Monkey\Filters;
use PersianKit\Modules\DateConversion\DateFilters;

class DateFiltersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // DateFilters constructor reads format options
        Functions\when('get_option')->alias(function (string $key) {
            return match ($key) {
                'date_format' => 'Y/m/d',
                'time_format' => 'H:i',
                default => '',
            };
        });
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_tier1_registers_all_hooks(): void
    {
        $filters = new DateFilters(false);
        $filters->registerTier1();

        $expectedHooks = [
            'get_the_date',
            'the_date',
            'get_the_time',
            'get_the_modified_date',
            'get_the_modified_time',
            'get_comment_date',
            'get_comment_time',
            'get_post_time',
        ];

        foreach ($expectedHooks as $hook) {
            $this->assertTrue(
                has_filter($hook),
                "Filter '{$hook}' should be registered by tier 1"
            );
        }
    }

    public function test_register_tier2_not_registered_when_disabled(): void
    {
        $filters = new DateFilters(false);
        $filters->registerTier2();

        $this->assertFalse(has_filter('wp_date'));
    }

    public function test_register_tier2_registered_when_enabled(): void
    {
        $filters = new DateFilters(true);
        $filters->registerTier2();

        $this->assertTrue(has_filter('wp_date'));
    }

    public function test_register_admin_filters(): void
    {
        $filters = new DateFilters(false);
        $filters->registerAdminFilters();

        $this->assertTrue(has_action('admin_bar_menu'));
    }

    public function test_filter_post_date_returns_jalali(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('apply_filters')->returnArg(2);

        $filters = new DateFilters(false);

        $post = new \stdClass();
        $post->post_date_gmt = '2025-03-21 12:00:00';
        $post->post_date = '2025-03-21 15:30:00';

        $result = $filters->filterPostDate('March 21, 2025', 'Y/m/d', $post);

        $this->assertSame('1404/01/01', $result);
    }

    public function test_filter_post_date_uses_cached_date_format_when_format_empty(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('apply_filters')->returnArg(2);

        $filters = new DateFilters(false);

        $post = new \stdClass();
        $post->post_date_gmt = '2025-03-21 12:00:00';
        $post->post_date = '2025-03-21 15:30:00';

        // Empty format should fall back to cached 'Y/m/d' from constructor
        $result = $filters->filterPostDate('March 21, 2025', '', $post);

        $this->assertSame('1404/01/01', $result);
    }

    public function test_filter_post_date_returns_original_when_no_post(): void
    {
        $filters = new DateFilters(false);

        $result = $filters->filterPostDate('March 21, 2025', 'Y/m/d', null);

        $this->assertSame('March 21, 2025', $result);
    }

    public function test_filter_wp_date_guards_against_recursion(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('apply_filters')->returnArg(2);

        $filters = new DateFilters(true);

        // Use reflection to set inFilter = true to simulate recursion
        $ref = new \ReflectionClass(DateFilters::class);
        $prop = $ref->getProperty('inFilter');
        $prop->setValue(null, true);

        $result = $filters->filterWpDate('2025-03-21', 'Y-m-d', time());

        // Should return original date when guard is active
        $this->assertSame('2025-03-21', $result);

        // Reset
        $prop->setValue(null, false);
    }

    public function test_filter_modified_date(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('apply_filters')->returnArg(2);

        $filters = new DateFilters(false);

        $post = new \stdClass();
        $post->post_modified_gmt = '2025-03-21 12:00:00';
        $post->post_modified = '2025-03-21 15:30:00';

        $result = $filters->filterModifiedDate('March 21, 2025', 'Y/m/d', $post);

        $this->assertSame('1404/01/01', $result);
    }

    public function test_filter_comment_date(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('apply_filters')->returnArg(2);

        $filters = new DateFilters(false);

        $comment = new \stdClass();
        $comment->comment_date = '2025-03-21 15:30:00';

        $result = $filters->filterCommentDate('March 21, 2025', 'Y/m/d', $comment);

        $this->assertSame('1404/01/01', $result);
    }
}
