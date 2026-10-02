<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use Automattic\WooCommerce\EmailEditor\Engine\PersonalizationTags\Personalization_Tag;
use Automattic\WooCommerce\EmailEditor\Engine\PersonalizationTags\Personalization_Tags_Registry;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\WooCommerce\WooDateDisplayFilter;
use PHPUnit\Framework\TestCase;

class WooDateDisplayFilterTest extends TestCase
{
    /** 2026-03-21 00:00 local, as the offset timestamp date_i18n receives. */
    private const NOWRUZ_1405 = 1774051200;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('wc_get_order_types')->justReturn(['shop_order', 'shop_order_refund']);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_adds_context_hooks(): void
    {
        $filter = new WooDateDisplayFilter();
        $filter->register();

        $this->assertNotFalse(has_filter('date_i18n', [$filter, 'filterDateI18n']));
        $this->assertNotFalse(has_action('current_screen', [$filter, 'detectScreen']));
        $this->assertNotFalse(has_action('woocommerce_before_template_part', [$filter, 'enterTemplate']));
        $this->assertNotFalse(has_action('woocommerce_after_template_part', [$filter, 'leaveTemplate']));
        $this->assertNotFalse(has_filter('render_block_data', [$filter, 'enterBlock']));
        $this->assertNotFalse(has_filter('render_block', [$filter, 'leaveBlock']));
        $this->assertSame(10, has_filter('woocommerce_email_format_string', [$filter, 'filterEmailOrderDate']));
        $this->assertSame(20, has_filter('woocommerce_email_editor_register_personalization_tags', [$filter, 'filterPersonalizationTags']));
    }

    public function test_the_email_editor_order_date_tag_renders_in_the_woocommerce_context(): void
    {
        require_once dirname(__DIR__) . '/Support/email-editor-stubs.php';
        $filter = new WooDateDisplayFilter();
        $inContext = static fn (): string => $filter->isWooDateContext() ? 'woo' : 'outside';
        $registry = new Personalization_Tags_Registry();
        $registry->register(new Personalization_Tag('Order Date', 'woocommerce/order-date', 'Order', static fn (array $context, array $args = []): string => $inContext() . '|' . ($args['format'] ?? '')));
        $registry->register(new Personalization_Tag('Order Number', 'woocommerce/order-number', 'Order', $inContext));

        $this->assertSame($registry, $filter->filterPersonalizationTags($registry));

        $this->assertSame('woo|j F Y', $registry->get_by_token('[woocommerce/order-date]')->execute_callback([], ['format' => 'j F Y']));
        $this->assertSame('outside', $registry->get_by_token('[woocommerce/order-number]')->execute_callback([]), 'only the date tag');
        $this->assertFalse($filter->isWooDateContext(), 'closed after');
    }

    public function test_order_date_in_email_subjects_becomes_jalali(): void
    {
        Functions\when('wc_date_format')->justReturn('F j, Y');
        $email = $this->email('March 21, 2026', new \DateTimeImmutable('2026-03-21 10:00:00', new \DateTimeZone('Asia/Tehran')));

        $subject = (new WooDateDisplayFilter())->filterEmailOrderDate('Note added to your Shop order from March 21, 2026', $email);

        $this->assertSame('Note added to your Shop order from فروردین 1, 1405', $subject);
        $this->assertSame('فروردین 1, 1405', $email->placeholders['{order_date}'], 'kept for the filters after this one');
    }

    public function test_order_date_in_email_subjects_needs_an_order_with_a_date(): void
    {
        Functions\when('wc_date_format')->justReturn('F j, Y');
        $filter = new WooDateDisplayFilter();
        $subject = 'Your Shop order from March 21, 2026';

        $this->assertSame($subject, $filter->filterEmailOrderDate($subject, $this->email('March 21, 2026', null)));
        $this->assertSame($subject, $filter->filterEmailOrderDate($subject, (object) ['placeholders' => ['{order_date}' => 'March 21, 2026'], 'object' => null]));
        $this->assertSame($subject, $filter->filterEmailOrderDate($subject, (object) ['placeholders' => []]));
        $this->assertSame($subject, $filter->filterEmailOrderDate($subject, null));
    }

    public function test_dates_outside_woocommerce_contexts_are_untouched(): void
    {
        $filter = new WooDateDisplayFilter();

        $this->assertFalse($filter->isWooDateContext());
        $this->assertSame('Mar 21, 2026', $filter->filterDateI18n('Mar 21, 2026', 'M j, Y', self::NOWRUZ_1405));
    }

    /**
     * @dataProvider wooScreens
     */
    public function test_woocommerce_admin_screens_convert_dates(string $id, string $postType): void
    {
        $filter = new WooDateDisplayFilter();
        $filter->detectScreen($this->screen($id, $postType));

        $this->assertTrue($filter->isWooDateContext());
        $this->assertSame('1405/01/01', $filter->filterDateI18n('2026/03/21', 'Y/m/d', self::NOWRUZ_1405));
    }

    public static function wooScreens(): array
    {
        return [
            'hpos orders'   => ['woocommerce_page_wc-orders', ''],
            'legacy orders' => ['edit-shop_order', 'shop_order'],
            'legacy edit'   => ['shop_order', 'shop_order'],
        ];
    }

    public function test_other_admin_screens_do_not_convert(): void
    {
        $filter = new WooDateDisplayFilter();
        $filter->detectScreen($this->screen('woocommerce_page_wc-orders', ''));
        $filter->detectScreen($this->screen('edit-post', 'post'));

        $this->assertFalse($filter->isWooDateContext());
    }

    public function test_template_parts_convert_dates_while_rendering(): void
    {
        $filter = new WooDateDisplayFilter();

        $filter->enterTemplate();
        $filter->enterTemplate();
        $filter->leaveTemplate();
        $this->assertSame('1405/01/01', $filter->filterDateI18n('2026/03/21', 'Y/m/d', self::NOWRUZ_1405));

        $filter->leaveTemplate();
        $filter->leaveTemplate();
        $this->assertFalse($filter->isWooDateContext());
    }

    public function test_order_confirmation_blocks_convert_dates_while_rendering(): void
    {
        $filter = new WooDateDisplayFilter();
        $block = ['blockName' => 'woocommerce/order-confirmation-downloads'];

        $this->assertSame(['blockName' => 'core/paragraph'], $filter->enterBlock(['blockName' => 'core/paragraph']));
        $this->assertFalse($filter->isWooDateContext());

        $this->assertSame($block, $filter->enterBlock($block));
        $this->assertTrue($filter->isWooDateContext());

        $this->assertSame('<p>html</p>', $filter->leaveBlock('<p>html</p>', $block));
        $this->assertFalse($filter->isWooDateContext());
    }

    public function test_machine_formats_are_preserved_in_woocommerce_contexts(): void
    {
        $filter = new WooDateDisplayFilter();
        $filter->enterTemplate();

        $this->assertSame(
            '2026-03-21T00:00:00+00:00',
            $filter->filterDateI18n('2026-03-21T00:00:00+00:00', DATE_RFC3339, self::NOWRUZ_1405, true)
        );
    }

    private function email(string $orderDate, ?\DateTimeInterface $created): object
    {
        return (object) [
            'placeholders' => ['{order_date}' => $orderDate],
            'object'       => new \WC_Order(['date_created' => $created]),
        ];
    }

    private function screen(string $id, string $postType): \WP_Screen
    {
        $screen = new \WP_Screen();
        $screen->id = $id;
        $screen->post_type = $postType;

        return $screen;
    }
}
