<?php

namespace PersianKit\Tests\Integration\Multilingual;

use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\DigitConversion\DigitConversionModule;
use PersianKit\Tests\Integration\Support\UsesPolylang;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * An email in the customer's language: Polylang for WooCommerce switches
 * the locale for it, as switch_to_locale() does here.
 *
 * @group polylang
 */
class PolylangWooEmailTest extends WordPressIntegrationTestCase
{
    use UsesPolylang;

    /** @var list<array<string, mixed>> */
    private array $sent = [];

    public function set_up(): void
    {
        parent::set_up();

        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not installed next to the plugin.');
        }

        $this->setUpPolylang();

        update_option('timezone_string', 'Asia/Tehran');
        update_option('woocommerce_currency', 'IRT');
        update_option('woocommerce_price_num_decimals', 0);
        update_option('date_format', 'F j, Y');
        update_option(SettingsManager::OPTION_KEY, ['digit_conversion' => array_replace(DigitConversionModule::defaults(), ['enabled' => true, 'emails' => true])]);
        $settings = new SettingsManager();
        $settings->registerDefaults(DigitConversionModule::key(), DigitConversionModule::defaults());
        (new DigitConversionModule($settings))->boot(ServiceContainer::getInstance());

        // The plugin switching to the customer's language takes WooCommerce's place.
        add_filter('woocommerce_email_setup_locale', '__return_false');
        add_filter('woocommerce_email_restore_locale', '__return_false');

        // WooCommerce adds its email hooks when the mailer is first made, and
        // the test case removes hooks added during a test: make a new one.
        $instance = new \ReflectionProperty(\WC_Emails::class, 'instance');
        $instance->setValue(null, null);
        WC()->mailer();

        add_filter('pre_wp_mail', function ($return, array $atts) {
            $this->sent[] = $atts;

            return true;
        }, 10, 2);
    }

    public function test_an_english_email_keeps_english_digits_and_gregorian_dates(): void
    {
        $order = $this->order();

        switch_to_locale('en_US');
        $english = $this->send($order);
        restore_previous_locale();

        // The plain text email writes the order heading in capitals.
        $this->assertStringContainsStringIgnoringCase('March 21, 2026', $english);
        $this->assertDoesNotMatchRegularExpression('/[۰-۹]/u', $english);
    }

    public function test_a_persian_email_has_persian_digits_and_jalali_dates(): void
    {
        $persian = $this->send($this->order());

        $this->assertStringContainsString('۱۴۰۵', $persian);
        $this->assertStringNotContainsString('2026', $persian);
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
        $order->set_date_created('2026-03-21 10:00:00');
        $order->calculate_totals(false);
        $order->set_status('processing');
        $order->save();

        return $order;
    }

    private function send(\WC_Order $order): string
    {
        $email = WC()->mailer()->emails['WC_Email_Customer_Processing_Order'];
        $email->email_type = 'plain';
        $this->sent = [];

        $email->trigger($order->get_id(), $order);

        $this->assertCount(1, $this->sent);

        return $this->sent[0]['subject'] . "\n" . $this->sent[0]['message'];
    }
}
