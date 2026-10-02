<?php

namespace PersianKit\Tests\Integration\Multilingual;

use PersianKit\Dependencies\Eram\Abzar\Text\CharNormalizer;
use PersianKit\Modules\CharNormalization\BatchMigrator;
use PersianKit\Modules\CharNormalization\SaveNormalizer;
use PersianKit\Tests\Integration\Support\UsesPolylang;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * The writing tools fix Persian content only: Arabic keeps ي and ك.
 *
 * @group polylang
 */
class PolylangWritingTest extends WordPressIntegrationTestCase
{
    use UsesPolylang;

    public function set_up(): void
    {
        parent::set_up();
        $this->setUpPolylang();
    }

    public function tear_down(): void
    {
        $_POST = [];
        $_REQUEST = [];
        parent::tear_down();
    }

    public function test_arabic_posts_keep_their_letters_and_persian_ones_are_fixed(): void
    {
        $arabic = $this->postIn('ar');
        $persian = $this->postIn('fa');
        $this->fixLetters();

        wp_update_post(['ID' => $arabic, 'post_title' => 'كتاب', 'post_content' => 'كتاب جديد']);
        wp_update_post(['ID' => $persian, 'post_title' => 'كتاب', 'post_content' => 'كتاب جديد']);

        $this->assertSame('كتاب', get_post($arabic)->post_title);
        $this->assertSame('كتاب جديد', get_post($arabic)->post_content);
        $this->assertSame('کتاب', get_post($persian)->post_title);
        $this->assertSame('کتاب جدید', get_post($persian)->post_content);
    }

    public function test_comments_follow_their_posts_language(): void
    {
        $arabic = $this->postIn('ar');
        $persian = $this->postIn('fa');
        $this->fixLetters();

        $onArabic = apply_filters('preprocess_comment', ['comment_post_ID' => $arabic, 'comment_author' => 'علي', 'comment_content' => 'كتاب']);
        $onPersian = apply_filters('preprocess_comment', ['comment_post_ID' => $persian, 'comment_author' => 'علي', 'comment_content' => 'كتاب']);

        $this->assertSame(['علي', 'كتاب'], [$onArabic['comment_author'], $onArabic['comment_content']]);
        $this->assertSame(['علی', 'کتاب'], [$onPersian['comment_author'], $onPersian['comment_content']]);
    }

    public function test_the_language_chosen_in_the_classic_editor_wins(): void
    {
        $postId = $this->postIn('fa');
        $this->fixLetters();

        // The language box of a post being moved to Arabic.
        $_POST = ['post_ID' => (string) $postId, 'post_lang_choice' => 'ar'];
        wp_update_post(['ID' => $postId, 'post_title' => 'كتاب']);

        $this->assertSame('كتاب', get_post($postId)->post_title);
    }

    public function test_terms_follow_the_language_chosen_for_them(): void
    {
        $this->fixLetters();

        // The language box sends the language's term ID.
        $_POST = ['term_lang_choice' => (string) PLL()->model->get_language('ar')->term_id];
        $arabic = wp_insert_term('كتاب', 'category');
        $_POST = ['term_lang_choice' => (string) PLL()->model->get_language('fa')->term_id];
        $persian = wp_insert_term('كتابي', 'category');

        $this->assertIsArray($arabic);
        $this->assertIsArray($persian);
        $this->assertSame('كتاب', get_term($arabic['term_id'])->name);
        $this->assertSame('کتابی', get_term($persian['term_id'])->name);
    }

    public function test_arabic_posts_get_cores_slugs(): void
    {
        $this->useLanguage('ar');
        $arabic = wp_insert_post(['post_title' => 'كتاب', 'post_status' => 'publish']);

        $this->useLanguage('fa');
        $persian = wp_insert_post(['post_title' => 'كتاب نو', 'post_status' => 'publish']);

        $this->assertSame(sanitize_title_with_dashes('كتاب', '', 'save'), get_post($arabic)->post_name);
        $this->assertSame('کتاب-نو', rawurldecode(get_post($persian)->post_name));
    }

    public function test_a_block_editor_save_keeps_an_arabic_posts_letters_and_slug(): void
    {
        $arabic = $this->postIn('ar', ['post_status' => 'draft']);
        $this->fixLetters();
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        // As the block editor publishes it: no language in the request.
        $request = new \WP_REST_Request('POST', "/wp/v2/posts/$arabic");
        $request->set_body_params(['title' => 'كتاب جديد', 'status' => 'publish']);
        $response = rest_do_request($request);

        $this->assertSame(200, $response->get_status());
        $this->assertSame('كتاب جديد', get_post($arabic)->post_title);
        $this->assertSame(sanitize_title_with_dashes('كتاب جديد', '', 'save'), get_post($arabic)->post_name);
    }

    public function test_fix_letters_in_existing_posts_skips_other_languages(): void
    {
        $arabic = $this->postIn('ar', ['post_title' => 'كتاب']);
        $persian = $this->postIn('fa', ['post_title' => 'كتاب']);

        $result = (new BatchMigrator(new CharNormalizer()))->processBatch(['post'], 100, false, 0);

        $this->assertSame(1, $result->modified);
        $this->assertSame('كتاب', get_post($arabic)->post_title);
        $this->assertSame('کتاب', get_post($persian)->post_title);
        $this->assertSame(max($arabic, $persian), $result->lastId);
    }

    private function fixLetters(): void
    {
        (new SaveNormalizer(new CharNormalizer(), true, false))->register();
    }
}
