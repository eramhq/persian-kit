<?php

namespace PersianKit\Tests\Integration;

use Automattic\WooCommerce\Utilities\OrderUtil;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Dependencies\Eram\Abzar\Digits\DigitConverter;
use PersianKit\Modules\DigitConversion\DigitConversionModule;
use PersianKit\Modules\WooCommerce\OrderNumberInput;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * WooCommerce's order emails as WooCommerce sends them, with Persian digits
 * in emails on or off. The plugin booted with the digit module off, so each
 * test boots one. Runs when WooCommerce is loaded (see tests/bootstrap.php).
 */
class WooCommerceEmailDigitsTest extends WordPressIntegrationTestCase
{
    private const PHONE = '09123456789';
    private const POSTCODE = '1234567891';

    /** @var list<array<string, mixed>> */
    private array $sent = [];

    public function set_up(): void
    {
        parent::set_up();

        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not installed next to the plugin.');
        }

        update_option('woocommerce_currency', 'IRT');
        update_option('woocommerce_price_num_decimals', 0);
        update_option('date_format', 'F j, Y');
        // A site title with digits keeps them in subjects.
        update_option('blogname', 'Shop 24');

        self::newMailer();

        $this->sent = [];
        add_filter('pre_wp_mail', function ($return, array $atts) {
            $this->sent[] = $atts;

            return true;
        }, 10, 2);
    }

    public function tear_down(): void
    {
        unset($GLOBALS['current_screen']);
        parent::tear_down();
    }

    public function test_an_email_sent_from_the_admin_has_persian_digits_where_people_read_them(): void
    {
        $this->inAdmin();
        $this->inPersian();
        $this->bootDigits(['emails' => true]);
        $order = $this->order();
        $number = DigitConverter::toPersian((string) $order->get_order_number());

        $body = $this->sendEmail('WC_Email_Customer_Processing_Order', $order)['message'];

        $this->assertStringContainsString('#' . $number, $body, 'order number');
        $this->assertStringContainsString('۲۴۰,۰۰۰', $body, 'price');
        $this->assertMatchesRegularExpression('/(×|&times;)?\s*۲\s*<\/td>/u', $body, 'quantity');
        $this->assertStringContainsString('فروردین ۱, ۱۴۰۵', $body, 'Jalali date');

        $this->assertStringContainsString(self::PHONE, $body);
        $this->assertStringContainsString(self::POSTCODE, $body);
        foreach ($this->hrefs($body) as $href) {
            $this->assertDoesNotMatchRegularExpression('/[۰-۹]/u', $href);
        }

        $markup = $this->orderStructuredData();
        $this->assertSame((string) $order->get_order_number(), $markup['orderNumber']);
        $this->assertSame(2, $markup['acceptedOffer'][0]['priceSpecification']['eligibleQuantity']['value']);
        $this->assertSame('IRR', $markup['priceCurrency']);
        $this->assertDoesNotMatchRegularExpression('/[۰-۹]/u', (string) wp_json_encode($markup, JSON_UNESCAPED_UNICODE));
    }

    public function test_the_subject_and_heading_have_the_order_number_in_persian_digits(): void
    {
        $this->inAdmin();
        $this->inPersian();
        add_filter('woocommerce_new_order_email_allows_resend', '__return_true');
        $this->bootDigits(['emails' => true]);
        $order = $this->order();
        $number = DigitConverter::toPersian((string) $order->get_order_number());

        $mail = $this->sendEmail('WC_Email_New_Order', $order);

        $this->assertSame('[Shop 24]: New order #' . $number, $mail['subject']);
        $this->assertStringContainsString('#' . $number, $mail['message']);
    }

    public function test_the_plain_text_email_keeps_its_links_working(): void
    {
        $this->inAdmin();
        $this->inPersian();
        $this->bootDigits(['emails' => true]);
        $order = $this->order();

        // The invoice for an unpaid order links to its payment page.
        $order->set_status('pending');
        $order->save();
        $body = $this->sendEmail('WC_Email_Customer_Invoice', $order, 'plain')['message'];

        $this->assertStringContainsString('#' . DigitConverter::toPersian((string) $order->get_order_number()), $body);
        $this->assertMatchesRegularExpression('/(×|X) ۲/u', $body, 'quantity');
        $this->assertStringContainsString('۱۴۰۵', $body);
        $this->assertStringContainsString(self::PHONE, $body);
        $this->assertGreaterThan(0, preg_match_all('#https?://\S+#u', $body, $urls));
        foreach ($urls[0] as $url) {
            $this->assertDoesNotMatchRegularExpression('/[۰-۹]/u', $url);
        }
    }

    public function test_with_the_option_off_emails_keep_english_digits_even_where_the_site_converts(): void
    {
        $this->inPersian();
        $this->bootDigits(['emails' => false]);
        $order = $this->order();

        $mail = $this->sendEmail('WC_Email_Customer_Processing_Order', $order);

        $this->assertDoesNotMatchRegularExpression('/[۰-۹]/u', $mail['subject'] . $mail['message']);
        $this->assertSame('۱۲', apply_filters('formatted_woocommerce_price', '12', 12, 0, '.', ','), 'the shop still converts');
    }

    public function test_emails_in_english_keep_english_digits(): void
    {
        $this->inAdmin();
        $this->bootDigits(['emails' => true]);
        $order = $this->order();

        $mail = $this->sendEmail('WC_Email_Customer_Processing_Order', $order);

        $this->assertDoesNotMatchRegularExpression('/[۰-۹]/u', $mail['subject'] . $mail['message']);
    }

    public function test_the_customer_note_subject_has_a_jalali_order_date_in_persian_digits(): void
    {
        $this->inAdmin();
        $this->inPersian();
        $this->bootDigits(['emails' => true]);
        $order = $this->order();

        $order->add_order_note('Shipped', 1);
        $subjects = array_column($this->sent, 'subject');

        $this->assertNotEmpty($subjects);
        $this->assertStringContainsString('فروردین ۱, ۱۴۰۵', end($subjects));
    }

    public function test_the_admin_order_search_finds_an_order_number_typed_in_persian_digits(): void
    {
        $this->inAdmin();
        $order = $this->order();
        (new OrderNumberInput())->register();
        $typed = DigitConverter::toPersian((string) $order->get_id());

        // The orders list with order tables (HPOS) passes its args to wc_get_orders().
        if (OrderUtil::custom_orders_table_usage_is_enabled()) {
            $args = apply_filters('woocommerce_order_list_table_prepare_items_query_args', ['s' => $typed, 'return' => 'ids']);
            $this->assertContains($order->get_id(), wc_get_orders($args));
        }

        // The orders list with posts storage, and other searches.
        $this->assertContains($order->get_id(), wc_order_search($typed));
    }

    private function inAdmin(): void
    {
        set_current_screen('edit-shop_order');
    }

    private function inPersian(): void
    {
        add_filter('locale', static fn () => 'fa_IR');
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function bootDigits(array $settings): void
    {
        update_option(SettingsManager::OPTION_KEY, ['digit_conversion' => array_replace(DigitConversionModule::defaults(), ['enabled' => true], $settings)]);
        $manager = new SettingsManager();
        $manager->registerDefaults(DigitConversionModule::key(), DigitConversionModule::defaults());

        (new DigitConversionModule($manager))->boot(ServiceContainer::getInstance());
    }

    private function order(): \WC_Order
    {
        $product = new \WC_Product_Simple();
        $product->set_name('Tea');
        $product->set_regular_price('120000');
        $product->save();

        $order = wc_create_order();
        $order->set_currency('IRT');
        $order->add_product($product, 2);
        $order->set_billing_first_name('Ali');
        $order->set_billing_email('customer@example.test');
        $order->set_billing_phone(self::PHONE);
        $order->set_billing_postcode(self::POSTCODE);
        $order->set_billing_country('IR');
        $order->set_date_created('2026-03-21 10:00:00');
        $order->calculate_totals(false);
        $order->set_status('processing');
        $order->save();

        $this->sent = [];

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    private function sendEmail(string $class, \WC_Order $order, string $type = 'html'): array
    {
        $email = WC()->mailer()->emails[$class];
        $email->email_type = $type;
        $this->sent = [];

        $email->trigger($order->get_id(), $order);

        $this->assertCount(1, $this->sent);

        return $this->sent[0];
    }

    /**
     * @return list<string>
     */
    private function hrefs(string $html): array
    {
        preg_match_all('/href="([^"]*)"/', $html, $matches);

        return $matches[1];
    }

    /**
     * The order's structured data, as the email made it. WooCommerce's
     * style inliner then drops it with the hidden element around it, but
     * older versions and other inliners keep it.
     *
     * @return array<string, mixed>
     */
    private function orderStructuredData(): array
    {
        $orders = array_filter(WC()->structured_data->get_data(), static fn ($data) => is_array($data) && ($data['@type'] ?? null) === 'Order');
        $this->assertNotEmpty($orders, 'the email made the order\'s structured data');

        return end($orders);
    }
}
