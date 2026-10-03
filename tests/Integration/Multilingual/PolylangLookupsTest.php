<?php

namespace PersianKit\Tests\Integration\Multilingual;

use PersianKit\Bootstrap;
use PersianKit\Modules\DateConversion\JalaliPermalinks;
use PersianKit\Modules\DateConversion\PostTypeMonthFilter;
use PersianKit\Modules\Utilities\PersianSlugFilter;
use PersianKit\Tests\Integration\Support\BootsDateConversion;
use PersianKit\Tests\Integration\Support\UsesPolylang;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * The calendar, the admin month filters and the redirect lookups run their
 * own queries; they follow the current language as Polylang's own do.
 *
 * @group polylang
 */
class PolylangLookupsTest extends WordPressIntegrationTestCase
{
    use BootsDateConversion;
    use UsesPolylang;

    public function set_up(): void
    {
        parent::set_up();
        $this->setUpPolylang();

        update_option('timezone_string', 'Asia/Tehran');
        $this->set_permalink_structure('/%year%/%monthnum%/%day%/%postname%/');
        $this->bootDateConversionWith(['jalali_permalinks' => true]);
    }

    public function tear_down(): void
    {
        unset($GLOBALS['year'], $GLOBALS['monthnum'], $GLOBALS['m']);
        unregister_post_type('book');
        parent::tear_down();
    }

    public function test_the_calendar_links_only_days_and_months_with_posts_in_the_pages_language(): void
    {
        $this->postIn('fa', ['post_date' => '2025-08-10 10:00:00']); // 19 Mordad 1404
        $this->postIn('en', ['post_date' => '2025-09-10 10:00:00']); // 19 Shahrivar
        $this->postIn('fa', ['post_date' => '2025-10-01 10:00:00']); // 9 Mehr
        $this->postIn('en', ['post_date' => '2025-10-05 10:00:00']); // 13 Mehr
        $this->postIn('en', ['post_date' => '2025-11-01 10:00:00']); // 10 Aban
        $this->postIn('fa', ['post_date' => '2025-11-25 10:00:00']); // 4 Azar

        $calendar = $this->calendarFor(1404, 7);

        $this->assertStringContainsString('<a href="' . home_url('/1404/07/09/') . '"', $calendar);
        $this->assertStringNotContainsString('/1404/07/13/', $calendar, 'English only');
        $this->assertStringContainsString('<span class="wp-calendar-nav-prev"><a href="' . home_url('/1404/05/') . '">', $calendar, 'Mordad, past the English-only Shahrivar');
        $this->assertStringContainsString('<span class="wp-calendar-nav-next"><a href="' . home_url('/1404/09/') . '">', $calendar, 'Azar, past the English-only Aban');
    }

    public function test_the_calendar_of_an_untranslated_post_type_lists_every_post(): void
    {
        register_post_type('book', ['public' => true, 'has_archive' => true]);
        $this->assertFalse(pll_is_translated_post_type('book'));

        self::factory()->post->create(['post_type' => 'book', 'post_status' => 'publish', 'post_date' => '2025-10-01 10:00:00']);
        $this->postIn('en', ['post_date' => '2025-10-05 10:00:00']);

        $this->assertStringContainsString('/1404/07/09/', $this->calendarFor(1404, 7, 'book'));
    }

    public function test_the_admin_month_filter_follows_the_language_filter(): void
    {
        $this->postIn('fa', ['post_date' => '2025-10-01 10:00:00']); // Mehr 1404
        $this->postIn('en', ['post_date' => '2025-11-01 10:00:00']); // Aban 1404
        $filter = new PostTypeMonthFilter();

        $this->assertSame(['140407'], array_column($filter->monthOptions('post'), 'value'));

        $this->useLanguage('en');
        $this->assertSame(['140408'], array_column($filter->monthOptions('post'), 'value'), 'each language has its own cache entry');

        // "All languages".
        PLL()->curlang = null;
        $this->assertSame(['140408', '140407'], array_column($filter->monthOptions('post'), 'value'));
    }

    public function test_an_old_slug_finds_the_post_in_the_current_language_first(): void
    {
        $english = $this->postIn('en', ['post_date' => '2026-10-01 10:00:00', 'post_name' => 'new-en']);
        $persian = $this->postIn('fa', ['post_date' => '2026-10-01 11:00:00', 'post_name' => 'new-fa']);
        add_post_meta($english, '_wp_old_slug', 'old-name');
        add_post_meta($persian, '_wp_old_slug', 'old-name');

        $this->requestJalaliPost('old-name');

        $this->assertSame($persian, $this->permalinks()->findByOldSlug(0));

        $this->useLanguage('en');
        $this->assertSame($english, $this->permalinks()->findByOldSlug(0));
    }

    public function test_an_old_slug_in_another_language_only_still_redirects(): void
    {
        $english = $this->postIn('en', ['post_date' => '2026-10-01 10:00:00', 'post_name' => 'new-en']);
        add_post_meta($english, '_wp_old_slug', 'old-name');

        $this->requestJalaliPost('old-name');

        $this->assertSame($english, $this->permalinks()->findByOldSlug(0));
    }

    public function test_a_cut_off_address_guesses_the_post_in_the_current_language_first(): void
    {
        $english = $this->postIn('en', ['post_date' => '2026-10-01 10:00:00', 'post_name' => 'my-post-en']);
        $persian = $this->postIn('fa', ['post_date' => '2026-10-01 11:00:00', 'post_name' => 'my-post-fa']);

        $this->requestJalaliPost('my-po');

        $this->assertSame(get_permalink($persian), $this->permalinks()->guess404Permalink(null));

        $this->useLanguage('en');
        $this->assertSame(get_permalink($english), $this->permalinks()->guess404Permalink(null));

        wp_delete_post($english, true);
        $this->assertSame(get_permalink($persian), $this->permalinks()->guess404Permalink(null), 'only a Persian match');
    }

    public function test_a_legacy_persian_slug_finds_the_post_in_the_current_language_first(): void
    {
        global $wpdb;

        $english = $this->postIn('en');
        $persian = $this->postIn('fa');
        // Polylang without its Pro slug sharing keeps slugs unique; old posts may not be.
        foreach ([$english, $persian] as $postId) {
            $wpdb->update($wpdb->posts, ['post_name' => 'سلام-دنیا'], ['ID' => $postId]);
        }
        clean_post_cache($english);
        clean_post_cache($persian);

        $slugs = new PersianSlugFilter();

        $this->assertSame($persian, (int) $slugs->findLegacyPost('سلام دنیا')?->ID);

        $this->useLanguage('en');
        $this->assertSame($english, (int) $slugs->findLegacyPost('سلام دنیا')?->ID);

        wp_delete_post($english, true);
        $this->assertSame($persian, (int) $slugs->findLegacyPost('سلام دنیا')?->ID, 'only a Persian match');
    }

    /**
     * The query vars of /1405/07/09/{name}/ that found nothing.
     */
    private function requestJalaliPost(string $name): void
    {
        $GLOBALS['wp_query'] = new \WP_Query();
        set_query_var('year', 1405);
        set_query_var('monthnum', 7);
        set_query_var('day', 9);
        set_query_var('name', $name);
    }

    private function calendarFor(int $jy, int $jm, string $postType = 'post'): string
    {
        $GLOBALS['year'] = $jy;
        $GLOBALS['monthnum'] = $jm;

        return (string) get_calendar(['display' => false, 'post_type' => $postType]);
    }

    private function permalinks(): JalaliPermalinks
    {
        return Bootstrap::get(JalaliPermalinks::class);
    }
}
