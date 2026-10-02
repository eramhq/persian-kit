<?php

namespace PersianKit\Tests\Unit\Service\Language;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PersianKit\Service\Language\ContentLanguage;
use PersianKit\Tests\Unit\Support\FakeLanguageSource;
use PHPUnit\Framework\TestCase;

class ContentLanguageTest extends TestCase
{
    private FakeLanguageSource $source;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        ContentLanguage::reset();

        Functions\when('determine_locale')->justReturn('fa_IR');
        Functions\when('get_user_locale')->justReturn('en_US');
        Functions\when('is_locale_switched')->justReturn(false);
        Functions\when('is_admin')->justReturn(false);
        Functions\when('wp_doing_ajax')->justReturn(false);
        Functions\when('wp_is_serving_rest_request')->justReturn(false);
        Functions\when('admin_url')->justReturn('https://example.test/wp-admin/');
        Functions\when('wp_get_raw_referer')->justReturn('');

        $this->source = new FakeLanguageSource();
    }

    protected function tearDown(): void
    {
        ContentLanguage::reset();
        unset($_GET['_locale'], $GLOBALS['pagenow'], $GLOBALS['post']);
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_a_site_without_a_multilingual_plugin_is_persian_whatever_its_locale(): void
    {
        ContentLanguage::useSource(null);
        Functions\when('determine_locale')->justReturn('en_US');

        $this->assertFalse(ContentLanguage::isMultilingual());
        $this->assertTrue(ContentLanguage::displaysPersian());
        $this->assertTrue(ContentLanguage::postIsPersian(5));
        $this->assertTrue(ContentLanguage::writesPersian('post', 5));
        $this->assertTrue(ContentLanguage::writesPersian('term'));
        $this->assertSame('en_US', ContentLanguage::currentLocale());
    }

    public function test_a_plugin_with_no_languages_set_up_changes_nothing(): void
    {
        $this->source->languages = [];
        $this->source->current = 'en_US';
        ContentLanguage::useSource($this->source);

        $this->assertFalse(ContentLanguage::isMultilingual());
        $this->assertTrue(ContentLanguage::displaysPersian());
    }

    public function test_other_plugins_opt_in_and_the_locale_decides(): void
    {
        ContentLanguage::useSource(null);
        Filters\expectApplied('persian_kit_multilingual')->with(false)->andReturn(true);
        Functions\when('determine_locale')->justReturn('en_US');

        $this->assertTrue(ContentLanguage::isMultilingual());
        $this->assertFalse(ContentLanguage::displaysPersian());
        // No plugin to ask what is being saved: unknown counts as Persian.
        $this->assertTrue(ContentLanguage::writesPersian('post', 5));
    }

    public function test_a_site_can_turn_it_off(): void
    {
        $this->source->current = 'en_US';
        ContentLanguage::useSource($this->source);
        Filters\expectApplied('persian_kit_multilingual')->with(true)->andReturn(false);

        $this->assertTrue(ContentLanguage::displaysPersian());
    }

    /**
     * @dataProvider locales
     */
    public function test_persian_is_fa_and_its_locales(string $locale, bool $persian): void
    {
        $this->assertSame($persian, ContentLanguage::isPersianLocale($locale));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function locales(): array
    {
        return [
            'fa'    => ['fa', true],
            'fa_IR' => ['fa_IR', true],
            'fa_AF' => ['fa_AF', true],
            'en_US' => ['en_US', false],
            'ar'    => ['ar', false],
            'far'   => ['far', false],
            'empty' => ['', false],
        ];
    }

    public function test_which_locales_are_persian_can_be_changed(): void
    {
        Filters\expectApplied('persian_kit_is_persian_locale')->with(false, 'tg_TJ')->andReturn(true);

        $this->assertTrue(ContentLanguage::isPersianLocale('tg_TJ'));
    }

    public function test_pages_follow_their_language(): void
    {
        ContentLanguage::useSource($this->source);

        $this->source->current = 'en_US';
        $this->assertSame('en_US', ContentLanguage::currentLocale());
        $this->assertFalse(ContentLanguage::displaysPersian());

        $this->source->current = 'fa_IR';
        $this->assertTrue(ContentLanguage::displaysPersian());
    }

    public function test_before_the_language_is_known_the_default_one_is_used(): void
    {
        $this->source->default = 'en_US';
        ContentLanguage::useSource($this->source);

        $this->assertSame('en_US', ContentLanguage::currentLocale());

        $this->source->default = null;
        $this->assertSame('fa_IR', ContentLanguage::currentLocale(), "WordPress's locale, last");
    }

    public function test_a_switched_locale_wins_over_the_page_language(): void
    {
        $this->source->current = 'fa_IR';
        ContentLanguage::useSource($this->source);
        Functions\when('is_locale_switched')->justReturn(true);
        Functions\when('determine_locale')->justReturn('en_US');

        $this->assertSame('en_US', ContentLanguage::currentLocale());
    }

    public function test_a_language_switched_by_the_plugin_wins_over_the_admins_language(): void
    {
        $this->source->switched = 'en_US';
        ContentLanguage::useSource($this->source);
        Functions\when('is_admin')->justReturn(true);
        Functions\when('get_user_locale')->justReturn('fa_IR');

        $this->assertSame('en_US', ContentLanguage::currentLocale());
    }

    public function test_admin_screens_follow_the_admins_profile_language(): void
    {
        $this->source->current = 'fa_IR';
        ContentLanguage::useSource($this->source);
        Functions\when('is_admin')->justReturn(true);

        $this->assertTrue(ContentLanguage::isAdminRequest());
        $this->assertSame('en_US', ContentLanguage::currentLocale());
        $this->assertFalse(ContentLanguage::displaysPersian());
    }

    public function test_admin_ajax_from_the_front_end_follows_the_page(): void
    {
        $this->source->current = 'fa_IR';
        ContentLanguage::useSource($this->source);
        Functions\when('is_admin')->justReturn(true);
        Functions\when('wp_doing_ajax')->justReturn(true);
        Functions\when('wp_get_raw_referer')->justReturn('https://example.test/blog/');

        $this->assertFalse(ContentLanguage::isAdminRequest());
        $this->assertSame('fa_IR', ContentLanguage::currentLocale());
    }

    public function test_admin_ajax_from_wp_admin_or_without_a_referer_is_admin(): void
    {
        Functions\when('is_admin')->justReturn(true);
        Functions\when('wp_doing_ajax')->justReturn(true);

        Functions\when('wp_get_raw_referer')->justReturn('https://example.test/wp-admin/upload.php');
        $this->assertTrue(ContentLanguage::isAdminRequest());

        ContentLanguage::reset();
        Functions\when('wp_get_raw_referer')->justReturn('');
        $this->assertTrue(ContentLanguage::isAdminRequest());
    }

    public function test_the_block_editors_rest_requests_follow_the_admins_language(): void
    {
        $this->source->current = 'fa_IR';
        ContentLanguage::useSource($this->source);
        Functions\when('wp_is_serving_rest_request')->justReturn(true);

        $this->assertSame('fa_IR', ContentLanguage::currentLocale());

        $_GET['_locale'] = 'user';
        $this->assertSame('en_US', ContentLanguage::currentLocale());
    }

    public function test_the_reading_language_can_be_changed(): void
    {
        $this->source->current = 'en_US';
        ContentLanguage::useSource($this->source);
        Filters\expectApplied('persian_kit_current_locale')->with('en_US')->andReturn('fa_IR');

        $this->assertTrue(ContentLanguage::displaysPersian());
    }

    public function test_a_posts_language_is_its_own_then_the_default_one(): void
    {
        $this->source->posts = [5 => 'en_US', 6 => 'fa_AF'];
        $this->source->default = 'ar';
        ContentLanguage::useSource($this->source);

        $post = new \WP_Post();
        $post->ID = 5;

        $this->assertFalse(ContentLanguage::postIsPersian($post));
        $this->assertTrue(ContentLanguage::postIsPersian(6));
        $this->assertFalse(ContentLanguage::postIsPersian(7), 'the default language');

        $this->source->default = null;
        $this->assertTrue(ContentLanguage::postIsPersian(7), 'unknown counts as Persian');
    }

    public function test_writing_follows_the_language_the_request_gives_first(): void
    {
        $this->source->requested = ['post:5' => 'fa_IR'];
        $this->source->posts = [5 => 'ar'];
        ContentLanguage::useSource($this->source);

        $this->assertTrue(ContentLanguage::writesPersian('post', 5));
    }

    public function test_writing_follows_the_posts_own_language(): void
    {
        $this->source->posts = [5 => 'ar'];
        $this->source->current = 'fa_IR';
        ContentLanguage::useSource($this->source);

        $this->assertFalse(ContentLanguage::writesPersian('post', 5));
    }

    public function test_writing_follows_the_terms_own_language(): void
    {
        $this->source->terms = [3 => 'ar'];
        $this->source->posts = [3 => 'fa_IR'];
        ContentLanguage::useSource($this->source);

        $this->assertFalse(ContentLanguage::writesPersian('term', 3));
    }

    public function test_writing_without_an_id_follows_the_post_open_in_the_editor(): void
    {
        $this->source->posts = [9 => 'ar'];
        $this->source->current = 'fa_IR';
        ContentLanguage::useSource($this->source);

        $post = new \WP_Post();
        $post->ID = 9;
        $GLOBALS['post'] = $post;

        $GLOBALS['pagenow'] = 'edit.php';
        $this->assertTrue(ContentLanguage::writesPersian('post'), 'only in the editor');

        $GLOBALS['pagenow'] = 'post.php';
        $this->assertFalse(ContentLanguage::writesPersian('post'));
    }

    public function test_writing_without_an_id_follows_the_object_the_rest_request_saves(): void
    {
        $this->source->posts = [9 => 'ar'];
        $this->source->terms = [4 => 'ar'];
        $this->source->current = 'fa_IR';
        ContentLanguage::useSource($this->source);

        $posts = ['callback' => [new \WP_REST_Posts_Controller(), 'update_item']];
        $terms = ['callback' => [new \WP_REST_Terms_Controller(), 'update_item']];

        $this->assertSame('response', ContentLanguage::captureRestObject('response', $posts, new \WP_REST_Request('POST', '/wp/v2/posts/9', ['id' => 9])));
        $this->assertFalse(ContentLanguage::writesPersian('post'));
        $this->assertTrue(ContentLanguage::writesPersian('term'), 'not a term request');

        ContentLanguage::captureRestObject(null, $terms, new \WP_REST_Request('POST', '/wp/v2/categories/4', ['id' => 4]));
        $this->assertFalse(ContentLanguage::writesPersian('term'));
        $this->assertTrue(ContentLanguage::writesPersian('post'));

        // Creating: no ID in the route.
        ContentLanguage::captureRestObject(null, $posts, new \WP_REST_Request('POST', '/wp/v2/posts', []));
        $this->assertTrue(ContentLanguage::writesPersian('post'));
    }

    public function test_writing_falls_back_to_the_current_then_the_default_language(): void
    {
        $this->source->current = 'ar';
        ContentLanguage::useSource($this->source);
        $this->assertFalse(ContentLanguage::writesPersian('post', 5));

        $this->source->current = null;
        $this->source->default = 'en_US';
        $this->assertFalse(ContentLanguage::writesPersian('term'));

        $this->source->default = null;
        $this->assertTrue(ContentLanguage::writesPersian('post', 5), 'unknown counts as Persian');
    }

    public function test_writing_ignores_the_admins_profile_language(): void
    {
        $this->source->posts = [5 => 'fa_IR'];
        ContentLanguage::useSource($this->source);
        Functions\when('is_admin')->justReturn(true);

        $this->assertFalse(ContentLanguage::displaysPersian());
        $this->assertTrue(ContentLanguage::writesPersian('post', 5));
    }

    public function test_the_writing_language_can_be_changed(): void
    {
        $this->source->posts = [5 => 'ar'];
        ContentLanguage::useSource($this->source);
        Filters\expectApplied('persian_kit_content_locale')->with('ar', 'post', 5)->andReturn('fa_IR');

        $this->assertTrue(ContentLanguage::writesPersian('post', 5));
    }

    public function test_the_answer_is_kept_once_init_has_run_and_forgotten_on_switch_blog(): void
    {
        ContentLanguage::useSource($this->source);
        do_action('init');

        $this->assertTrue(ContentLanguage::isMultilingual());

        $this->source->languages = [];
        $this->assertTrue(ContentLanguage::isMultilingual(), 'kept');

        ContentLanguage::forgetSite();
        $this->assertFalse(ContentLanguage::isMultilingual());
    }

    public function test_an_early_answer_is_not_kept(): void
    {
        $this->source->languages = [];
        ContentLanguage::useSource($this->source);

        $this->assertFalse(ContentLanguage::isMultilingual());

        $this->source->languages = ['fa_IR', 'en_US'];
        $this->assertTrue(ContentLanguage::isMultilingual());
    }

    public function test_register_hooks_the_source(): void
    {
        ContentLanguage::useSource($this->source);
        ContentLanguage::register();

        $this->assertSame(1, $this->source->registered);
        $this->assertNotFalse(has_action('switch_blog', [ContentLanguage::class, 'forgetSite']));
    }
}
