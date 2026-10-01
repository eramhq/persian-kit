<?php

namespace PersianKit\Tests\Unit\WooCommerce;

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

    private function screen(string $id, string $postType): \WP_Screen
    {
        $screen = new \WP_Screen();
        $screen->id = $id;
        $screen->post_type = $postType;

        return $screen;
    }
}
