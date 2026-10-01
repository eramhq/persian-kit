<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Modules\DateConversion\JalaliPeriod;
use PersianKit\Tests\Integration\Support\BootsDateConversion;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

class JalaliCalendarTest extends WordPressIntegrationTestCase
{
    use BootsDateConversion;

    protected function setUp(): void
    {
        parent::setUp();

        update_option('timezone_string', 'Asia/Tehran');
        update_option('start_of_week', 6);
        $this->set_permalink_structure('/%year%/%monthnum%/%day%/%postname%/');

        // 31 Shahrivar, 1 and 9 Mehr, and 4 Azar 1404. Aban has no posts.
        foreach (['2025-09-22 10:00:00', '2025-09-23 10:00:00', '2025-10-01 10:00:00', '2025-11-25 10:00:00'] as $date) {
            self::factory()->post->create(['post_status' => 'publish', 'post_date' => $date]);
        }
    }

    public function test_jalali_month_archive_shows_that_month(): void
    {
        $this->go_to(home_url('/1404/07/'));

        $calendar = $this->calendar();

        $this->assertStringContainsString('<table id="wp-calendar" class="wp-calendar-table">', $calendar);
        $this->assertStringContainsString('<caption>مهر 1404</caption>', $calendar);
        $this->assertSame(30, preg_match_all('#<td(?: id="today")?>(?:<a [^>]+>)?\d+(?:</a>)?</td>#', $calendar));
    }

    public function test_first_row_is_padded_for_a_saturday_start(): void
    {
        $this->go_to(home_url('/1404/07/'));

        $calendar = $this->calendar();

        // Saturday first; 1 Mehr 1404 is a Tuesday, the fourth column.
        $this->assertMatchesRegularExpression('#<thead>\s*<tr>\s*<th scope="col" aria-label="Saturday">S</th>#', $calendar);
        $this->assertMatchesRegularExpression('#<tbody>\s*<tr>\s*<td colspan="3" class="pad">&nbsp;</td><td>(<a [^>]+>)?1(</a>)?</td>#', $calendar);
        // 30 Mehr is a Wednesday: two empty columns after it.
        $this->assertMatchesRegularExpression('#>30</td>\s*<td class="pad" colspan="2">&nbsp;</td>\s*</tr>#', $calendar);
    }

    public function test_days_with_posts_link_to_jalali_day_archives(): void
    {
        update_option('date_format', 'j F Y');
        $this->go_to(home_url('/1404/07/'));

        $calendar = $this->calendar();

        $this->assertStringContainsString('<a href="' . home_url('/1404/07/09/') . '" aria-label="Posts published on 9 مهر 1404">9</a>', $calendar);
        $this->assertStringContainsString('<a href="' . home_url('/1404/07/01/') . '"', $calendar);
        $this->assertSame(2, substr_count($calendar, '<a href="' . home_url('/1404/07/')));
    }

    public function test_previous_and_next_link_to_the_nearest_jalali_months_with_posts(): void
    {
        $this->go_to(home_url('/1404/07/'));

        $calendar = $this->calendar();

        $this->assertStringContainsString('<nav aria-label="Previous and next months" class="wp-calendar-nav">', $calendar);
        $this->assertStringContainsString('<span class="wp-calendar-nav-prev"><a href="' . home_url('/1404/06/') . '">&laquo; شهریور</a></span>', $calendar);
        $this->assertStringContainsString('<span class="wp-calendar-nav-next"><a href="' . home_url('/1404/09/') . '">آذر &raquo;</a></span>', $calendar);
    }

    public function test_gregorian_month_archive_shows_the_jalali_month_it_starts_in(): void
    {
        // 1 October 2025 is 9 Mehr 1404.
        $this->go_to(get_month_link(2025, 10));

        $this->assertStringContainsString('<caption>مهر 1404</caption>', $this->calendar());
    }

    public function test_today_is_marked_in_the_current_month(): void
    {
        $this->go_to(home_url('/'));
        $today = JalaliPeriod::fromGregorian(current_datetime());

        $calendar = $this->calendar();

        $this->assertMatchesRegularExpression('#<td id="today">(<a [^>]+>)?' . $today['jd'] . '(</a>)?</td>#', $calendar);
        $this->assertStringContainsString(JalaliPeriod::daysInMonth($today['jy'], $today['jm']) . '</td>', $calendar);
    }

    public function test_calendar_block_renders_the_jalali_month(): void
    {
        $block = do_blocks('<!-- wp:calendar {"month":10,"year":2025} /-->');

        $this->assertStringContainsString('wp-block-calendar', $block);
        $this->assertStringContainsString('<caption>مهر 1404</caption>', $block);
        $this->assertStringContainsString(home_url('/1404/07/09/'), $block);
    }

    public function test_setting_off_keeps_the_gregorian_calendar(): void
    {
        $this->bootDateConversionWith(['jalali_archives' => false]);
        $this->go_to(get_month_link(2025, 10));

        $calendar = $this->calendar();

        $this->assertStringContainsString('<caption>October 2025</caption>', $calendar);
        $this->assertStringNotContainsString('مهر', $calendar);
    }

    public function test_setting_off_shows_the_gregorian_month_of_a_jalali_archive(): void
    {
        $this->bootDateConversionWith(['jalali_archives' => false]);
        // Mehr 1404 runs from 23 September to 22 October 2025.
        $this->go_to(home_url('/1404/07/'));

        $calendar = $this->calendar();

        $this->assertStringContainsString('<caption>October 2025</caption>', $calendar);
        $this->assertStringContainsString(home_url('/2025/10/01/'), $calendar);
        $this->assertSame(1404, (int) $GLOBALS['year']);
        $this->assertSame(7, (int) $GLOBALS['monthnum']);
    }

    public function test_setting_off_with_the_m_var(): void
    {
        $this->bootDateConversionWith(['jalali_archives' => false]);
        $this->set_permalink_structure('');
        $this->go_to(home_url('/?m=140407'));

        $this->assertStringContainsString('<caption>October 2025</caption>', $this->calendar());
    }

    public function test_classic_calendar_widget_shows_the_jalali_month(): void
    {
        $this->go_to(home_url('/1404/07/'));

        ob_start();
        the_widget('WP_Widget_Calendar', ['title' => '']);
        $widget = (string) ob_get_clean();

        $this->assertStringContainsString('<caption>مهر 1404</caption>', $widget);
        $this->assertStringContainsString(home_url('/1404/07/09/'), $widget);
    }

    private function calendar(): string
    {
        return (string) get_calendar(['display' => false]);
    }
}
