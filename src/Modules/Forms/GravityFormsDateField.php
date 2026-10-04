<?php

namespace PersianKit\Modules\Forms;

use PersianKit\Modules\DateConversion\CalendarNames;
use PersianKit\Modules\DateConversion\DatePicker;
use PersianKit\Modules\DateConversion\JalaliFormatter;
use PersianKit\Service\Language\ContentLanguage;

defined('ABSPATH') || exit;

/**
 * Jalali dates in Gravity Forms' Date fields, in all three styles:
 *
 *     Date Picker      the Jalali date picker in place of Gravity Forms'
 *                      own (jQuery UI before 3.0, Apex since)
 *     Date Field       number boxes for a Jalali day, month and year
 *     Date Drop Down   Jalali month names and years
 *
 * The field still submits, checks and saves the Gregorian date
 * (GravityFormsInputNormalizer converts the boxes and drop-downs when the
 * form is sent), so conditions, entries and add-ons work as before. A field
 * with "Gregorian calendar" ticked, and forms on pages in a language that
 * doesn't read Jalali dates, keep Gravity Forms' own.
 *
 * While Date Conversion is on, the Entries screens and merge tags in
 * emails and confirmations show the Jalali date. {Date:1:raw},
 * {Date:1:urlencode}, merge tags in redirect URLs and exports keep the
 * saved Gregorian date, and {Date:1:label} prints the label.
 */
class GravityFormsDateField
{
    /** The field setting that keeps Gravity Forms' own Gregorian calendar. */
    public const GREGORIAN_SETTING = 'persianKitGregorian';

    /** Gravity Forms' date formats => PHP's. */
    public const FORMATS = [
        'mdy'       => 'm/d/Y',
        'dmy'       => 'd/m/Y',
        'dmy_dash'  => 'd-m-Y',
        'dmy_dot'   => 'd.m.Y',
        'ymd_slash' => 'Y/m/d',
        'ymd_dash'  => 'Y-m-d',
        'ymd_dot'   => 'Y.m.d',
    ];

    /** Classes Gravity Forms' pickers start on, and their icon's. */
    private const PICKER_CLASSES = ['datepicker', 'gform-datepicker', 'datepicker_with_icon', 'gdatepicker_with_icon', 'datepicker_no_icon', 'gdatepicker-no-icon'];

    /** The number of each part's input or drop-down: input_1_3_1 is the month. */
    private const PART_INPUTS = ['m' => 1, 'd' => 2, 'Y' => 3];

    /** Whether the merge tags being filled are URL-encoded (a redirect's query string). */
    private bool $urlEncoding = false;

    /** @var array<string, mixed>|null The entry whose merge tags are being filled. */
    private ?array $entry = null;

    public function __construct(private bool $jalaliDates)
    {
    }

    public function register(): void
    {
        add_filter('gform_field_content', [$this, 'upgradeMarkup'], 10, 3);
        add_action('gform_enqueue_scripts', [$this, 'enqueue']);
        add_action('gform_field_standard_settings', [$this, 'renderSetting']);
        add_action('gform_editor_js', [$this, 'renderEditorScript']);

        if ($this->jalaliDates) {
            add_filter('gform_entries_field_value', [$this, 'formatListValue'], 10, 4);
            add_filter('gform_entry_field_value', [$this, 'formatEntryValue'], 10, 3);
            add_filter('gform_pre_replace_merge_tags', [$this, 'startMergeTags'], 10, 4);
            add_filter('gform_merge_tag_filter', [$this, 'formatMergeTag'], 10, 6);
        }
    }

    /**
     * A Date field, also a Custom Field shown as a date.
     */
    public static function isDate(object $field): bool
    {
        $type = method_exists($field, 'get_input_type') ? $field->get_input_type() : ($field->type ?? '');

        return $type === 'date';
    }

    /**
     * Whether the field reads and shows Jalali dates.
     */
    public static function isJalali(object $field): bool
    {
        return self::isDate($field) && empty($field->{self::GREGORIAN_SETTING});
    }

