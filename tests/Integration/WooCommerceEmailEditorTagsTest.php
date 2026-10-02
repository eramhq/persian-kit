<?php

namespace PersianKit\Tests\Integration;

use Automattic\WooCommerce\EmailEditor\Engine\Logger\Email_Editor_Logger;
use Automattic\WooCommerce\EmailEditor\Engine\PersonalizationTags\Personalization_Tags_Registry;
use Automattic\WooCommerce\EmailEditor\Engine\Personalizer;
use Automattic\WooCommerce\Internal\EmailEditor\PersonalizationTagManager;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Dependencies\Eram\Abzar\Digits\DigitConverter;
use PersianKit\Modules\DigitConversion\DigitConversionModule;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Personalization tags in WooCommerce's block email editor, filled in by its
 * own Personalizer with WooCommerce's own tags, then passed through the
 * finished email's filter. Runs when WooCommerce is loaded with the editor's
 * package (see tests/bootstrap.php).
 */
class WooCommerceEmailEditorTagsTest extends WordPressIntegrationTestCase
{
    private const CONTENT = '<p><!--[woocommerce/order-number]--> <!--[woocommerce/order-date]--> <!--[woocommerce/order-total]--></p>'
        . '<a data-link-href="[woocommerce/order-number]">x</a>';

    public function set_up(): void
    {
        parent::set_up();

        if (!class_exists('WooCommerce') || !class_exists(Personalizer::class) || !class_exists(PersonalizationTagManager::class)) {
            $this->markTestSkipped('WooCommerce with its block email editor is not installed next to the plugin.');
        }

        update_option('woocommerce_currency', 'IRT');
        update_option('date_format', 'F j, Y');
    }

    public function test_order_tags_have_persian_digits_and_a_jalali_date(): void
    {
        $this->inPersian();
        $this->bootDigits(['emails' => true]);
        $order = $this->order();

        $email = $this->personalize($order);

        $this->assertStringContainsString('<p>' . DigitConverter::toPersian((string) $order->get_order_number()) . ' ', $email, 'order number');
        $this->assertStringContainsString('فروردین ۱, ۱۴۰۵', $email, 'Jalali date');
        $this->assertStringContainsString(DigitConverter::toPersian((string) $order->get_total()) . '</p>', $email, 'total');
        $this->assertStringContainsString('۲۴۰۰۰۰', $email);
        // The editor sets a link to the tag's value through esc_url(), and
        // the style inliner would percent-encode Persian digits.
        $this->assertMatchesRegularExpression('#href="(http://)?' . $order->get_order_number() . '"#', $email, 'links keep English digits');
    }

    public function test_with_the_option_off_the_date_is_jalali_in_english_digits(): void
    {
        $this->inPersian();
        $this->bootDigits(['emails' => false]);
        $order = $this->order();

        $email = $this->personalize($order);

        $this->assertStringContainsString('فروردین 1, 1405', $email);
        $this->assertStringContainsString('<p>' . $order->get_order_number() . ' ', $email);
        $this->assertDoesNotMatchRegularExpression('/[۰-۹]/u', $email);
    }

    public function test_emails_in_english_keep_english_digits(): void
    {
        $this->bootDigits(['emails' => true]);
        $order = $this->order();

        $email = $this->personalize($order);

        $this->assertStringContainsString('<p>' . $order->get_order_number() . ' ', $email);
        $this->assertDoesNotMatchRegularExpression('/[۰-۹]/u', $email);
    }

    public function test_only_links_in_the_finished_email_go_back_to_english_digits(): void
    {
        $this->bootDigits(['emails' => true]);
        $html = '<p title="سفارش ۱۰۹">سفارش ۱۰۹</p><a href="https://example.test/?o=۱۰۹&amp;p=1" title="سفارش ۱۰۹">سفارش ۱۰۹</a>';

        $this->assertSame(
            '<p title="سفارش ۱۰۹">سفارش ۱۰۹</p><a href="https://example.test/?o=109&#038;p=1" title="سفارش ۱۰۹">سفارش ۱۰۹</a>',
            apply_filters('woocommerce_mail_content', $html)
        );
    }

    public function test_permalinks_keep_their_encoded_digits(): void
    {
        // A slug with Persian digits, as WordPress stores and links it.
        $this->bootDigits(['emails' => true]);
        $html = '<p>۱۰۳</p><a href="https://example.test/product/%DA%86%D8%A7%DB%8C-%DB%B1%DB%B0%DB%B0/">چای ۱۰۰</a>';

        $this->assertSame($html, apply_filters('woocommerce_mail_content', $html));
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
        $order->set_billing_email('customer@example.test');
        $order->set_date_created('2026-03-21 10:00:00');
        $order->calculate_totals(false);
        $order->save();

        return $order;
    }

    /**
     * The tags as the editor fills them in: a registry built through the
     * filter with WooCommerce's tags, then the email as WooCommerce sends it.
     */
    private function personalize(\WC_Order $order): string
    {
        wc_get_container()->get(PersonalizationTagManager::class)->init();
        $registry = new Personalization_Tags_Registry(new Email_Editor_Logger());
        $registry->initialize();

        $personalizer = new Personalizer($registry);
        $personalizer->set_context(['order' => $order]);

        // As WC_Email::send(): the style inliner, then the finished email's filter.
        $inlined = WC()->mailer()->emails['WC_Email_New_Order']->style_inline($personalizer->personalize_content(self::CONTENT));

        return (string) apply_filters('woocommerce_mail_content', $inlined);
    }
}
