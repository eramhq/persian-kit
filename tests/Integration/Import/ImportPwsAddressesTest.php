<?php

namespace PersianKit\Tests\Integration\Import;

use Automattic\WooCommerce\Utilities\OrderUtil;
use PersianKit\Bootstrap;
use PersianKit\Service\Import\ImportLog;
use PersianKit\Service\Import\ImportReport;
use PersianKit\Service\Import\ImportRunner;
use PersianKit\Service\Import\Sources\PersianWooCommerce\PersianWooCommerceSource;
use PersianKit\Service\Import\Sources\Shipping\PwsSource;
use PersianKit\Tests\Integration\Support\UsesImportLog;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * The shipping plugin's ids and names in customers, orders, zones and the
 * store address become WooCommerce's codes and city names, with the plugin
 * inactive (its taxonomy unregistered), under both order stores.
 */
class ImportPwsAddressesTest extends WordPressIntegrationTestCase
{
    use UsesImportLog;

    /** @var array<string, int> Term ids by name. */
    private array $terms = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not loaded.');
        }

        $this->setUpImportLog();
        update_option('persian_kit_settings', []);
        (fn () => $this->cache = null)->call(Bootstrap::get(\PersianKit\Core\SettingsManager::class));

        // As the plugin installs them: provinces by two-letter slug, cities
        // and districts under them.
        register_taxonomy(PwsSource::TAXONOMY, null, ['hierarchical' => true]);
        $tehran = $this->term('تهران', 'te');
        $isfahan = $this->term('اصفهان', 'is');
        $this->terms['tehran-city'] = $this->term('تهران', 'تهران-شهر', $tehran);
        $this->terms['shahriar'] = $this->term('شهریار', 'شهریار', $tehran);
        $this->terms['kashan'] = $this->term('کاشان', 'کاشان', $isfahan);
        $this->terms['narmak'] = $this->term('نارمک', 'نارمک', $this->terms['tehran-city']);
        $this->terms['tehran'] = $tehran;
        $this->terms['isfahan'] = $isfahan;
        unregister_taxonomy(PwsSource::TAXONOMY);
    }

    public function test_customer_addresses(): void
    {
        $customer = $this->customer([
            'billing_state' => $this->terms['tehran'], 'billing_city' => $this->terms['tehran-city'], 'billing_district' => $this->terms['narmak'],
            'shipping_state' => $this->terms['isfahan'], 'shipping_city' => $this->terms['kashan'], 'shipping_address_2' => 'واحد ۳',
        ]);
        $abroad = $this->customer(['billing_country' => 'DE', 'billing_state' => '12', 'shipping_country' => '']);

        $this->switchFrom(new PwsSource());

        $this->assertSame('THR', get_user_meta($customer, 'billing_state', true));
        $this->assertSame('تهران', get_user_meta($customer, 'billing_city', true));
        // The district fills the empty second line; its own field is kept.
        $this->assertSame('نارمک', get_user_meta($customer, 'billing_address_2', true));
        $this->assertSame((string) $this->terms['narmak'], get_user_meta($customer, 'billing_district', true));
        $this->assertSame('ESF', get_user_meta($customer, 'shipping_state', true));
        $this->assertSame('کاشان', get_user_meta($customer, 'shipping_city', true));
        $this->assertSame('واحد ۳', get_user_meta($customer, 'shipping_address_2', true));
        $this->assertSame('12', get_user_meta($abroad, 'billing_state', true));

        // As WooCommerce reads it.
        $this->assertSame('THR', (new \WC_Customer($customer))->get_billing_state());
    }

    public function test_the_district_line_can_be_left_out(): void
    {
        $customer = $this->customer(['billing_state' => $this->terms['tehran'], 'billing_city' => $this->terms['tehran-city'], 'billing_district' => $this->terms['narmak']]);

        $this->switchFrom(new PwsSource(), ['options' => ['district_line' => false]]);

        $this->assertSame('', get_user_meta($customer, 'billing_address_2', true));
        $report = Bootstrap::get(ImportReport::class)->page(new PwsSource(), ImportLog::ATTENTION, 1);
        $this->assertStringContainsString('نارمک', $report['rows'][0]['reason']);
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
    public function test_order_addresses(string $orderTables): void
    {
        $this->useOrderTables($orderTables === 'yes');

        // From the classic checkout: names, with the ids beside them.
        $checkout = $this->order(['state' => 'تهران', 'city' => 'شهریار'], ['_billing_state_id' => $this->terms['tehran'], '_billing_city_id' => $this->terms['shahriar']]);
        // From the admin, REST or the block checkout: raw ids.
        $admin = $this->order(['state' => (string) $this->terms['isfahan'], 'city' => (string) $this->terms['kashan']]);
        // A deleted term and nothing else to go on.
        $lost = $this->order(['state' => '99999', 'city' => '88888']);
        // Already WooCommerce's: left alone.
        $done = $this->order(['state' => 'THR', 'city' => 'تهران']);
        $refund = wc_create_refund(['order_id' => $admin->get_id(), 'amount' => 0]);

        $this->assertSame(3, (new \PersianKit\Service\Import\Tasks\OrderAddressTask())->count(Bootstrap::get(ImportRunner::class)->context(new \PersianKit\Service\Import\ImportJob('x', 'y'))));

        $this->switchFrom(new PwsSource());

        $checkout = wc_get_order($checkout->get_id());
        $this->assertSame('THR', $checkout->get_billing_state());
        $this->assertSame('شهریار', $checkout->get_billing_city());
        // Its own ids are kept.
        $this->assertSame((string) $this->terms['tehran'], (string) $checkout->get_meta('_billing_state_id'));

        $admin = wc_get_order($admin->get_id());
        $this->assertSame('ESF', $admin->get_billing_state());
        $this->assertSame('کاشان', $admin->get_billing_city());

        $this->assertSame('99999', wc_get_order($lost->get_id())->get_billing_state());
        $this->assertSame('THR', wc_get_order($done->get_id())->get_billing_state());
        $this->assertInstanceOf(\WC_Order_Refund::class, wc_get_order($refund->get_id()));

        $attention = Bootstrap::get(ImportReport::class)->page(new PwsSource(), ImportLog::ATTENTION, 1)['rows'];
        $this->assertSame([$lost->get_id()], array_column($attention, 'object_id'));
    }

    public function test_zones_and_the_store_address(): void
    {
        $zone = new \WC_Shipping_Zone();
        $zone->set_zone_name('Tehran and Shahriar');
        $zone->add_location('IR:' . $this->terms['tehran'], 'state');
        $zone->add_location('IR:' . $this->terms['shahriar'], 'state');
        $zone->add_location('IR:THR', 'state');
        $zone->save();
        update_option('woocommerce_default_country', 'IR:' . $this->terms['isfahan']);

        $this->switchFrom(new PwsSource());

        $codes = array_column((new \WC_Shipping_Zone($zone->get_id()))->get_zone_locations(), 'code');
        // Once, though it was listed twice; the city stays and is reported.
        $this->assertSame(['IR:THR', 'IR:' . $this->terms['shahriar']], $codes);
        $this->assertSame('IR:ESF', get_option('woocommerce_default_country'));

        $attention = Bootstrap::get(ImportReport::class)->page(new PwsSource(), ImportLog::ATTENTION, 1)['rows'];
        $this->assertStringContainsString('postcodes', $attention[0]['reason']);
    }

    public function test_tapin_ids(): void
    {
        update_option('pws_tapin', ['enable' => 1]);
        // Tapin's Isfahan and Kashan.
        $customer = $this->customer(['billing_state' => '6', 'billing_city' => '871']);

        $this->switchFrom(new PwsSource());

        $this->assertSame('ESF', get_user_meta($customer, 'billing_state', true));
        $this->assertSame('کاشان', get_user_meta($customer, 'billing_city', true));
    }

    public function test_addresses_read_as_names_before_the_switch(): void
    {
        $address = WC()->countries->get_formatted_address([
            'country' => 'IR',
            'state'   => (string) $this->terms['tehran'],
            'city'    => (string) $this->terms['shahriar'],
        ], ', ');

        $this->assertStringContainsString('تهران', $address);
        $this->assertStringContainsString('شهریار', $address);
        $this->assertStringNotContainsString((string) $this->terms['shahriar'], $address);
    }

    public function test_undo_keeps_what_changed_after_the_import(): void
    {
        $first = $this->customer(['billing_state' => $this->terms['tehran'], 'billing_city' => $this->terms['shahriar']]);
        $second = $this->customer(['billing_state' => $this->terms['isfahan'], 'billing_city' => $this->terms['kashan']]);
        $this->switchFrom(new PwsSource());

        // The customer changed their city since.
        update_user_meta($second, 'billing_city', 'آران و بیدگل');

        Bootstrap::get(ImportRunner::class)->undo(new PwsSource(), 'test', 0, '', 30.0);

        $this->assertSame((string) $this->terms['tehran'], get_user_meta($first, 'billing_state', true));
        $this->assertSame((string) $this->terms['shahriar'], get_user_meta($first, 'billing_city', true));
        $this->assertSame((string) $this->terms['isfahan'], get_user_meta($second, 'billing_state', true));
        $this->assertSame('آران و بیدگل', get_user_meta($second, 'billing_city', true));
    }

    public function test_persian_woocommerce_old_two_letter_codes(): void
    {
        update_option('PW_Options', ['persian_price' => 'yes']);
        $customer = $this->customer(['billing_state' => 'TE', 'billing_city' => 'تهران', 'shipping_state' => (string) $this->terms['isfahan']]);
        $order = $this->order(['state' => 'YA', 'city' => 'یزد']);

        $this->switchFrom(new PersianWooCommerceSource());

        $this->assertSame('THR', get_user_meta($customer, 'billing_state', true));
        // Only its own codes: the shipping plugin's ids wait for that switch.
        $this->assertSame((string) $this->terms['isfahan'], get_user_meta($customer, 'shipping_state', true));
        $this->assertSame('YZD', wc_get_order($order->get_id())->get_billing_state());
    }

    /**
     * @param array<string, mixed> $choices
     */
    private function switchFrom(\PersianKit\Service\Import\Source $source, array $choices = []): void
    {
        $runner = Bootstrap::get(ImportRunner::class);
        $runner->start($source, $choices + ['rows' => []]);
        $this->assertTrue($runner->run('test', 60.0)->isFinished());
    }

    private function term(string $name, string $slug, int $parent = 0): int
    {
        $term = wp_insert_term($name, PwsSource::TAXONOMY, ['slug' => $slug, 'parent' => $parent]);
        $this->assertIsArray($term);

        return (int) $term['term_id'];
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function customer(array $meta): int
    {
        $id = self::factory()->user->create(['role' => 'customer']);
        foreach ($meta + ['billing_country' => 'IR', 'shipping_country' => 'IR'] as $key => $value) {
            update_user_meta($id, $key, (string) $value);
        }

        return $id;
    }

    /**
     * @param array{state: string, city: string} $billing
     * @param array<string, mixed>               $meta
     */
    private function order(array $billing, array $meta = []): \WC_Order
    {
        $order = wc_create_order();
        $order->set_billing_country('IR');
        $order->set_billing_state($billing['state']);
        $order->set_billing_city($billing['city']);
        foreach ($meta as $key => $value) {
            $order->update_meta_data($key, (string) $value);
        }
        $order->save();

        return $order;
    }

    /**
     * The order tables, created outside any test: CREATE TABLE ends the
     * test's transaction.
     */
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

    private function useOrderTables(bool $enabled): void
    {
        update_option('woocommerce_custom_orders_table_enabled', $enabled ? 'yes' : 'no');
        update_option('woocommerce_custom_orders_table_data_sync_enabled', 'no');

        $this->assertSame($enabled, OrderUtil::custom_orders_table_usage_is_enabled());
    }
}
