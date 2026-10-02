<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Bootstrap;
use PersianKit\Modules\DateConversion\JalaliPermalinks;
use PersianKit\Tests\Integration\Support\BootsDateConversion;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

class JalaliPermalinksTest extends WordPressIntegrationTestCase
{
    use BootsDateConversion;

    protected function setUp(): void
    {
        parent::setUp();

        update_option('timezone_string', 'Asia/Tehran');
        $this->set_permalink_structure('/%year%/%monthnum%/%day%/%postname%/');
    }

    public function test_post_links_use_the_jalali_date(): void
    {
        $this->bootDateConversionWith(['jalali_permalinks' => true]);

        // 9 Mehr 1405.
        $this->assertSame(home_url('/1405/07/09/my-post/'), get_permalink($this->postOn('2026-10-01 10:00:00', 'my-post')));
        // The last day of 1404 and the first of 1405.
        $this->assertSame(home_url('/1404/12/29/last-day/'), get_permalink($this->postOn('2026-03-20 23:30:00', 'last-day')));
        $this->assertSame(home_url('/1405/01/01/first-day/'), get_permalink($this->postOn('2026-03-21 00:10:00', 'first-day')));
    }

    public function test_month_and_name_links_use_the_jalali_month(): void
    {
        $this->set_permalink_structure('/%year%/%monthnum%/%postname%/');
        $this->bootDateConversionWith(['jalali_permalinks' => true]);

        $this->assertSame(home_url('/1405/07/my-post/'), get_permalink($this->postOn('2026-10-01 10:00:00', 'my-post')));
    }

    public function test_structures_without_the_date_are_unchanged(): void
    {
        $this->set_permalink_structure('/%postname%/');
        $this->bootDateConversionWith(['jalali_permalinks' => true]);

        $this->assertSame(home_url('/my-post/'), get_permalink($this->postOn('2026-10-01 10:00:00', 'my-post')));
    }

    public function test_links_stay_gregorian_with_the_option_off(): void
    {
        $this->bootDateConversionWith(['jalali_permalinks' => false]);

        $this->assertSame(home_url('/2026/10/01/my-post/'), get_permalink($this->postOn('2026-10-01 10:00:00', 'my-post')));
    }

    public function test_the_sample_permalink_uses_the_jalali_date(): void
    {
        $this->bootDateConversionWith(['jalali_permalinks' => true]);
        $postId = $this->postOn('2026-10-01 10:00:00', 'my-post');

        require_once ABSPATH . 'wp-admin/includes/post.php';
        [$structure] = get_sample_permalink($postId);

        $this->assertSame(home_url('/1405/07/09/%postname%/'), $structure);
    }

    public function test_the_jalali_address_opens_the_post(): void
    {
        $this->bootDateConversionWith(['jalali_permalinks' => true]);
        $postId = $this->postOn('2026-10-01 10:00:00', 'my-post');
        // A post on the same day with another name.
        $this->postOn('2026-10-01 12:00:00', 'other-post');

        $this->go_to(get_permalink($postId));

        $this->assertTrue(is_single());
        $this->assertFalse(is_404());
        $this->assertSame($postId, get_queried_object_id());
    }

    public function test_31_shahrivar_opens_the_post(): void
    {
        $this->bootDateConversionWith(['jalali_permalinks' => true]);
        $postId = $this->postOn('2026-09-22 10:00:00', 'my-post');

        $this->assertSame(home_url('/1405/06/31/my-post/'), get_permalink($postId));
        $this->go_to(get_permalink($postId));

        $this->assertTrue(is_single());
        $this->assertSame($postId, get_queried_object_id());
    }

    public function test_feed_and_pages_of_the_post_work(): void
    {
        $this->bootDateConversionWith(['jalali_permalinks' => true]);
        $postId = $this->postOn('2026-10-01 10:00:00', 'my-post');

        $this->go_to(home_url('/1405/07/09/my-post/feed/'));
        $this->assertTrue(is_feed());
        $this->assertTrue(is_single());
        $this->assertSame($postId, get_queried_object_id());

        $this->go_to(home_url('/1405/07/09/my-post/2/'));
        $this->assertTrue(is_single());
        $this->assertSame(2, (int) get_query_var('page'));
        $this->assertSame($postId, get_queried_object_id());
    }

    public function test_a_gregorian_address_redirects_to_the_jalali_one(): void
    {
        $this->bootDateConversionWith(['jalali_permalinks' => true]);
        $this->postOn('2026-10-01 10:00:00', 'my-post');

        $this->go_to(home_url('/2026/10/01/my-post/2/'));

        $this->assertSame(
            home_url('/1405/07/09/my-post/2/?utm_source=x'),
            $this->permalinks()->redirectUrl('/2026/10/01/my-post/2/?utm_source=x')
        );
        $this->assertSame(home_url('/1405/07/09/my-post/'), $this->permalinks()->redirectUrl('/2026/10/01/my-post'));
    }

