<?php

namespace PersianKit\Tests\Unit\Forms;

use PersianKit\Modules\Forms\FormDateValues;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/functions.php';

class FormDateValuesTest extends TestCase
{
    public function test_typed_jalali_dates_are_read_in_the_fields_format_or_year_first(): void
    {
        $this->assertSame('2026-10-02', FormDateValues::toGregorian('۱۰/۰۷/۱۴۰۵', 'd/m/Y'));
        $this->assertSame('2026-10-02', FormDateValues::toGregorian('7/10/1405', 'm/d/Y'));
        $this->assertSame('2026-10-02', FormDateValues::toGregorian('١٤٠٥.٧.١٠', 'd.m.Y'));
        $this->assertSame('2026-10-02', FormDateValues::toGregorian(' 1405/07/10 ', 'm-d-Y'));
        $this->assertSame('2026-10-02', FormDateValues::toGregorian('2026-10-02', 'd/m/Y'));

        foreach (['', 'soon', '10/02', '31/02/2026', '1405/13/01', '10/02/26'] as $value) {
            $this->assertNull(FormDateValues::toGregorian($value, 'd/m/Y'), $value);
        }
    }

    public function test_dates_are_written_in_the_fields_format(): void
    {
        $this->assertSame('02.10.2026', FormDateValues::inFormat('2026-10-02', 'd.m.Y'));
        $this->assertSame('2026/10/02', FormDateValues::inFormat('2026-10-02', 'Y/m/d'));
        $this->assertSame('not a date', FormDateValues::inFormat('not a date', 'd/m/Y'));
    }

    public function test_a_gregorian_date_gives_its_jalali_parts(): void
    {
        $this->assertSame(['Y' => 1405, 'm' => 7, 'd' => 12], FormDateValues::jalaliParts(2026, 10, 4));
        $this->assertSame(['Y' => 1370, 'm' => 6, 'd' => 31], FormDateValues::jalaliParts(1991, 9, 22));
        $this->assertNull(FormDateValues::jalaliParts(2026, 2, 30));
    }
}
