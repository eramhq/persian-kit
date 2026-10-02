<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use Brain\Monkey;
use Brain\Monkey\Filters;
use PersianKit\Modules\WooCommerce\CityField;
use PHPUnit\Framework\TestCase;

class CityFieldTest extends TestCase
{
    private const DATA_FILE = __DIR__ . '/../../../resources/data/ir-cities.json';

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
