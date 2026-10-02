<?php

namespace PersianKit\Tests\Unit\CharNormalization;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PersianKit\Dependencies\Eram\Abzar\Text\CharNormalizer;
use PersianKit\Modules\CharNormalization\SaveNormalizer;
use PersianKit\Tests\Unit\Support\FailsPcre;
use PHPUnit\Framework\TestCase;
use PersianKit\Service\Language\ContentLanguage;
use PersianKit\Tests\Unit\Support\UsesLanguages;

class SaveNormalizerTest extends TestCase
{
    use UsesLanguages;

    use FailsPcre;

    private const ZWNJ = "\u{200C}";

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        ContentLanguage::reset();

        Functions\when('get_post_type_object')->alias(fn (string $type) => match ($type) {
            'post', 'page'  => (object) ['public' => true],
            'nav_menu_item' => (object) ['public' => false],
            'wp_block'      => (object) ['public' => false],
            default         => null,
        });
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('wp_is_post_autosave')->justReturn(false);
        Functions\when('get_post')->justReturn(null);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function normalizer(bool $letters = true, bool $halfSpaces = false): SaveNormalizer
    {
        return new SaveNormalizer(new CharNormalizer(), $letters, $halfSpaces);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function post(array $data): array
    {
        return array_replace(['post_type' => 'post', 'post_status' => 'publish'], $data);
    }

    public function test_register_adds_post_comment_and_term_hooks(): void
    {
        $normalizer = $this->normalizer();
        $normalizer->register();

        $this->assertNotFalse(has_filter('wp_insert_post_data', [$normalizer, 'filterPostData']));
        $this->assertNotFalse(has_filter('preprocess_comment', [$normalizer, 'filterComment']));
        $this->assertNotFalse(has_filter('pre_term_name', [$normalizer, 'filterText']));
        $this->assertNotFalse(has_filter('pre_term_description', [$normalizer, 'filterContent']));
    }

    public function test_register_skips_a_hook_the_filter_turns_off(): void
    {
        Filters\expectApplied('persian_kit_char_normalization')
            ->andReturnUsing(fn (bool $enabled, string $hook) => $hook !== 'pre_term_name');

        $normalizer = $this->normalizer();
        $normalizer->register();

        $this->assertFalse(has_filter('pre_term_name', [$normalizer, 'filterText']));
        $this->assertNotFalse(has_filter('pre_term_description', [$normalizer, 'filterContent']));
    }

    public function test_register_with_half_spaces_only_adds_the_post_hook(): void
    {
        $normalizer = $this->normalizer(false, true);
        $normalizer->register();

        $this->assertNotFalse(has_filter('wp_insert_post_data', [$normalizer, 'filterPostData']));
        $this->assertFalse(has_filter('preprocess_comment', [$normalizer, 'filterComment']));
        $this->assertFalse(has_filter('pre_term_name', [$normalizer, 'filterText']));
    }

    public function test_post_title_excerpt_and_content_get_persian_letters(): void
    {
        $data = $this->normalizer()->filterPostData($this->post([
            'post_title'   => 'كتابي',
            'post_excerpt' => 'كتاب',
            'post_content' => '<p class="ك">سلامي ١٢٣</p>',
        ]), []);

        $this->assertSame('کتابی', $data['post_title']);
        $this->assertSame('کتاب', $data['post_excerpt']);
        $this->assertSame('<p class="ك">سلامی ۱۲۳</p>', $data['post_content']);
    }

    public function test_menu_items_are_fixed_although_they_are_not_public(): void
    {
        $data = $this->normalizer()->filterPostData($this->post(['post_type' => 'nav_menu_item', 'post_title' => 'تماس با ما و كتاب']), []);

        $this->assertSame('تماس با ما و کتاب', $data['post_title']);
    }

    public function test_other_non_public_post_types_are_left_alone(): void
    {
        $data = $this->normalizer()->filterPostData($this->post(['post_type' => 'wp_block', 'post_title' => 'كتاب']), []);

        $this->assertSame('كتاب', $data['post_title']);
    }

    public function test_auto_drafts_are_left_alone(): void
    {
        $data = $this->normalizer()->filterPostData($this->post(['post_status' => 'auto-draft', 'post_title' => 'كتاب']), []);

        $this->assertSame('كتاب', $data['post_title']);
    }

    public function test_unsegmentable_content_is_saved_unchanged(): void
    {
        $html = self::unsegmentableHtml();
        $data = self::withFailingPcre(fn () => $this->normalizer()->filterPostData(
            $this->post(['post_title' => 'كتاب', 'post_content' => $html]),
            []
        ));

        $this->assertSame('کتاب', $data['post_title']);
        $this->assertSame($html, $data['post_content']);
    }

    public function test_comment_content_and_author_get_persian_letters(): void
    {
        $comment = $this->normalizer()->filterComment([
            'comment_author'  => 'علي',
            'comment_content' => '<a href="/ك">كتاب</a> خوبي بود',
            'comment_type'    => 'comment',
        ]);

        $this->assertSame('علی', $comment['comment_author']);
        $this->assertSame('<a href="/ك">کتاب</a> خوبی بود', $comment['comment_content']);
        $this->assertSame('comment', $comment['comment_type']);
    }

    public function test_term_name_and_description_get_persian_letters(): void
    {
        $normalizer = $this->normalizer();

        $this->assertSame('کتاب‌های کودک', $normalizer->filterText('كتاب‌هاي كودك'));
        $this->assertSame('<strong title="ك">کتاب</strong>', $normalizer->filterContent('<strong title="ك">كتاب</strong>'));
        $this->assertSame(['not a string'], $normalizer->filterText(['not a string']));
    }

    public function test_half_spaces_are_added_to_text_and_never_to_markup(): void
    {
        $data = $this->normalizer(true, true)->filterPostData($this->post([
            'post_title'   => 'كتاب ها',
            'post_excerpt' => 'می روم',
            'post_content' => '<!-- wp:paragraph --><p class="می روم">کتاب ها را می خوانم</p><!-- /wp:paragraph --><code>می روم</code>',
        ]), []);

        $this->assertSame('کتاب' . self::ZWNJ . 'ها', $data['post_title']);
        $this->assertSame('می' . self::ZWNJ . 'روم', $data['post_excerpt']);
        $this->assertSame(
            '<!-- wp:paragraph --><p class="می روم">کتاب' . self::ZWNJ . 'ها را می' . self::ZWNJ . 'خوانم</p><!-- /wp:paragraph --><code>می روم</code>',
            $data['post_content']
        );
    }

    public function test_half_spaces_alone_keep_the_letters_as_typed(): void
    {
        $data = $this->normalizer(false, true)->filterPostData($this->post(['post_title' => 'كتاب ها']), []);

        $this->assertSame('كتاب' . self::ZWNJ . 'ها', $data['post_title']);
    }

    public function test_letters_alone_keep_the_spaces(): void
    {
        $data = $this->normalizer()->filterPostData($this->post(['post_title' => 'كتاب ها']), []);

        $this->assertSame('کتاب ها', $data['post_title']);
    }

    public function test_comments_never_get_half_spaces(): void
    {
        $comment = $this->normalizer(true, true)->filterComment(['comment_content' => 'می روم']);

        $this->assertSame('می روم', $comment['comment_content']);
    }

    public function test_on_multilingual_sites_only_persian_posts_are_fixed(): void
    {
        $source = $this->inLanguage('fa_IR');
        $source->posts = [5 => 'ar', 6 => 'fa_IR'];
        $normalizer = $this->normalizer();

        $arabic = $normalizer->filterPostData($this->post(['post_title' => 'كتاب']), ['ID' => 5]);
        $persian = $normalizer->filterPostData($this->post(['post_title' => 'كتاب']), ['ID' => 6]);

        $this->assertSame('كتاب', $arabic['post_title']);
        $this->assertSame('کتاب', $persian['post_title']);
    }

    public function test_the_language_chosen_in_the_editor_wins_over_the_stored_one(): void
    {
        $source = $this->inLanguage('fa_IR');
        $source->posts = [5 => 'fa_IR'];
        $source->requested = ['post:5' => 'ar'];

        $data = $this->normalizer()->filterPostData($this->post(['post_title' => 'كتاب']), ['ID' => 5]);

        $this->assertSame('كتاب', $data['post_title']);
    }

    public function test_should_normalize_gets_the_language_and_can_override_it(): void
    {
        $this->inLanguage('fa_IR')->posts = [5 => 'ar'];
        Filters\expectApplied('persian_kit_should_normalize')->once()->with(false, \Mockery::any(), \Mockery::any(), \Mockery::any())->andReturn(true);

        $data = $this->normalizer()->filterPostData($this->post(['post_title' => 'كتاب']), ['ID' => 5]);

        $this->assertSame('کتاب', $data['post_title']);
    }

    public function test_comments_follow_their_posts_language(): void
    {
        $this->inLanguage('fa_IR')->posts = [5 => 'ar'];
        $normalizer = $this->normalizer();

        $this->assertSame('كتاب', $normalizer->filterComment(['comment_post_ID' => 5, 'comment_content' => 'كتاب'])['comment_content']);
        $this->assertSame('کتاب', $normalizer->filterComment(['comment_post_ID' => 6, 'comment_content' => 'كتاب'])['comment_content']);
    }

    public function test_terms_follow_the_language_the_request_gives_them(): void
    {
        $source = $this->inLanguage('fa_IR');
        $source->requested = ['term:0' => 'ar'];
        $normalizer = $this->normalizer();

        $this->assertSame('كتاب', $normalizer->filterText('كتاب'));
        $this->assertSame('<p>كتاب</p>', $normalizer->filterContent('<p>كتاب</p>'));

        $source->requested = [];
        $this->assertSame('کتاب', $normalizer->filterText('كتاب'));
    }
}
