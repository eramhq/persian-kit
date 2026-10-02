<?php

namespace PersianKit\Tests\Unit\Service\Language;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Service\Language\PolylangSource;
use PHPUnit\Framework\TestCase;

class PolylangSourceTest extends TestCase
{
    private PolylangSource $source;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('sanitize_key')->alias(static fn (string $key): string => strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', $key)));
        Functions\when('wp_unslash')->returnArg();
        // fa_IR and fa_AF share the slug "fa" on a site with only one of them.
        Functions\when('pll_languages_list')->alias(static fn (array $args): array => match ($args['fields']) {
            'slug'   => ['fa', 'en', 'ar'],
            'locale' => ['fa_AF', 'en_US', 'ar'],
        });

        $this->source = new PolylangSource();
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_GET = [];
        $_REQUEST = [];
        unset($GLOBALS['pagenow']);
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_it_is_detected_only_with_polylangs_version_constant(): void
    {
        Functions\when('pll_current_language')->justReturn('fa_IR');

        $this->assertFalse(PolylangSource::detected());
    }

    public function test_languages_are_locales(): void
    {
        $this->assertSame(['fa_AF', 'en_US', 'ar'], $this->source->languages());
    }

    public function test_it_answers_in_locales_and_null_when_polylang_does_not_know(): void
    {
        Functions\expect('pll_current_language')->with('locale')->andReturn('en_US', false);
        Functions\expect('pll_default_language')->with('locale')->andReturn('fa_AF');
        Functions\expect('pll_get_post_language')->with(5, 'locale')->andReturn(false);
        Functions\expect('pll_get_term_language')->with(3, 'locale')->andReturn('ar');

        $this->assertSame('en_US', $this->source->currentLocale());
        $this->assertNull($this->source->currentLocale(), 'not set yet, or "All languages"');
        $this->assertSame('fa_AF', $this->source->defaultLocale());
        $this->assertNull($this->source->postLocale(5));
        $this->assertSame('ar', $this->source->termLocale(3));
        $this->assertNull($this->source->postLocale(0));
        $this->assertNull($this->source->switchedLocale());
    }

    public function test_the_classic_editors_language_box_is_for_the_post_being_edited(): void
    {
        $_POST = ['post_ID' => '5', 'post_lang_choice' => 'ar'];

        $this->assertSame('ar', $this->source->requestedLocale('post', 5));
        $this->assertSame('ar', $this->source->requestedLocale('post', 0));
        $this->assertNull($this->source->requestedLocale('post', 8), 'another post saved in the request');
        $this->assertNull($this->source->requestedLocale('term', 0));
    }

    public function test_slugs_are_mapped_to_locales(): void
    {
        $_POST = ['post_ID' => '5', 'post_lang_choice' => 'fa'];

        $this->assertSame('fa_AF', $this->source->requestedLocale('post', 5));

        $_POST['post_lang_choice'] = 'xx';
        $this->assertNull($this->source->requestedLocale('post', 5), 'not a language of the site');
    }

    public function test_quick_edit_and_bulk_edit(): void
    {
        $_POST = ['post_ID' => '5', 'inline_lang_choice' => 'en'];
        $_REQUEST = $_POST;
        $this->assertSame('en_US', $this->source->requestedLocale('post', 5));

        $_POST = [];
        $_REQUEST = ['inline_lang_choice' => 'ar', 'post' => ['5', '6']];
        $this->assertSame('ar', $this->source->requestedLocale('post', 6));
        $this->assertNull($this->source->requestedLocale('post', 7));

        $_REQUEST['inline_lang_choice'] = '-1';
        $this->assertNull($this->source->requestedLocale('post', 6), '-1 keeps each language');
    }

    public function test_a_new_translation(): void
    {
        $_GET = ['new_lang' => 'en'];
        $this->assertNull($this->source->requestedLocale('post', 0), 'only on post-new.php');

        $GLOBALS['pagenow'] = 'post-new.php';
        $this->assertSame('en_US', $this->source->requestedLocale('post', 0));
    }

    public function test_the_term_forms(): void
    {
        $_POST = ['term_lang_choice' => 'ar'];
        $this->assertSame('ar', $this->source->requestedLocale('term', 0));

        $_POST = ['tag_ID' => '3', 'term_lang_choice' => 'en'];
        $this->assertSame('en_US', $this->source->requestedLocale('term', 3));
        $this->assertNull($this->source->requestedLocale('term', 4));

        $_POST = ['tax_ID' => '3', 'inline_lang_choice' => 'ar'];
        $this->assertSame('ar', $this->source->requestedLocale('term', 3));
    }

    public function test_rest_saves_give_the_language_in_lang(): void
    {
        $request = new \WP_REST_Request('POST', '/wp/v2/posts/5', ['id' => 5, 'lang' => 'ar']);
        $handler = ['callback' => [new \WP_REST_Posts_Controller(), 'update_item']];

        $this->assertSame('response', $this->source->captureRestLanguage('response', $handler, $request));
        $this->assertSame('ar', $this->source->requestedLocale('post', 5));
        $this->assertSame('ar', $this->source->requestedLocale('post', 0));
        $this->assertNull($this->source->requestedLocale('post', 6));
        $this->assertNull($this->source->requestedLocale('term', 5));
    }

    public function test_rest_term_saves(): void
    {
        $request = new \WP_REST_Request('POST', '/wp/v2/categories', ['lang' => 'en']);
        $handler = ['callback' => [new \WP_REST_Terms_Controller(), 'create_item']];

        $this->source->captureRestLanguage(null, $handler, $request);

        $this->assertSame('en_US', $this->source->requestedLocale('term', 0));
        $this->assertSame('en_US', $this->source->requestedLocale('term', 12), 'the term the request creates');
        $this->assertNull($this->source->requestedLocale('post', 0));
    }

    public function test_a_later_rest_request_without_lang_clears_it(): void
    {
        $handler = ['callback' => [new \WP_REST_Posts_Controller(), 'update_item']];
        $this->source->captureRestLanguage(null, $handler, new \WP_REST_Request('POST', '/wp/v2/posts/5', ['id' => 5, 'lang' => 'ar']));
        $this->source->captureRestLanguage(null, $handler, new \WP_REST_Request('POST', '/wp/v2/posts/5', ['id' => 5]));

        $this->assertNull($this->source->requestedLocale('post', 5));
    }

    public function test_switching_site_reads_the_languages_again(): void
    {
        $this->assertSame(['fa_AF', 'en_US', 'ar'], $this->source->languages());

        Functions\when('pll_languages_list')->justReturn([]);
        $this->assertSame(['fa_AF', 'en_US', 'ar'], $this->source->languages(), 'kept for the request');

        $this->source->forgetLanguages();
        $this->assertSame([], $this->source->languages());
    }
}
