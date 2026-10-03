<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Bootstrap;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\DateConversion\DateConversionModule;
use PersianKit\Modules\DateConversion\JalaliDateArchive;
use PersianKit\Modules\DateConversion\JalaliPermalinks;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Jalali addresses from Parsi Date, WP Jalali or Persian Kit keep working
 * while Jalali dates are off, with no data from those plugins.
 */
class LegacyJalaliUrlsTest extends WordPressIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        update_option('timezone_string', 'Asia/Tehran');
        $this->set_permalink_structure('/%year%/%monthnum%/%day%/%postname%/');

        // The plugin booted Jalali dates on at load; start from nothing.
        $this->unhookJalaliAddresses();
    }

    public function test_without_the_fallback_a_jalali_address_is_a_404(): void
    {
        $this->postOn('2024-08-02 10:00:00', 'my-post');

        $this->go_to(home_url('/1403/05/12/my-post/'));

        $this->assertTrue(is_404());
    }

    public function test_a_jalali_post_address_redirects_to_the_gregorian_link(): void
    {
        $this->bootDisabled();
        // 12 Mordad 1403.
        $postId = $this->postOn('2024-08-02 10:00:00', 'my-post');

        $this->go_to(home_url('/1403/05/12/my-post/'));

        $this->assertTrue(is_single());
        $this->assertSame($postId, get_queried_object_id());
        $this->assertSame(home_url('/2024/08/02/my-post/'), get_permalink($postId));
        $this->assertSame(home_url('/2024/08/02/my-post/'), $this->permalinks()->redirectUrl('/1403/05/12/my-post/'));
    }

    public function test_feeds_pages_embeds_and_attachments_keep_their_tail(): void
    {
        $this->bootDisabled();
        $postId = $this->postOn('2024-08-02 10:00:00', 'my-post');
        self::factory()->attachment->create([
            'post_parent' => $postId,
            'post_name'   => 'photo',
            'post_status' => 'inherit',
        ]);
        add_filter('rank_math/frontend/attachment/redirect_url', '__return_empty_string');

        $this->go_to(home_url('/1403/05/12/my-post/feed/'));
        $this->assertTrue(is_feed());
        $this->assertSame(home_url('/2024/08/02/my-post/feed/'), $this->permalinks()->redirectUrl('/1403/05/12/my-post/feed/'));

        $this->go_to(home_url('/1403/05/12/my-post/2/'));
        $this->assertSame(home_url('/2024/08/02/my-post/2/?a=1'), $this->permalinks()->redirectUrl('/1403/05/12/my-post/2/?a=1'));

        $this->go_to(home_url('/1403/05/12/my-post/embed/'));
        $this->assertTrue(is_embed());
        $this->assertSame(home_url('/2024/08/02/my-post/embed/'), $this->permalinks()->redirectUrl('/1403/05/12/my-post/embed/'));

        $this->go_to(home_url('/1403/05/12/my-post/photo/'));
        $this->assertTrue(is_attachment());
        $this->assertSame(home_url('/2024/08/02/my-post/photo/'), $this->permalinks()->redirectUrl('/1403/05/12/my-post/photo/'));
    }

    public function test_jalali_archives_list_their_posts(): void
    {
        $this->bootDisabled();
        $inMordad = $this->postOn('2024-08-02 10:00:00', 'in-mordad');
        // 1 Shahrivar 1403.
        $this->postOn('2024-08-22 10:00:00', 'in-shahrivar');

        $this->go_to(home_url('/1403/05/'));
        $this->assertTrue(is_month());
        $this->assertSame([$inMordad], wp_list_pluck($GLOBALS['wp_query']->posts, 'ID'));

        $this->go_to(home_url('/?m=140305'));
        $this->assertSame([$inMordad], wp_list_pluck($GLOBALS['wp_query']->posts, 'ID'));

        $this->go_to(home_url('/?m=14030512'));
        $this->assertTrue(is_day());
        $this->assertSame([$inMordad], wp_list_pluck($GLOBALS['wp_query']->posts, 'ID'));
    }

    public function test_paged_jalali_archives(): void
    {
        $this->bootDisabled();
        update_option('posts_per_page', 1);
        $older = $this->postOn('2024-08-01 10:00:00', 'older');
        $this->postOn('2024-08-02 10:00:00', 'newer');

        $this->go_to(home_url('/1403/05/page/2/'));

        $this->assertTrue(is_month());
        $this->assertSame(2, (int) get_query_var('paged'));
        $this->assertSame([$older], wp_list_pluck($GLOBALS['wp_query']->posts, 'ID'));
    }

    public function test_days_core_thinks_invalid_are_jalali_days(): void
    {
        $this->bootDisabled();
        // 31 Shahrivar and 31 Ordibehesht 1403.
        $shahrivar = $this->postOn('2024-09-21 10:00:00', 'shahrivar');
        $ordibehesht = $this->postOn('2024-05-20 10:00:00', 'ordibehesht');

        $this->go_to(home_url('/1403/06/31/'));
        $this->assertFalse(is_404());
        $this->assertSame([$shahrivar], wp_list_pluck($GLOBALS['wp_query']->posts, 'ID'));

        $this->go_to(home_url('/1403/02/31/'));
        $this->assertFalse(is_404());
        $this->assertSame([$ordibehesht], wp_list_pluck($GLOBALS['wp_query']->posts, 'ID'));

        $this->go_to(home_url('/1403/06/31/shahrivar/'));
        $this->assertSame(home_url('/2024/09/21/shahrivar/'), $this->permalinks()->redirectUrl('/1403/06/31/shahrivar/'));
    }

    public function test_other_structures_with_the_date(): void
    {
        $this->bootDisabled();
        $postId = $this->postOn('2024-08-02 10:00:00', 'my-post');

        $this->set_permalink_structure('/%year%/%monthnum%/%day%/%postname%.html');
        $this->go_to(home_url('/1403/05/12/my-post.html'));
        $this->assertSame($postId, get_queried_object_id());
        $this->assertSame(home_url('/2024/08/02/my-post.html'), $this->permalinks()->redirectUrl('/1403/05/12/my-post.html'));

        $this->set_permalink_structure('/%year%/%monthnum%/%day%/%post_id%/');
        $old = '/1403/05/12/' . $postId . '/';
        $this->go_to(home_url($old));
        $this->assertSame($postId, get_queried_object_id());
        $this->assertSame(home_url('/2024/08/02/' . $postId . '/'), $this->permalinks()->redirectUrl($old));
    }

    public function test_a_cut_off_address_guesses_the_gregorian_link(): void
    {
        $this->bootDisabled();
        $this->postOn('2024-08-02 10:00:00', 'my-post');

        $this->go_to(home_url('/1403/05/12/my-po/'));

        $this->assertSame(home_url('/2024/08/02/my-post/'), redirect_guess_404_permalink());
    }

    public function test_gregorian_links_and_archives_are_unchanged(): void
    {
        $this->bootDisabled();
        $postId = $this->postOn('2024-08-02 10:00:00', 'my-post');

        $this->go_to(get_permalink($postId));
        $this->assertTrue(is_single());
        $this->assertNull($this->permalinks()->redirectUrl('/2024/08/02/my-post/'));

        $this->go_to(home_url('/2024/08/'));
        $this->assertSame([$postId], wp_list_pluck($GLOBALS['wp_query']->posts, 'ID'));
    }

    private function bootDisabled(): void
    {
        update_option('persian_kit_settings', [
            DateConversionModule::key() => ['enabled' => false] + DateConversionModule::defaults(),
        ]);

        $settings = new SettingsManager();
        $settings->registerDefaults(DateConversionModule::key(), DateConversionModule::defaults());
        $module = new DateConversionModule($settings);

        $this->assertFalse($module->isEnabled());
        $module->bootDisabled(Bootstrap::container());
    }

    private function unhookJalaliAddresses(): void
    {
        $archive = Bootstrap::get(JalaliDateArchive::class);
        remove_filter('wp_checkdate', [$archive, 'filterCheckdate']);
        remove_action('pre_get_posts', [$archive, 'filterQuery']);
        remove_filter('posts_pre_query', [$archive, 'restoreDateVars']);

        $permalinks = $this->permalinks();
        remove_action('template_redirect', [$permalinks, 'redirectToCurrentCalendar'], 9);
        remove_filter('old_slug_redirect_post_id', [$permalinks, 'findByOldSlug']);
        remove_filter('pre_redirect_guess_404_permalink', [$permalinks, 'guess404Permalink']);
        remove_filter('pre_post_link', [$permalinks, 'filterStructure']);
    }

    private function permalinks(): JalaliPermalinks
    {
        return Bootstrap::get(JalaliPermalinks::class);
    }

    private function postOn(string $date, string $name): int
    {
        return self::factory()->post->create([
            'post_status'  => 'publish',
            'post_date'    => $date,
            'post_name'    => $name,
            'post_content' => 'One<!--nextpage-->Two',
        ]);
    }
}
