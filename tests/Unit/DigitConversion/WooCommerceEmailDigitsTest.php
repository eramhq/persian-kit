<?php

namespace PersianKit\Tests\Unit\DigitConversion;

use Automattic\WooCommerce\EmailEditor\Engine\PersonalizationTags\Personalization_Tag;
use Automattic\WooCommerce\EmailEditor\Engine\PersonalizationTags\Personalization_Tags_Registry;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\DigitConversion\WooCommerceEmailDigits;
use PHPUnit\Framework\TestCase;
use PersianKit\Service\Language\ContentLanguage;
use PersianKit\Tests\Unit\Support\UsesLanguages;

class WooCommerceEmailDigitsTest extends TestCase
{
    use UsesLanguages;

    private int $entered = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        ContentLanguage::reset();
        Functions\when('determine_locale')->justReturn('fa_IR');
    }

    protected function tearDown(): void
    {
        while ($this->entered-- > 0) {
            WooCommerceEmailDigits::leaveTemplate('emails/email-header.php');
        }
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_adds_the_converters(): void
    {
        $digits = new WooCommerceEmailDigits();
        $digits->register();

        $this->assertSame(99, has_filter('formatted_woocommerce_price', [$digits, 'filterText']));
        $this->assertSame(99, has_filter('woocommerce_email_order_item_quantity', [$digits, 'filterQuantity']));
        $this->assertSame(99, has_filter('date_i18n', [$digits, 'filterDate']));
        $this->assertSame(20, has_filter('woocommerce_email_format_string', [$digits, 'filterFormatString']));
        $this->assertSame(PHP_INT_MAX, has_filter('woocommerce_order_number', [$digits, 'filterText']));
        $this->assertSame(PHP_INT_MAX, has_filter('clean_url', [$digits, 'filterUrl']));
        $this->assertSame(PHP_INT_MAX, has_filter('woocommerce_structured_data_order', [$digits, 'filterStructuredData']));
        $this->assertSame(31, has_filter('woocommerce_email_editor_register_personalization_tags', [$digits, 'filterMoneyTags']));
        $this->assertSame(PHP_INT_MAX, has_filter('woocommerce_mail_style_inline_callback', [$digits, 'filterStyleInlineCallback']));
        $this->assertSame(PHP_INT_MAX, has_filter('woocommerce_mail_content', [$digits, 'filterMailContent']));
    }

    public function test_tracking_hooks_the_template_parts_and_the_order_tags(): void
    {
        WooCommerceEmailDigits::trackRendering();

        $this->assertNotFalse(has_action('woocommerce_before_template_part', [WooCommerceEmailDigits::class, 'enterTemplate']));
        $this->assertNotFalse(has_action('woocommerce_after_template_part', [WooCommerceEmailDigits::class, 'leaveTemplate']));
        $this->assertSame(30, has_filter('woocommerce_email_editor_register_personalization_tags', [WooCommerceEmailDigits::class, 'scopeOrderTags']));
    }

    public function test_order_tags_render_as_part_of_the_email(): void
    {
        $registry = $this->registry([
            'woocommerce/order-number' => static fn (): string => WooCommerceEmailDigits::isRendering() ? 'inside' : 'outside',
            'woocommerce/site-title'   => static fn (): string => WooCommerceEmailDigits::isRendering() ? 'inside' : 'outside',
        ]);

        WooCommerceEmailDigits::scopeOrderTags($registry);

        $this->assertSame('inside', $registry->get_by_token('[woocommerce/order-number]')->execute_callback([]));
        $this->assertSame('outside', $registry->get_by_token('[woocommerce/site-title]')->execute_callback([]), 'only order tags');
        $this->assertFalse(WooCommerceEmailDigits::isRendering());
    }

    public function test_the_order_tag_scope_closes_when_a_tag_throws(): void
    {
        $registry = $this->registry([
            'woocommerce/order-number' => static function (): string {
                throw new \RuntimeException('no order');
            },
        ]);
        WooCommerceEmailDigits::scopeOrderTags($registry);

        try {
            $registry->get_by_token('[woocommerce/order-number]')->execute_callback([]);
            $this->fail('the exception passes through');
        } catch (\RuntimeException $e) {
            $this->assertSame('no order', $e->getMessage());
        }

        $this->assertFalse(WooCommerceEmailDigits::isRendering());
    }

    public function test_money_tags_get_persian_digits_in_persian_emails(): void
    {
        $registry = $this->registry([
            'woocommerce/order-total'    => static fn (): string => '220000.00',
            'woocommerce/order-shipping' => static fn (): string => '<span class="amount"><bdi>20,000&nbsp;<span>&#36;</span></bdi></span>',
            'woocommerce/order-number'   => static fn (): string => '109',
        ]);
        (new WooCommerceEmailDigits())->filterMoneyTags($registry);

        $this->assertSame('۲۲۰۰۰۰.۰۰', $registry->get_by_token('[woocommerce/order-total]')->execute_callback([]));
        $this->assertSame(
            '<span class="amount"><bdi>۲۰,۰۰۰&nbsp;<span>&#36;</span></bdi></span>',
            $registry->get_by_token('[woocommerce/order-shipping]')->execute_callback([]),
            'wc_price() markup is kept'
        );
        $this->assertSame('109', $registry->get_by_token('[woocommerce/order-number]')->execute_callback([]), 'the order number has its own filter');

        Functions\when('determine_locale')->justReturn('en_US');
        $this->assertSame('220000.00', $registry->get_by_token('[woocommerce/order-total]')->execute_callback([]));
    }

    public function test_links_in_the_finished_email_keep_english_digits(): void
    {
        if (!class_exists(\WP_HTML_Tag_Processor::class)) {
            $this->markTestSkipped('Needs the WordPress HTML API; the integration tests cover it too.');
        }

        $html = '<p>سفارش ۱۰۹</p><a href="https://example.test/?o=۱۰۹&amp;p=1" title="سفارش ۱۰۹">سفارش ۱۰۹</a>';

        $this->assertSame(
            '<p>سفارش ۱۰۹</p><a href="https://example.test/?o=109&#038;p=1" title="سفارش ۱۰۹">سفارش ۱۰۹</a>',
            (new WooCommerceEmailDigits())->filterMailContent($html)
        );
    }

    public function test_the_style_inliner_gets_the_content_through_the_email_once_more(): void
    {
        $digits = new WooCommerceEmailDigits();
        $email = new class ($digits) {
            /** @var list<mixed> */
            public array $callbacks = [];
            public \Closure $default;

            public function __construct(private WooCommerceEmailDigits $digits)
            {
                $this->default = static fn (string $content): string => '<inlined>' . $content;
            }

            public function style_inline(string $content): string
            {
                // As WC_Email: the filter picks the callback, here the default.
                $callback = $this->digits->filterStyleInlineCallback($this->default, $content, $this);
                $this->callbacks[] = $callback;

                return $callback($content);
            }
        };

        $this->assertSame('<inlined><a href="https://example.test/?o=109">109</a>', $email->style_inline('<a href="https://example.test/?o=109">109</a>'));
        $this->assertCount(2, $email->callbacks, 'the replacement inlines through the email again');
        $this->assertNotSame($email->default, $email->callbacks[0]);
        $this->assertSame($email->default, $email->callbacks[1], 'the second time, the default inliner');
        $this->assertSame('<inlined>x', $email->style_inline('x'), 'and again on the next email');

        $this->assertSame('callback', $digits->filterStyleInlineCallback('callback', 'x', null), 'needs the email');
    }

    public function test_messages_without_persian_digits_in_links_are_left_alone(): void
    {
        $digits = new WooCommerceEmailDigits();

        $this->assertSame('<a href="https://example.test/?o=109">109</a>', $digits->filterMailContent('<a href="https://example.test/?o=109">109</a>'));
        $this->assertSame("سفارش ۱۰۹\nhttps://example.test/?o=109", $digits->filterMailContent("سفارش ۱۰۹\nhttps://example.test/?o=109"));
        $this->assertNull($digits->filterMailContent(null));
    }

    public function test_values_are_left_alone_outside_an_email(): void
    {
        $digits = new WooCommerceEmailDigits();

        $this->assertSame('120,000', $digits->filterText('120,000'));
        $this->assertSame(2, $digits->filterQuantity(2));
        $this->assertSame(123, $digits->filterText(123));
        $this->assertSame('10 مهر 1405', $digits->filterDate('10 مهر 1405', 'j F Y'));
        $this->assertSame('https://example.test/?order=۱۲۳', $digits->filterUrl('https://example.test/?order=۱۲۳'));
    }

    public function test_values_are_converted_inside_an_email(): void
    {
        $digits = new WooCommerceEmailDigits();
        $this->enterEmail();

        $this->assertSame('۱۲۰,۰۰۰', $digits->filterText('120,000'));
        $this->assertSame('۱۲۳', $digits->filterText(123), 'order numbers are the order ID, an int');
        $this->assertSame('۲', $digits->filterQuantity(2));
        $this->assertSame('<del>۲</del> <ins>۱</ins>', $digits->filterQuantity('<del>2</del> <ins>1</ins>'));
        $this->assertSame('۱۰ مهر ۱۴۰۵', $digits->filterDate('10 مهر 1405', 'j F Y'));
        $this->assertSame('October ۲, ۲۰۲۶', $digits->filterDate('October 2, 2026', 'F j, Y'), 'Gregorian dates too');
    }

    public function test_machine_date_formats_keep_english_digits(): void
    {
        $digits = new WooCommerceEmailDigits();
        $this->enterEmail();

        $this->assertSame('2026-10-02T10:00:00+03:30', $digits->filterDate('2026-10-02T10:00:00+03:30', 'c'));
        $this->assertSame('2026-10-02 10:00:00', $digits->filterDate('2026-10-02 10:00:00', 'Y-m-d H:i:s'));
    }

    public function test_only_email_template_parts_count_and_nesting_is_tracked(): void
    {
        WooCommerceEmailDigits::enterTemplate('single-product/price.php');
        $this->assertFalse(WooCommerceEmailDigits::isRendering());

        $this->enterEmail('emails/customer-processing-order.php');
        $this->enterEmail('emails/plain/email-order-items.php');
        WooCommerceEmailDigits::leaveTemplate('emails/plain/email-order-items.php');
        $this->entered--;
        WooCommerceEmailDigits::leaveTemplate('single-product/price.php');
        $this->assertTrue(WooCommerceEmailDigits::isRendering());

        WooCommerceEmailDigits::leaveTemplate('emails/customer-processing-order.php');
        $this->entered--;
        $this->assertFalse(WooCommerceEmailDigits::isRendering());

        WooCommerceEmailDigits::leaveTemplate('emails/email-footer.php');
        $this->assertFalse(WooCommerceEmailDigits::isRendering(), 'never below zero');
    }

    public function test_emails_in_other_languages_keep_english_digits(): void
    {
        Functions\when('determine_locale')->justReturn('en_US');
        $digits = new WooCommerceEmailDigits();
        $this->enterEmail();

        $this->assertSame('120,000', $digits->filterText('120,000'));
        $this->assertSame(2, $digits->filterQuantity(2));
        $this->assertSame('October 2, 2026', $digits->filterDate('October 2, 2026', 'F j, Y'));
        $this->assertSame(
            'Your Shop order #123 is complete',
            $digits->filterFormatString('Your Shop order #123 is complete', $this->email(['{order_number}' => '123']))
        );
    }

    public function test_on_multilingual_sites_the_emails_language_decides(): void
    {
        $digits = new WooCommerceEmailDigits();
        $this->enterEmail();

        // An admin with a Persian profile changes an English customer's order:
        // WooCommerce Multilingual switches WPML's language for the email.
        $source = $this->inLanguage('fa_IR', true);
        $source->switched = 'en_US';
        $this->assertSame('120,000', $digits->filterText('120,000'));

        // Polylang for WooCommerce switches the locale instead.
        $source->switched = null;
        Functions\when('is_locale_switched')->justReturn(true);
        Functions\when('determine_locale')->justReturn('en_US');
        $this->assertSame('120,000', $digits->filterText('120,000'));

        Functions\when('determine_locale')->justReturn('fa_IR');
        $this->assertSame('۱۲۰,۰۰۰', $digits->filterText('120,000'));
    }

    public function test_subject_converts_the_order_number_and_date_only(): void
    {
        $digits = new WooCommerceEmailDigits();
        $email = $this->email(['{site_title}' => 'Shop 24', '{order_number}' => '123', '{order_date}' => '10 مهر 1405']);

        $this->assertSame(
            '[Shop 24]: New order #۱۲۳ from ۱۰ مهر ۱۴۰۵',
            $digits->filterFormatString('[Shop 24]: New order #123 from 10 مهر 1405', $email)
        );
    }

    public function test_subject_matches_whole_values_only(): void
    {
        $digits = new WooCommerceEmailDigits();

        $this->assertSame(
            'Since 2012: order #۱۲',
            $digits->filterFormatString('Since 2012: order #12', $this->email(['{order_number}' => '12']))
        );
    }

    public function test_subject_tolerates_a_missing_email_or_placeholders(): void
    {
        $digits = new WooCommerceEmailDigits();

        $this->assertSame('Order #123', $digits->filterFormatString('Order #123'));
        $this->assertSame('Order #123', $digits->filterFormatString('Order #123', $this->email([])));
        $this->assertSame('', $digits->filterFormatString('', $this->email(['{order_number}' => '123'])));
    }

    public function test_the_order_number_opt_out_keeps_order_numbers_english(): void
    {
        $digits = new WooCommerceEmailDigits(false);
        $digits->register();
        $this->enterEmail();

        $this->assertFalse(has_filter('woocommerce_order_number', [$digits, 'filterText']));
        $this->assertSame('۱۲۰,۰۰۰', $digits->filterText('120,000'), 'prices still convert');
        $this->assertSame(
            'Order #123 from ۱۰ مهر ۱۴۰۵',
            $digits->filterFormatString('Order #123 from 10 مهر 1405', $this->email(['{order_number}' => '123', '{order_date}' => '10 مهر 1405']))
        );
    }

    public function test_links_keep_english_digits_inside_an_email(): void
    {
        $digits = new WooCommerceEmailDigits();
        $this->enterEmail();

        $this->assertSame('https://example.test/track?order=123', $digits->filterUrl('https://example.test/track?order=۱۲۳'));
    }

    public function test_structured_data_goes_back_to_english_digits(): void
    {
        $markup = (new WooCommerceEmailDigits())->filterStructuredData([
            '@type'         => 'Order',
            'orderNumber'   => '۱۲۳',
            'price'         => '240000',
            'acceptedOffer' => [[
                'priceSpecification' => [
                    'price'            => '۱۲۰۰۰۰',
                    'eligibleQuantity' => ['@type' => 'QuantitativeValue', 'value' => '۲'],
                ],
            ]],
            'merchant'      => ['name' => 'Shop'],
        ]);

        $this->assertSame('123', $markup['orderNumber']);
        $this->assertSame('240000', $markup['price']);
        $this->assertSame('120000', $markup['acceptedOffer'][0]['priceSpecification']['price']);
        $this->assertSame(2, $markup['acceptedOffer'][0]['priceSpecification']['eligibleQuantity']['value']);
        $this->assertSame('Shop', $markup['merchant']['name']);
    }

    public function test_structured_data_keeps_non_numeric_quantities_and_other_values(): void
    {
        $digits = new WooCommerceEmailDigits();

        $this->assertSame(['eligibleQuantity' => ['value' => '<del>2</del>']], $digits->filterStructuredData(['eligibleQuantity' => ['value' => '<del>۲</del>']]));
        $this->assertNull($digits->filterStructuredData(null));
    }

    /**
     * @param array<string, callable> $callbacks By token.
     */
    private function registry(array $callbacks): Personalization_Tags_Registry
    {
        require_once dirname(__DIR__) . '/Support/email-editor-stubs.php';

        $registry = new Personalization_Tags_Registry();
        foreach ($callbacks as $token => $callback) {
            $registry->register(new Personalization_Tag($token, $token, 'Order', $callback));
        }

        return $registry;
    }

    private function enterEmail(string $template = 'emails/email-header.php'): void
    {
        WooCommerceEmailDigits::enterTemplate($template);
        $this->entered++;
    }

    /**
     * @param array<string, string> $placeholders
     */
    private function email(array $placeholders): object
    {
        return (object) ['placeholders' => $placeholders];
    }
}
