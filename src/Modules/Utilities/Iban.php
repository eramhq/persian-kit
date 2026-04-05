<?php

namespace PersianKit\Modules\Utilities;

use PersianKit\Modules\DigitConversion\DigitConverter;

defined('ABSPATH') || exit;

class Iban
{
    /**
     * IBAN bank codes (3-digit, positions 4-6).
     * Source: persian-tools v5.0.0-beta.0 sheba/codes.skip.ts (37 entries + 2)
     *
     * @since 1.0.0
     */
    private const BANKS = [
        '010' => 'بانک مرکزی جمهوری اسلامی ایران',
        '011' => 'بانک صنعت و معدن',
        '012' => 'بانک ملت',
        '013' => 'بانک رفاه کارگران',
        '014' => 'بانک مسکن',
        '015' => 'بانک سپه',
        '016' => 'بانک کشاورزی',
        '017' => 'بانک ملی ایران',
        '018' => 'بانک تجارت',
        '019' => 'بانک صادرات ایران',
        '020' => 'بانک توسعه صادرات',
        '021' => 'پست بانک ایران',
        '022' => 'بانک توسعه تعاون',
        '051' => 'موسسه اعتباری توسعه',
        '052' => 'بانک قوامین',
        '053' => 'بانک کارآفرین',
        '054' => 'بانک پارسیان',
        '055' => 'بانک اقتصاد نوین',
        '056' => 'بانک سامان',
        '057' => 'بانک پاسارگاد',
        '058' => 'بانک سرمایه',
        '059' => 'بانک سینا',
        '060' => 'بانک مهر ایران',
        '061' => 'بانک شهر',
        '062' => 'بانک آینده',
        '063' => 'بانک انصار',
        '064' => 'بانک گردشگری',
        '065' => 'بانک حکمت ایرانیان',
        '066' => 'بانک دی',
        '069' => 'بانک ایران زمین',
        '070' => 'بانک قرض الحسنه رسالت',
        '073' => 'موسسه اعتباری کوثر',
        '075' => 'موسسه اعتباری ملل',
        '078' => 'بانک خاورمیانه',
        '079' => 'بانک مهر اقتصاد',
        '080' => 'موسسه اعتباری نور',
        '090' => 'بانک مهر ایران',
        '095' => 'بانک ایران و ونزوئلا',
    ];

    public static function validate(string $input): ValidationResult
    {
        $input = trim($input);
        $input = DigitConverter::toEnglish($input);
        $input = strtoupper($input);
        $input = preg_replace('/\s/', '', $input);

        if ($input === '') {
            return ValidationResult::failure('شماره شبا نمی‌تواند خالی باشد');
        }

        // Auto-prepend IR for 24-digit input
        if (preg_match('/^\d{24}$/', $input)) {
            $input = 'IR' . $input;
        }

        if (!preg_match('/^IR\d{24}$/', $input)) {
            if (preg_match('/^[A-Z]{2}/', $input) && !str_starts_with($input, 'IR')) {
                return ValidationResult::failure('شماره شبا باید با IR شروع شود');
            }
            return ValidationResult::failure('شماره شبا باید ۲۶ کاراکتر باشد (IR + ۲۴ رقم)');
        }

        if (!self::mod97($input)) {
            return ValidationResult::failure('شماره شبا نامعتبر است');
        }

        $bankCode = substr($input, 4, 3);
        $bank = self::BANKS[$bankCode] ?? null;

        return ValidationResult::success([
            'bank_code' => $bankCode,
            'bank'      => $bank,
        ]);
    }

    private static function mod97(string $iban): bool
    {
        // Move first 4 chars to end
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);

        // Convert letters to numbers: A=10, B=11, ..., Z=35
        $numeric = '';
        for ($i = 0; $i < strlen($rearranged); $i++) {
            $char = $rearranged[$i];
            if (ctype_alpha($char)) {
                $numeric .= (string) (ord($char) - ord('A') + 10);
            } else {
                $numeric .= $char;
            }
        }

        // Iterative mod-97 to avoid big-number overflow
        $remainder = '';
        for ($i = 0; $i < strlen($numeric); $i++) {
            $remainder .= $numeric[$i];
            $remainder = (string) ((int) $remainder % 97);
        }

        return (int) $remainder === 1;
    }
}
