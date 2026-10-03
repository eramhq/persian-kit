<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use PersianKit\Modules\WooCommerce\CityNames;
use PHPUnit\Framework\TestCase;

class CityNamesTest extends TestCase
{
    private const DATA_FILE = __DIR__ . '/../../../resources/data/ir-cities.json';
    private const FIXTURE = __DIR__ . '/../../fixtures/city-matches.json';

    /**
     * @return array<string, array{string, string}>
     */
    public static function keys(): array
    {
        return [
            'Arabic yeh and kaf'          => ['شهريار كرج', 'شهریارکرج'],
            'alef maksura'                => ['موسى', 'موسی'],
            'yeh with hamza'              => ['قائمشهر', 'قایمشهر'],
            'gaf'                         => ['مشگین', 'مشکین'],
            'teh marbuta and heh forms'   => ['ة ۀ ە ھ ہ', 'ههههه'],
            'alef forms'                  => ['أإآٱ', 'اااا'],
            'waw with hamza'              => ['مؤمن', 'مومن'],
            'hamza and tashkeel'          => ['ضیاءالدّین', 'ضیاالدین'],
            'heh with hamza above'        => ['خانهٔ', 'خانه'],
            'spaces, NBSP and ZWNJ'       => ["اسلام\u{00A0}آباد\u{200C} غرب", 'اسلامابادغرب'],
            'kashida'                     => ['تهـــران', 'تهران'],
            'punctuation'                 => ['شهر-صدرا (فارس). / x', 'شهرصدرافارسx'],
            'dashes'                      => ["الف\u{2013}ب\u{2014}پ", 'الفبپ'],
            'Persian and Arabic digits'   => ['۱۲٣4', '1234'],
        ];
    }

    /**
     * @dataProvider keys
     */
    public function test_key(string $typed, string $key): void
    {
        $this->assertSame($key, CityNames::key($typed));
    }

    public function test_the_shared_cases_match_the_real_list(): void
    {
        $names = new CityNames($this->realCities());
        $fixture = json_decode((string) file_get_contents(self::FIXTURE), true);

        foreach ($fixture['cases'] as $case) {
            $this->assertSame(
                $case['official'],
                $names->officialName('IR', $case['state'], $case['typed']),
                $case['state'] . ': ' . $case['typed']
            );
        }
    }

    public function test_a_key_two_names_share_keeps_the_city_as_typed(): void
    {
        $names = new CityNames(['THR' => ['نظر آباد', 'نظرآباد', 'تهران']]);

        $this->assertNull($names->officialName('IR', 'THR', 'نظراباد'));
        $this->assertSame('تهران', $names->officialName('IR', 'THR', 'تهران'));
    }

    public function test_the_same_name_listed_twice_still_matches(): void
    {
        $names = new CityNames(['THR' => ['تهران', 'تهران']]);

        $this->assertSame('تهران', $names->officialName('IR', 'THR', 'تهران '));
    }

    public function test_an_unknown_province_or_another_country_keeps_the_city(): void
    {
        $names = new CityNames(['THR' => ['تهران']]);

        $this->assertNull($names->officialName('IR', 'XYZ', 'تهران'));
        $this->assertNull($names->officialName('IR', '', 'تهران'));
        $this->assertNull($names->officialName('DE', 'THR', 'تهران'));
    }

    public function test_only_whole_names_match(): void
    {
        $names = new CityNames(['THR' => ['اسلامشهر']]);

        $this->assertNull($names->officialName('IR', 'THR', 'اسلام'));
        $this->assertNull($names->officialName('IR', 'THR', ''));
        $this->assertNull($names->officialName('IR', 'THR', ' - '));
    }

    /**
     * @return array<string, list<string>>
     */
    private function realCities(): array
    {
        $data = json_decode((string) file_get_contents(self::DATA_FILE), true);

        return array_filter($data, static fn ($key): bool => !str_starts_with((string) $key, '_'), ARRAY_FILTER_USE_KEY);
    }
}
