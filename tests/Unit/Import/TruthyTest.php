<?php

namespace PersianKit\Tests\Unit\Import;

use PersianKit\Service\Import\AbstractSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TruthyTest extends TestCase
{
    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function values(): array
    {
        return [
            'true'      => [true, true],
            '1'         => [1, true],
            "'1'"       => ['1', true],
            'on'        => ['on', true],
            'yes'       => ['yes', true],
            'Yes'       => ['Yes', true],
            'enable'    => ['enable', true],
            'false'     => [false, false],
            '0'         => [0, false],
            "'0'"       => ['0', false],
            'no'        => ['no', false],
            'off'       => ['off', false],
            'disable'   => ['disable', false],
            'empty'     => ['', false],
            'null'      => [null, false],
            'an array'  => [['yes'], false],
        ];
    }

    #[DataProvider('values')]
    public function test_reads_how_plugins_store_on(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, AbstractSource::truthy($value));
    }
}
