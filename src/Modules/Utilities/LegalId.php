<?php

namespace PersianKit\Modules\Utilities;

use PersianKit\Modules\DigitConversion\DigitConverter;

defined('ABSPATH') || exit;

class LegalId
{
    private const COEFFICIENTS = [29, 27, 23, 19, 17];

    public static function validate(string $input): ValidationResult
    {
        $input = trim($input);
        $input = DigitConverter::toEnglish($input);

        if ($input === '') {
            return ValidationResult::failure('شناسه حقوقی نمی‌تواند خالی باشد');
        }

        if (!preg_match('/^\d{11}$/', $input)) {
            return ValidationResult::failure('شناسه حقوقی باید ۱۱ رقم باشد');
        }

        $digits = array_map('intval', str_split($input));

        // Middle 6 digits (positions 3-8) must not all be zero
        $middle = array_slice($digits, 3, 6);
        if (array_sum($middle) === 0) {
            return ValidationResult::failure('شناسه حقوقی نامعتبر است');
        }

        $d = $digits[9] + 2;
        $sum = 0;

        for ($i = 0; $i < 10; $i++) {
            $sum += ($d + $digits[$i]) * self::COEFFICIENTS[$i % 5];
        }

        $checksum = $sum % 11;
        if ($checksum === 10) {
            $checksum = 0;
        }

        if ($digits[10] !== $checksum) {
            return ValidationResult::failure('شناسه حقوقی نامعتبر است');
        }

        return ValidationResult::success();
    }
}
