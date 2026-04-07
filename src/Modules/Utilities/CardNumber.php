<?php

namespace PersianKit\Modules\Utilities;

use PersianKit\Modules\DigitConversion\DigitConverter;

defined('ABSPATH') || exit;

class CardNumber
{
    /**
     * Bank BIN (first 6 digits) to Persian bank name.
     * Source: persian-tools v5.0.0-beta.0 banksCode.skip.ts (49 entries)
     *
     * @since 0.9.0
     */
    private const BANKS = [
        '636214' => 'بانک آینده',
        '627412' => 'بانک اقتصاد نوین',
        '627381' => 'بانک انصار',
        '505785' => 'بانک ایران زمین',
        '622106' => 'بانک پارسیان',
        '627884' => 'بانک پارسیان',
        '639194' => 'بانک پارسیان',
        '502229' => 'بانک پاسارگاد',
        '639347' => 'بانک پاسارگاد',
        '627760' => 'پست بانک ایران',
        '585983' => 'بانک تجارت',
        '627353' => 'بانک تجارت',
        '502908' => 'بانک توسعه تعاون',
        '207177' => 'بانک توسعه صادرات',
        '627648' => 'بانک توسعه صادرات',
        '636949' => 'بانک حکمت ایرانیان',
        '585947' => 'بانک خاورمیانه',
        '502938' => 'بانک دی',
        '504172' => 'بانک رسالت',
        '589463' => 'بانک رفاه کارگران',
        '621986' => 'بانک سامان',
        '589210' => 'بانک سپه',
        '639607' => 'بانک سرمایه',
        '639346' => 'بانک سینا',
        '502806' => 'بانک شهر',
        '504706' => 'بانک شهر',
        '603769' => 'بانک صادرات ایران',
        '903769' => 'بانک صادرات ایران',
        '627961' => 'بانک صنعت و معدن',
        '639370' => 'بانک قرض الحسنه مهر',
        '639599' => 'بانک قوامین',
        '627488' => 'بانک کارآفرین',
        '502910' => 'بانک کارآفرین',
        '603770' => 'بانک کشاورزی',
        '639217' => 'بانک کشاورزی',
        '505416' => 'بانک گردشگری',
        '505426' => 'بانک گردشگری',
        '636797' => 'بانک مرکزی ایران',
        '628023' => 'بانک مسکن',
        '610433' => 'بانک ملت',
        '991975' => 'بانک ملت',
        '170019' => 'بانک ملی ایران',
        '603799' => 'بانک ملی ایران',
        '606373' => 'بانک مهر ایران',
        '505801' => 'موسسه کوثر',
        '606256' => 'موسسه اعتباری ملل',
        '628157' => 'موسسه اعتباری توسعه',
        '636795' => 'بانک مرکزی جمهوری اسلامی ایران',
        '507677' => 'موسسه نور',
    ];

    public static function validate(string $input): ValidationResult
    {
        $input = trim($input);
        $input = DigitConverter::toEnglish($input);
        $input = preg_replace('/[\s\-]/', '', $input);

        if ($input === '') {
            return ValidationResult::failure('شماره کارت نمی‌تواند خالی باشد');
        }

        if (!preg_match('/^\d{16}$/', $input)) {
            return ValidationResult::failure('شماره کارت باید ۱۶ رقم باشد');
        }

        if (!self::luhn($input)) {
            return ValidationResult::failure('شماره کارت نامعتبر است');
        }

        $bin = substr($input, 0, 6);
        $bank = self::BANKS[$bin] ?? null;

        return ValidationResult::success([
            'bank' => $bank,
            'bin'  => $bin,
        ]);
    }

    private static function luhn(string $number): bool
    {
        $sum = 0;
        $length = strlen($number);

        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $number[$length - 1 - $i];

            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        return $sum % 10 === 0;
    }
}
