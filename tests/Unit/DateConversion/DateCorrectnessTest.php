<?php

namespace PersianKit\Tests\Unit\DateConversion;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\DateConversion\DateDisplayGuard;
use PersianKit\Modules\DateConversion\DateFilters;
use PersianKit\Modules\DateConversion\JalaliFormatter;
use PersianKit\Modules\DateConversion\MediaAttachmentDateFormatter;
use PersianKit\Modules\DateConversion\RestApiExtension;
use PersianKit\Modules\WooCommerce\WooDateDisplayFilter;
use PersianKit\Modules\WooCommerce\WooDateHelper;
use PHPUnit\Framework\TestCase;

/**
 * Timezone, feed and missing-date behaviour of the date filters, with the site
 * in Asia/Tehran (UTC+03:30). 2025-03-20 is 1403/12/30, 2025-03-21 is 1404/01/01.
 */
class DateCorrectnessTest extends TestCase
{
    private const ZERO_DATE = '0000-00-00 00:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('get_option')->alias(static fn (string $key) => match ($key) {
            'date_format' => 'Y/m/d',
            'time_format' => 'H:i',
            default       => '',
        });
        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_feed')->justReturn(false);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wp_query']);
        Monkey\tearDown();
        parent::tearDown();
    }

    // ── Feeds and machine formats ─────────────────────────────────────

    public function test_display_filters_leave_feed_output_gregorian(): void
    {
        $GLOBALS['wp_query'] = new \WP_Query();
        Functions\when('is_feed')->justReturn(true);

        $filters = new DateFilters(true);
        $post = $this->post('2025-03-21 15:30:00', '2025-03-21 12:00:00');

        $this->assertSame('March 21, 2025', $filters->filterPostDate('March 21, 2025', 'F j, Y', $post));
        $this->assertSame('March 21, 2025', $filters->filterWpDate('March 21, 2025', 'F j, Y', 1742558400));
    }

    /**
     * @dataProvider feedFormats
     */
    public function test_feed_timestamp_formats_are_never_converted(string $format, string $value): void
    {
        Functions\when('get_post')->justReturn($this->post('2025-03-21 15:30:00', '2025-03-21 12:00:00'));
        Functions\expect('get_post_datetime')->never();

        $filters = new DateFilters(true);

        $this->assertSame($value, $filters->filterGetPostTime($value, $format, true));
        $this->assertSame($value, $filters->filterWpDate($value, $format, 1742558400));
    }

    public static function feedFormats(): array
    {
        return [
            'rss2 pubDate source' => ['Y-m-d H:i:s', '2025-03-21 12:00:00'],
            'atom published'      => ['Y-m-d\TH:i:s\Z', '2025-03-21T12:00:00Z'],
            'gmt timestamp'       => ['G', '1742558400'],
            'unix timestamp'      => ['U', '1742571000'],
        ];
    }

    public function test_guard_lists_wordpress_feed_formats(): void
    {
        $this->assertTrue(DateDisplayGuard::isMachineFormat('Y-m-d H:i:s'));
        $this->assertTrue(DateDisplayGuard::isMachineFormat('Y-m-d\TH:i:s\Z'));
        $this->assertTrue(DateDisplayGuard::isMachineFormat(DATE_RSS));
        $this->assertFalse(DateDisplayGuard::isMachineFormat('F j, Y'));
    }

    // ── Local vs GMT ──────────────────────────────────────────────────

    public function test_comment_late_in_the_evening_keeps_its_local_day(): void
    {
        $comment = (object) [
            'comment_date'     => '2025-03-20 23:00:00',
            'comment_date_gmt' => '2025-03-20 19:30:00',
        ];

        $filters = new DateFilters(false);

        $this->assertSame('1403/12/30', $filters->filterCommentDate('2025/03/20', 'Y/m/d', $comment));
        $this->assertSame('23:00', $filters->filterCommentTime('23:00', 'H:i', false, true, $comment));
    }

    public function test_comment_gmt_time_is_shown_in_utc(): void
    {
        $comment = (object) [
            'comment_date'     => '2025-03-20 23:00:00',
            'comment_date_gmt' => '2025-03-20 19:30:00',
        ];

        $filters = new DateFilters(false);

        $this->assertSame('1403/12/30 19:30', $filters->filterCommentTime('x', 'Y/m/d H:i', true, true, $comment));
    }

    public function test_post_date_is_read_in_site_timezone(): void
    {
        $filters = new DateFilters(false);
        $post = $this->post('2025-03-20 23:00:00', '2025-03-20 19:30:00');

        $this->assertSame('1403/12/30 23:00', $filters->filterPostDate('x', 'Y/m/d H:i', $post));
        $this->assertSame('23:00', $filters->filterPostTime('x', 'H:i', $post));
    }

    public function test_draft_with_zero_gmt_date_uses_local_date(): void
    {
        $filters = new DateFilters(false);
        $draft = $this->post('2025-03-21 15:30:00', self::ZERO_DATE);
        $draft->post_modified = '2025-03-21 15:30:00';
        $draft->post_modified_gmt = self::ZERO_DATE;

        $this->assertSame('1404/01/01', $filters->filterPostDate('x', 'Y/m/d', $draft));
        $this->assertSame('1404/01/01', $filters->filterModifiedDate('x', 'Y/m/d', $draft));
    }

    public function test_unparseable_dates_return_core_output_instead_of_1970(): void
    {
        $filters = new DateFilters(false);
        $broken = $this->post(self::ZERO_DATE, self::ZERO_DATE);

        $this->assertSame('core output', $filters->filterPostDate('core output', 'Y/m/d', $broken));
        $this->assertSame('core output', $filters->filterPostDate('core output', 'Y/m/d', $this->post('not a date', '')));
    }

    public function test_formatter_treats_zero_and_empty_mysql_dates_as_missing(): void
    {
        $this->assertNull(JalaliFormatter::fromGmtMysql('Y', self::ZERO_DATE));
        $this->assertNull(JalaliFormatter::fromGmtMysql('Y', ''));
        $this->assertNull(JalaliFormatter::fromLocalMysql('Y', null));
        $this->assertSame('1404/01/01 03:30', JalaliFormatter::fromGmtMysql('Y/m/d H:i', '2025-03-21 00:00:00'));
        $this->assertSame('1404/01/01 00:00', JalaliFormatter::fromLocalMysql('Y/m/d H:i', '2025-03-21 00:00:00'));
    }

    // ── the_date ──────────────────────────────────────────────────────

    public function test_the_date_stays_empty_for_repeat_posts_of_a_day(): void
    {
        Functions\expect('get_post')->never();

        $this->assertSame('', (new DateFilters(false))->filterTheDate('', 'Y/m/d', '<h2>', '</h2>'));
    }

    public function test_the_date_wraps_jalali_date(): void
    {
        Functions\when('get_post')->justReturn($this->post('2025-03-20 23:00:00', '2025-03-20 19:30:00'));

        $this->assertSame(
            '<h2>1403/12/30</h2>',
            (new DateFilters(false))->filterTheDate('<h2>2025/03/20</h2>', 'Y/m/d', '<h2>', '</h2>')
        );
    }

    // ── get_post_time ─────────────────────────────────────────────────

    public function test_get_post_time_converts_when_time_belongs_to_global_post(): void
    {
        $post = $this->post('2025-03-20 23:00:00', '2025-03-20 19:30:00');
        Functions\when('get_post')->justReturn($post);
        Functions\when('get_post_datetime')->justReturn(new \DateTimeImmutable('2025-03-20 23:00:00', new \DateTimeZone('Asia/Tehran')));

        $filters = new DateFilters(false);

        $this->assertSame('1403/12/30 23:00', $filters->filterGetPostTime('2025/03/20 23:00', 'Y/m/d H:i', false));
    }

    public function test_get_post_time_honours_gmt(): void
    {
        Functions\when('get_post')->justReturn($this->post('2025-03-20 23:00:00', '2025-03-20 19:30:00'));
        Functions\when('get_post_datetime')->justReturn(new \DateTimeImmutable('2025-03-20 23:00:00', new \DateTimeZone('Asia/Tehran')));

        $filters = new DateFilters(false);

        $this->assertSame('1403/12/30 19:30', $filters->filterGetPostTime('2025/03/20 19:30', 'Y/m/d H:i', true));
    }

    public function test_get_post_time_leaves_other_posts_alone(): void
    {
        Functions\when('get_post')->justReturn($this->post('2025-03-20 23:00:00', '2025-03-20 19:30:00'));
        Functions\when('get_post_datetime')->justReturn(new \DateTimeImmutable('2025-03-20 23:00:00', new \DateTimeZone('Asia/Tehran')));
        Functions\when('wp_date')->justReturn('something else');

        $filters = new DateFilters(false);

        $this->assertSame('2024/01/01 10:00', $filters->filterGetPostTime('2024/01/01 10:00', 'Y/m/d H:i', false));
    }

    // ── date_i18n offset timestamps ───────────────────────────────────

    public function test_dashboard_date_i18n_does_not_apply_offset_twice(): void
    {
        $filters = new DateFilters(false);

        $this->assertSame('1403/12/30 23:00', $filters->filterDashboardDateI18n('x', 'Y/m/d H:i', $this->offsetTimestamp('2025-03-20 23:00:00')));
    }

    public function test_woo_date_i18n_does_not_apply_offset_twice(): void
    {
        $filter = new WooDateDisplayFilter();
        $filter->enterTemplate();

        $this->assertSame('1403/12/30 23:00', $filter->filterDateI18n('x', 'Y/m/d H:i', $this->offsetTimestamp('2025-03-20 23:00:00')));
    }

    // ── REST, media, Woo helpers ──────────────────────────────────────

    public function test_rest_draft_without_gmt_date_uses_local_date(): void
    {
        $rest = new RestApiExtension();

        $this->assertSame('1403-12-30T23:00:00', $rest->getDateJalali(['date_gmt' => null, 'date' => '2025-03-20T23:00:00']));
        $this->assertSame('1403-12-30T23:00:00', $rest->getDateJalali(['date_gmt' => '2025-03-20T19:30:00', 'date' => '2025-03-20T23:00:00']));
        $this->assertNull($rest->getDateJalali(['date_gmt' => null, 'date' => null]));
    }

    public function test_media_date_is_read_in_site_timezone(): void
    {
        Functions\when('get_option')->justReturn('F j, Y');

        $response = (new MediaAttachmentDateFormatter())->filterAttachmentData(
            ['dateFormatted' => 'March 20, 2025'],
            (object) ['post_date' => '2025-03-20 23:00:00']
        );

        $this->assertSame('اسفند 30, 1403', $response['dateFormatted']);
    }

    public function test_woo_month_range_rejects_years_daynum_cannot_represent(): void
    {
        $this->assertNull(WooDateHelper::jalaliMonthToGregorianRange('000001'));
        $this->assertSame(
            ['start' => '2025-03-21', 'end' => '2025-04-20'],
            WooDateHelper::jalaliMonthToGregorianRange('140401')
        );
    }

    private function post(string $local, string $gmt): object
    {
        return (object) ['post_date' => $local, 'post_date_gmt' => $gmt];
    }

    /**
     * What core passes to date_i18n: a UTC timestamp with the site offset added.
     */
    private function offsetTimestamp(string $local): int
    {
        return (new \DateTimeImmutable($local, new \DateTimeZone('UTC')))->getTimestamp();
    }
}
