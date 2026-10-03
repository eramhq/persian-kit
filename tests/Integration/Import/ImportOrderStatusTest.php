<?php

namespace PersianKit\Tests\Integration\Import;

use Automattic\WooCommerce\Utilities\OrderUtil;
use PersianKit\Bootstrap;
use PersianKit\Service\Import\ImportReview;
use PersianKit\Service\Import\ImportRunner;
use PersianKit\Service\Import\Sources\Shipping\PwsSource;
use PersianKit\Tests\Integration\Support\UsesImportLog;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Orders in the shipping plugin's statuses move to WooCommerce's, with a
 * note, no email and stock untouched.
 */
class ImportOrderStatusTest extends WordPressIntegrationTestCase
{
    use UsesImportLog;

    /** @var list<array<string, mixed>> */
    private array $mails = [];

    public static function set_up_before_class(): void
    {
        parent::set_up_before_class();

        if (class_exists('WooCommerce')) {
            $synchronizer = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class);
            if (!$synchronizer->check_orders_table_exists()) {
                $synchronizer->create_database_tables();
            }
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not loaded.');
        }

        $this->setUpImportLog();

        // WooCommerce adds its email hooks when the mailer is first made.
        $instance = new \ReflectionProperty(\WC_Emails::class, 'instance');
        $instance->setValue(null, null);
        WC()->mailer();
        add_filter('pre_wp_mail', function ($short, array $mail) {
            $this->mails[] = $mail;

            return true;
        }, 10, 2);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function orderStores(): array
    {
        return ['posts' => ['no'], 'order tables' => ['yes']];
    }

    /**
     * @dataProvider orderStores
     */
    public function test_orders_move_to_the_chosen_status_quietly(string $orderTables): void
    {
        $this->useOrderTables($orderTables === 'yes');
        $product = new \WC_Product_Simple();
        $product->set_regular_price('100');
        $product->set_manage_stock(true);
        $product->set_stock_quantity(10);
        $product->save();

        $packaged = $this->orderIn('wc-pws-packaged', $product);
        $courier = $this->orderIn('wc-pws-courier', $product);
        $other = $this->orderIn('wc-on-hold', $product);
        $this->mails = [];

        $options = Bootstrap::get(ImportReview::class)->review(new PwsSource())['tasks'];
        $statuses = array_column($options, 'options', 'key')['order_statuses'];
        $this->assertSame('status_map', $statuses['type']);
        $this->assertSame(['wc-pws-courier', 'wc-pws-packaged'], array_column($statuses['statuses'], 'slug'));
        $this->assertSame('بسته بندی شده', array_column($statuses['statuses'], 'label', 'slug')['wc-pws-packaged']);

        $runner = Bootstrap::get(ImportRunner::class);
        $runner->start(new PwsSource(), ['rows' => [], 'tasks' => ['order_statuses'], 'options' => ['status_map' => ['wc-pws-courier' => 'wc-completed']]]);
        $this->assertTrue($runner->run('test', 60.0)->isFinished());

        $packaged = wc_get_order($packaged);
        $this->assertSame('processing', $packaged->get_status());
        $this->assertSame('completed', wc_get_order($courier)->get_status());
        $this->assertSame('on-hold', wc_get_order($other)->get_status());

        $notes = wc_get_order_notes(['order_id' => $packaged->get_id()]);
        $this->assertStringContainsString('(wc-pws-packaged) when switching to Persian Kit', $notes[0]->content);

        $this->assertSame([], $this->mails);
        $this->assertSame(10, wc_get_product($product->get_id())->get_stock_quantity());

        // Undo puts them back in the plugin's statuses, which WooCommerce does not know.
        $runner->undo(new PwsSource(), 'test', 0, '', 30.0);
        $this->assertSame('pws-packaged', wc_get_order($packaged->get_id())->get_status());
        $this->assertSame('pws-courier', wc_get_order($courier)->get_status());
        $this->assertNotSame('', (new PwsSource())->undoWarning());
    }

    private function orderIn(string $status, \WC_Product $product): int
    {
        $order = wc_create_order();
        $order->set_billing_email('customer@example.org');
        $order->add_product($product, 2);
        $order->calculate_totals();
        $order->save();

        // As the plugin saves it: a status WooCommerce does not know without it.
        global $wpdb;
        if (OrderUtil::custom_orders_table_usage_is_enabled()) {
            $wpdb->update($wpdb->prefix . 'wc_orders', ['status' => $status], ['id' => $order->get_id()]);
            wc_get_container()->get(\Automattic\WooCommerce\Caches\OrderCache::class)->remove($order->get_id());
        } else {
            $wpdb->update($wpdb->posts, ['post_status' => $status], ['ID' => $order->get_id()]);
            clean_post_cache($order->get_id());
        }

        return $order->get_id();
    }

    private function useOrderTables(bool $enabled): void
    {
        update_option('woocommerce_custom_orders_table_enabled', $enabled ? 'yes' : 'no');
        update_option('woocommerce_custom_orders_table_data_sync_enabled', 'no');
        $this->assertSame($enabled, OrderUtil::custom_orders_table_usage_is_enabled());
    }
}
