<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Dependencies\Eram\Abzar\Text\CharNormalizer;
use PersianKit\Modules\CharNormalization\CharNormalizationModule;
use PersianKit\Modules\CharNormalization\SaveNormalizer;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

class CharNormalizationIntegrationTest extends WordPressIntegrationTestCase
{
    public function test_saves_are_left_alone_by_default(): void
    {
        $postId = wp_insert_post([
            'post_title'   => 'كتابي',
            'post_status'  => 'publish',
            'post_type'    => 'post',
        ]);

        $this->assertSame('كتابي', get_post($postId)->post_title);
    }

    public function test_wp_insert_post_normalizes_public_post_content_when_enabled(): void
    {
        $this->bootModuleWith(['normalize_on_save' => true]);

        $postId = wp_insert_post([
            'post_title'   => 'كتابي',
            'post_content' => '<p>سلامي ١٢٣</p>',
            'post_excerpt' => 'كتاب',
            'post_status'  => 'publish',
            'post_type'    => 'post',
        ]);

        $post = get_post($postId);

        $this->assertSame('کتابی', $post->post_title);
        $this->assertSame('<p>سلامی ۱۲۳</p>', $post->post_content);
        $this->assertSame('کتاب', $post->post_excerpt);
    }

    public function test_search_with_persian_letters_finds_a_post_stored_with_arabic_letters(): void
    {
        $postId = self::factory()->post->create([
            'post_title'   => 'كتابي تازه',
            'post_content' => 'نمونه',
            'post_status'  => 'publish',
        ]);

        $this->go_to('/?s=' . rawurlencode('کتابی'));

        global $wp_query;

        $this->assertSame('کتابی', $wp_query->get('s'), 'the search terms are not rewritten');
        $this->assertSame([$postId], wp_list_pluck($wp_query->posts, 'ID'));
    }

    public function test_search_with_arabic_letters_finds_a_post_stored_with_persian_letters(): void
    {
        $postId = self::factory()->post->create([
            'post_title'   => 'نمونه',
            'post_content' => 'یک کتاب تازه',
            'post_status'  => 'publish',
        ]);

        $this->go_to('/?s=' . rawurlencode('كتاب'));

        global $wp_query;

        $this->assertSame([$postId], wp_list_pluck($wp_query->posts, 'ID'));
    }

    public function test_excluded_term_excludes_both_spellings(): void
    {
        self::factory()->post->create(['post_title' => 'باغ كتاب', 'post_status' => 'publish']);
        self::factory()->post->create(['post_title' => 'باغ کتاب', 'post_status' => 'publish']);
        $keptId = self::factory()->post->create(['post_title' => 'باغ گل', 'post_status' => 'publish']);

        $this->go_to('/?s=' . rawurlencode('باغ -کتاب'));

        global $wp_query;

        $this->assertSame([$keptId], wp_list_pluck($wp_query->posts, 'ID'));
    }

    public function test_search_without_yeh_or_kaf_keeps_core_sql(): void
    {
        self::factory()->post->create(['post_title' => 'باغ گل', 'post_status' => 'publish']);

        $this->go_to('/?s=' . rawurlencode('باغ'));

        global $wp_query;

        $this->assertStringNotContainsString(' OR ' . $GLOBALS['wpdb']->posts . '.post_title LIKE', $wp_query->request);
        $this->assertCount(1, $wp_query->posts);
    }

    public function test_a_new_term_is_saved_with_persian_letters_when_enabled(): void
    {
        $this->bootModuleWith(['normalize_on_save' => true]);

        $term = wp_insert_term('كتاب كودك', 'post_tag', ['description' => '<abbr title="ك">كتابهاي</abbr> كودك']);
        $saved = get_term($term['term_id']);

        $this->assertSame('کتاب کودک', $saved->name);
        $this->assertSame('<abbr title="ك">کتابهای</abbr> کودک', $saved->description);
    }

