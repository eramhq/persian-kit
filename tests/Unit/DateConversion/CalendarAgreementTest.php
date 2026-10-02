<?php

namespace PersianKit\Tests\Unit\DateConversion;

use PersianKit\Dependencies\Eram\Daynum\CivilDateTime;
use PHPUnit\Framework\TestCase;

/**
 * The server (daynum) must agree with the date picker's Persian calendar,
 * or a picked date is saved one day off. tests/fixtures/persian-years.json
 * holds what the picker computes; tests/js/calendar-agreement.test.mjs
 * checks the fixture against the picker and the admin date editors.
 *
 * daynum follows a different leap-year rule from the picker (ICU's), but
 * the two agree on every year from 1178 to 1633 AP (1799–2254).
 */
class CalendarAgreementTest extends TestCase
{
    public function test_daynum_agrees_with_the_date_picker_from_1300_to_1500(): void
    {
        $years = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/fixtures/persian-years.json'), true);

        $this->assertCount(201, $years);

        foreach ($years as $year => [$farvardin1, $esfandLength]) {
            $start = CivilDateTime::fromJalali((int) $year, 1, 1);
            $esfand = CivilDateTime::fromJalali((int) $year, 12, 1)->jalali()->endOfMonth();

            $this->assertSame($farvardin1, $start->toDateTimeImmutable()->format('Y-m-d'), "1 Farvardin {$year}");
            $this->assertSame($esfandLength, $esfand->jalali()->day(), "Esfand {$year}");
        }
    }
}
