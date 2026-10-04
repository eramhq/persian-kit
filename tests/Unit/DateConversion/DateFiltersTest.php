<?php

namespace PersianKit\Tests\Unit\DateConversion;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Brain\Monkey\Filters;
use PersianKit\Modules\DateConversion\DateFilters;
use PersianKit\Modules\DateConversion\GregorianCompanion;
use PersianKit\Service\Language\ContentLanguage;
use PersianKit\Tests\Unit\Support\UsesLanguages;

class DateFiltersTest extends TestCase
{
    use UsesLanguages;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        ContentLanguage::reset();

        // The site's date and time formats
        Functions\when('get_option')->alias(function (string $key) {
            return match ($key) {
                'date_format' => 'Y/m/d',
                'time_format' => 'H:i',
                default => '',
            };
        });
        Functions\when('is_admin')->justReturn(false);
        Functions\when('esc_html')->alias(static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
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

    public function test_filter_post_date_uses_the_site_date_format_when_format_empty(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('apply_filters')->returnArg(2);

        $filters = new DateFilters(false);

        $post = new \stdClass();
        $post->post_date_gmt = '2025-03-21 12:00:00';
        $post->post_date = '2025-03-21 15:30:00';

        $result = $filters->filterPostDate('March 21, 2025', '', $post);

        $this->assertSame('1404/01/01', $result);
    }

    public function test_the_site_formats_are_read_on_each_call(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('apply_filters')->returnArg(2);

        $filters = new DateFilters(false);

        $post = new \stdClass();
        $post->post_date_gmt = '2025-03-21 12:00:00';
        $post->post_date = '2025-03-21 15:30:00';

        // Polylang and WPML translate the formats once the language is known.
        Functions\when('get_option')->alias(static fn (string $key): string => $key === 'date_format' ? 'Y-n-j' : 'G:i');

        $this->assertSame('1404-1-1', $filters->filterPostDate('March 21, 2025', '', $post));
        $this->assertSame('15:30', $filters->filterPostTime('3:30 pm', '', $post));
    }

    public function test_pages_not_in_persian_keep_gregorian_dates(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        $this->inLanguage('en_US');

        $filters = new DateFilters(false);

        $post = new \stdClass();
        $post->post_date_gmt = '2025-03-21 12:00:00';
        $post->post_date = '2025-03-21 15:30:00';

        $this->assertSame('March 21, 2025', $filters->filterPostDate('March 21, 2025', 'F j, Y', $post));
        $this->assertSame('March 21, 2025', $filters->filterWpDate('March 21, 2025', 'F j, Y', 1742556000));

        $this->inLanguage('fa_IR');
        $this->assertSame('1404/01/01', $filters->filterPostDate('March 21, 2025', 'Y/m/d', $post));
    }

    public function test_the_admin_bar_clock_follows_the_language(): void
    {
        $filters = new DateFilters(false);

        $adminBar = \Mockery::mock();
        $adminBar->shouldReceive('add_node')->once();

        $this->inLanguage('en_US');
        $filters->addAdminBarClock($adminBar);

        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('apply_filters')->returnArg(2);
        $this->inLanguage('fa_IR', true);
        $filters->addAdminBarClock($adminBar);

        $this->addToAssertionCount(1);
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

    public function test_post_date_block_escapes_formatted_text(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));

        $filters = new DateFilters(false);
        $html = '<div class="wp-block-post-date"><time datetime="2025-03-21T15:30:00+03:30">March 21, 2025</time></div>';

        $result = $filters->filterPostDateBlock($html, ['attrs' => ['format' => 'Y \\<\\b\\> &']]);

        $this->assertStringContainsString('1404 &lt;b&gt; &amp;', $result);
        $this->assertStringNotContainsString('<b>', $result);
    }

    /**
     * Filters with "Show the Gregorian date too" on, on a front end request
     * in Tehran.
     */
    private function withGregorian(): DateFilters
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('wp_doing_ajax')->justReturn(false);
        Functions\when('wp_is_serving_rest_request')->justReturn(false);