    public function test_a_new_comment_is_saved_with_persian_letters_when_enabled(): void
    {
        $this->bootModuleWith(['normalize_on_save' => true]);
        $postId = self::factory()->post->create();

        $commentId = wp_new_comment([
            'comment_post_ID'      => $postId,
            'comment_author'       => 'علي',
            'comment_author_email' => 'ali@example.org',
            'comment_author_url'   => '',
            'comment_content'      => 'كتاب خوبي بود',
            'comment_type'         => 'comment',
        ]);

        $comment = get_comment($commentId);
        $this->assertSame('علی', $comment->comment_author);
        $this->assertSame('کتاب خوبی بود', $comment->comment_content);
    }

    public function test_a_menu_item_title_is_saved_with_persian_letters_when_enabled(): void
    {
        $this->bootModuleWith(['normalize_on_save' => true]);
        $menuId = wp_create_nav_menu('منو');

        $itemId = wp_update_nav_menu_item($menuId, 0, [
            'menu-item-title'  => 'تماس با ما و كتاب',
            'menu-item-url'    => 'https://example.org/',
            'menu-item-status' => 'publish',
        ]);

        $this->assertSame('تماس با ما و کتاب', get_post($itemId)->post_title);
    }

    public function test_half_spaces_are_added_to_post_text_but_not_markup_when_enabled(): void
    {
        $this->bootModuleWith(['half_space_fix' => true]);

        $postId = wp_insert_post([
            'post_title'   => 'کتاب ها',
            'post_content' => '<p title="می روم">می روم</p>',
            'post_status'  => 'publish',
            'post_type'    => 'post',
        ]);

        $post = get_post($postId);
        $this->assertSame("کتاب\u{200C}ها", $post->post_title);
        $this->assertSame("<p title=\"می روم\">می\u{200C}روم</p>", $post->post_content);
    }

    public function test_search_with_persian_digits_finds_a_post_written_with_english_digits(): void
    {
        $postId = self::factory()->post->create([
            'post_title'   => 'برنامه سال 1405',
            'post_status'  => 'publish',
        ]);

        $this->go_to('/?s=' . rawurlencode('۱۴۰۵'));

        global $wp_query;

        $this->assertSame([$postId], wp_list_pluck($wp_query->posts, 'ID'));
    }

    public function test_search_with_english_digits_finds_a_post_written_with_persian_digits(): void
    {
        $postId = self::factory()->post->create([
            'post_title'   => 'برنامه سال ۱۴۰۵',
            'post_status'  => 'publish',
        ]);

        $this->go_to('/?s=1405');

        global $wp_query;

        $this->assertSame([$postId], wp_list_pluck($wp_query->posts, 'ID'));
    }

    public function test_a_product_search_on_the_shop_goes_through_the_same_filter(): void
    {
        if (!post_type_exists('product')) {
            register_post_type('product', ['public' => true, 'has_archive' => true]);
        }

        $productId = self::factory()->post->create([
            'post_title'  => 'كتاب ۱۴۰۵',
            'post_type'   => 'product',
            'post_status' => 'publish',
        ]);

        $this->go_to('/?post_type=product&s=' . rawurlencode('کتاب 1405'));

        global $wp_query;

        $this->assertTrue($wp_query->is_main_query());
        $this->assertSame([$productId], wp_list_pluck($wp_query->posts, 'ID'));
    }

    /**
     * Hook the save fixes as the module would with these settings. Hooks
     * added here are removed again after the test.
     *
     * @param array<string, mixed> $values
     */
    private function bootModuleWith(array $values): void
    {
        $settings = array_replace(CharNormalizationModule::defaults(), $values);

        (new SaveNormalizer(
            new CharNormalizer(tehMarbuta: $settings['teh_marbuta']),
            $settings['normalize_on_save'],
            $settings['half_space_fix'],
        ))->register();
    }
}
