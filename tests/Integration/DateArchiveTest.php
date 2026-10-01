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

    public function test_month_link_with_jalali_parts_points_at_the_posts_gregorian_month(): void
    {
        $postId = self::factory()->post->create([
            'post_status' => 'publish',
            'post_date'   => '2025-03-05 10:00:00',
        ]);
        $this->go_to(get_permalink($postId));
        the_post();

        // A theme building the link from the (Jalali) displayed date.
        $this->assertSame('1403', get_the_time('Y'));
        $this->assertSame(home_url('/2025/03/'), get_month_link(get_the_time('Y'), get_the_time('m')));
        $this->assertSame(home_url('/2025/'), get_year_link(get_the_time('Y')));
        $this->assertSame(home_url('/2025/03/05/'), get_day_link(get_the_time('Y'), get_the_time('m'), get_the_time('d')));
    }

    public function test_month_link_with_jalali_parts_outside_the_loop_uses_the_first_day(): void
    {
        // 1 Farvardin 1404 is 21 March 2025.
        $this->assertSame(home_url('/2025/03/'), get_month_link(1404, 1));
        $this->assertSame(home_url('/2025/03/21/'), get_day_link(1404, 1, 1));
        $this->assertSame(home_url('/2025/03/'), get_month_link('۱۴۰۴', '۰۱'));
    }

    public function test_gregorian_links_are_unchanged(): void
    {
        $this->assertSame(home_url('/2025/03/'), get_month_link(2025, 3));
        $this->assertSame(home_url('/2025/'), get_year_link(2025));
    }
}
