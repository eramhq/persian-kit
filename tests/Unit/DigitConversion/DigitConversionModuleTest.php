<?php

namespace PersianKit\Tests\Unit\DigitConversion;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\DigitConversion\DigitConversionModule;
use PersianKit\Tests\Unit\Support\FailsPcre;
use PHPUnit\Framework\TestCase;

class DigitConversionModuleTest extends TestCase
{
    use FailsPcre;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('wp_is_serving_rest_request')->justReturn(false);
        Functions\when('is_feed')->justReturn(false);
        Functions\when('doing_filter')->justReturn(false);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wp_query']);
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_convert_content_converts_text_but_not_markup(): void
    {
        $this->assertSame(
            '<p class="c1">سال ۱۴۰۳</p>',
            DigitConversionModule::convertContent('<p class="c1">سال 1403</p>')
        );
    }

    public function test_convert_content_returns_input_when_segmentation_fails(): void
    {
        $html = self::unsegmentableHtml();

        $this->assertSame($html, self::withFailingPcre(fn () => DigitConversionModule::convertContent($html)));
    }

    public function test_boot_registers_front_end_filters_once_per_hook(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $module = $this->bootModule();

        foreach (['the_content', 'the_title', 'get_the_excerpt', 'comment_text', 'widget_text', 'widget_text_content', 'human_time_diff', 'get_the_terms'] as $hook) {
            $this->assertNotFalse(has_filter($hook), $hook);
        }
        $this->assertFalse(has_filter('the_excerpt'), 'get_the_excerpt already covers the_excerpt');
        $this->assertSame(99, has_filter('the_content', [$module, 'filterContent']));
    }

    public function test_boot_skips_admin_screens(): void
    {
        Functions\when('is_admin')->justReturn(true);
        Functions\when('wp_doing_ajax')->justReturn(false);

        $this->bootModule();

        $this->assertFalse(has_filter('the_content'));
    }

    public function test_boot_skips_admin_ajax_from_wp_admin(): void
    {
        Functions\when('is_admin')->justReturn(true);
        Functions\when('wp_doing_ajax')->justReturn(true);
        Functions\when('admin_url')->justReturn('https://example.test/wp-admin/');
        Functions\when('wp_get_raw_referer')->justReturn('https://example.test/wp-admin/edit.php');

        $this->bootModule();

        $this->assertFalse(has_filter('the_content'));
    }

    public function test_boot_converts_front_end_ajax(): void
    {
        Functions\when('is_admin')->justReturn(true);
        Functions\when('wp_doing_ajax')->justReturn(true);
        Functions\when('admin_url')->justReturn('https://example.test/wp-admin/');
        Functions\when('wp_get_raw_referer')->justReturn('https://example.test/blog/');

        $this->bootModule();

        $this->assertNotFalse(has_filter('the_content'));
    }

    public function test_filters_tolerate_null_and_empty(): void
    {
        $module = $this->makeModule();

        $this->assertNull($module->filterContent(null));
        $this->assertNull($module->filterText(null));
        $this->assertSame('', $module->filterText(''));
        $this->assertSame('۱۲', $module->filterText('12'));
    }

    public function test_filters_skip_rest_feeds_and_mail(): void
    {
        $module = $this->makeModule();

        Functions\when('wp_is_serving_rest_request')->justReturn(true);
        $this->assertSame('12', $module->filterText('12'));

        Functions\when('wp_is_serving_rest_request')->justReturn(false);
        $GLOBALS['wp_query'] = new \WP_Query();
        Functions\when('is_feed')->justReturn(true);
        $this->assertSame('<p>12</p>', $module->filterContent('<p>12</p>'));

        Functions\when('is_feed')->justReturn(false);
        Functions\when('doing_filter')->alias(static fn (?string $hook = null): bool => $hook === 'wp_mail');
        $this->assertSame('<p>12</p>', $module->filterContent('<p>12</p>'));
    }

    public function test_terms_are_renamed_on_copies(): void
    {
        $cached = (object) ['term_id' => 1, 'name' => 'Top 10'];

        $result = $this->makeModule()->filterTerms([$cached]);

        $this->assertSame('Top ۱۰', $result[0]->name);
        $this->assertSame('Top 10', $cached->name);
        $this->assertNotSame($cached, $result[0]);
    }

    public function test_terms_filter_passes_through_errors(): void
    {
        $this->assertFalse($this->makeModule()->filterTerms(false));
    }

    public function test_convert_content_keeps_digits_in_kbd_and_samp(): void
    {
        $this->assertSame(
            '<p>کلید <kbd>Ctrl+1</kbd> در ۲ ثانیه <samp>Error 404</samp> ۳</p>',
            DigitConversionModule::convertContent('<p>کلید <kbd>Ctrl+1</kbd> در 2 ثانیه <samp>Error 404</samp> 3</p>')
        );
    }

    public function test_boot_registers_dates_counts_and_prices_when_enabled(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $module = $this->bootModule();

        $this->assertSame(99, has_filter('persian_kit_date_display', [$module, 'filterText']));
        $this->assertSame(99, has_filter('number_format_i18n', [$module, 'filterText']));
        $this->assertSame(99, has_filter('formatted_woocommerce_price', [$module, 'filterText']));
    }

    public function test_boot_skips_options_that_are_off(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $this->bootModule(['dates' => false, 'numbers' => false, 'prices' => false]);

        $this->assertFalse(has_filter('persian_kit_date_display'));
        $this->assertFalse(has_filter('number_format_i18n'));
        $this->assertFalse(has_filter('formatted_woocommerce_price'));
        $this->assertNotFalse(has_filter('the_content'));
    }

    public function test_new_installs_start_with_the_module_off(): void
    {
        $this->assertFalse(DigitConversionModule::defaults()['enabled']);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function makeModule(array $settings = []): DigitConversionModule
    {
        $merged = array_replace(DigitConversionModule::defaults(), ['enabled' => true], $settings);
        $manager = Mockery::mock(SettingsManager::class);
        $manager->shouldReceive('module')->andReturnUsing(
            static fn (string $key, ?string $subKey = null, mixed $default = null) => $subKey === null ? $merged : ($merged[$subKey] ?? $default)
        );

        return new DigitConversionModule($manager);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function bootModule(array $settings = []): DigitConversionModule
    {
        $module = $this->makeModule($settings);
        $module->boot(Mockery::mock(ServiceContainer::class));

        return $module;
    }
}
