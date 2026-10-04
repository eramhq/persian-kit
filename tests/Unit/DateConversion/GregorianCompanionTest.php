<?php

namespace PersianKit\Tests\Unit\DateConversion;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PersianKit\Modules\DateConversion\GregorianCompanion;
use PersianKit\Service\Language\ContentLanguage;
use PHPUnit\Framework\TestCase;

class GregorianCompanionTest extends TestCase
{
    private const JALALI = '۱۰ مهر ۱۴۰۵';

    private \DateTimeImmutable $date;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        ContentLanguage::reset();

        Functions\when('is_admin')->justReturn(false);
        Functions\when('wp_doing_ajax')->justReturn(false);
        Functions\when('wp_is_serving_rest_request')->justReturn(false);

        $this->date = new \DateTimeImmutable('2026-10-02 10:30:00', new \DateTimeZone('Asia/Tehran'));
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * @dataProvider fullDateFormats
     */
    public function test_full_dates_get_the_gregorian_date(string $format): void
    {
        $this->assertTrue(GregorianCompanion::isFullDate($format));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function fullDateFormats(): array
    {
        return [
            'day month year'       => ['j F Y'],
            'numbers'              => ['Y/m/d'],
            'weekday and time'     => ['l j F Y H:i'],
            'short year'           => ['d.m.y'],
            'escaped words around' => ['\\R\\o\\z j F Y'],
        ];
    }

    /**
     * @dataProvider partialFormats
     */
    public function test_times_and_parts_of_a_date_stay_alone(string $format): void
    {
        $this->assertFalse(GregorianCompanion::isFullDate($format));
        $this->assertSame('x', (new GregorianCompanion(true))->append('x', $format, $this->date));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function partialFormats(): array
    {
        return [
            'time'           => ['H:i'],
            'year'           => ['Y'],
            'month and year' => ['F Y'],
            'day'            => ['j'],
            'escaped tokens' => ['\\Y\\e\\a\\r Y'],
            'escaped day'    => ['\\j F Y'],
            'ISO 8601'       => ['c'],
            'RFC 2822'       => ['r'],
            'Unix timestamp' => ['U'],
        ];
    }

    /**
     * @dataProvider machineFormats
     */
    public function test_machine_formats_stay_alone(string $format): void
    {
        $this->assertSame('x', (new GregorianCompanion(true))->append('x', $format, $this->date));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function machineFormats(): array
    {
        return [
            'MySQL'    => ['Y-m-d H:i:s'],
            'RFC 3339' => [DATE_RFC3339],
            'W3C'      => [DATE_W3C],
        ];
    }

    public function test_numbers_by_default(): void
    {
        $this->assertSame(
            self::JALALI . ' (' . self::ltr('2026-10-02') . ')',
            (new GregorianCompanion(true))->append(self::JALALI, 'j F Y', $this->date)
        );
    }

    public function test_month_names_in_persian_without_ezafe(): void
    {
        $companion = new GregorianCompanion(true, 'named');

        $this->assertSame(self::JALALI . ' (2 اکتبر 2026)', $companion->append(self::JALALI, 'j F Y', $this->date));
        $this->assertSame('5 مه 2026', GregorianCompanion::gregorian('j F Y', new \DateTimeImmutable('2026-05-05')));
        $this->assertSame('1 ژانویه 2026', GregorianCompanion::gregorian('j F Y', new \DateTimeImmutable('2026-01-01')));
    }

    public function test_the_gregorian_part_is_the_date_only(): void
    {
        $this->assertSame(
            'شنبه ۱۰ مهر ۱۴۰۵ ۱۰:۳۰ (' . self::ltr('2026-10-02') . ')',
            (new GregorianCompanion(true))->append('شنبه ۱۰ مهر ۱۴۰۵ ۱۰:۳۰', 'l j F Y H:i', $this->date)
        );
    }

    public function test_the_gregorian_date_can_come_first(): void
    {
        $this->assertSame(
            self::ltr('2026-10-02') . ' (' . self::JALALI . ')',
            (new GregorianCompanion(true, 'numeric', 'gregorian_first'))->append(self::JALALI, 'j F Y', $this->date)
        );
    }

    /**
     * @dataProvider separators
     */
    public function test_separators(string $separator, string $expected): void
    {
        $companion = new GregorianCompanion(true, 'numeric', 'jalali_first', $separator);

        $this->assertSame($expected, $companion->append(self::JALALI, 'j F Y', $this->date));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function separators(): array
    {
        return [
            'parentheses' => ['parentheses', self::JALALI . ' (' . self::ltr('2026-10-02') . ')'],
            'slash'       => ['slash', self::JALALI . ' / ' . self::ltr('2026-10-02')],
            'dash'        => ['dash', self::JALALI . ' – ' . self::ltr('2026-10-02')],
            'unknown'     => ['brackets', self::JALALI . ' (' . self::ltr('2026-10-02') . ')'],
        ];
    }

    public function test_off_by_default(): void
    {
        $this->assertSame(self::JALALI, (new GregorianCompanion(false))->append(self::JALALI, 'j F Y', $this->date));
    }

    public function test_admin_screens_keep_one_date(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $this->assertSame(self::JALALI, (new GregorianCompanion(true))->append(self::JALALI, 'j F Y', $this->date));
    }

    public function test_rest_requests_keep_one_date(): void
    {
        Functions\when('wp_is_serving_rest_request')->justReturn(true);

        $this->assertSame(self::JALALI, (new GregorianCompanion(true))->append(self::JALALI, 'j F Y', $this->date));
    }

    public function test_front_end_ajax_gets_both_dates(): void
    {
        Functions\when('is_admin')->justReturn(true);
        Functions\when('wp_doing_ajax')->justReturn(true);
        Functions\when('wp_get_raw_referer')->justReturn('https://example.com/blog/');
        Functions\when('admin_url')->justReturn('https://example.com/wp-admin/');

        $this->assertSame(
            self::JALALI . ' (' . self::ltr('2026-10-02') . ')',
            (new GregorianCompanion(true))->append(self::JALALI, 'j F Y', $this->date)
        );
    }

    public function test_a_theme_can_turn_it_off_for_one_spot(): void
    {
        Filters\expectApplied('persian_kit_gregorian_date')
            ->once()
            ->with(true, 'j F Y', $this->date)
            ->andReturn(false);

        $this->assertSame(self::JALALI, (new GregorianCompanion(true))->append(self::JALALI, 'j F Y', $this->date));
    }

    public function test_the_gregorian_part_can_be_filtered(): void
    {
        Filters\expectApplied('persian_kit_gregorian_date_display')
            ->once()
            ->with('2026-10-02', 'Y-m-d', $this->date->getTimestamp(), \Mockery::type(\DateTimeZone::class))
            ->andReturn('۲۰۲۶-۱۰-۰۲');

        $this->assertSame(
            self::JALALI . ' (' . self::ltr('۲۰۲۶-۱۰-۰۲') . ')',
            (new GregorianCompanion(true))->append(self::JALALI, 'j F Y', $this->date)
        );
    }

    public function test_an_empty_gregorian_part_leaves_the_jalali_date_alone(): void
    {
        Filters\expectApplied('persian_kit_gregorian_date_display')->andReturn('');

        $this->assertSame(self::JALALI, (new GregorianCompanion(true))->append(self::JALALI, 'j F Y', $this->date));
    }

    /**
     * A numeric Gregorian date as the page gets it: between invisible
     * left-to-right isolate marks.
     */
    private static function ltr(string $date): string
    {
        return "\u{2066}" . $date . "\u{2069}";
    }
}
