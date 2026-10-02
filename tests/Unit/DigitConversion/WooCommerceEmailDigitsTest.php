<?php

namespace PersianKit\Tests\Unit\DigitConversion;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\DigitConversion\WooCommerceEmailDigits;
use PHPUnit\Framework\TestCase;

class WooCommerceEmailDigitsTest extends TestCase
{
    private int $entered = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
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
    }

    public function test_tracking_hooks_the_template_parts(): void
    {
        WooCommerceEmailDigits::trackRendering();

        $this->assertNotFalse(has_action('woocommerce_before_template_part', [WooCommerceEmailDigits::class, 'enterTemplate']));
        $this->assertNotFalse(has_action('woocommerce_after_template_part', [WooCommerceEmailDigits::class, 'leaveTemplate']));
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