    public function test_a_jalali_address_redirects_to_the_gregorian_one_with_the_option_off(): void
    {
        $this->bootDateConversionWith(['jalali_permalinks' => false]);
        $this->postOn('2026-10-01 10:00:00', 'my-post');

        $this->go_to(home_url('/1405/07/09/my-post/'));

        $this->assertTrue(is_single());
        $this->assertSame(home_url('/2026/10/01/my-post/'), $this->permalinks()->redirectUrl('/1405/07/09/my-post/'));
    }

    public function test_the_current_address_does_not_redirect(): void
    {
        $this->bootDateConversionWith(['jalali_permalinks' => true]);
        $this->postOn('2026-10-01 10:00:00', 'my-post');

        $this->go_to(home_url('/1405/07/09/my-post/'));

        $this->assertNull($this->permalinks()->redirectUrl('/1405/07/09/my-post/'));
    }

    public function test_a_percent_encoded_persian_slug_redirects(): void
    {
        $this->bootDateConversionWith(['jalali_permalinks' => true]);
        $postId = $this->postOn('2026-10-01 10:00:00', 'سلام-دنیا');
        $slug = get_post($postId)->post_name;
        // As a browser sends it.
        $requested = '/2026/10/01/' . strtoupper(rawurlencode(rawurldecode($slug))) . '/feed/';

        $this->go_to(home_url($requested));

        $this->assertSame(get_permalink($postId) . 'feed/', $this->permalinks()->redirectUrl($requested));
        $this->assertStringStartsWith(home_url('/1405/07/09/'), get_permalink($postId));
    }

    public function test_an_attachment_under_the_post_redirects(): void
    {
        $this->bootDateConversionWith(['jalali_permalinks' => true]);
        $postId = $this->postOn('2026-10-01 10:00:00', 'my-post');
        self::factory()->attachment->create([
            'post_parent' => $postId,
            'post_name'   => 'photo',
            'post_status' => 'inherit',
        ]);
        // Rank Math sends attachment pages to their post, and exits.
        add_filter('rank_math/frontend/attachment/redirect_url', '__return_empty_string');

        $this->go_to(home_url('/2026/10/01/my-post/photo/'));

        $this->assertTrue(is_attachment());
        $this->assertSame(home_url('/1405/07/09/my-post/photo/'), $this->permalinks()->redirectUrl('/2026/10/01/my-post/photo/'));
    }

    public function test_no_redirect_for_previews_pages_or_structures_without_the_date(): void
    {
        $this->bootDateConversionWith(['jalali_permalinks' => true]);
        $postId = $this->postOn('2026-10-01 10:00:00', 'my-post');

        $this->go_to(home_url('/2026/10/01/my-post/?preview=true'));
        $this->assertTrue(is_preview());
        $this->assertNull($this->permalinks()->redirectUrl('/2026/10/01/my-post/?preview=true'));

        $pageId = self::factory()->post->create(['post_type' => 'page', 'post_name' => 'about', 'post_status' => 'publish']);
        $this->go_to(get_permalink($pageId));
        $this->assertTrue(is_page());
        $this->assertNull($this->permalinks()->redirectUrl('/about/'));

        $this->set_permalink_structure('/%postname%/');
        $this->go_to(get_permalink($postId));
        $this->assertNull($this->permalinks()->redirectUrl('/my-post/'));
    }

    public function test_an_old_slug_under_a_jalali_date_finds_the_post(): void
    {
        $this->bootDateConversionWith(['jalali_permalinks' => true]);
        $postId = $this->postOn('2026-10-01 10:00:00', 'new-name');
        add_post_meta($postId, '_wp_old_slug', 'old-name');

        $this->go_to(home_url('/1405/07/09/old-name/'));

        $this->assertTrue(is_404());
        $this->assertSame($postId, apply_filters('old_slug_redirect_post_id', 0));
    }

    public function test_an_old_date_under_a_jalali_date_finds_the_post(): void
    {
        $this->bootDateConversionWith(['jalali_permalinks' => true]);
        $postId = $this->postOn('2026-10-01 10:00:00', 'my-post');
        // Moved from 8 Mehr.
        add_post_meta($postId, '_wp_old_date', '2026-09-30');

        $this->go_to(home_url('/1405/07/08/my-post/'));

        $this->assertTrue(is_404());
        $this->assertSame($postId, apply_filters('old_slug_redirect_post_id', 0));
    }

    public function test_an_unknown_slug_under_a_jalali_date_finds_nothing(): void
    {
        $this->bootDateConversionWith(['jalali_permalinks' => true]);
        $postId = $this->postOn('2026-10-01 10:00:00', 'new-name');
        add_post_meta($postId, '_wp_old_slug', 'old-name');

        // The old slug, on another day.
        $this->go_to(home_url('/1405/07/10/old-name/'));

        $this->assertSame(0, apply_filters('old_slug_redirect_post_id', 0));
    }

