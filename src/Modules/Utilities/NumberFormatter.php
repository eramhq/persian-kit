<?php

namespace PersianKit\Modules\Utilities;

use PersianKit\Modules\DigitConversion\DigitConverter;

defined('ABSPATH') || exit;

class NumberFormatter
{
    public static function withSeparators(int|float|string $number, string $separator = ','): string
    {
        if (is_string($number)) {
            $number = DigitConverter::toEnglish($number);
            $number = str_replace([',', '٬'], '', $number);
        }

        $numberStr = (string) $number;

        if (!preg_match('/^-?\d+(\.\d+)?$/', $numberStr)) {
            throw new \InvalidArgumentException("مقدار ورودی عددی معتبر نیست: {$numberStr}");
        }

        $negative = str_starts_with($numberStr, '-');
        if ($negative) {
            $numberStr = substr($numberStr, 1);
        }

        $parts = explode('.', $numberStr, 2);
        $integerPart = $parts[0];
        $decimalPart = $parts[1] ?? null;

        $integerPart = preg_replace('/\B(?=(\d{3})+(?!\d))/', $separator, $integerPart);

        $result = $integerPart;
        if ($decimalPart !== null) {
            $result .= '.' . $decimalPart;
        }

        if ($negative) {
            $result = '-' . $result;
        }

        return $result;
    }
}
