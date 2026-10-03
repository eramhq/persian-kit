<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use Brain\Monkey;
use Brain\Monkey\Filters;
use PersianKit\Modules\WooCommerce\CityField;
use PersianKit\Modules\WooCommerce\CityNames;
use PHPUnit\Framework\TestCase;

class CityFieldTest extends TestCase
{
    private const DATA_FILE = __DIR__ . '/../../../resources/data/ir-cities.json';

    /** Each province's capital, which the list puts first. */
    private const CAPITALS = [
        'ABZ' => 'کرج',
        'ADL' => 'اردبیل',
        'BHR' => 'بوشهر',
        'CHB' => 'شهرکرد',
        'EAZ' => 'تبریز',
        'ESF' => 'اصفهان',
        'FRS' => 'شیراز',
        'GIL' => 'رشت',
        'GLS' => 'گرگان',
        'GZN' => 'قزوین',
        'HDN' => 'همدان',
        'HRZ' => 'بندرعباس',
        'ILM' => 'ایلام',
        'KBD' => 'یاسوج',
        'KHZ' => 'اهواز',
        'KRD' => 'سنندج',
        'KRH' => 'کرمانشاه',
        'KRN' => 'کرمان',
        'LRS' => 'خرم‌آباد',
        'MKZ' => 'اراک',
        'MZN' => 'ساری',
        'NKH' => 'بجنورد',
        'QHM' => 'قم',
        'RKH' => 'مشهد',
        'SBN' => 'زاهدان',
        'SKH' => 'بیرجند',
        'SMN' => 'سمنان',
        'THR' => 'تهران',
        'WAZ' => 'ارومیه',
        'YZD' => 'یزد',
        'ZJN' => 'زنجان',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_the_city_list_has_every_iranian_province_woocommerce_knows(): void
    {
        $cities = (new CityField(self::DATA_FILE))->cities();

        $this->assertSame([
            'ABZ', 'ADL', 'BHR', 'CHB', 'EAZ', 'ESF', 'FRS', 'GIL', 'GLS', 'GZN', 'HDN', 'HRZ', 'ILM', 'KBD', 'KHZ', 'KRD',
            'KRH', 'KRN', 'LRS', 'MKZ', 'MZN', 'NKH', 'QHM', 'RKH', 'SBN', 'SKH', 'SMN', 'THR', 'WAZ', 'YZD', 'ZJN',
        ], $this->sortedKeys($cities));
        $this->assertContains('اسلامشهر', $cities['THR']);
    }

    public function test_each_province_lists_its_capital_first(): void
    {
        $cities = (new CityField(self::DATA_FILE))->cities();

        $this->assertCount(31, self::CAPITALS);
        foreach (self::CAPITALS as $state => $capital) {
            $this->assertSame($capital, $cities[$state][0], $state);
        }
    }

    public function test_no_two_cities_of_a_province_share_a_matching_key(): void
    {
        foreach ((new CityField(self::DATA_FILE))->cities() as $state => $names) {
            $keys = array_map([CityNames::class, 'key'], $names);

            $this->assertSame(count($keys), count(array_unique($keys)), $state);
        }
    }

    public function test_register_adds_the_save_hooks(): void
    {
        $field = new CityField(self::DATA_FILE);
        $field->register();

        $this->assertNotFalse(has_action('woocommerce_store_api_checkout_update_customer_from_request', [$field, 'nameCustomerCities']));
        $this->assertSame(20, has_filter('woocommerce_checkout_posted_data', [$field, 'namePostedCities']));
        $this->assertNotFalse(has_action('woocommerce_after_save_address_validation', [$field, 'nameSavedAddressCity']));
    }

    public function test_the_classic_checkout_saves_a_city_under_its_listed_name(): void
    {
        $data = (new CityField(self::DATA_FILE))->namePostedCities([
            'billing_country'  => 'IR',
            'billing_state'    => 'MZN',
            'billing_city'     => 'قائمشهر',
            'shipping_country' => 'IR',
            'shipping_state'   => 'THR',
            'shipping_city'    => 'روستای من',
        ]);

        $this->assertSame('قایم شهر', $data['billing_city']);
        $this->assertSame('روستای من', $data['shipping_city'], 'a place not on the list is kept');
    }

    public function test_the_block_checkout_and_my_account_set_the_customers_city(): void
    {
        $field = new CityField(self::DATA_FILE);
        $customer = new \WC_Customer([
            'billing_country'  => 'IR',
            'billing_state'    => 'ADL',
            'billing_city'     => 'مشکین شهر',
            'shipping_country' => 'DE',
            'shipping_state'   => 'ADL',
            'shipping_city'    => 'مشکین شهر',
        ]);

        $field->nameCustomerCities($customer);

        $this->assertSame('مشگین شهر', $customer->get_billing_city());
        $this->assertSame('مشکین شهر', $customer->get_shipping_city(), 'only Iranian addresses');

        $customer->set_shipping_country('IR');
        $field->nameSavedAddressCity(1, 'shipping', [], $customer);
        $this->assertSame('مشگین شهر', $customer->get_shipping_city());
    }

    public function test_the_city_names_use_persian_letters(): void
    {
        $json = (string) file_get_contents(self::DATA_FILE);

        $this->assertStringNotContainsString("\u{064A}", $json);
        $this->assertStringNotContainsString("\u{0643}", $json);
    }

    public function test_a_missing_file_gives_no_cities(): void
    {
        $this->assertSame([], (new CityField('/does/not/exist.json'))->cities());
    }

    public function test_the_filter_can_add_places_and_its_result_is_cleaned(): void
    {
        Filters\expectApplied('persian_kit_woocommerce_cities')
            ->once()
            ->andReturnUsing(static function (array $cities): array {
                $cities['QHM'][] = 'روستای من';
                $cities['XYZ'] = ['جایی', 7, '', ['nested']];
                $cities[3] = ['عدد'];
                $cities['BAD'] = 'not a list';

                return $cities;
            });

        $field = new CityField(self::DATA_FILE);
        $cities = $field->cities();

        $this->assertSame('روستای من', end($cities['QHM']));
        $this->assertSame(['جایی'], $cities['XYZ']);
        $this->assertArrayNotHasKey(3, $cities);
        $this->assertArrayNotHasKey('BAD', $cities);
        $this->assertSame($cities, $field->cities(), 'filtered once, then kept');
    }

    public function test_a_filter_returning_nothing_useful_gives_no_cities(): void
    {
        Filters\expectApplied('persian_kit_woocommerce_cities')->once()->andReturn(null);

        $this->assertSame([], (new CityField(self::DATA_FILE))->cities());
    }

    /**
     * @param array<string, mixed> $array
     * @return list<string>
     */
    private function sortedKeys(array $array): array
    {
        $keys = array_keys($array);
        sort($keys);

        return $keys;
    }
}
