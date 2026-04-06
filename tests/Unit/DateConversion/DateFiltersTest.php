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
        Functions\when('is_admin')->justReturn(false);
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
            'render_block_core/post-date',
            'render_block_core/latest-comments',
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

    public function test_filter_post_date_preserves_machine_format_output(): void
    {
        $filters = new DateFilters(false);

        $post = new \stdClass();
        $post->post_date_gmt = '2025-03-21 12:00:00';
        $post->post_date = '2025-03-21 15:30:00';

        $result = $filters->filterPostDate('2025-03-21T15:30:00+03:30', DATE_RFC3339, $post);

        $this->assertSame('2025-03-21T15:30:00+03:30', $result);
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

    public function test_filter_post_time_preserves_unix_timestamp_format(): void
    {
        $filters = new DateFilters(false);

        $post = new \stdClass();
        $post->post_date_gmt = '2025-03-21 12:00:00';
        $post->post_date = '2025-03-21 15:30:00';

        $result = $filters->filterPostTime('1742558400', 'U', $post);

        $this->assertSame('1742558400', $result);
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

    public function test_filter_post_date_block_preserves_link_and_datetime_attribute(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('apply_filters')->returnArg(2);

        $filters = new DateFilters(false);

        $block = [
            'attrs' => [
                'format' => 'Y/m/d',
            ],
        ];

        $content = '<div class="wp-block-post-date"><time datetime="2025-03-21T15:30:00+03:30"><a href="/hello-world/">March 21, 2025</a></time></div>';

        $result = $filters->filterPostDateBlock($content, $block);

        $this->assertStringContainsString('datetime="2025-03-21T15:30:00+03:30"', $result);
        $this->assertStringContainsString('<a href="/hello-world/">1404/01/01</a>', $result);
    }

    public function test_filter_post_date_block_returns_original_when_time_tag_missing(): void
    {
        $filters = new DateFilters(false);

        $content = '<div class="wp-block-post-date">March 21, 2025</div>';

        $result = $filters->filterPostDateBlock($content, ['attrs' => ['format' => 'Y/m/d']]);

        $this->assertSame($content, $result);
    }

    public function test_filter_post_date_block_preserves_machine_format_output(): void
    {
        $filters = new DateFilters(false);

        $content = '<div class="wp-block-post-date"><time datetime="2025-03-21T15:30:00+03:30"><a href="/hello-world/">2025-03-21T15:30:00+03:30</a></time></div>';

        $result = $filters->filterPostDateBlock($content, ['attrs' => ['format' => DATE_RFC3339]]);

        $this->assertSame($content, $result);
    }

    public function test_filter_latest_comments_block_returns_original_when_time_tag_missing(): void
    {
        $filters = new DateFilters(false);

        $content = '<ol class="wp-block-latest-comments"><li>No date</li></ol>';

        $result = $filters->filterLatestCommentsBlock($content, []);

        $this->assertSame($content, $result);
    }

    public function test_filter_latest_comments_block_updates_visible_time_text(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('apply_filters')->returnArg(2);

        $filters = new DateFilters(false);

        $content = '<ol class="wp-block-latest-comments"><li><time datetime="2025-03-21T15:30:00+03:30" class="wp-block-latest-comments__comment-date">March 21, 2025</time></li></ol>';

        $result = $filters->filterLatestCommentsBlock($content, []);

        $this->assertStringContainsString('datetime="2025-03-21T15:30:00+03:30"', $result);
        $this->assertStringContainsString('1404', $result);
        $this->assertStringNotContainsString('March 21, 2025', $result);
    }

    public function test_filter_get_post_time_preserves_machine_format_output(): void
    {
        Functions\when('get_post')->justReturn((object) [
            'post_date_gmt' => '2025-03-21 12:00:00',
            'post_date'     => '2025-03-21 15:30:00',
        ]);

        $filters = new DateFilters(false);

        $result = $filters->filterGetPostTime('Fri, 21 Mar 2025 15:30:00 +0330', DATE_RFC2822, false);

        $this->assertSame('Fri, 21 Mar 2025 15:30:00 +0330', $result);
    }
}