    /**
     * datepicker, datefield (number boxes) or datedropdown.
     */
    public static function style(object $field): string
    {
        $style = $field->dateType ?? '';

        return in_array($style, ['datefield', 'datedropdown'], true) ? $style : 'datepicker';
    }

    /**
     * The field's date format as PHP's, such as d/m/Y.
     */
    public static function format(object $field): string
    {
        return self::FORMATS[(string) ($field->dateFormat ?? '')] ?? self::FORMATS['mdy'];
    }

    /**
     * The order of the boxes and drop-downs, and of their submitted values,
     * such as ['d', 'm', 'Y'].
     *
     * @return list<string>
     */
    public static function order(object $field): array
    {
        return str_split(str_replace(['-', '/', '.'], '', self::format($field)));
    }

    /**
     * Puts the Jalali picker on a Date Picker, and Jalali parts in the boxes
     * and drop-downs. Gravity Forms prints them with the Gregorian date, from
     * the default value, the entry, or a form shown again after a failed
     * check, a page change or a resume.
     *
     * @param mixed $content
     * @param mixed $field   GF_Field
     * @param mixed $value   The value it was printed with.
     * @return mixed
     */
    public function upgradeMarkup($content, $field, $value = '')
    {
        if (!is_string($content) || $content === '' || !is_object($field) || !self::isJalali($field)
            || (method_exists($field, 'is_form_editor') && $field->is_form_editor()) || !ContentLanguage::displaysPersian()
        ) {
            return $content;
        }

        return self::style($field) === 'datepicker' ? self::upgradeCalendar($content, $field) : self::upgradeParts($content, $field, $value);
    }

    /**
     * With the form's own scripts, also for forms loaded over AJAX and later
     * pages of a form: the picker upgrades inputs added later.
     *
     * @param mixed $form
     */
    public function enqueue($form): void
    {
        if (is_array($form) && self::hasJalaliCalendar($form) && ContentLanguage::displaysPersian()) {
            DatePicker::enqueue();
        }
    }

    /**
     * "Gregorian calendar" under the Date field's Date Format.
     *
     * @param mixed $position
     */
    public function renderSetting($position): void
    {
        if ((int) $position !== 1225) {
            return;
        }

        printf(
            '<li class="persian_kit_gregorian_setting field_setting"><input type="checkbox" id="field_persian_kit_gregorian" onclick="SetFieldProperty(\'%1$s\', this.checked);"> <label for="field_persian_kit_gregorian" class="inline">%2$s</label><br><small>%3$s</small></li>',
            esc_attr(self::GREGORIAN_SETTING),
            esc_html__('Gregorian calendar', 'persian-kit'),
            esc_html__('Keep Gravity Forms\' own calendar instead of the Jalali one.', 'persian-kit')
        );
    }

    public function renderEditorScript(): void
    {
        printf(
            "<script>fieldSettings.date += ', .persian_kit_gregorian_setting';jQuery(document).on('gform_load_field_settings', function (event, field) { jQuery('#field_persian_kit_gregorian').prop('checked', !!field[%s]); });</script>",
            wp_json_encode(self::GREGORIAN_SETTING)
        );
    }

    /**
     * A Date column on the Entries list, in the admin's language.
     *
     * @param mixed $value
     * @param mixed $formId
     * @param mixed $fieldId
     * @param mixed $entry
     * @return mixed
     */
    public function formatListValue($value, $formId = 0, $fieldId = '', $entry = [])
    {
        if (!is_string($value) || $value === '' || !is_array($entry) || !class_exists('GFAPI') || !ContentLanguage::displaysPersian()) {
            return $value;
        }

        $field = \GFAPI::get_field((int) $formId, $fieldId);

        return is_object($field) ? $this->jalaliValue($field, $entry[(string) $fieldId] ?? null) ?? $value : $value;
    }

