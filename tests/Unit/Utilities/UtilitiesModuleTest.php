<?php

namespace PersianKit\Tests\Unit\Utilities;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\Utilities\PersianSlugFilter;
use PersianKit\Modules\Utilities\UtilitiesModule;
use PHPUnit\Framework\TestCase;

class UtilitiesModuleTest extends TestCase
{
    private const ZWNJ = "\u{200C}";

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('wp_basename')->alias(static fn (string $path): string => basename($path));
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_boot_adds_slug_filter_after_core(): void
    {
        Functions\expect('remove_filter')->never();

        $container = ServiceContainer::getInstance();
        $module = new UtilitiesModule($this->settings(['enabled' => true]));
        $module->register($container);
        $module->boot($container);

        $this->assertSame(11, has_filter('sanitize_title', [$container->get(PersianSlugFilter::class), 'sanitizeTitle']));
        $this->assertNotFalse(has_filter('pre_handle_404'));
        $this->assertNotFalse(has_action('template_redirect'));
    }

    public function test_boot_respects_utilities_filter(): void
    {
        Filters\expectApplied('persian_kit_utilities')->once()->with(true, 'sanitize_title')->andReturn(false);

        $container = ServiceContainer::getInstance();
        $module = new UtilitiesModule($this->settings(['enabled' => true]));
        $module->register($container);
        $module->boot($container);

        $this->assertFalse(has_filter('pre_handle_404'));
    }

    public function test_persian_slugs_setting_turns_the_filter_off(): void
    {
        $container = ServiceContainer::getInstance();
        $module = new UtilitiesModule($this->settings(['enabled' => true, 'persian_slugs' => false]));
        $module->register($container);
        $module->boot($container);

        $this->assertFalse(has_filter('sanitize_title'));
        $this->assertSame(['enabled' => true, 'persian_slugs' => false], $module->sanitizeSettings(['enabled' => '1']));
    }

    public function test_persian_title_is_slugged_in_save_context(): void
    {
        Functions\expect('sanitize_title_with_dashes')->never();

        $filter = new PersianSlugFilter();

        $this->assertSame('سلام-دنیا', $filter->sanitizeTitle('سلام دنیا', 'سلام دنیا', 'save'));
    }

    public function test_zwnj_becomes_dash_in_save_context(): void
    {
        $filter = new PersianSlugFilter();

        $this->assertSame('می-خواهم', $filter->sanitizeTitle('core-encoded', 'می' . self::ZWNJ . 'خواهم', 'save'));
    }

    public function test_query_context_keeps_zwnj_so_legacy_slugs_resolve(): void
    {
        $filter = new PersianSlugFilter();
        $legacy = 'می' . self::ZWNJ . 'خواهم-کتاب';

        $this->assertSame($legacy, $filter->sanitizeTitle($legacy, $legacy, 'query'));
    }

    public function test_query_context_matches_new_dash_slugs(): void
    {
        $filter = new PersianSlugFilter();

        $this->assertSame('می-خواهم', $filter->sanitizeTitle('core-encoded', 'می-خواهم', 'query'));
    }

    public function test_percent_encoded_persian_is_decoded_before_slugging(): void
    {
        $filter = new PersianSlugFilter();

        $this->assertSame('سلام', $filter->sanitizeTitle('core-encoded', rawurlencode('سلام'), 'query'));
    }

    /**
     * @dataProvider coreTitles
     */
    public function test_non_persian_titles_keep_cores_result(string $title, string $context): void
    {
        Functions\expect('sanitize_title_with_dashes')->never();

        $filter = new PersianSlugFilter();

        $this->assertSame('core-result', $filter->sanitizeTitle('core-result', $title, $context));
    }

    public static function coreTitles(): array
    {
        return [
            'woo attribute taxonomy' => ['pa_color', 'save'],
            'accented latin'         => ['Café', 'save'],
            'percent-encoded latin'  => ['50%25-off', 'query'],
            'empty'                  => ['', 'save'],
        ];
    }