    public function test_a_partial_slug_under_a_jalali_date_guesses_the_post(): void
    {
        $this->bootDateConversionWith(['jalali_permalinks' => true]);
        $postId = $this->postOn('2026-10-01 10:00:00', 'my-post');

        $this->go_to(home_url('/1405/07/09/my-po/'));

        $this->assertTrue(is_404());
        $this->assertSame(get_permalink($postId), redirect_guess_404_permalink());
        $this->assertSame(home_url('/1405/07/09/my-post/'), get_permalink($postId));
    }

    public function test_a_partial_slug_under_a_jalali_month_guesses_the_post(): void
    {
        $this->set_permalink_structure('/%year%/%monthnum%/%postname%/');
        $this->bootDateConversionWith(['jalali_permalinks' => true]);
        $this->postOn('2026-10-01 10:00:00', 'my-post');

        $this->go_to(home_url('/1405/07/my-po/'));

        $this->assertSame(home_url('/1405/07/my-post/'), redirect_guess_404_permalink());
    }

    public function test_the_guess_keeps_the_feed_and_the_page(): void
    {
        $this->bootDateConversionWith(['jalali_permalinks' => true]);
        $postId = $this->postOn('2026-10-01 10:00:00', 'my-post');

        $this->go_to(home_url('/1405/07/09/my-po/feed/'));
        $this->assertSame(get_post_comments_feed_link($postId, 'feed'), redirect_guess_404_permalink());
        $this->assertStringStartsWith(home_url('/1405/07/09/my-post/feed/'), redirect_guess_404_permalink());

        $this->go_to(home_url('/1405/07/09/my-po/2/'));
        $this->assertSame(home_url('/1405/07/09/my-post/2/'), redirect_guess_404_permalink());
    }

    public function test_an_old_jalali_address_guesses_the_gregorian_permalink_with_the_option_off(): void
    {
        $this->bootDateConversionWith(['jalali_permalinks' => false]);
        $this->postOn('2026-10-01 10:00:00', 'my-post');

        $this->go_to(home_url('/1405/07/09/my-po/'));

        $this->assertSame(home_url('/2026/10/01/my-post/'), redirect_guess_404_permalink());
    }

    public function test_no_guess_on_another_day_for_a_strict_guess_or_for_a_draft(): void
    {
        $this->bootDateConversionWith(['jalali_permalinks' => true]);
        $this->postOn('2026-10-01 10:00:00', 'my-post');
        self::factory()->post->create([
            'post_status' => 'draft',
            'post_date'   => '2026-10-01 11:00:00',
            'post_name'   => 'my-draft',
        ]);

        // The right slug, on 10 Mehr.
        $this->go_to(home_url('/1405/07/10/my-post/'));
        $this->assertFalse(redirect_guess_404_permalink());

        $this->go_to(home_url('/1405/07/09/my-d/'));
        $this->assertFalse(redirect_guess_404_permalink());

        add_filter('strict_redirect_guess_404_permalink', '__return_true');
        $this->go_to(home_url('/1405/07/09/my-po/'));
        $this->assertFalse(redirect_guess_404_permalink());
    }

    public function test_a_partial_slug_under_a_gregorian_date_guesses_the_jalali_permalink(): void
    {
        $this->bootDateConversionWith(['jalali_permalinks' => true]);
        $this->postOn('2026-10-01 10:00:00', 'my-post');

        $this->go_to(home_url('/2026/10/01/my-po/'));

        $this->assertSame(home_url('/1405/07/09/my-post/'), redirect_guess_404_permalink());
    }

    public function test_url_to_postid_resolves_the_jalali_address(): void
    {
        $this->bootDateConversionWith(['jalali_permalinks' => true]);
        $postId = $this->postOn('2026-10-01 10:00:00', 'my-post');

        $this->assertSame($postId, url_to_postid(home_url('/1405/07/09/my-post/')));

        // In the admin too, as in admin-ajax.
        require_once ABSPATH . 'wp-admin/includes/screen.php';
        set_current_screen('edit-post');
        $this->assertTrue(is_admin());
        $this->assertSame($postId, url_to_postid(home_url('/1405/07/09/my-post/')));
    }

    private function permalinks(): JalaliPermalinks
    {
        return Bootstrap::get(JalaliPermalinks::class);
    }

    private function postOn(string $date, string $name): int
    {
        // Two pages, so /2/ is not a 404.
        return self::factory()->post->create([
            'post_status'  => 'publish',
            'post_date'    => $date,
            'post_name'    => $name,
            'post_content' => 'One<!--nextpage-->Two',
        ]);
    }
}
