<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\WooCommerce\CityField;
use PHPUnit\Framework\TestCase;

class CityFieldTest extends TestCase
{
    private const DATA_FILE = __DIR__ . '/../../../resources/data/ir-cities.json';

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\stubTranslationFunctions();
        Functions\when('is_admin')->justReturn(false);
        Functions\when('wp_doing_ajax')->justReturn(false);
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_unslash')->returnArg();
    }

    protected function tearDown(): void
    {
        unset($_POST['billing_state'], $_POST['billing_city']);
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
        $this->assertSame('تهران', $cities['THR'][0], 'the capital comes first');
        $this->assertContains('اسلامشهر', $cities['THR']);
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

    public function test_iranian_city_becomes_a_select_of_the_province_cities(): void
    {
        $_POST['billing_state'] = 'QHM';
        $_POST['billing_city'] = 'قم';

        $fields = (new CityField(self::DATA_FILE))->filterBillingFields(['billing_city' => ['type' => 'text']], 'IR');

        $this->assertSame('select', $fields['billing_city']['type']);
        $this->assertSame(['', 'قم', 'جعفریه'], array_slice(array_keys($fields['billing_city']['options']), 0, 3));
    }

    public function test_a_saved_city_the_list_lacks_is_kept(): void
    {
        $options = (new CityField(self::DATA_FILE))->options('QHM', 'روستای من');

        $this->assertArrayHasKey('روستای من', $options);
        $this->assertSame('Select a city…', $options['']);
    }

    public function test_other_countries_keep_a_text_city(): void
    {
        $fields = (new CityField(self::DATA_FILE))->filterShippingFields(['shipping_city' => ['type' => 'text']], 'DE');

        $this->assertSame('text', $fields['shipping_city']['type']);
    }

    public function test_admin_screens_keep_a_text_city(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $fields = (new CityField(self::DATA_FILE))->filterBillingFields(['billing_city' => ['type' => 'text']], 'IR');

        $this->assertSame('text', $fields['billing_city']['type']);
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
