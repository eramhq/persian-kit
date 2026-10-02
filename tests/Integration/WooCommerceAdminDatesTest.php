<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Modules\WooCommerce\WooPostedDateNormalizer;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Dates sent to WooCommerce's admin AJAX handlers, run through WooCommerce
 * itself. The WooCommerce module registers the normalizer in the admin
 * only, so each test registers one. Runs when WooCommerce is loaded (see
 * tests/bootstrap.php).
 */
class WooCommerceAdminDatesTest extends WordPressIntegrationTestCase
{
    public function set_up(): void
    {
        parent::set_up();

        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not installed next to the plugin.');
        }

        $user = self::factory()->user->create_and_get(['role' => 'administrator']);
        $user->add_cap('edit_products');
        wp_set_current_user($user->ID);

        (new WooPostedDateNormalizer())->register();

        // wp_die() ends an AJAX handler; end it with an exception instead.
        add_filter('wp_doing_ajax', '__return_true');
        add_filter('wp_die_ajax_handler', fn () => static function (): void {
            throw new \WPAjaxDieStopException('wp_die');
        });
    }

    public function tear_down(): void
    {
        $_POST = [];
        $_REQUEST = [];
        parent::tear_down();
    }

    public function test_bulk_sale_schedule_typed_in_jalali_is_saved_gregorian(): void
    {
        [$product, $variation] = $this->variableProduct();

        $this->bulkEditVariations($product, 'variable_sale_schedule', [
            'date_from' => '۱۴۰۵/۰۸/۰۱',
            'date_to'   => '1405-08-30',
        ]);

        $variation = wc_get_product($variation->get_id());
        $this->assertSame('2026-10-23 00:00:00', $variation->get_date_on_sale_from()->date('Y-m-d H:i:s'));
        $this->assertSame('2026-11-21 23:59:59', $variation->get_date_on_sale_to()->date('Y-m-d H:i:s'));
    }

    public function test_a_cancelled_prompt_keeps_that_date(): void
    {
        [$product, $variation] = $this->variableProduct();
        $variation->set_date_on_sale_to('2026-12-31 23:59:59');
        $variation->save();

        $this->bulkEditVariations($product, 'variable_sale_schedule', [
            'date_from' => '1405-08-01',
            'date_to'   => 'false',
        ]);

        $variation = wc_get_product($variation->get_id());
        $this->assertSame('2026-10-23', $variation->get_date_on_sale_from()->date('Y-m-d'));
        $this->assertSame('2026-12-31', $variation->get_date_on_sale_to()->date('Y-m-d'));
    }

    public function test_without_a_valid_nonce_woocommerce_refuses_and_nothing_is_converted(): void
    {
        [$product] = $this->variableProduct();

        $this->bulkEditVariations($product, 'variable_sale_schedule', ['date_from' => '1405-08-01'], 'not-a-nonce');

        $this->assertSame('1405-08-01', wp_unslash($_POST['data']['date_from']));
    }

    /**
     * @return array{\WC_Product_Variable, \WC_Product_Variation}
     */
    private function variableProduct(): array
    {
        $product = new \WC_Product_Variable();
        $product->set_name('Shirt');
        $product->save();

        $variation = new \WC_Product_Variation();
        $variation->set_parent_id($product->get_id());
        $variation->set_regular_price('100');
        $variation->save();

        return [$product, $variation];
    }

    /**
     * Posts the request meta-boxes-product-variation.js sends, slashed as
     * WordPress keeps $_POST, to wp_ajax_woocommerce_bulk_edit_variations.
     *
     * @param array<string, string> $data
     */
    private function bulkEditVariations(\WC_Product $product, string $action, array $data, ?string $nonce = null): void
    {
        $_POST = wp_slash([
            'action'      => 'woocommerce_bulk_edit_variations',
            'security'    => $nonce ?? wp_create_nonce('bulk-edit-variations'),
            'product_id'  => (string) $product->get_id(),
            'bulk_action' => $action,
            'data'        => $data,
        ]);
        $_REQUEST = $_POST;

        $level = ob_get_level();
        try {
            do_action('wp_ajax_woocommerce_bulk_edit_variations');
            $this->fail('WooCommerce did not end the request.');
        } catch (\WPAjaxDieStopException $exception) {
            // The handler's wp_die().
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }
}
