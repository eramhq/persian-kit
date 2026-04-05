<?php

namespace PersianKit\Modules\Utilities;

use PersianKit\Modules\DigitConversion\DigitConverter;

defined('ABSPATH') || exit;

class OrdinalNumber
{
    public static function toWord(int $n): string
    {
        if ($n < 1) {
            throw new \InvalidArgumentException('عدد ترتیبی باید بزرگ‌تر از صفر باشد');
        }

        $word = NumberToWords::convert($n);

        return self::addSuffix($word);
    }

    public static function toShort(int $n, string $digits = 'persian'): string
    {
        if ($n < 1) {
            throw new \InvalidArgumentException('عدد ترتیبی باید بزرگ‌تر از صفر باشد');
        }

        $str = (string) $n;

        if ($digits === 'persian') {
            $str = DigitConverter::toPersian($str);
        }

        return $str . 'ام';
    }

    public static function addSuffix(string $persianWord): string
    {
        $word = trim($persianWord);

        if ($word === '') {
            throw new \InvalidArgumentException('ورودی نمی‌تواند خالی باشد');
        }

        if (str_ends_with($word, 'سه')) {
            return mb_substr($word, 0, mb_strlen($word) - 2) . 'سوم';
        }

        if (str_ends_with($word, 'ی')) {
            return $word . ' اُم';
        }

        return $word . 'م';
    }
}
