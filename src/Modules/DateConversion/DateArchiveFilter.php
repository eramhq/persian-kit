<?php

namespace PersianKit\Modules\DateConversion;

use PersianKit\Dependencies\Eram\Abzar\Digits\DigitConverter;

defined('ABSPATH') || exit;

/**
 * Titles for date archives. A Jalali archive (/1405/07/, see
 * JalaliDateArchive) is named for its period ("مهر 1405"). A Gregorian one
 * (/2025/03/) names the Jalali period it covers ("اسفند 1403 – فروردین 1404")
 * instead of the first post's Jalali month.
 *
 * Date links built from Jalali parts, such as
 * get_month_link(get_the_time('Y'), get_the_time('m')), already lead to the
 * Jalali archive; parts written with Persian digits are made ASCII.
 */
class DateArchiveFilter
{
    public function register(): void
    {
        add_filter('get_the_archive_title', [$this, 'filterArchiveTitle'], 10, 3);
        add_filter('document_title_parts', [$this, 'filterDocumentTitleParts']);
        add_filter('year_link', [$this, 'filterYearLink'], 10, 2);
        add_filter('month_link', [$this, 'filterMonthLink'], 10, 3);
        add_filter('day_link', [$this, 'filterDayLink'], 10, 4);
    }

    public function filterArchiveTitle(string $title, string $originalTitle = '', string $prefix = ''): string
    {
        $label = $this->currentArchiveLabel();
        if ($label === null) {
            return $title;
        }

        $label = esc_html($label);

        if ($originalTitle !== '' && str_contains($title, $originalTitle)) {
            return str_replace($originalTitle, $label, $title);
        }

        return $prefix !== '' ? $prefix . ' <span>' . $label . '</span>' : $label;
    }

    /**
     * @param array<string, string> $parts
     * @return array<string, string>
     */
    public function filterDocumentTitleParts(array $parts): array
    {
        $label = $this->currentArchiveLabel();
        if ($label !== null) {
            $parts['title'] = $label;
        }

        return $parts;
    }

    public function filterYearLink(string $link, mixed $year): string
    {
        $parts = $this->asciiParts($year, null, null);

        return $parts === null ? $link : get_year_link($parts[0]);
    }

    public function filterMonthLink(string $link, mixed $year, mixed $month): string
    {
        $parts = $this->asciiParts($year, $month, null);

        return $parts === null ? $link : get_month_link($parts[0], $parts[1]);
    }

    public function filterDayLink(string $link, mixed $year, mixed $month, mixed $day): string
    {
        $parts = $this->asciiParts($year, $month, $day);

        return $parts === null ? $link : get_day_link($parts[0], $parts[1], $parts[2]);
    }

    /**
     * The Jalali label for the date archive being viewed, or null elsewhere.
     */
    public function currentArchiveLabel(): ?string
    {
        if (is_admin() || !is_date() || is_feed()) {
            return null;
        }

        $jalali = JalaliDateArchive::current();
        if ($jalali !== null) {
            return $this->jalaliArchiveLabel($jalali['year'], $jalali['month'], $jalali['day']);
        }

        [$year, $month, $day] = $this->queriedDate();
        if ($year === 0) {
            return null;
        }

        if (is_day() && $month > 0 && $day > 0) {
            $date = $this->gregorianDate($year, $month, $day);

            return $date === null ? null : JalaliFormatter::formatDateTime(get_option('date_format'), $date);
        }

        if (is_month() && $month > 0) {
            $start = $this->gregorianDate($year, $month, 1);
            $end = $start === null ? null : $this->gregorianDate($year, $month, (int) $start->format('t'));

            return $start === null || $end === null ? null : $this->monthRangeLabel($start, $end);
        }

        if (is_year()) {
            $start = $this->gregorianDate($year, 1, 1);
            $end = $this->gregorianDate($year, 12, 31);

            return $start === null || $end === null ? null : $this->range(
                JalaliFormatter::formatDateTime('Y', $start),
                JalaliFormatter::formatDateTime('Y', $end)
            );
        }

        return null;
    }

    private function jalaliArchiveLabel(int $year, ?int $month, ?int $day): ?string
    {
        if (is_day() && $month !== null && $day !== null) {
            $date = JalaliPeriod::toDateTime($year, $month, $day);

            return $date === null ? null : JalaliFormatter::formatDateTime(get_option('date_format'), $date);
        }

        $date = JalaliPeriod::toDateTime($year, $month ?? 1, 1);
        if ($date === null) {
            return null;
        }

        if (is_month() && $month !== null) {
            return JalaliFormatter::formatDateTime('F Y', $date);
        }

        return is_year() ? JalaliFormatter::formatDateTime('Y', $date) : null;
    }

    private function monthRangeLabel(\DateTimeImmutable $start, \DateTimeImmutable $end): string
    {
        $sameYear = JalaliFormatter::formatDateTime('Y', $start) === JalaliFormatter::formatDateTime('Y', $end);

        return $this->range(
            JalaliFormatter::formatDateTime($sameYear ? 'F' : 'F Y', $start),
            JalaliFormatter::formatDateTime('F Y', $end)
        );
    }

    private function range(string $start, string $end): string
    {
        if ($start === $end) {
            return $start;
        }

        /* translators: 1: start of a date range, 2: end of a date range. */
        return sprintf(_x('%1$s – %2$s', 'date range', 'persian-kit'), $start, $end);
    }

    /**
     * Gregorian year, month and day of the current date query (0 when absent),
     * read from year/monthnum/day or from the compact "m" query var.
     *
     * @return array{int, int, int}
     */
    private function queriedDate(): array
    {
        $m = preg_replace('/\D/', '', (string) get_query_var('m'));

        if (is_string($m) && strlen($m) >= 4) {
            return [
                (int) substr($m, 0, 4),
                strlen($m) >= 6 ? (int) substr($m, 4, 2) : 0,
                strlen($m) >= 8 ? (int) substr($m, 6, 2) : 0,
            ];
        }

        return [
            (int) get_query_var('year'),
            (int) get_query_var('monthnum'),
            (int) get_query_var('day'),
        ];
    }

    private function gregorianDate(int $year, int $month, int $day): ?\DateTimeImmutable
    {
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        // Noon keeps the calendar day clear of DST transitions.
        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d 12:00:00', $year, $month, $day), wp_timezone());
    }

    /**
     * ASCII [year, month, day] for link parts written with Persian or Arabic
     * digits, or null when they are already ASCII or not numbers.
     *
     * @return array{int, int, int}|null
     */
    private function asciiParts(mixed $year, mixed $month, mixed $day): ?array
    {
        $parts = [];
        $hadNonAsciiDigits = false;

        foreach ([$year, $month, $day] as $raw) {
            if ($raw === null) {
                $parts[] = 0;
                continue;
            }

            if (!is_int($raw) && !is_string($raw)) {
                return null;
            }

            $raw = trim((string) $raw);
            $english = DigitConverter::toEnglish($raw);
            if ($english === '' || !ctype_digit($english)) {
                return null;
            }

            $hadNonAsciiDigits = $hadNonAsciiDigits || $english !== $raw;
            $parts[] = (int) $english;
        }

        return $hadNonAsciiDigits ? [$parts[0], $parts[1], $parts[2]] : null;
    }
}
