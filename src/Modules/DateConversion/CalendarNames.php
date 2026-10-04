<?php

namespace PersianKit\Modules\DateConversion;

use PersianKit\Service\Language\ContentLanguage;

defined('ABSPATH') || exit;

/**
 * The names a Jalali date is written with. Afghan and Kurdish readers use
 * the same calendar (same days, leap years and year number) under other
 * names: Dari حمل…حوت, Pashto وری…کب, Sorani Kurdish خاکەلێوە…ڕەشەمە.
 *
 * Each request uses the set its language reads (setFor()), unless the
 * settings fix one: for the site, or on multilingual sites for each
 * language. Until the Jalali dates module passes its settings in
 * (configure()), every date uses the Iranian names.
 *
 * Persian ی and ک throughout, never Arabic ي and ك, and no ezafe (ٔ).
 *
 * @phpstan-type Names array{
 *     months: array<int, string>,
 *     months_short: array<int, string>,
 *     weekdays: array<int, string>,
 *     weekdays_short: array<int, string>,
 *     am: string,
 *     pm: string,
 *     am_long: string,
 *     pm_long: string,
 *     ordinal: string,
 *     gregorian_months: array<int, string>,
 *     seasons: list<string>
 * }
 */
final class CalendarNames
{
    /** The name sets; the first is the default. */
    public const SETS = ['iranian', 'dari', 'pashto', 'kurdish'];

    /** The setting's values: 'auto' follows the language. */
    public const CHOICES = ['auto', 'iranian', 'dari', 'pashto', 'kurdish'];

    private const IRANIAN_WEEKDAYS = [
        0 => 'یکشنبه',
        1 => 'دوشنبه',
        2 => 'سه‌شنبه',
        3 => 'چهارشنبه',
        4 => 'پنج‌شنبه',
        5 => 'جمعه',
        6 => 'شنبه',
    ];

    private const IRANIAN_WEEKDAYS_SHORT = [0 => 'ی', 1 => 'د', 2 => 'س', 3 => 'چ', 4 => 'پ', 5 => 'ج', 6 => 'ش'];

    private const DARI_MONTHS = [
        1  => 'حمل',
        2  => 'ثور',
        3  => 'جوزا',
        4  => 'سرطان',
        5  => 'اسد',
        6  => 'سنبله',
        7  => 'میزان',
        8  => 'عقرب',
        9  => 'قوس',
        10 => 'جدی',
        11 => 'دلو',
        12 => 'حوت',
    ];

    private const PASHTO_MONTHS = [
        1  => 'وری',
        2  => 'غویی',
        3  => 'غبرګولی',
        4  => 'چنګاښ',
        5  => 'زمری',
        6  => 'وږی',
        7  => 'تله',
        8  => 'لړم',
        9  => 'لیندۍ',
        10 => 'مرغومی',
        11 => 'سلواغه',
        12 => 'کب',
    ];

    /** As Afghan news sites write them (BBC Pashto, Pajhwok). */
    private const PASHTO_WEEKDAYS = [
        0 => 'یکشنبه',
        1 => 'دوشنبه',
        2 => 'سې شنبه',
        3 => 'چهارشنبه',
        4 => 'پنجشنبه',
        5 => 'جمعه',
        6 => 'شنبه',
    ];

    private const KURDISH_MONTHS = [
        1  => 'خاکەلێوە',
        2  => 'گوڵان',
        3  => 'جۆزەردان',
        4  => 'پووشپەڕ',
        5  => 'گەلاوێژ',
        6  => 'خەرمانان',
        7  => 'ڕەزبەر',
        8  => 'گەڵاڕێزان',
        9  => 'سەرماوەز',
        10 => 'بەفرانبار',
        11 => 'ڕێبەندان',
        12 => 'ڕەشەمە',
    ];

    private const KURDISH_WEEKDAYS = [
        0 => 'یەکشەممە',
        1 => 'دووشەممە',
        2 => 'سێشەممە',
        3 => 'چوارشەممە',
        4 => 'پێنجشەممە',
        5 => 'هەینی',
        6 => 'شەممە',
    ];

