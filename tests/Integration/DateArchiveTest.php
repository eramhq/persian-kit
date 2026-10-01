<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

class DateArchiveTest extends WordPressIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        update_option('timezone_string', 'Asia/Tehran');
        $this->set_permalink_structure('/%year%/%monthnum%/%day%/%postname%/');
    }

    public function test_month_archive_title_names_both_jalali_months(): void
    {
        // March 2025 runs from 11 Esfand 1403 to 10 Farvardin 1404.
        self::factory()->post->create([
            'post_status' => 'publish',
            'post_date'   => '2025-03-05 10:00:00',
        ]);

        $this->go_to(get_month_link(2025, 3));
        $this->assertTrue(is_month());

        $expected = 'اسفند 1403 – فروردین 1404';
        $this->assertStringContainsString($expected, get_the_archive_title());
        $this->assertStringNotContainsString('<span>اسفند 1403</span>', get_the_archive_title());
        $this->assertStringContainsString($expected, wp_get_document_title());
    }

    public function test_month_archive_title_within_one_jalali_year(): void
    {
        // June 2025 runs from 11 Khordad to 9 Tir 1404.
        self::factory()->post->create([
            'post_status' => 'publish',
            'post_date'   => '2025-06-15 10:00:00',
        ]);

        $this->go_to(get_month_link(2025, 6));

        $this->assertStringContainsString('خرداد – تیر 1404', get_the_archive_title());
    }

    public function test_year_archive_title_names_both_jalali_years(): void
    {
        self::factory()->post->create([
            'post_status' => 'publish',
            'post_date'   => '2025-06-15 10:00:00',
        ]);

        $this->go_to(get_year_link(2025));
        $this->assertTrue(is_year());

        $this->assertStringContainsString('1403 – 1404', get_the_archive_title());
        $this->assertStringContainsString('1403 – 1404', wp_get_document_title());
    }

    public function test_jalali_month_archive_lists_only_that_month(): void
    {
        // 1 Farvardin 1404 is 21 March 2025; 1 Ordibehesht is 21 April.
        $esfand = $this->postOn('2025-03-20 23:30:00');
        $firstDay = $this->postOn('2025-03-21 00:10:00');
        $lastDay = $this->postOn('2025-04-20 23:30:00');
        $ordibehesht = $this->postOn('2025-04-21 08:00:00');

        $this->go_to(home_url('/1404/01/'));

        $this->assertTrue(is_month());
        $this->assertFalse(is_404());
        $this->assertEqualsCanonicalizing([$firstDay, $lastDay], wp_list_pluck($GLOBALS['wp_query']->posts, 'ID'));
        $this->assertNotContains($esfand, wp_list_pluck($GLOBALS['wp_query']->posts, 'ID'));
        $this->assertNotContains($ordibehesht, wp_list_pluck($GLOBALS['wp_query']->posts, 'ID'));

        $this->assertSame(1404, (int) get_query_var('year'));
        $this->assertSame(1, (int) get_query_var('monthnum'));
        $this->assertSame(1, (int) $GLOBALS['monthnum']);
        $this->assertStringContainsString('<span>فروردین 1404</span>', get_the_archive_title());
        $this->assertStringContainsString('فروردین 1404', wp_get_document_title());
    }

    public function test_jalali_month_archive_from_the_m_var(): void
    {
        $this->set_permalink_structure('');
        $inMonth = $this->postOn('2025-04-01 10:00:00');
        $this->postOn('2025-03-01 10:00:00');

        $this->go_to(home_url('/?m=140401'));

        $this->assertTrue(is_month());
        $this->assertSame([$inMonth], wp_list_pluck($GLOBALS['wp_query']->posts, 'ID'));
        $this->assertSame('140401', get_query_var('m'));
        $this->assertStringContainsString('فروردین 1404', get_the_archive_title());
    }

    public function test_jalali_year_archive(): void
    {
        $this->postOn('2025-03-20 10:00:00');
        $first = $this->postOn('2025-03-21 10:00:00');
        $last = $this->postOn('2026-03-20 10:00:00');
        $this->postOn('2026-03-21 10:00:00');

        $this->go_to(home_url('/1404/'));

        $this->assertTrue(is_year());
        $this->assertEqualsCanonicalizing([$first, $last], wp_list_pluck($GLOBALS['wp_query']->posts, 'ID'));
        $this->assertStringContainsString('<span>1404</span>', get_the_archive_title());
    }

    public function test_jalali_day_archive_for_31_shahrivar(): void
    {
        update_option('date_format', 'j F Y');
        // 31 Shahrivar 1404 is 22 September 2025; June has no 31st.
        $onDay = $this->postOn('2025-09-22 10:00:00');
        $this->postOn('2025-09-23 10:00:00');

        $this->go_to(home_url('/1404/06/31/'));

        $this->assertFalse(is_404());
        $this->assertTrue(is_day());
        $this->assertSame([$onDay], wp_list_pluck($GLOBALS['wp_query']->posts, 'ID'));
        $this->assertStringContainsString('31 شهریور 1404', get_the_archive_title());
        $this->assertTrue(wp_checkdate(6, 31, 1404, '1404-06-31'));
    }

    public function test_an_invalid_jalali_day_is_a_404(): void
    {
        $this->postOn('2025-10-22 10:00:00');

        // Mehr has 30 days.
        $this->go_to(home_url('/1404/07/31/'));

        $this->assertTrue(is_404());
        $this->assertFalse(wp_checkdate(7, 31, 1404, '1404-07-31'));
    }

    public function test_date_links_from_the_displayed_jalali_date_lead_to_the_jalali_archive(): void
    {
        $postId = $this->postOn('2025-03-05 10:00:00');
        $this->go_to(get_permalink($postId));
        the_post();

        // A theme building the link from the (Jalali) displayed date.
        $this->assertSame('1403', get_the_time('Y'));
        $this->assertSame(home_url('/1403/12/'), get_month_link(get_the_time('Y'), get_the_time('m')));
        $this->assertSame(home_url('/1403/'), get_year_link(get_the_time('Y')));
        $this->assertSame(home_url('/1403/12/15/'), get_day_link(get_the_time('Y'), get_the_time('m'), get_the_time('d')));
    }

    public function test_date_links_with_persian_digits_are_ascii(): void
    {
        $this->assertSame(home_url('/1405/07/'), get_month_link('۱۴۰۵', '۰۷'));
        $this->assertSame(home_url('/1405/'), get_year_link('۱۴۰۵'));
        $this->assertSame(home_url('/1405/07/09/'), get_day_link('۱۴۰۵', '۰۷', '۰۹'));
        $this->assertSame(home_url('/2025/03/'), get_month_link('۲۰۲۵', '۰۳'));
    }

    public function test_gregorian_archive_still_lists_the_gregorian_month(): void
    {
        $march = [$this->postOn('2025-03-05 10:00:00'), $this->postOn('2025-03-25 10:00:00')];
        $this->postOn('2025-04-05 10:00:00');

        $this->go_to(get_month_link(2025, 3));

        $this->assertEqualsCanonicalizing($march, wp_list_pluck($GLOBALS['wp_query']->posts, 'ID'));
        $this->assertSame(2025, (int) get_query_var('year'));
        $this->assertSame('', get_query_var(\PersianKit\Modules\DateConversion\JalaliDateArchive::QUERY_VAR));
    }

    public function test_gregorian_links_are_unchanged(): void
    {
        $this->assertSame(home_url('/2025/03/'), get_month_link(2025, 3));
        $this->assertSame(home_url('/2025/'), get_year_link(2025));
    }

    private function postOn(string $date): int
    {
        return self::factory()->post->create([
            'post_status' => 'publish',
            'post_date'   => $date,
        ]);
    }
}