        return new DateFilters(false, new GregorianCompanion(true));
    }

    private function post(): object
    {
        return (object) [
            'post_date'         => '2026-10-02 10:30:00',
            'post_date_gmt'     => '2026-10-02 07:00:00',
            'post_modified'     => '2026-10-03 09:00:00',
            'post_modified_gmt' => '2026-10-03 05:30:00',
        ];
    }

    public function test_post_dates_show_the_gregorian_date_too(): void
    {
        $filters = $this->withGregorian();

        $this->assertSame('1405/07/10 (' . self::ltr('2026-10-02') . ')', $filters->filterPostDate('October 2, 2026', '', $this->post()));
        $this->assertSame('1405/07/11 (' . self::ltr('2026-10-03') . ')', $filters->filterModifiedDate('October 3, 2026', 'Y/m/d', $this->post()));
        $this->assertSame('10 مهر 1405 (' . self::ltr('2026-10-02') . ')', $filters->filterPostTime('October 2, 2026', 'j F Y', $this->post()));
    }

    public function test_the_gregorian_date_is_read_from_the_gmt_date_when_the_local_one_is_missing(): void
    {
        $filters = $this->withGregorian();
        $post = (object) ['post_date' => '0000-00-00 00:00:00', 'post_date_gmt' => '2026-10-01 21:00:00'];

        $this->assertSame('1405/07/10 (' . self::ltr('2026-10-02') . ')', $filters->filterPostDate('', 'Y/m/d', $post));
    }

    public function test_comment_dates_show_the_gregorian_date_too(): void
    {
        $comment = (object) ['comment_date' => '2026-10-02 10:30:00', 'comment_date_gmt' => '2026-10-02 07:00:00'];

        $this->assertSame('1405/07/10 (' . self::ltr('2026-10-02') . ')', $this->withGregorian()->filterCommentDate('October 2, 2026', '', $comment));
    }

    public function test_the_date_shows_the_gregorian_date_inside_before_and_after(): void
    {
        $filters = $this->withGregorian();
        Functions\when('get_post')->justReturn($this->post());

        $this->assertSame('<h2>1405/07/10 (' . self::ltr('2026-10-02') . ')</h2>', $filters->filterTheDate('<h2>October 2, 2026</h2>', '', '<h2>', '</h2>'));
    }

    public function test_times_keep_one_date(): void
    {
        $filters = $this->withGregorian();
        $comment = (object) ['comment_date' => '2026-10-02 10:30:00', 'comment_date_gmt' => '2026-10-02 07:00:00'];

        $this->assertSame('10:30', $filters->filterPostTime('10:30', '', $this->post()));
        $this->assertSame('09:00', $filters->filterModifiedTime('09:00', '', $this->post()));
        $this->assertSame('10:30', $filters->filterCommentTime('10:30', '', false, true, $comment));
    }

    public function test_post_date_block_shows_the_gregorian_date_and_keeps_the_datetime_attribute(): void
    {
        $content = '<div class="wp-block-post-date"><time datetime="2026-10-02T10:30:00+03:30"><a href="/hello/">October 2, 2026</a></time></div>';

        $result = $this->withGregorian()->filterPostDateBlock($content, ['attrs' => ['format' => 'Y/m/d']]);

        $this->assertSame(
            '<div class="wp-block-post-date"><time datetime="2026-10-02T10:30:00+03:30"><a href="/hello/">1405/07/10 (' . self::ltr('2026-10-02') . ')</a></time></div>',
            $result
        );
    }

    public function test_latest_comments_block_shows_the_gregorian_date(): void
    {
        $content = '<ol><li><time datetime="2026-10-02T10:30:00+03:30" class="wp-block-latest-comments__comment-date">October 2, 2026</time></li></ol>';

        $this->assertSame(
            '<ol><li><time datetime="2026-10-02T10:30:00+03:30" class="wp-block-latest-comments__comment-date">1405/07/10 (' . self::ltr('2026-10-02') . ')</time></li></ol>',
            $this->withGregorian()->filterLatestCommentsBlock($content, [])
        );
    }

    public function test_get_post_time_and_wp_date_keep_one_date(): void
    {
        $filters = $this->withGregorian();
        $post = $this->post();
        Functions\when('get_post')->justReturn($post);
        Functions\when('get_post_datetime')->justReturn(new \DateTimeImmutable($post->post_date, new \DateTimeZone('Asia/Tehran')));

        $this->assertSame('1405/07/10', $filters->filterGetPostTime('2026/10/02', 'Y/m/d', false));
        $this->assertSame('1405/07/10', $filters->filterWpDate('2026/10/02', 'Y/m/d', 1790924400, new \DateTimeZone('Asia/Tehran')));
    }

    /**
     * A numeric Gregorian date as the page gets it: between invisible
     * left-to-right isolate marks.
     */
    private static function ltr(string $date): string
    {
        return "\u{2066}" . $date . "\u{2069}";
    }
}
