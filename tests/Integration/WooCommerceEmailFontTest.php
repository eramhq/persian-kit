<?php

namespace PersianKit\Tests\Integration;

use Automattic\WooCommerce\EmailEditor\Email_Editor_Container;
use Automattic\WooCommerce\EmailEditor\Engine\Renderer\Renderer;
use Automattic\WooCommerce\EmailEditor\Engine\Templates\Templates;
use Automattic\WooCommerce\EmailEditor\Engine\Theme_Controller;
use PersianKit\Container\ServiceContainer;
use PersianKit\Modules\WooCommerce\PersianEmailFont;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * The Persian font in WooCommerce's emails, classic and block, as
 * WooCommerce renders them. The plugin booted with the option on (its
 * default); tests with it off remove its hooks. Runs when WooCommerce is
 * loaded (see tests/bootstrap.php).
 */
class WooCommerceEmailFontTest extends WordPressIntegrationTestCase
{
    private const HELVETICA = "'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif";

    /** @var list<array<string, mixed>> */
    private array $sent = [];

    private string $direction = 'ltr';

    public function set_up(): void
    {
        parent::set_up();

        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not installed next to the plugin.');
        }

        $this->direction = $GLOBALS['wp_locale']->text_direction;

        // WooCommerce adds its email hooks when the mailer is first made, and
        // the test case removes hooks added during a test: make a new one.
        $instance = new \ReflectionProperty(\WC_Emails::class, 'instance');
        $instance->setValue(null, null);
        WC()->mailer();

