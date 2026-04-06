<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\WooCommerce\WooAdminDateFields;
use PHPUnit\Framework\TestCase;

class WooAdminDateFieldsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        if (!defined('PERSIAN_KIT_URL')) {
            define('PERSIAN_KIT_URL', 'https://example.com/wp-content/plugins/persian-kit/');
        }

        if (!defined('PERSIAN_KIT_VERSION')) {
            define('PERSIAN_KIT_VERSION', '1.0.0');
        }

        Functions\when('sanitize_key')->returnArg();
        Functions\when('wp_unslash')->returnArg();
    }

    protected function tearDown(): void
    {
        unset($_GET['action']);

        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_adds_admin_enqueue_hook(): void
    {
        $script = new WooAdminDateFields();
        $script->register();

        $this->assertTrue(has_action('admin_enqueue_scripts'));
    }

    public function test_enqueue_loads_assets_on_product_edit_screen(): void
    {
        $registerCalls = [];
        $enqueueCalls = [];
        $screen = (object) ['id' => 'product', 'post_type' => 'product'];

        Functions\expect('get_current_screen')->once()->andReturn($screen);
        Functions\when('wp_register_script')->alias(function (...$args) use (&$registerCalls) {
            $registerCalls[] = $args;
        });
        Functions\when('wp_enqueue_script')->alias(function (...$args) use (&$enqueueCalls) {
            $enqueueCalls[] = $args;
        });

        (new WooAdminDateFields())->enqueue('post.php');

        $this->assertSame([[
            'persian-kit-jalali',
            PERSIAN_KIT_URL . 'public/js/jalali.js',
            [],
            PERSIAN_KIT_VERSION,
            true,
        ]], $registerCalls);

        $this->assertSame([
            ['persian-kit-jalali'],
            [
                'persian-kit-woocommerce-date-fields',
                PERSIAN_KIT_URL . 'public/js/woocommerce-date-fields.js',
                ['jquery', 'persian-kit-jalali'],
                PERSIAN_KIT_VERSION,
                true,
            ],
        ], $enqueueCalls);
    }

    public function test_enqueue_loads_assets_on_hpos_order_edit_screen(): void
    {
        $screen = (object) ['id' => 'woocommerce_page_wc-orders'];
        $_GET['action'] = 'edit';

        Functions\expect('get_current_screen')->once()->andReturn($screen);
        Functions\expect('wp_register_script')->once();
        Functions\expect('wp_enqueue_script')->times(2);

        (new WooAdminDateFields())->enqueue('woocommerce_page_wc-orders');

        $this->assertTrue(true);
    }

    public function test_enqueue_skips_unsupported_admin_pages(): void
    {
        $screen = (object) ['id' => 'edit-post', 'post_type' => 'post'];

        Functions\expect('get_current_screen')->once()->andReturn($screen);
        Functions\expect('wp_register_script')->never();
        Functions\expect('wp_enqueue_script')->never();

        (new WooAdminDateFields())->enqueue('edit.php');

        $this->assertTrue(true);
    }
}
