<?php

namespace PersianKit\Modules\DateConversion;

defined('ABSPATH') || exit;

/**
 * The Jalali date picker for form fields: the bundled <intl-datepicker>
 * (public/js/datepicker.js) and the script that puts it on every
 * <input data-persian-kit-date> (public/js/date-field.js).
 *
 * The field keeps submitting a Gregorian date, so whatever reads the form
 * needs no change. Integrations add the attributes() to their date inputs
 * and call enqueue() on the pages that have one.
 */
final class DatePicker
{
    public const SCRIPT = 'persian-kit-datepicker';
    public const FIELD = 'persian-kit-date-field';

    /**
     * Value formats the field script reads and writes, besides day, month
     * and year in any order with -, / or . between them (isFormat()).
     */
    public const FORMATS = ['Y-m-d', 'Ymd', 'Y-m-d H:i:s'];

    /** Picker types; types other than 'date' submit the picker's own value. */
    public const TYPES = ['date', 'range', 'multiple', 'month', 'year'];

    /**
     * Intl tags with each set's month names. Plain ckb has no Solar Hijri
     * names in CLDR, ckb-IR has. Where the browser lacks a tag's names, the
     * picker falls back to fa-AF or fa-IR (datepicker-entry.js).
     */
    private const SET_LOCALES = [
        'iranian' => 'fa-IR',
        'dari'    => 'fa-AF',
        'pashto'  => 'ps-AF',
        'kurdish' => 'ckb-IR',
    ];

    public static function register(): void
    {
        if (wp_script_is(self::FIELD, 'registered')) {
            return;
        }

        wp_register_script(
            self::SCRIPT,
            PERSIAN_KIT_URL . 'public/js/datepicker.js',
            [],
            PERSIAN_KIT_VERSION,
            true
        );

        wp_register_script(
            self::FIELD,
            PERSIAN_KIT_URL . 'public/js/date-field.js',
            [self::SCRIPT],
            PERSIAN_KIT_VERSION,
            true
        );

        wp_add_inline_script(
            self::FIELD,
            'window.persianKitDateField = ' . wp_json_encode([
                'locale' => self::locale(),
                'labels' => [
                    'time' => __('Time', 'persian-kit'),
                ],
            ]) . ';',
            'before'
        );

        wp_register_style(
            self::FIELD,
            PERSIAN_KIT_URL . 'public/css/date-field.css',
            [],
            PERSIAN_KIT_VERSION
        );
    }

    public static function enqueue(): void
    {
        self::register();

        wp_enqueue_script(self::FIELD);
        wp_enqueue_style(self::FIELD);
    }

    /**
     * The attributes that turn an <input> into a Jalali date field.
     *
     * @param array{format?: string, type?: string, min?: string, max?: string, disable_past?: bool, disable_future?: bool, locale?: string} $options
     *        format is one isFormat() accepts; min and max are Gregorian Y-m-d dates.
     * @return array<string, string>
     */
    public static function attributes(array $options = []): array
    {
        $attributes = ['data-persian-kit-date' => ''];

        $format = $options['format'] ?? 'Y-m-d';
        if ($format !== 'Y-m-d' && self::isFormat($format)) {
            $attributes['data-persian-kit-date-format'] = $format;
        }

        $type = $options['type'] ?? 'date';
        if ($type !== 'date' && in_array($type, self::TYPES, true)) {
            $attributes['data-persian-kit-date-type'] = $type;
        }

        foreach (['min', 'max'] as $limit) {
            $date = isset($options[$limit]) ? DateInputParser::toGregorian((string) $options[$limit]) : null;
            if ($date !== null) {
                $attributes["data-persian-kit-date-{$limit}"] = $date;
            }
        }

        foreach (['disable_past' => 'disable-past', 'disable_future' => 'disable-future'] as $option => $name) {
            if (!empty($options[$option])) {
                $attributes["data-persian-kit-date-{$name}"] = '';
            }
        }

        if (!empty($options['locale'])) {
            $attributes['data-persian-kit-date-locale'] = (string) $options['locale'];
        }

        return $attributes;
    }

    /**
     * Whether the field script reads and writes this format: one of FORMATS,
     * or day, month and year in any order with -, / or . between them, such
     * as d/m/Y or m.d.Y.
     */
    public static function isFormat(string $format): bool
    {
        return in_array($format, self::FORMATS, true)
            || preg_match('/^([dmY])([-\/.])(?!\1)([dmY])\2(?!\1|\3)[dmY]$/', $format) === 1;
    }

    /**
     * The attributes() as HTML, escaped.
     *
     * @param array<string, string> $attributes
     */
    public static function attributesHtml(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            $html .= $value === '' ? ' ' . $name : sprintf(' %s="%s"', $name, esc_attr($value));
        }

        return $html;
    }

    /**
     * The picker's locale: the page's language and region as a BCP 47 tag.
     * WordPress variants such as pt_PT_ao90 are not valid tags for Intl.
     * With Dari, Pashto or Kurdish month names, a tag whose Intl data has
     * them (SET_LOCALES), and the Iranian ones where the language reads
     * other names.
     */
    private static function locale(): string
    {
        $locale = determine_locale();
        $set = CalendarNames::currentSet();

        if ($set !== 'iranian' || CalendarNames::setFor($locale) !== 'iranian') {
            return self::SET_LOCALES[$set];
        }

        if (!preg_match('/^([a-z]{2,3})(?:_([A-Z]{2}))?/', $locale, $matches)) {
            return 'fa-IR';
        }

        return isset($matches[2]) ? "{$matches[1]}-{$matches[2]}" : $matches[1];
    }
}
