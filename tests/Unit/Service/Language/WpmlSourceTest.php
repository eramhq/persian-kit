<?php

namespace PersianKit\Tests\Unit\Service\Language;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PersianKit\Service\Language\WpmlSource;
use PHPUnit\Framework\TestCase;

class WpmlSourceTest extends TestCase
{
    private WpmlSource $source;

    private string $current = 'fa';

    /** @var array<string, array<string, string>> */
    private array $active = [
        'fa' => ['code' => 'fa', 'default_locale' => 'fa_IR'],
        'en' => ['code' => 'en', 'default_locale' => 'en_US'],
        'ar' => ['code' => 'ar', 'default_locale' => 'ar'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('sanitize_key')->alias(static fn (string $key): string => strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', $key)));
        Functions\when('wp_unslash')->returnArg();
        Filters\expectApplied('wpml_active_languages')->andReturnUsing(fn () => $this->active);
        Filters\expectApplied('wpml_current_language')->andReturnUsing(fn () => $this->current);

        $this->source = new WpmlSource();
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_GET = [];
        unset($GLOBALS['pagenow']);
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_it_is_not_detected_without_wpml(): void
    {
        $this->assertFalse(WpmlSource::detected());
    }

    public function test_languages_are_the_default_locales_of_the_active_ones(): void
    {
        $this->assertSame(['fa_IR', 'en_US', 'ar'], $this->source->languages());
    }

    public function test_the_current_language(): void
    {
        $this->assertSame('fa_IR', $this->source->currentLocale());

        $this->current = 'en';
        $this->assertSame('en_US', $this->source->currentLocale());

        $this->current = 'all';
        $this->assertNull($this->source->currentLocale(), '"All languages" in the admin');
    }

    public function test_the_default_language(): void
    {
        Filters\expectApplied('wpml_default_language')->andReturn('ar');

        $this->assertSame('ar', $this->source->defaultLocale());
    }

    public function test_a_posts_language(): void
    {
        Functions\expect('get_post_type')->with(5)->andReturn('page');
        Filters\expectApplied('wpml_element_language_code')
            ->with(null, ['element_id' => 5, 'element_type' => 'page'])
            ->andReturn('en');

        $this->assertSame('en_US', $this->source->postLocale(5));
    }

    public function test_a_terms_language_is_kept_by_term_taxonomy_id(): void
    {
        $term = new \WP_Term();
        $term->term_id = 3;
        $term->term_taxonomy_id = 30;
        $term->taxonomy = 'post_tag';
        Functions\expect('get_term')->with(3)->andReturn($term);
        Filters\expectApplied('wpml_element_language_code')
            ->with(null, ['element_id' => 30, 'element_type' => 'post_tag'])
            ->andReturn('ar');

        $this->assertSame('ar', $this->source->termLocale(3));
    }

    public function test_switches_away_from_the_requests_language_are_tracked(): void
    {
        $this->source->trackSwitch();
        $this->assertNull($this->source->switchedLocale(), 'WPML setting its language before init');

        $this->source->recordBaseline();

        // WooCommerce Multilingual, for an English customer email.
        $this->current = 'en';
        $this->source->trackSwitch();
        $this->assertSame('en_US', $this->source->switchedLocale());

        // And back: wpml_switch_language with null.
        $this->current = 'fa';
        $this->source->trackSwitch();
        $this->assertNull($this->source->switchedLocale());
    }

    public function test_register_hooks_init_the_switch_and_switch_blog(): void
    {
        $this->source->register();

        $this->assertSame(PHP_INT_MAX, has_action('init', [$this->source, 'recordBaseline']));
        $this->assertNotFalse(has_action('wpml_language_has_switched', [$this->source, 'trackSwitch']));
        $this->assertNotFalse(has_action('switch_blog', [$this->source, 'forgetLanguages']));
    }

    public function test_an_empty_list_is_asked_again(): void
    {
        $this->active = [];
        $this->assertSame([], $this->source->languages());

        $this->active = ['en' => ['code' => 'en', 'default_locale' => 'en_US']];
        $this->assertSame(['en_US'], $this->source->languages());
    }

    public function test_the_classic_editors_language_box(): void
    {
        $_POST = ['post_ID' => '5', 'icl_post_language' => 'ar'];

        $this->assertSame('ar', $this->source->requestedLocale('post', 5));
        $this->assertSame('ar', $this->source->requestedLocale('post', 0));
        $this->assertNull($this->source->requestedLocale('post', 6));
    }

    public function test_a_new_translation(): void
    {
        $GLOBALS['pagenow'] = 'post-new.php';
        $_GET = ['lang' => 'en'];

        $this->assertSame('en_US', $this->source->requestedLocale('post', 0));
    }

    public function test_the_term_forms(): void
    {
        $_POST = ['taxonomy' => 'category', 'icl_tax_category_language' => 'ar'];
        $this->assertSame('ar', $this->source->requestedLocale('term', 0));

        $_POST['tag_ID'] = '3';
        $this->assertSame('ar', $this->source->requestedLocale('term', 3));
        $this->assertNull($this->source->requestedLocale('term', 4));
        $this->assertNull($this->source->requestedLocale('post', 0));
    }
}
