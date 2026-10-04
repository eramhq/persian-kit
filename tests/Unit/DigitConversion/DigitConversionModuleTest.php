<?php

namespace PersianKit\Tests\Unit\DigitConversion;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\DigitConversion\DigitConversionModule;
use PersianKit\Modules\DigitConversion\WooCommerceEmailDigits;
use PersianKit\Service\Language\ContentLanguage;
use PersianKit\Tests\Unit\Support\FailsPcre;
use PersianKit\Tests\Unit\Support\UsesLanguages;
use PHPUnit\Framework\TestCase;

class DigitConversionModuleTest extends TestCase
{
    use FailsPcre;
    use UsesLanguages;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        ContentLanguage::reset();
        Functions\when('wp_is_serving_rest_request')->justReturn(false);
        Functions\when('is_feed')->justReturn(false);
        Functions\when('doing_filter')->justReturn(false);
        Functions\when('doing_action')->justReturn(false);
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

        foreach ([
            'the_content', 'the_title', 'get_the_excerpt', 'comment_text', 'widget_text', 'widget_text_content',
            'single_post_title', 'single_cat_title', 'single_tag_title', 'single_term_title',
            'get_the_archive_description', 'term_description', 'widget_title', 'widget_block_content',
            'widget_custom_html_content', 'list_cats', 'get_comment_excerpt', 'woocommerce_short_description',
            'render_block_core/navigation', 'render_block_core/page-list', 'render_block_core/term-name',
        ] as $hook) {
            $this->assertSame(99, has_filter($hook, [$module, 'filterContent']), $hook);
        }
        $this->assertSame(99, has_filter('get_the_archive_title', [$module, 'filterArchiveTitle']));
        $this->assertSame(99, has_filter('wp_generate_tag_cloud_data', [$module, 'filterTagCloudData']));
        $this->assertSame(99, has_filter('human_time_diff', [$module, 'filterText']));
        $this->assertSame(99, has_filter('get_the_terms', [$module, 'filterTerms']));
        foreach (['pre_get_document_title', 'document_title', 'wp_title'] as $hook) {
            $this->assertSame(9999, has_filter($hook, [$module, 'filterDocumentTitle']), $hook);
        }
        $this->assertFalse(has_filter('the_excerpt'), 'get_the_excerpt already covers the_excerpt');
    }

    public function test_titles_keep_their_character_references(): void
    {
        Functions\when('get_bloginfo')->justReturn('');
        $module = $this->makeModule();

        $this->assertSame('Don&#8217;t ۱۰ &#8211; x', $module->filterContent('Don&#8217;t 10 &#8211; x'));
        $this->assertSame('Top ۱۰ &amp; more', $module->filterDocumentTitle('Top 10 &amp; more'));
    }

    public function test_convert_content_takes_a_fast_path_for_plain_text(): void
    {
        $this->assertSame('سال ۱۴۰۳', DigitConversionModule::convertContent('سال 1403'));
        // No markup to segment, so a PCRE failure doesn't matter.
        $this->assertSame('۱۲', self::withFailingPcre(fn () => DigitConversionModule::convertContent('12')));
    }

    public function test_an_empty_pre_get_document_title_stays_empty(): void
    {
        $this->assertSame('', $this->makeModule()->filterDocumentTitle(''));
    }

    public function test_the_document_title_keeps_the_site_name_as_typed(): void
    {
        Functions\when('get_bloginfo')->justReturn('Shop24');

        $this->assertSame('Top ۱۰ &#8211; Shop24', $this->makeModule()->filterDocumentTitle('Top 10 &#8211; Shop24'));
    }

    public function test_the_document_title_converts_whole_when_the_site_name_has_no_digits(): void
    {
        Functions\when('get_bloginfo')->justReturn('فروشگاه');

        $this->assertSame('صفحه ۲ &#8211; فروشگاه', $this->makeModule()->filterDocumentTitle('صفحه 2 &#8211; فروشگاه'));
    }

    public function test_tags_in_wp_head_keep_their_digits_but_the_document_title_converts(): void
    {
        Functions\when('get_bloginfo')->justReturn('');
        Functions\when('doing_action')->alias(static fn (?string $hook = null): bool => $hook === 'wp_head');
        $module = $this->makeModule();

        $this->assertSame('Top 10', $module->filterContent('Top 10'));
        $this->assertSame('Top ۱۰', $module->filterDocumentTitle('Top 10'));
    }

    public function test_nothing_converts_while_a_post_is_saved(): void
    {
        Functions\when('doing_action')->alias(static fn (?string $hook = null): bool => $hook === 'wp_insert_post');

        $this->assertSame('Top 10', $this->makeModule()->filterContent('Top 10'));
    }

    public function test_date_archive_titles_are_left_to_the_date_options(): void
    {
        $module = $this->makeModule();

        Functions\when('is_date')->justReturn(true);
        $this->assertSame('Year: 2025', $module->filterArchiveTitle('Year: 2025'));

        Functions\when('is_date')->justReturn(false);
        $this->assertSame('Category: <span>Top ۱۰</span>', $module->filterArchiveTitle('Category: <span>Top 10</span>'));
    }

    public function test_tag_cloud_converts_names_and_counts_only(): void
    {
        $tag = [
            'url'        => 'https://example.test/tag/top-10/',
            'name'       => 'Top 10 &amp; more',
            'real_count' => 12,
            'aria_label' => ' aria-label="Top 10 (12 items)"',
            'show_count' => '<span class="tag-link-count"> (12)</span>',
        ];

        $converted = $this->makeModule()->filterTagCloudData([$tag])[0];

        $this->assertSame('Top ۱۰ &amp; more', $converted['name']);
        $this->assertSame('<span class="tag-link-count"> (۱۲)</span>', $converted['show_count']);
        $this->assertSame($tag['url'], $converted['url']);
        $this->assertSame(12, $converted['real_count']);
        $this->assertSame($tag['aria_label'], $converted['aria_label']);

        $withoutNumbers = $this->makeModule(['numbers' => false])->filterTagCloudData([$tag])[0];
        $this->assertSame('Top ۱۰ &amp; more', $withoutNumbers['name']);
        $this->assertSame($tag['show_count'], $withoutNumbers['show_count'], 'counts follow the numbers option');

        $this->assertNull($this->makeModule()->filterTagCloudData(null));
    }

    public function test_archive_links_convert_only_the_count(): void
    {
        $link = "\t<li><a href='https://example.test/2025/03/'>March 2025</a>&nbsp;(12)</li>\n";

        $this->assertSame(
            "\t<li><a href='https://example.test/2025/03/'>March 2025</a>&nbsp;(۱۲)</li>\n",
            $this->makeModule()->filterArchivesLink($link, 'https://example.test/2025/03/', 'March 2025', 'html', '', '&nbsp;(12)')
        );
        $this->assertSame($link, $this->makeModule()->filterArchivesLink($link, '', 'March 2025', 'html', '', ''), 'no count, nothing to do');
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

    public function test_on_multilingual_sites_only_persian_pages_are_converted(): void
    {
        $module = $this->makeModule();

        $this->inLanguage('en_US');
        $this->assertSame('12', $module->filterText('12'));
        $this->assertSame('<p>12</p>', $module->filterContent('<p>12</p>'));
        $this->assertSame('Top 10', $module->filterTerms([(object) ['name' => 'Top 10']])[0]->name);

        $this->inLanguage('fa_IR');
        $this->assertSame('۱۲', $module->filterText('12'));
    }

    public function test_block_price_script_skips_pages_not_in_persian(): void
    {
        Functions\expect('wp_enqueue_script')->never();
        Functions\when('is_cart')->justReturn(true);
        Functions\when('has_block')->justReturn(true);
        $this->inLanguage('en_US');

        $this->makeModule()->enqueueBlockPriceScript();
        $this->addToAssertionCount(1);
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
        $this->assertSame(99, has_filter('persian_kit_gregorian_date_display', [$module, 'filterText']));
        $this->assertSame(99, has_filter('number_format_i18n', [$module, 'filterText']));
        $this->assertSame(99, has_filter('formatted_woocommerce_price', [$module, 'filterText']));
        $this->assertSame(99, has_filter('get_archives_link', [$module, 'filterArchivesLink']));
        $this->assertSame(99, has_filter('render_block_core/term-count', [$module, 'filterContent']));
        $this->assertSame(99, has_filter('render_block_core/query-total', [$module, 'filterContent']));
        $this->assertNotFalse(has_action('wp_enqueue_scripts', [$module, 'enqueueBlockPriceScript']));
    }

    public function test_each_new_hook_can_be_turned_off_by_name(): void
    {
        Functions\when('is_admin')->justReturn(false);
        Monkey\Filters\expectApplied('persian_kit_digit_conversion')
            ->andReturnUsing(static fn (bool $convert, string $hook): bool => !in_array($hook, ['document_title', 'list_cats'], true));

        $this->bootModule();

        $this->assertFalse(has_filter('document_title'));
        $this->assertFalse(has_filter('list_cats'));
        $this->assertNotFalse(has_filter('wp_title'));
    }

    public function test_block_price_script_loads_on_block_cart_and_checkout_only(): void
    {
        $enqueued = [];
        Functions\when('wp_enqueue_script')->alias(function (string $handle) use (&$enqueued) {
            $enqueued[] = $handle;
        });
        Functions\when('is_checkout')->justReturn(false);
        $module = $this->makeModule();

        Functions\when('is_cart')->justReturn(false);
        $module->enqueueBlockPriceScript();
        $this->assertSame([], $enqueued, 'not on other pages');

        Functions\when('is_cart')->justReturn(true);
        Functions\when('has_block')->justReturn(false);
        $module->enqueueBlockPriceScript();
        $this->assertSame([], $enqueued, 'not on the shortcode cart, whose prices the server converts');

        Functions\when('has_block')->alias(fn (string $block) => $block === 'woocommerce/cart');
        $module->enqueueBlockPriceScript();
        $this->assertSame(['persian-kit-woocommerce-block-prices'], $enqueued);
    }

    public function test_boot_skips_options_that_are_off(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $this->bootModule(['dates' => false, 'numbers' => false, 'prices' => false]);

        $this->assertFalse(has_filter('persian_kit_date_display'));
        $this->assertFalse(has_filter('persian_kit_gregorian_date_display'));
        $this->assertFalse(has_filter('number_format_i18n'));
        $this->assertFalse(has_filter('formatted_woocommerce_price'));
        $this->assertFalse(has_filter('get_archives_link'));
        $this->assertFalse(has_filter('render_block_core/term-count'));
        $this->assertFalse(has_filter('render_block_core/query-total'));
        $this->assertFalse(has_action('wp_enqueue_scripts'));
        $this->assertNotFalse(has_filter('the_content'));
    }

    public function test_new_installs_start_with_the_module_off(): void
    {
        $this->assertFalse(DigitConversionModule::defaults()['enabled']);
    }

    public function test_woocommerce_emails_start_off_and_are_sanitized(): void
    {
        $this->assertFalse(DigitConversionModule::defaults()['emails']);
        $this->assertTrue($this->makeModule()->sanitizeSettings(['emails' => '1'])['emails']);
        $this->assertFalse($this->makeModule()->sanitizeSettings([])['emails']);
    }

    public function test_email_digits_register_in_the_admin_when_the_option_is_on(): void
    {
        Functions\when('is_admin')->justReturn(true);
        Functions\when('wp_doing_ajax')->justReturn(false);

        $this->bootModule(['emails' => true], true);

        $this->assertNotFalse(has_filter('woocommerce_email_format_string'));
        $this->assertNotFalse(has_filter('woocommerce_order_number'));
        $this->assertNotFalse(has_action('woocommerce_before_template_part', [WooCommerceEmailDigits::class, 'enterTemplate']));
        $this->assertNotFalse(has_filter('woocommerce_email_editor_register_personalization_tags', [WooCommerceEmailDigits::class, 'scopeOrderTags']));
        $this->assertSame(31, has_filter('woocommerce_email_editor_register_personalization_tags', WooCommerceEmailDigits::class . '->filterMoneyTags()'));
        $this->assertNotFalse(has_filter('woocommerce_mail_content'));
        $this->assertFalse(has_filter('the_content'), 'the admin still keeps its digits');
    }

    public function test_email_digits_are_off_by_default_but_emails_are_still_tracked(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $this->bootModule([], true);

        $this->assertFalse(has_filter('woocommerce_email_format_string'));
        $this->assertNotFalse(has_action('woocommerce_before_template_part', [WooCommerceEmailDigits::class, 'enterTemplate']));
        $this->assertSame(30, has_filter('woocommerce_email_editor_register_personalization_tags', [WooCommerceEmailDigits::class, 'scopeOrderTags']), 'block email tags too');
        $this->assertFalse(has_filter('woocommerce_email_editor_register_personalization_tags', WooCommerceEmailDigits::class . '->filterMoneyTags()'));
        $this->assertFalse(has_filter('woocommerce_mail_content'));
    }

    public function test_email_digits_need_woocommerce(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $this->bootModule(['emails' => true]);

        $this->assertFalse(has_filter('woocommerce_email_format_string'));
        $this->assertFalse(has_action('woocommerce_before_template_part'));
    }

    public function test_email_digits_have_opt_outs(): void
    {
        Functions\when('is_admin')->justReturn(false);
        Monkey\Filters\expectApplied('persian_kit_digit_conversion')
            ->andReturnUsing(static fn (bool $convert, string $hook): bool => $hook !== 'woocommerce_email_order_number');

        $this->bootModule(['emails' => true], true);

        $this->assertNotFalse(has_filter('formatted_woocommerce_price'));
        $this->assertFalse(has_filter('woocommerce_order_number'), 'order numbers opted out on their own');
    }

    public function test_site_wide_filters_leave_emails_to_their_own_option(): void
    {
        $module = $this->makeModule();

        WooCommerceEmailDigits::enterTemplate('emails/email-order-details.php');
        try {
            $this->assertSame('120,000', $module->filterText('120,000'));
        } finally {
            WooCommerceEmailDigits::leaveTemplate('emails/email-order-details.php');
        }

        Functions\when('doing_filter')->alias(static fn (?string $hook = null): bool => $hook === 'woocommerce_email_format_string');
        $this->assertSame('10 Mehr 1405', $module->filterText('10 Mehr 1405'), 'subjects and headings too');
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function makeModule(array $settings = [], bool $withWooCommerce = false): DigitConversionModule
    {
        $merged = array_replace(DigitConversionModule::defaults(), ['enabled' => true], $settings);
        $manager = Mockery::mock(SettingsManager::class);
        $manager->shouldReceive('module')->andReturnUsing(
            static fn (string $key, ?string $subKey = null, mixed $default = null) => $subKey === null ? $merged : ($merged[$subKey] ?? $default)
        );

        if (!$withWooCommerce) {
            return new DigitConversionModule($manager);
        }

        return new DigitConversionModuleWithWooCommerce($manager);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function bootModule(array $settings = [], bool $withWooCommerce = false): DigitConversionModule
    {
        $module = $this->makeModule($settings, $withWooCommerce);
        $module->boot(Mockery::mock(ServiceContainer::class));

        return $module;
    }
}

class DigitConversionModuleWithWooCommerce extends DigitConversionModule
{
    protected function wooCommerceLoaded(): bool
    {
        return true;
    }
}
