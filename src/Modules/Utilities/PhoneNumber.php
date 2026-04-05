<?php

namespace PersianKit\Modules\Utilities;

use PersianKit\Modules\DigitConversion\DigitConverter;

defined('ABSPATH') || exit;

class PhoneNumber
{
    /**
     * Operator prefixes (3-digit after 09).
     * Source: persian-tools v5.0.0-beta.0
     *
     * @since 1.0.0
     */
    private const OPERATORS = [
        // MCI (همراه اول)
        '910' => 'همراه اول',
        '911' => 'همراه اول',
        '912' => 'همراه اول',
        '913' => 'همراه اول',
        '914' => 'همراه اول',
        '915' => 'همراه اول',
        '916' => 'همراه اول',
        '917' => 'همراه اول',
        '918' => 'همراه اول',
        '919' => 'همراه اول',
        '990' => 'همراه اول',
        '991' => 'همراه اول',
        '992' => 'همراه اول',
        '993' => 'همراه اول',
        '994' => 'همراه اول',
        '995' => 'همراه اول',
        '996' => 'همراه اول',
        // Irancell (ایرانسل)
        '900' => 'ایرانسل',
        '901' => 'ایرانسل',
        '902' => 'ایرانسل',
        '903' => 'ایرانسل',
        '904' => 'ایرانسل',
        '905' => 'ایرانسل',
        '930' => 'ایرانسل',
        '933' => 'ایرانسل',
        '935' => 'ایرانسل',
        '936' => 'ایرانسل',
        '937' => 'ایرانسل',
        '938' => 'ایرانسل',
        '939' => 'ایرانسل',
        '941' => 'ایرانسل',
        // RighTel (رایتل)
        '920' => 'رایتل',
        '921' => 'رایتل',
        '922' => 'رایتل',
        '923' => 'رایتل',
        // Taliya (تالیا)
        '932' => 'تالیا',
        // Shatel Mobile (شاتل موبایل)
        '998' => 'شاتل موبایل',
        // Aptel (آپتل)
        '999' => 'آپتل',
    ];

    public static function validate(string $input): ValidationResult
    {
        $input = trim($input);
        $input = DigitConverter::toEnglish($input);
        $input = preg_replace('/[\s()\-]/', '', $input);

        if ($input === '') {
            return ValidationResult::failure('شماره تلفن نمی‌تواند خالی باشد');
        }

        // Normalize prefix
        if (str_starts_with($input, '+98')) {
            $input = '0' . substr($input, 3);
        } elseif (str_starts_with($input, '0098')) {
            $input = '0' . substr($input, 4);
        } elseif (str_starts_with($input, '98') && strlen($input) === 12) {
            $input = '0' . substr($input, 2);
        } elseif (preg_match('/^9\d{9}$/', $input)) {
            $input = '0' . $input;
        }

        if (!preg_match('/^09\d{9}$/', $input)) {
            return ValidationResult::failure('شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود');
        }

        $prefix = substr($input, 1, 3);
        $operator = self::OPERATORS[$prefix] ?? null;

        return ValidationResult::success([
            'normalized' => $input,
            'operator'   => $operator,
            'type'       => 'mobile',
        ]);
    }

    public static function normalize(string $input): ?string
    {
        $result = self::validate($input);

        if (!$result->isValid()) {
            return null;
        }

        return $result->details()['normalized'];
    }
}