    /**
     * A Date field on an entry's page and its printout.
     *
     * @param mixed $value
     * @param mixed $field
     * @param mixed $entry
     * @return mixed
     */
    public function formatEntryValue($value, $field = null, $entry = [])
    {
        if (!is_string($value) || $value === '' || !is_object($field) || !is_array($entry) || !ContentLanguage::displaysPersian()) {
            return $value;
        }

        $jalali = $this->jalaliValue($field, $entry[(string) ($field->id ?? '')] ?? null);

        return $jalali === null ? $value : esc_html($jalali);
    }

    /**
     * Gravity Forms fills each text's merge tags in one go: whether they are
     * URL-encoded, and the entry, hold for its merge tags.
     *
     * @param mixed $text
     * @param mixed $form
     * @param mixed $entry
     * @param mixed $urlEncode
     * @return mixed
     */
    public function startMergeTags($text, $form = null, $entry = null, $urlEncode = false)
    {
        $this->urlEncoding = (bool) $urlEncode;
        $this->entry = is_array($entry) ? $entry : null;

        return $text;
    }

    /**
     * {Date:1} and Date fields in {all_fields}. Called for a field's merge
     * tag with its input id and modifiers, and for {all_fields} with
     * 'all_fields' and that tag's options.
     *
     * @param mixed $value
     * @param mixed $mergeTag Input id, or 'all_fields'.
     * @param mixed $modifier
     * @param mixed $field
     * @param mixed $rawValue The saved value.
     * @param mixed $format   html or text.
     * @return mixed
     */
    public function formatMergeTag($value, $mergeTag = '', $modifier = '', $field = null, $rawValue = '', $format = 'html')
    {
        if (!is_string($value) || $value === '' || !is_object($field) || !self::isJalali($field) || $this->urlEncoding || !$this->readsJalali()) {
            return $value;
        }

        // :raw keeps the saved date, :label prints the label instead, and
        // :urlencode is for a URL.
        $modifiers = $mergeTag === 'all_fields' ? [] : array_map('trim', explode(',', strtolower((string) $modifier)));
        if (array_intersect(['raw', 'label', 'urlencode'], $modifiers) !== []) {
            return $value;
        }

        $jalali = $this->jalaliValue($field, $rawValue, self::modifierFormat($modifiers));
        if ($jalali === null) {
            return $value;
        }

        return $format === 'html' ? esc_html($jalali) : $jalali;
    }

    /**
     * A saved date (Gregorian Y-m-d) as a Jalali date in the site's date
     * format, or in a merge tag modifier's; null for other fields and values.
     *
     * @param mixed $saved
     */
    private function jalaliValue(object $field, $saved, ?string $format = null): ?string
    {
        if (!self::isJalali($field) || !is_string($saved) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $saved)) {
            return null;
        }