        $this->sent = [];
        add_filter('pre_wp_mail', function ($return, array $atts) {
            $this->sent[] = $atts;

            return true;
        }, 10, 2);
    }

    public function tear_down(): void
    {
        $GLOBALS['wp_locale']->text_direction = $this->direction;
        unset($GLOBALS['current_screen']);
        parent::tear_down();
    }

    public function test_a_persian_email_uses_the_persian_font_everywhere(): void
    {
        $this->inPersian();

        $body = $this->sendEmail('WC_Email_Customer_Processing_Order', $this->order())['message'];

        $this->assertMatchesRegularExpression('/<h1[^>]*font-family: ?Tahoma,/', $body, 'heading');
        $this->assertMatchesRegularExpression('/id="body_content_inner"[^>]*font-family: ?Tahoma,/', $body, 'body');
        $this->assertMatchesRegularExpression('/<t[dh][^>]*class="[^"]*\bfont-family\b[^"]*"[^>]*font-family: ?Tahoma,/', $body, 'order table');
        $this->assertStringNotContainsString('Helvetica', $body);
    }

    public function test_an_english_email_keeps_woocommerces_font(): void
    {
        $body = $this->sendEmail('WC_Email_Customer_Processing_Order', $this->order())['message'];

        $this->assertStringContainsString('Helvetica', $body);
        $this->assertStringNotContainsString('Tahoma', $body);
    }

    public function test_with_the_option_off_persian_emails_keep_woocommerces_font(): void
    {
        $this->inPersian();
        $this->turnOff();

        $body = $this->sendEmail('WC_Email_Customer_Processing_Order', $this->order())['message'];

        $this->assertStringContainsString('Helvetica', $body);
        $this->assertStringNotContainsString('Tahoma', $body);
    }

    public function test_a_font_the_store_picked_is_kept(): void
    {
        $this->inPersian();
        $features = get_option('woocommerce_feature_email_improvements_enabled');
        update_option('woocommerce_feature_email_improvements_enabled', 'yes');
        update_option('woocommerce_email_font_family', 'Georgia');

        try {
            $body = $this->sendEmail('WC_Email_Customer_Processing_Order', $this->order())['message'];
        } finally {
            update_option('woocommerce_feature_email_improvements_enabled', $features);
        }

        $this->assertMatchesRegularExpression('/<h1[^>]*font-family: ?Georgia,/', $body);
        $this->assertStringNotContainsString('Tahoma', $body);
    }

    public function test_a_font_another_plugin_adds_is_kept(): void
    {
        $this->inPersian();
        add_filter('woocommerce_email_styles', static fn (string $css): string => $css . "\n.td { font-family: Vazirmatn, sans-serif; }");

        $body = $this->sendEmail('WC_Email_Customer_Processing_Order', $this->order())['message'];

        $this->assertMatchesRegularExpression('/class="td[^"]*"[^>]*font-family: ?Vazirmatn, ?sans-serif/', $body);
        $this->assertMatchesRegularExpression('/<h1[^>]*font-family: ?Tahoma,/', $body);
    }

    public function test_the_admins_new_order_email_follows_the_admins_language(): void
    {
        add_filter('woocommerce_new_order_email_allows_resend', '__return_true');
        $order = $this->order();

        $english = $this->sendEmail('WC_Email_New_Order', $order)['message'];
        $this->inPersian();
        $persian = $this->sendEmail('WC_Email_New_Order', $order)['message'];

        $this->assertStringNotContainsString('Tahoma', $english);
        $this->assertMatchesRegularExpression('/<h1[^>]*font-family: ?Tahoma,/', $persian);
    }

    public function test_the_font_stack_can_be_changed_but_not_broken_out_of(): void
    {
        $this->assertSame(PersianEmailFont::STACK, PersianEmailFont::stack());

        add_filter('persian_kit_email_font_family', static fn (): string => 'Vazirmatn, Tahoma, sans-serif');
        $this->assertSame('Vazirmatn, Tahoma, sans-serif', PersianEmailFont::stack());

        add_filter('persian_kit_email_font_family', static fn (): string => 'Tahoma; } body { color: red', 20);
        $this->assertSame(PersianEmailFont::STACK, PersianEmailFont::stack());
    }

    public function test_the_block_email_theme_uses_the_persian_font_in_persian_only(): void
    {
        $this->requireEmailEditor();

        $english = (new Theme_Controller())->get_styles();
        $this->inPersian();
        $persian = (new Theme_Controller())->get_styles();

        $this->assertStringStartsWith('Arial', $english['typography']['fontFamily']);
        $this->assertSame(PersianEmailFont::STACK, $persian['typography']['fontFamily']);
        $this->assertSame(PersianEmailFont::STACK, $persian['elements']['heading']['typography']['fontFamily']);
        $this->assertSame('40px', $persian['elements']['h1']['typography']['fontSize'], 'other styles stay');

        $this->turnOff();
        $this->assertStringStartsWith('Arial', (new Theme_Controller())->get_styles()['typography']['fontFamily']);
    }

    public function test_the_stores_own_block_email_styles_win(): void
    {
        $this->requireEmailEditor();
        $this->inPersian();
        $styles = self::factory()->post->create([
            'post_type'    => 'wp_global_styles',
            'post_name'    => 'wp-global-styles-woocommerce-email',
            'post_status'  => 'publish',
            'post_content' => (string) wp_json_encode([
                'version'                     => 3,
                'isGlobalStylesUserThemeJSON' => true,
                'styles'                      => ['typography' => ['fontFamily' => "Georgia, Times, 'Times New Roman', serif"]],
            ]),
        ]);

        $theme = (new Theme_Controller())->get_styles();
        wp_delete_post($styles, true);

        $this->assertStringStartsWith('Georgia', $theme['typography']['fontFamily']);
        $this->assertSame(PersianEmailFont::STACK, $theme['elements']['heading']['typography']['fontFamily'], 'headings the store left alone');
    }

    public function test_block_emails_read_right_to_left_on_rtl_sites_only(): void
    {
        $this->requireEmailEditor();
        $css = '.email_content_wrapper { direction: ltr; text-align: left; }';
        $post = self::factory()->post->create_and_get();

        $this->inPersian();
        $this->assertSame($css, apply_filters('woocommerce_email_renderer_styles', $css, $post), 'Persian, left to right');

        $GLOBALS['wp_locale']->text_direction = 'rtl';
        $this->assertStringContainsString('.email_content_wrapper { direction: rtl; text-align: right; }', apply_filters('woocommerce_email_renderer_styles', $css, $post));
        $this->assertStringContainsString('.email_footer { direction: rtl; }', apply_filters('woocommerce_email_renderer_styles', $css, $post));

        remove_all_filters('locale');
        $this->assertSame($css, apply_filters('woocommerce_email_renderer_styles', $css, $post), 'right to left, not Persian');
    }

    public function test_a_rendered_block_email_has_the_persian_font_and_reads_right_to_left(): void
    {
        $this->requireEmailEditor();
        $this->inPersian();
        $GLOBALS['wp_locale']->text_direction = 'rtl';

        $html = $this->renderBlockEmail();

        $this->assertMatchesRegularExpression('/<body[^>]*font-family: ?Tahoma,/', $html);
        $this->assertMatchesRegularExpression('/<h2[^>]*font-family: ?Tahoma,/', $html);
        $this->assertMatchesRegularExpression('/class="email_content_wrapper"[^>]*direction: rtl; text-align: right/', $html);
        $this->assertStringNotContainsString('direction: ltr', $html);
    }

    public function test_a_rendered_english_block_email_is_left_alone(): void
    {
        $this->requireEmailEditor();

        $html = $this->renderBlockEmail();

        $this->assertMatchesRegularExpression('/<h2[^>]*font-family: ?Arial,/', $html);
        $this->assertMatchesRegularExpression('/class="email_content_wrapper"[^>]*direction: ltr/', $html);
        $this->assertStringNotContainsString('Tahoma', $html);
    }

    private function inPersian(): void
    {
        add_filter('locale', static fn () => 'fa_IR');
    }

    private function turnOff(): void
    {
        $font = ServiceContainer::getInstance()->get(PersianEmailFont::class);
        remove_filter('woocommerce_email_styles', [$font, 'filterClassicStyles'], 20);
        remove_filter('woocommerce_email_editor_theme_json', [$font, 'filterBlockTheme'], 20);
        remove_filter('woocommerce_email_renderer_styles', [$font, 'filterBlockStyles'], 20);
    }

    private function requireEmailEditor(): void
    {
        if (!class_exists(Email_Editor_Container::class) || !class_exists(Theme_Controller::class)) {
            $this->markTestSkipped('WooCommerce\'s block email editor is not installed next to the plugin.');
        }
    }

    /**
     * A heading and a paragraph through the editor's own renderer, with its
     * general template, as WooCommerce renders a block email.
     */
    private function renderBlockEmail(): string
    {
        $container = Email_Editor_Container::container();
        $container->get(Templates::class)->initialize(['post']);
        $post = self::factory()->post->create_and_get([
            'post_content' => "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">سفارش شما</h2>\n<!-- /wp:heading -->\n"
                . "<!-- wp:paragraph -->\n<p>سپاس از خرید شما.</p>\n<!-- /wp:paragraph -->",
        ]);

        return (string) $container->get(Renderer::class)->render($post, 'Subject', '', 'fa')['html'];
    }

    private function order(): \WC_Order
    {
        $product = new \WC_Product_Simple();
        $product->set_name('Tea');
        $product->set_regular_price('120000');
        $product->save();

        $order = wc_create_order();
        $order->add_product($product, 2);
        $order->set_billing_first_name('Ali');
        $order->set_billing_email('customer@example.test');
        $order->set_billing_country('IR');
        $order->calculate_totals(false);
        $order->set_status('processing');
        $order->save();

        $this->sent = [];

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    private function sendEmail(string $class, \WC_Order $order): array
    {
        $email = WC()->mailer()->emails[$class];
        $email->email_type = 'html';
        $this->sent = [];

        $email->trigger($order->get_id(), $order);

        $this->assertCount(1, $this->sent);

        return $this->sent[0];
    }
}
