<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Tests\Integration\Support\BootsDateConversion;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

class JalaliArchiveListTest extends WordPressIntegrationTestCase
{
    use BootsDateConversion;

    /** @var array<string, int> */
    private array $posts = [];

    protected function setUp(): void
    {
        parent::setUp();

        update_option('timezone_string', 'Asia/Tehran');
        $this->set_permalink_structure('/%year%/%monthnum%/%day%/%postname%/');

        // 29 Esfand 1403, 1 Farvardin 1404 and 5 Ordibehesht 1404.
        foreach (['esfand' => '2025-03-19 10:00:00', 'farvardin' => '2025-03-21 10:00:00', 'ordibehesht' => '2025-04-25 10:00:00'] as $name => $date) {
            $this->posts[$name] = self::factory()->post->create(['post_status' => 'publish', 'post_date' => $date]);
        }
    }

    public function test_monthly_list_names_jalali_months_with_counts(): void
    {
        $output = $this->archives(['show_post_count' => true]);

        $this->assertSame(
            [
                [home_url('/1404/02/'), 'اردیبهشت 1404', '&nbsp;(1)'],
                [home_url('/1404/01/'), 'فروردین 1404', '&nbsp;(1)'],
                [home_url('/1403/12/'), 'اسفند 1403', '&nbsp;(1)'],
            ],
            $this->entries($output)
        );
        $this->assertStringNotContainsString('/2025/', $output);
    }

    public function test_limit_and_order_apply_to_jalali_months(): void
    {
        $this->assertSame(['اردیبهشت 1404'], array_column($this->entries($this->archives(['limit' => 1])), 1));
        $this->assertSame(
            ['اسفند 1403', 'فروردین 1404', 'اردیبهشت 1404'],
            array_column($this->entries($this->archives(['order' => 'ASC'])), 1)
        );
    }

    public function test_option_format_for_dropdowns(): void
    {
        $output = $this->archives(['format' => 'option', 'show_post_count' => true]);

        $this->assertSame(3, substr_count($output, '<option '));
        $this->assertStringContainsString("<option value='" . home_url('/1404/01/') . "'> فروردین 1404 &nbsp;(1)</option>", $output);
    }

    public function test_the_current_jalali_month_is_selected(): void
    {
        $this->go_to(home_url('/1404/01/'));

        $output = $this->archives();

        $this->assertMatchesRegularExpression("#<a href='" . preg_quote(home_url('/1404/01/'), '#') . "' aria-current=\"page\">فروردین 1404</a>#", $output);
        $this->assertSame(1, substr_count($output, 'aria-current'));
    }

    public function test_yearly_and_daily_lists(): void
    {
        update_option('date_format', 'j F Y');

        $this->assertSame(
            [[home_url('/1404/'), '1404', '&nbsp;(2)'], [home_url('/1403/'), '1403', '&nbsp;(1)']],
            $this->entries($this->archives(['type' => 'yearly', 'show_post_count' => true]))
        );
        $this->assertSame(
            [[home_url('/1404/02/05/'), '5 اردیبهشت 1404', ''], [home_url('/1404/01/01/'), '1 فروردین 1404', ''], [home_url('/1403/12/29/'), '29 اسفند 1403', '']],
            $this->entries($this->archives(['type' => 'daily']))
        );
    }

    public function test_other_plugins_where_clauses_are_kept(): void
    {
        $excluded = $this->posts['farvardin'];
        add_filter('getarchives_where', static fn (string $where): string => $where . ' AND ID != ' . $excluded);

        $this->assertSame(['اردیبهشت 1404', 'اسفند 1403'], array_column($this->entries($this->archives()), 1));
    }

    public function test_weekly_and_post_lists_are_left_alone(): void
    {
        $this->assertStringContainsString('?m=2025', $this->archives(['type' => 'weekly']));
        $this->assertSame(3, substr_count($this->archives(['type' => 'postbypost']), '<li>'));
    }

    public function test_later_archive_links_are_not_swallowed(): void
    {
        $this->archives();

        $this->assertStringContainsString('/custom/', get_archives_link(home_url('/custom/'), 'Custom'));
    }

    public function test_archives_block_lists_jalali_months(): void
    {
        $list = do_blocks('<!-- wp:archives {"showPostCounts":true} /-->');
        $dropdown = do_blocks('<!-- wp:archives {"displayAsDropdown":true} /-->');

        $this->assertStringContainsString('اردیبهشت 1404', $list);
        $this->assertStringContainsString(home_url('/1403/12/'), $list);
        $this->assertStringNotContainsString('/2025/', $list);
        $this->assertSame(3, substr_count($dropdown, "<option value='" . home_url('/14')));
    }

    public function test_classic_archives_widget_lists_jalali_months(): void
    {
        ob_start();
        the_widget('WP_Widget_Archives', ['count' => 1]);
        $list = (string) ob_get_clean();

        ob_start();
        the_widget('WP_Widget_Archives', ['dropdown' => 1]);
        $dropdown = (string) ob_get_clean();

        $this->assertSame(['اردیبهشت 1404', 'فروردین 1404', 'اسفند 1403'], array_column($this->entries($list), 1));
        $this->assertStringNotContainsString('/2025/', $list);
        $this->assertSame(3, substr_count($dropdown, "<option value='" . home_url('/14')));
    }

    public function test_the_counts_are_kept_between_requests_until_a_post_changes(): void
    {
        $queries = 0;
        add_filter('query', static function (string $query) use (&$queries): string {
            $queries += str_contains($query, 'GROUP BY DATE(post_date)') ? 1 : 0;

            return $query;
        });

        $first = $this->archives(['show_post_count' => true]);
        // A new request without a persistent object cache.
        wp_cache_flush();
        $second = $this->archives(['show_post_count' => true]);

        $this->assertSame(1, $queries);
        $this->assertSame($first, $second);

        self::factory()->post->create(['post_status' => 'publish', 'post_date' => '2025-03-22 10:00:00']);
        wp_cache_flush();

        $this->assertContains([home_url('/1404/01/'), 'فروردین 1404', '&nbsp;(2)'], $this->entries($this->archives(['show_post_count' => true])));
        $this->assertSame(2, $queries);
    }

    public function test_setting_off_keeps_the_gregorian_list(): void
    {
        $this->bootDateConversionWith(['jalali_archives' => false]);

        $output = $this->archives();

        $this->assertStringContainsString(home_url('/2025/03/'), $output);
        $this->assertStringNotContainsString('فروردین', $output);
    }

    public function test_filter_off_keeps_the_gregorian_list(): void
    {
        add_filter('persian_kit_jalali_archives', '__return_false');
        $this->bootDateConversionWith([]);

        $this->assertStringContainsString(home_url('/2025/04/'), $this->archives());
    }

    /**
     * @param array<string, mixed> $args
     */
    private function archives(array $args = []): string
    {
        return (string) wp_get_archives(['echo' => false] + $args);
    }

    /**
     * [url, text, after] of each list entry.
     *
     * @return list<array{string, string, string}>
     */
    private function entries(string $output): array
    {
        preg_match_all("#<li><a href='([^']+)'[^>]*>([^<]+)</a>([^<]*)</li>#u", $output, $matches, PREG_SET_ORDER);

        return array_map(static fn (array $match): array => [$match[1], $match[2], $match[3]], $matches);
    }
}