        return JalaliFormatter::fromLocalMysql($format ?? (string) get_option('date_format'), $saved . ' 00:00:00');
    }

    /**
     * The PHP format a merge tag modifier asks for: :year, :month and :day,
     * or an order such as :dmy or :ymd_dash. Null for the site's format.
     *
     * @param list<string> $modifiers
     */
    private static function modifierFormat(array $modifiers): ?string
    {
        foreach ($modifiers as $modifier) {
            $parts = ['year' => 'Y', 'month' => 'm', 'day' => 'd'];
            if (isset($parts[$modifier])) {
                return $parts[$modifier];
            }

            if (preg_match('/^(ymd|mdy|dmy)(?:_(dash|dot|slash))?$/', $modifier, $matches)) {
                $separator = ['dash' => '-', 'dot' => '.'][$matches[2] ?? ''] ?? '/';

                return implode($separator, str_split(str_replace('y', 'Y', $matches[1])));
            }
        }

        return null;
    }

    /**
     * Emails follow the page the form was sent from on multilingual sites;
     * confirmations and admin screens follow this request's language.
     */
    private function readsJalali(): bool
    {
        $pageId = (int) ($this->entry['source_id'] ?? 0);
        if ($pageId > 0 && ContentLanguage::isMultilingual() && get_post($pageId) !== null) {
            return ContentLanguage::postReadsJalali($pageId);
        }

        return ContentLanguage::displaysPersian();
    }

    private static function upgradeCalendar(string $content, object $field): string
    {
        $processor = new \WP_HTML_Tag_Processor($content);
        $found = false;

        while ($processor->next_tag(['tag_name' => 'input', 'class_name' => 'gform-datepicker'])) {
            foreach (self::PICKER_CLASSES as $class) {
                $processor->remove_class($class);
            }

            // 3.x's input mask, which drops Persian digits.
            $processor->remove_attribute('data-mask');

            // Gravity Forms' format as a placeholder, such as dd/mm/yyyy: the
            // picker shows its own Jalali example. A placeholder of the
            // field's own stays.
            $placeholder = $processor->get_attribute('placeholder');
            if (is_string($placeholder) && preg_match('/^(dd|mm|yyyy)([-\/.])(dd|mm|yyyy)\2(dd|mm|yyyy)$/i', trim($placeholder))) {
                $processor->remove_attribute('placeholder');
            }

            foreach (DatePicker::attributes(['format' => self::format($field)]) as $name => $value) {
                $processor->set_attribute($name, $value === '' ? true : $value);
            }

            $found = true;
        }

        if (!$found) {
            return $content;
        }

        DatePicker::enqueue();

        // Gravity Forms' calendar button (3.x) and icon (2.9 and 2.10).
        return (string) preg_replace([
            '#<button\b[^>]*\bgform-datepicker-toggle\b.*?</button>#s',
            '#<input\b[^>]*\bid=([\'"])gforms_calendar_icon_[^>]*>#',
        ], '', $processor->get_updated_html());
    }

    /**
     * The number boxes and drop-downs: their values as Jalali parts, Jalali
     * years, and Jalali month names in the drop-down.
     *
     * @param mixed $value The submitted parts in the field's order, when it is shown again.
     */
    private static function upgradeParts(string $content, object $field, $value): string
    {
        $fieldId = (int) ($field->id ?? 0);
        $submitted = is_array($value) && count($value) === 3 ? array_combine(self::order($field), array_map('strval', array_values($value))) : [];
        $values = [];

        // A Jalali year that wasn't a date has no Gregorian option to select.
        foreach (self::PART_INPUTS as $part => $number) {
            $values[$part] = self::partValue($content, $fieldId, $number);
            if ($values[$part] === '' && isset($submitted[$part])) {
                $values[$part] = persian_kit_to_english_digits(trim($submitted[$part]));
            }
        }

        $jalali = is_numeric($values['Y']) && (int) $values['Y'] >= 1700
            ? FormDateValues::jalaliParts((int) $values['Y'], (int) $values['m'], (int) $values['d'])
            : null;
        if ($jalali !== null) {
            $values = array_map('strval', $jalali);
        }

        foreach (self::PART_INPUTS as $part => $number) {
            $content = self::style($field) === 'datedropdown'
                ? self::replaceOptions($content, $fieldId, $number, $part, $values[$part])
                : self::replaceBoxValue($content, $fieldId, $number, $values[$part]);
        }

        return $content;
    }

    /**
     * The value Gravity Forms printed in a part's box, or selected in its
     * drop-down.
     */
    private static function partValue(string $content, int $fieldId, int $number): string
    {
        if (preg_match(self::controlPattern('select', $fieldId, $number), $content, $select)) {
            return preg_match('/<option\b[^>]*\bvalue=([\'"])([^\'"]*)\1[^>]*\bselected\b/i', $select[3], $option) ? $option[2] : '';
        }

        if (!preg_match(self::controlPattern('input', $fieldId, $number), $content, $input)) {
            return '';
        }

        $processor = new \WP_HTML_Tag_Processor($input[0]);
        $processor->next_tag();
        $value = $processor->get_attribute('value');

        return is_string($value) ? $value : '';
    }

    private static function replaceBoxValue(string $content, int $fieldId, int $number, string $value): string
    {
        return (string) preg_replace_callback(self::controlPattern('input', $fieldId, $number), static function (array $matches) use ($number, $value): string {
            $processor = new \WP_HTML_Tag_Processor($matches[0]);
            $processor->next_tag();
            $processor->set_attribute('value', $value);

            if ($number === self::PART_INPUTS['Y']) {
                foreach (['min' => false, 'max' => true] as $limit => $isMax) {
                    $year = $processor->get_attribute($limit);
                    if (is_string($year) && is_numeric($year)) {
                        $processor->set_attribute($limit, (string) self::jalaliYear((int) $year, $isMax));
                    }
                }
            }

            return $processor->get_updated_html();
        }, $content);
    }

    private static function replaceOptions(string $content, int $fieldId, int $number, string $part, string $selected): string
    {
        return (string) preg_replace_callback(self::controlPattern('select', $fieldId, $number), static function (array $matches) use ($part, $selected): string {
            // The placeholder Gravity Forms printed, such as "Month".
            $html = preg_match('/<option\b[^>]*\bvalue=([\'"])\1[^>]*>.*?<\/option>/s', $matches[3], $placeholder) ? $placeholder[0] : '';

            foreach (self::partOptions($part, $matches[3]) as $value => $label) {
                $html .= sprintf(
                    '<option value="%1$d"%2$s>%3$s</option>',
                    $value,
                    $selected !== '' && (int) $selected === $value ? ' selected="selected"' : '',
                    esc_html($label)
                );
            }

            return $matches[1] . $html . $matches[4];
        }, $content);
    }

    /**
     * A drop-down's options: Jalali month names, days, or the Jalali years
     * that cover Gravity Forms' Gregorian ones, newest first as it lists them.
     *
     * @return array<int, string>
     */
    private static function partOptions(string $part, string $options): array
    {
        if ($part === 'm') {
            $months = CalendarNames::current()['months'];

            return array_combine(range(1, 12), array_map(static fn (int $month): string => (string) $months[$month], range(1, 12)));
        }

        if ($part === 'd') {
            return array_combine(range(1, 31), array_map('strval', range(1, 31)));
        }

        preg_match_all('/<option\b[^>]*\bvalue=([\'"])(\d+)\1/', $options, $matches);
        $years = array_map('intval', $matches[2]);
        if ($years === []) {
            return [];
        }

        $range = range(self::jalaliYear(max($years), true), self::jalaliYear(min($years), false));

        return array_combine($range, array_map('strval', $range));
    }

    /**
     * The Jalali year a Gregorian year starts in (a range's first year), or
     * ends in (its last).
     */
    private static function jalaliYear(int $year, bool $end): int
    {
        return $year >= 1700 ? $year - ($end ? 621 : 622) : $year;
    }

    /**
     * A part's input or select: input_<form>_<field>_<n> on the site,
     * input_<field>_<n> when an entry is edited.
     */
    private static function controlPattern(string $tag, int $fieldId, int $number): string
    {
        $id = sprintf('input_(?:\d+_)?%d_%d', $fieldId, $number);

        return $tag === 'select'
            ? sprintf('#(<select\b[^>]*\bid=([\'"])%s\2[^>]*>)(.*?)(</select>)#s', $id)
            : sprintf('#<input\b[^>]*\bid=([\'"])%s\1[^>]*>#', $id);
    }

    /**
     * @param array<string, mixed> $form
     */
    private static function hasJalaliCalendar(array $form): bool
    {
        foreach (is_array($form['fields'] ?? null) ? $form['fields'] : [] as $field) {
            if (is_object($field) && self::isJalali($field) && self::style($field) === 'datepicker') {
                return true;
            }
        }

        return false;
    }
}
