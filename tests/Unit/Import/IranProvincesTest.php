<?php

namespace PersianKit\Tests\Unit\Import;

use PersianKit\Service\Import\Iran\IranProvinces;
use PHPUnit\Framework\TestCase;

class IranProvincesTest extends TestCase
{
    public function test_every_map_covers_the_31_provinces_once(): void
    {
        $codes = array_keys(IranProvinces::NAMES);
        sort($codes);

        $this->assertCount(31, $codes);
        foreach ([IranProvinces::LEGACY_TWO_LETTER, IranProvinces::TAPIN] as $map) {
            $mapped = array_values($map);
            sort($mapped);
            $this->assertSame($codes, $mapped);
        }
        $this->assertSame(range(1, 31), array_keys(IranProvinces::TAPIN));
    }

    public function test_codes_two_letter_codes_and_tapin_ids(): void
    {
        $this->assertSame('THR', IranProvinces::resolve('THR'));
        $this->assertSame('THR', IranProvinces::resolve('thr'));
        $this->assertSame('WAZ', IranProvinces::resolve('AW'));
        $this->assertSame('YZD', IranProvinces::fromLegacy('ya'));
        $this->assertSame('ABZ', IranProvinces::fromTapin(31));
        $this->assertNull(IranProvinces::fromTapin(32));
        $this->assertNull(IranProvinces::resolve('XX'));
    }

    public function test_names_in_their_usual_spellings(): void
    {
        $this->assertSame('THR', IranProvinces::fromName('تهران'));
        $this->assertSame('THR', IranProvinces::fromName('استان تهران'));
        // Arabic ي and ك, and no space before the ZWNJ.
        $this->assertSame('KRH', IranProvinces::fromName('كرمانشاه'));
        $this->assertSame('CHB', IranProvinces::fromName('چهار محال بختیاری'));
        $this->assertSame('KBD', IranProvinces::fromName('کهگیلوییه و بویراحمد'));
        $this->assertSame('EAZ', IranProvinces::fromName('اذربایجان شرقی'));
        $this->assertNull(IranProvinces::fromName('Tehran'));
    }
}