    /**
     * Months are indexed 1–12, weekdays as PHP's 'w' (Sunday 0), seasons
     * from spring.
     *
     * @var array<string, Names>
     */
    private const NAMES = [
        'iranian' => [
            'months'           => [
                1  => 'فروردین',
                2  => 'اردیبهشت',
                3  => 'خرداد',
                4  => 'تیر',
                5  => 'مرداد',
                6  => 'شهریور',
                7  => 'مهر',
                8  => 'آبان',
                9  => 'آذر',
                10 => 'دی',
                11 => 'بهمن',
                12 => 'اسفند',
            ],
            'months_short'     => [
                1  => 'فرو',
                2  => 'ارد',
                3  => 'خرد',
                4  => 'تیر',
                5  => 'مرد',
                6  => 'شهر',
                7  => 'مهر',
                8  => 'آبا',
                9  => 'آذر',
                10 => 'دی',
                11 => 'بهم',
                12 => 'اسف',
            ],
            'weekdays'         => self::IRANIAN_WEEKDAYS,
            'weekdays_short'   => self::IRANIAN_WEEKDAYS_SHORT,
            'am'               => 'ق.ظ',
            'pm'               => 'ب.ظ',
            'am_long'          => 'قبل از ظهر',
            'pm_long'          => 'بعد از ظهر',
            'ordinal'          => 'ام',
            'gregorian_months' => [
                1  => 'ژانویه',
                2  => 'فوریه',
                3  => 'مارس',
                4  => 'آوریل',
                5  => 'مه',
                6  => 'ژوئن',
                7  => 'ژوئیه',
                8  => 'اوت',
                9  => 'سپتامبر',
                10 => 'اکتبر',
                11 => 'نوامبر',
                12 => 'دسامبر',
            ],
            'seasons'          => ['بهار', 'تابستان', 'پاییز', 'زمستان'],
        ],
        'dari'    => [
            'months'           => self::DARI_MONTHS,
            'months_short'     => self::DARI_MONTHS,
            'weekdays'         => self::IRANIAN_WEEKDAYS,
            'weekdays_short'   => self::IRANIAN_WEEKDAYS_SHORT,
            'am'               => 'ق.ظ',
            'pm'               => 'ب.ظ',
            'am_long'          => 'قبل از ظهر',
            'pm_long'          => 'بعد از ظهر',
            'ordinal'          => 'ام',
            'gregorian_months' => [
                1  => 'جنوری',
                2  => 'فبروری',
                3  => 'مارچ',
                4  => 'اپریل',
                5  => 'می',
                6  => 'جون',
                7  => 'جولای',
                8  => 'اگست',
                9  => 'سپتمبر',
                10 => 'اکتوبر',
                11 => 'نومبر',
                12 => 'دسمبر',
            ],
            'seasons'          => ['بهار', 'تابستان', 'خزان', 'زمستان'],
        ],
        'pashto'  => [
            'months'           => self::PASHTO_MONTHS,
            'months_short'     => self::PASHTO_MONTHS,
            'weekdays'         => self::PASHTO_WEEKDAYS,
            'weekdays_short'   => self::PASHTO_WEEKDAYS,
            'am'               => 'غ.م.',
            'pm'               => 'غ.و.',
            'am_long'          => 'غ.م.',
            'pm_long'          => 'غ.و.',
            'ordinal'          => '',
            'gregorian_months' => [
                1  => 'جنوری',
                2  => 'فبروری',
                3  => 'مارچ',
                4  => 'اپریل',
                5  => 'مۍ',
                6  => 'جون',
                7  => 'جولای',
                8  => 'اګست',
                9  => 'سېپتمبر',
                10 => 'اکتوبر',
                11 => 'نومبر',
                12 => 'دسمبر',
            ],
            'seasons'          => ['پسرلی', 'دوبی', 'منی', 'ژمی'],
        ],
        'kurdish' => [
            'months'           => self::KURDISH_MONTHS,
            'months_short'     => self::KURDISH_MONTHS,
            'weekdays'         => self::KURDISH_WEEKDAYS,
            'weekdays_short'   => self::KURDISH_WEEKDAYS,
            'am'               => 'ب.ن',
            'pm'               => 'د.ن',
            'am_long'          => 'ب.ن',
            'pm_long'          => 'د.ن',
            'ordinal'          => '',
            'gregorian_months' => [
                1  => 'کانوونی دووەم',
                2  => 'شوبات',
                3  => 'ئازار',
                4  => 'نیسان',
                5  => 'ئایار',
                6  => 'حوزەیران',
                7  => 'تەمووز',
                8  => 'ئاب',
                9  => 'ئەیلوول',
                10 => 'تشرینی یەکەم',
                11 => 'تشرینی دووەم',
                12 => 'کانوونی یەکەم',
            ],
            'seasons'          => ['بەهار', 'هاوین', 'پاییز', 'زستان'],
        ],
    ];

