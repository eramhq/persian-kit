<?php

namespace PersianKit\Modules\Utilities;

defined('ABSPATH') || exit;

class NumberToWords
{
    private const ONES = ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];

    private const TEENS = ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده'];

    private const TENS = ['', '', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'];

    private const HUNDREDS = ['', 'یکصد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'];

    private const SCALES = ['', 'هزار', 'میلیون', 'میلیارد', 'تریلیون', 'کوادریلیون'];

    public static function convert(int|float $number): string
    {
        if ($number === 0 || $number === 0.0) {
            return 'صفر';
        }

        $prefix = '';
        if ($number < 0) {
            $prefix = 'منفی ';
            $number = abs($number);
        }

        if (is_float($number)) {
            $str = number_format($number, 10, '.', '');
            $parts = explode('.', $str);
            $integerPart = (int) $parts[0];
            $decimalPart = isset($parts[1]) ? rtrim($parts[1], '0') : '';

            $result = $integerPart === 0 && $decimalPart !== ''
                ? ''
                : self::convertInteger($integerPart);

            if ($decimalPart !== '') {
                $decimalValue = (int) $decimalPart;
                if ($decimalValue > 0) {
                    $separator = $result !== '' ? ' ممیز ' : 'صفر ممیز ';
                    $result .= $separator . self::convertInteger($decimalValue);
                }
            }

            return $prefix . ($result !== '' ? $result : 'صفر');
        }

        return $prefix . self::convertInteger((int) $number);
    }

    private static function convertInteger(int $number): string
    {
        if ($number === 0) {
            return 'صفر';
        }

        $groups = [];
        while ($number > 0) {
            $groups[] = $number % 1000;
            $number = intdiv($number, 1000);
        }

        $parts = [];
        for ($i = count($groups) - 1; $i >= 0; $i--) {
            if ($groups[$i] === 0) {
                continue;
            }

            $groupText = self::convertGroup($groups[$i]);
            if ($i > 0 && isset(self::SCALES[$i])) {
                $groupText .= ' ' . self::SCALES[$i];
            }

            $parts[] = $groupText;
        }

        return implode(' و ', $parts);
    }

    private static function convertGroup(int $number): string
    {
        $parts = [];

        $hundreds = intdiv($number, 100);
        if ($hundreds > 0) {
            $parts[] = self::HUNDREDS[$hundreds];
        }

        $remainder = $number % 100;

        if ($remainder >= 10 && $remainder <= 19) {
            $parts[] = self::TEENS[$remainder - 10];
        } else {
            $tens = intdiv($remainder, 10);
            $ones = $remainder % 10;

            if ($tens > 0) {
                $parts[] = self::TENS[$tens];
            }
            if ($ones > 0) {
                $parts[] = self::ONES[$ones];
            }
        }

        return implode(' و ', $parts);
    }
}
