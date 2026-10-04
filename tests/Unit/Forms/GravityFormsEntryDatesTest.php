<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\Forms\GravityFormsEntryDates;
use PersianKit\Service\Language\ContentLanguage;
use PersianKit\Tests\Unit\Support\UsesLanguages;
use PHPUnit\Framework\TestCase;

class GravityFormsEntryDatesTest extends TestCase
{
    use UsesLanguages;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        ContentLanguage::reset();
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_dates_are_filtered_on_the_entries_screens_only(): void
    {
        $dates = new GravityFormsEntryDates();
        $dates->register();
        $this->assertNotFalse(has_action('current_screen', [$dates, 'detectScreen']));

        $dates->detectScreen((object) ['id' => 'forms_page_gf_edit_forms']);
        $this->assertFalse(has_filter('date_i18n', [$dates, 'jalaliDate']));

        // The menu's title is translated in the screen id.
        $dates->detectScreen((object) ['id' => '%d9%81%d8%b1%d9%85_page_gf_entries']);
        $this->assertNotFalse(has_filter('date_i18n', [$dates, 'jalaliDate']));
    }

    public function test_submitted_on_dates_are_jalali_and_machine_formats_stay(): void
    {
        $dates = new GravityFormsEntryDates();
        // Gravity Forms passes the local time as a timestamp: 2026-10-04 13:07.
        $local = gmmktime(13, 7, 0, 10, 4, 2026);

        $this->assertSame('1405/07/12', $dates->jalaliDate('2026/10/04', 'Y/m/d', $local, true));
        $this->assertSame('13:07', $dates->jalaliDate('13:07', 'H:i', $local, true));
        $this->assertSame('2026-10-04 13:07:00', $dates->jalaliDate('2026-10-04 13:07:00', 'Y-m-d H:i:s', $local, true));

        // Already Jalali (Date Conversion's global conversion): the same date.
        $this->assertSame('1405/07/12', $dates->jalaliDate('1405/07/12', 'Y/m/d', $local, true));
    }

    public function test_an_admin_in_another_language_keeps_gregorian_dates(): void
    {
        $this->inLanguage('en_US', true);

        $this->assertSame('2026/10/04', (new GravityFormsEntryDates())->jalaliDate('2026/10/04', 'Y/m/d', gmmktime(13, 7, 0, 10, 4, 2026), true));
    }
}
