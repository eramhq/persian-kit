<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Persian slugs are stored with their letters, and their links are percent-encoded,
 * as core's own Persian slugs are. A raw UTF-8 link broke redirect_canonical():
 * PHP's parse_url() turns bytes it takes for control characters into "_" in
 * some locales (C.UTF-8 on macOS), so /?p=ID redirected to /بر_ا__/ and a 404.
 */
class PersianSlugLinksTest extends WordPressIntegrationTestCase
{
    private const SLUG = 'برنامه-آزمایشی-سال-1405';

    public function set_up(): void
    {
        parent::set_up();
        $this->set_permalink_structure('/%postname%/');
        // Category links read the taxonomy's rewrite rules, built when it was registered.
        create_initial_taxonomies();
    }

    public function tear_down(): void
    {
        $_GET = [];
        parent::tear_down();
    }

    public function test_a_persian_slug_is_stored_with_its_letters(): void
    {
        $postId = self::factory()->post->create(['post_title' => 'برنامه آزمایشی سال 1405']);

        $this->assertSame(self::SLUG, get_post($postId)->post_name);
    }

    public function test_a_persian_post_link_is_percent_encoded(): void
    {
        $postId = self::factory()->post->create(['post_title' => 'برنامه آزمایشی سال 1405']);
        $link = get_permalink($postId);

        $this->assertDoesNotMatchRegularExpression('/[\x80-\xFF]/', $link);
        $this->assertSame(home_url('/' . self::SLUG . '/'), rawurldecode($link));
    }

    public function test_page_term_and_attachment_links_are_percent_encoded(): void
    {
        $pageId = self::factory()->post->create(['post_type' => 'page', 'post_title' => 'درباره ما']);
        $termId = self::factory()->term->create(['taxonomy' => 'category', 'name' => 'کتاب کودک']);
        $attachmentId = self::factory()->attachment->create(['post_title' => 'تصویر جلد', 'post_parent' => $pageId]);

        foreach ([get_permalink($pageId), get_term_link($termId), get_attachment_link($attachmentId)] as $link) {
            $this->assertIsString($link);
            $this->assertDoesNotMatchRegularExpression('/[\x80-\xFF]/', $link, rawurldecode($link));
        }
        $this->assertSame(home_url('/درباره-ما/'), rawurldecode(get_permalink($pageId)));
        $this->assertSame(home_url('/category/کتاب-کودک/'), rawurldecode(get_term_link($termId)));
    }

    public function test_an_already_encoded_link_is_left_as_it_is(): void
    {
        // A post saved by core before Persian Kit was active keeps core's encoded slug.
        $encoded = sanitize_title_with_dashes('سلام دنیا', '', 'save');
        $postId = self::factory()->post->create(['post_title' => 'x']);
        global $wpdb;
        $wpdb->update($wpdb->posts, ['post_name' => $encoded], ['ID' => $postId]);
        clean_post_cache($postId);

        $this->assertSame(home_url('/' . $encoded . '/'), get_permalink($postId));
    }

    public function test_the_canonical_redirect_from_a_post_id_keeps_every_letter(): void
    {
        $postId = self::factory()->post->create(['post_title' => 'برنامه آزمایشی سال 1405']);

        $this->go_to('/?p=' . $postId);
        // redirect_canonical() reads the ID from $_GET, which go_to() leaves empty.
        $_GET = ['p' => (string) $postId];
        $redirect = redirect_canonical(home_url('/?p=' . $postId), false);

        $this->assertSame(get_permalink($postId), $redirect);
        $this->assertSame(home_url('/' . self::SLUG . '/'), rawurldecode($redirect));
    }

    public function test_the_canonical_redirect_from_a_category_id_keeps_every_letter(): void
    {
        $termId = self::factory()->term->create(['taxonomy' => 'category', 'name' => 'کتاب کودک']);
        self::factory()->post->create(['post_category' => [$termId]]);

        $this->go_to('/?cat=' . $termId);
        $_GET = ['cat' => (string) $termId];
        $redirect = redirect_canonical(home_url('/?cat=' . $termId), false);

        $this->assertSame(home_url('/category/کتاب-کودک/'), rawurldecode((string) $redirect));
    }

    public function test_the_encoded_post_link_opens_the_post_without_a_redirect(): void
    {
        $postId = self::factory()->post->create(['post_title' => 'برنامه آزمایشی سال 1405']);
        $link = get_permalink($postId);

        $this->go_to($link);

        $this->assertTrue(is_singular());
        $this->assertSame($postId, get_queried_object_id());
        $this->assertNull(redirect_canonical($link, false));
    }

    public function test_links_stay_encoded_after_the_persian_slugs_option_is_turned_off(): void
    {
        $postId = self::factory()->post->create(['post_title' => 'برنامه آزمایشی سال 1405']);
        $settings = get_option('persian_kit_settings');
        $settings['utilities']['persian_slugs'] = false;
        update_option('persian_kit_settings', $settings);

        $this->assertDoesNotMatchRegularExpression('/[\x80-\xFF]/', get_permalink($postId));
    }
}