    /** The site's choice, or null until configure(). */
    private static ?string $siteChoice = null;

    /** @var array<string, string> Choice by locale, on multilingual sites. */
    private static array $localeChoices = [];

    /** @var array<string, string> Set by locale, for this request. */
    private static array $resolved = [];

    /**
     * The settings: the site's choice, and each language's on multilingual
     * sites. Unknown values count as 'auto'.
     *
     * @param array<string, string> $byLocale
     */
    public static function configure(string $siteChoice, array $byLocale = []): void
    {
        self::$siteChoice = self::choice($siteChoice);
        self::$localeChoices = [];
        foreach ($byLocale as $locale => $choice) {
            if (self::choice((string) $choice) !== 'auto') {
                self::$localeChoices[(string) $locale] = self::choice((string) $choice);
            }
        }
        self::$resolved = [];
    }

    /**
     * @internal For tests.
     */
    public static function reset(): void
    {
        self::$siteChoice = null;
        self::$localeChoices = [];
        self::$resolved = [];
    }

    /**
     * One set's names; the Iranian names for an unknown set.
     *
     * @return Names
     */
    public static function for(string $set): array
    {
        return self::NAMES[$set] ?? self::NAMES['iranian'];
    }

    /**
     * The names this request's dates use.
     *
     * @return Names
     */
    public static function current(): array
    {
        return self::for(self::currentSet());
    }

    /**
     * A Jalali month and its year, such as "مهر 1405", with English digits.
     */
    public static function monthAndYear(int $year, int $month): string
    {
        return self::current()['months'][$month] . ' ' . $year;
    }

    public static function currentSet(): string
    {
        if (self::$siteChoice === null) {
            return 'iranian';
        }

        return self::setForLocale(ContentLanguage::currentLocale());
    }

    /**
     * The set a locale's dates use: the one the settings give it, else the
     * one it reads.
     */
    public static function setForLocale(string $locale): string
    {
        if (isset(self::$resolved[$locale])) {
            return self::$resolved[$locale];
        }

        $choice = ContentLanguage::isMultilingual() ? (self::$localeChoices[$locale] ?? 'auto') : (self::$siteChoice ?? 'auto');
        $set = $choice === 'auto' ? self::setFor($locale) : $choice;

        /**
         * The month and weekday names of Jalali dates in a language.
         *
         * @param string $set    'iranian', 'dari', 'pashto' or 'kurdish'.
         * @param string $locale A WordPress locale, such as fa_AF.
         */
        $set = apply_filters('persian_kit_calendar_names', $set, $locale);

        return self::$resolved[$locale] = in_array($set, self::SETS, true) ? $set : self::SETS[0];
    }

    /**
     * The set a locale reads: Dari for fa_AF, Pashto for ps, Sorani Kurdish
     * for ckb, Iranian for the rest.
     */
    public static function setFor(string $locale): string
    {
        return match (true) {
            $locale === 'fa_AF'              => 'dari',
            self::isLanguage($locale, 'ps')  => 'pashto',
            self::isLanguage($locale, 'ckb') => 'kurdish',
            default                          => 'iranian',
        };
    }

    /**
     * Whether the settings give a language of a multilingual site its own
     * set, so its pages show Jalali dates whatever language it is.
     */
    public static function isFixedFor(string $locale): bool
    {
        return isset(self::$localeChoices[$locale]) && ContentLanguage::isMultilingual();
    }

    /**
     * Whether a locale is $language or a region of it: ps, ps_AF.
     */
    public static function isLanguage(string $locale, string $language): bool
    {
        return $locale === $language || str_starts_with($locale, $language . '_');
    }

    private static function choice(string $value): string
    {
        return in_array($value, self::CHOICES, true) ? $value : 'auto';
    }
}