    public function test_404_for_legacy_zwnj_url_redirects_to_dash_slug(): void
    {
        $requested = 'می' . self::ZWNJ . 'خواهم';
        $this->mockPostLookup(['می-خواهم', $this->coreForm($requested)], (object) [
            'ID' => 7, 'post_name' => 'می-خواهم', 'post_type' => 'post',
        ]);
        Functions\expect('get_permalink')->once()->with(7)->andReturn('https://example.test/می-خواهم/');

        $query = new \WP_Query(['name' => rawurlencode($requested)]);
        $filter = new PersianSlugFilter();

        $this->assertFalse($filter->resolveLegacySlug(false, $query));
        $this->assertSame('https://example.test/می-خواهم/', $filter->redirectUrl());
        $this->assertSame([], $query->queried);
    }

    public function test_404_for_pre_activation_encoded_slug_is_served_in_place(): void
    {
        $encoded = $this->coreForm('سلام');
        $this->mockPostLookup(['سلام', $encoded], (object) [
            'ID' => 3, 'post_name' => $encoded, 'post_type' => 'post',
        ]);
        Functions\expect('get_permalink')->never();

        $query = new \WP_Query(['name' => $encoded]);
        $filter = new PersianSlugFilter();

        $filter->resolveLegacySlug(false, $query);

        $this->assertNull($filter->redirectUrl());
        $this->assertSame([['p' => 3, 'post_type' => 'post']], $query->queried);
    }

    public function test_404_for_pre_activation_page_is_requeried_by_page_id(): void
    {
        $encoded = $this->coreForm('درباره');
        $this->mockPostLookup(['درباره', $encoded], (object) [
            'ID' => 9, 'post_name' => $encoded, 'post_type' => 'page',
        ]);

        $query = new \WP_Query(['pagename' => 'parent/' . $encoded]);
        $filter = new PersianSlugFilter();

        $filter->resolveLegacySlug(false, $query);

        $this->assertSame([['page_id' => 9]], $query->queried);
    }

    public function test_404_without_match_stays_404(): void
    {
        $this->mockPostLookup(['ناموجود', $this->coreForm('ناموجود')], null);

        $query = new \WP_Query(['name' => 'ناموجود']);
        $filter = new PersianSlugFilter();

        $this->assertFalse($filter->resolveLegacySlug(false, $query));
        $this->assertNull($filter->redirectUrl());
        $this->assertSame([], $query->queried);
    }

    public function test_404_fallback_ignores_latin_slugs_and_found_posts(): void
    {
        $filter = new PersianSlugFilter();

        $latin = new \WP_Query(['name' => 'hello-world']);
        $this->assertFalse($filter->resolveLegacySlug(false, $latin));

        $found = new \WP_Query(['name' => 'سلام']);
        $found->posts = [(object) ['ID' => 1]];
        $this->assertFalse($filter->resolveLegacySlug(false, $found));

        $this->assertTrue($filter->resolveLegacySlug(true, new \WP_Query(['name' => 'سلام'])));
        $this->assertNull($filter->redirectUrl());
    }

    /**
     * Core's percent-encoded save form, as sanitize_title_with_dashes() would store it.
     */
    private function coreForm(string $slug): string
    {
        return strtolower(rawurlencode($slug));
    }

    private function settings(array $values): SettingsManager
    {
        $settings = Mockery::mock(SettingsManager::class);
        $settings->shouldReceive('module')->andReturnUsing(
            static fn (string $module, ?string $key = null, mixed $default = null) => $key === null ? $values : ($values[$key] ?? $default)
        );

        return $settings;
    }

    private function mockPostLookup(array $expectedNames, ?object $row): void
    {
        Functions\when('sanitize_title_with_dashes')->alias(
            fn (string $title): string => $this->coreForm($title)
        );
        Functions\when('get_post_types')->justReturn(['post' => 'post', 'page' => 'page']);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->posts = 'wp_posts';
        $wpdb->shouldReceive('prepare')
            ->once()
            ->with(Mockery::type('string'), array_merge($expectedNames, ['post', 'page']))
            ->andReturn('SELECT ...');
        $wpdb->shouldReceive('get_row')->once()->with('SELECT ...')->andReturn($row);

        $GLOBALS['wpdb'] = $wpdb;
    }
}
