<?php

namespace PersianKit\Tests\Unit\Import;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\WooCommerce\CityNames;
use PersianKit\Service\Import\Iran\AddressResult;
use PersianKit\Service\Import\Iran\IranAddressResolver;
use PersianKit\Service\Import\Iran\PwsTermMap;
use PersianKit\Service\Import\Iran\TapinCities;
use PHPUnit\Framework\TestCase;

class IranAddressResolverTest extends TestCase
{
    private PwsTermMap $terms;
    private TapinCities $tapin;
    private CityNames $cities;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('__')->returnArg();

        // The shipping plugin's terms: provinces by their two-letter slugs,
        // cities under them, a district under a city.
        $this->terms = new PwsTermMap([
            1   => ['تهران', 'te', 0],
            2   => ['اصفهان', 'is', 0],
            // Renamed by the owner: the slug still says "fa".
            3   => ['استان پارس', 'fa', 0],
            // Renamed slug and name: the name decides.
            4   => ['گیلان', 'gilan', 0],
            10  => ['تهران', 'تهران', 1],
            11  => ['شهریار', 'شهریار', 1],
            20  => ['کاشان', 'کاشان', 2],
            21  => ['قائمشهر', 'قائمشهر', 2],
            100 => ['نارمک', 'نارمک', 10],
        ]);
        $this->tapin = new TapinCities([
            1 => [1 => 'تهران', 3351 => 'شهریار'],
            6 => [6 => 'اصفهان', 5191 => 'کاشان'],
        ]);
        $this->cities = new CityNames([
            'THR' => ['تهران', 'شهریار'],
            'ESF' => ['اصفهان', 'کاشان'],
            'MZN' => ['قائم شهر'],
        ]);
    }

    public function test_term_ids_become_codes_and_names(): void
    {
        $result = $this->resolver()->resolve('1', '11');

        $this->assertSame(AddressResult::CHANGED, $result->outcome);
        $this->assertSame('THR', $result->state);
        $this->assertSame('شهریار', $result->city);
    }

    public function test_a_district_term_gives_its_city_and_the_district(): void
    {
        $result = $this->resolver()->resolve('1', '100');

        $this->assertSame('تهران', $result->city);
        $this->assertSame('نارمک', $result->district);

        // A customer's district saved on its own.
        $this->assertSame('نارمک', $this->resolver()->resolve('1', '10', [], '100')->district);
    }

    public function test_renamed_terms_still_resolve(): void
    {
        $this->assertSame('FRS', $this->resolver()->stateCode('3'));
        $this->assertSame('GIL', $this->resolver()->stateCode('4'));
    }

    public function test_a_deleted_term_needs_attention(): void
    {
        $result = $this->resolver()->resolve('1', '999');

        $this->assertSame(AddressResult::ATTENTION, $result->outcome);
        $this->assertStringContainsString('999', $result->reason);
        $this->assertSame(AddressResult::ATTENTION, $this->resolver()->resolve('77', '')->outcome);
    }

    public function test_an_order_falls_back_to_its_stored_names_when_a_term_is_gone(): void
    {
        $result = $this->resolver()->resolve('تهران', 'شهريار', ['state' => '77', 'city' => '999']);

        $this->assertSame('THR', $result->state);
        $this->assertSame('شهریار', $result->city);
    }

    public function test_a_city_not_in_its_province_needs_attention(): void
    {
        $result = $this->resolver()->resolve('1', '20');

        $this->assertSame(AddressResult::ATTENTION, $result->outcome);
        $this->assertStringContainsString('کاشان', $result->reason);
    }

    public function test_tapin_ids(): void
    {
        $result = $this->resolver(true)->resolve('6', '5191');

        $this->assertSame('ESF', $result->state);
        $this->assertSame('کاشان', $result->city);
    }

    public function test_a_number_both_lists_know_is_decided_by_the_city(): void
    {
        // 1 is Tehran in both lists: no doubt.
        $this->assertSame('THR', $this->resolver(true)->resolve('1', '3351')->state);

        // 2 is Isfahan as a term and Gilan in Tapin's list (here: none), so
        // a terms-only number is read as a term.
        $this->assertSame('ESF', $this->resolver(true)->resolve('2', '20')->state);
    }

    public function test_an_ambiguous_number_needs_attention(): void
    {
        $terms = new PwsTermMap([6 => ['تهران', 'te', 0], 60 => ['ری', 'ری', 6]]);
        $resolver = new IranAddressResolver($terms, $this->tapin, $this->cities, true);

        // 6 is Tehran as a term and Isfahan in Tapin's list; the city fits neither.
        $result = $resolver->resolve('6', 'جایی');
        $this->assertSame(AddressResult::ATTENTION, $result->outcome);

        // A city that fits one of them decides.
        $this->assertSame('ESF', $resolver->resolve('6', '5191')->state);
        $this->assertSame('THR', $resolver->resolve('6', '60')->state);
    }

    public function test_names_and_codes(): void
    {
        $this->assertSame(AddressResult::UNCHANGED, $this->resolver()->resolve('THR', 'تهران')->outcome);
        $this->assertSame('WAZ', $this->resolver()->resolve('AW', 'ارومیه')->state);

        // Typed another way: the listed name.
        $result = $this->resolver()->resolve('مازندران', 'قائمشهر');
        $this->assertSame('MZN', $result->state);
        $this->assertSame('قائم شهر', $result->city);

        // Not on the list: kept as written.
        $this->assertSame('روستای ما', $this->resolver()->resolve('THR', 'روستای ما')->city);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function resolver(bool $tapinMode = false): IranAddressResolver
    {
        return new IranAddressResolver($this->terms, $this->tapin, $this->cities, $tapinMode);
    }
}
