<?php

namespace PersianKit\Modules\Forms;

use PersianKit\Modules\DateConversion\DateInputParser;
use PersianKit\Modules\DateConversion\DatePicker;
use PersianKit\Modules\DateConversion\JalaliFormatter;
use PersianKit\Service\Language\ContentLanguage;

defined('ABSPATH') || exit;

/**
 * Forminator's Date fields in the Calendar style, with the Jalali date
 * picker. The field still submits the Gregorian date in its own format,
 * such as 10/02/2026 for dd/mm/yy, so Forminator's checks, conditions and
 * saved entries work as before. Without JavaScript it is a text field, and
 * a typed Jalali date is converted (ForminatorInputNormalizer).
 *
 * Dropdowns and Number boxes stay Gregorian, as does a Calendar with the
 * persian-kit-gregorian class, and forms on pages in another language.
 *
 * Emails and the Submissions screen show the Jalali date while Date
 * Conversion is on; exports keep the saved Gregorian date.
 */
class ForminatorDateField
{
    /** In a field's "Additional CSS Classes": keep Forminator's own calendar. */
    public const GREGORIAN_CLASS = 'persian-kit-gregorian';

    /** Forminator's date formats => PHP's. */
    public const FORMATS = [
        'mm/dd/yy' => 'm/d/Y',
        'mm.dd.yy' => 'm.d.Y',
        'mm-dd-yy' => 'm-d-Y',
        'yy-mm-dd' => 'Y-m-d',
        'yy.mm.dd' => 'Y.m.d',
        'yy/mm/dd' => 'Y/m/d',
        'dd/mm/yy' => 'd/m/Y',
        'dd.mm.yy' => 'd.m.Y',
        'dd-mm-yy' => 'd-m-Y',
    ];

    /** @var array<int, object|false> Forms of the entries on the Submissions screen. */
    private array $forms = [];

    public function __construct(private bool $jalaliDates)
    {
    }

    public function register(): void
    {
        add_filter('forminator_field_date_markup', [$this, 'upgradeMarkup'], 10, 2);
        add_action('forminator_custom_forms_enqueue_scripts', [$this, 'enqueue']);

        if ($this->jalaliDates) {
            add_filter('forminator_replace_form_data', [$this, 'formatMailMacros'], 10, 3);
            add_filter('forminator_custom_form_after_render_value', [$this, 'formatMailValue'], 10, 4);
            add_filter('forminator_get_entry_field_value', [$this, 'formatEntryValue'], 10, 4);
        }
    }

    /**
     * The PHP format of a Calendar date field, or null for other fields and
     * styles. Also for a Calendar with the persian-kit-gregorian class.
     *
     * @param array<string, mixed> $field Forminator's field settings.
     */
    public static function calendarFormat(array $field): ?string
    {
        if (($field['type'] ?? '') !== 'date' || trim((string) ($field['field_type'] ?? 'picker')) !== 'picker') {
            return null;
        }

        return self::FORMATS[$field['date_format'] ?? 'mm/dd/yy'] ?? null;
    }

    /**
     * Whether the field gets the Jalali picker and shows Jalali dates.
     *
     * @param array<string, mixed> $field
     */
    public static function isJalali(array $field): bool
    {
        if (self::calendarFormat($field) === null) {
            return false;
        }

        $classes = is_string($field['custom-class'] ?? null) ? preg_split('/\s+/', trim($field['custom-class'])) : [];

        return !in_array(self::GREGORIAN_CLASS, $classes ?: [], true);
    }

    /**
     * A date as Gregorian Y-m-d: written in the field's format, or year
     * first, Jalali or Gregorian, in any digits. Null when it isn't a date.
     */
    public static function toGregorian(string $value, string $format): ?string
    {
        $parts = preg_split('/[-\/.]/', persian_kit_to_english_digits(trim($value)));
        if (!is_array($parts) || count($parts) !== 3) {
            return null;
        }

        // Typed year first, as the picker shows a date.
        $order = strlen($parts[0]) === 4 ? ['Y', 'm', 'd'] : str_split(str_replace(['-', '/', '.'], '', $format));
        $date = array_combine($order, $parts);

        if (!isset($date['Y'], $date['m'], $date['d'])) {
            return null;
        }

        return DateInputParser::toGregorian("{$date['Y']}-{$date['m']}-{$date['d']}");
    }

    /**
     * A Gregorian Y-m-d date in the field's format.
     */
    public static function inFormat(string $date, string $format): string
    {
        $dateTime = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $dateTime ? $dateTime->format($format) : $date;
    }

    /**
     * Puts the Jalali picker on a Calendar's input. Its limits come from
     * Forminator's own: a start and end date (already Gregorian Y-m-d, also
     * "today ± N days"), and "no past dates", which in Forminator means none
     * before the default date. Limits on weekdays, single dates and other
     * date fields are checked by Forminator when the form is sent.
     *
     * @param mixed $html
     * @param mixed $field
     * @return mixed
     */
    public function upgradeMarkup($html, $field)
    {
        $format = is_array($field) && self::isJalali($field) ? self::calendarFormat($field) : null;

        if (!is_string($html) || $format === null || !ContentLanguage::displaysPersian()) {
            return $html;
        }

        $processor = new \WP_HTML_Tag_Processor($html);
        $found = false;

        while ($processor->next_tag(['tag_name' => 'input', 'class_name' => 'forminator-datepicker'])) {
            $options = ['format' => $format];

            foreach (['min' => 'data-start-date', 'max' => 'data-end-date'] as $limit => $attribute) {
                $date = $processor->get_attribute($attribute);
                if (is_string($date) && $date !== '') {
                    $options[$limit] = (string) preg_replace('/T.*$/', '', $date);
                }
            }

            $value = $processor->get_attribute('value');
            if (!isset($options['min']) && $processor->get_attribute('data-past-dates') === 'disable' && is_string($value) && $value !== '') {
                $options['min'] = (string) self::toGregorian($value, $format);
            }

            foreach (DatePicker::attributes($options) as $name => $attributeValue) {
                $processor->set_attribute($name, $attributeValue === '' ? true : $attributeValue);
            }

            // Forminator's browser checks skip hidden inputs without it.
            $processor->add_class('do-validate');
            $found = true;
        }

        if (!$found) {
            return $html;
        }

        DatePicker::enqueue();

        return $processor->get_updated_html();
    }

    /**
     * With the form's own scripts, also for forms loaded over AJAX: their
     * markup is filtered in the AJAX request, while the page loads the
     * scripts, and the picker upgrades inputs added later.
     *
     * @param mixed $render Forminator_CForm_Front
     */
    public function enqueue($render): void
    {
        if (is_object($render) && self::hasJalaliCalendar($render) && ContentLanguage::displaysPersian()) {
            DatePicker::enqueue();
        }
    }

    /**
     * {date-1} in an email shows the Jalali date in the site's date format.
     * Forminator has already put the submitted value in; it is swapped for
     * the Jalali date, also in the subject. Only in emails: the same
     * function fills redirect URLs and user meta.
     *
     * @param mixed $content
     * @param mixed $data     The submitted data.
     * @param mixed $original The text before its tags were filled.
     * @return mixed
     */
    public function formatMailMacros($content, $data = [], $original = '')
    {
        if (!is_string($content) || !is_string($original) || !str_contains($original, '{date-') || !is_array($data)
            || !self::inMail() || !self::sentInPersian($data)
        ) {
            return $content;
        }

        $form = self::submittedForm();
        if ($form === null || !preg_match_all('/\{(date-\d+(?:-\d+)?)\}/', $original, $matches)) {
            return $content;
        }

        foreach (array_unique($matches[1]) as $elementId) {
            $field = $form->get_field($elementId, true);
            $value = $data[$elementId] ?? null;
            $jalali = is_array($field) && is_string($value) ? self::jalaliDate($value, $field) : null;

            if ($jalali !== null) {
                $content = str_replace($value, esc_html($jalali), $content);
            }
        }

        return $content;
    }

    /**
     * Date fields in {all_fields} and {all_non_empty_fields} of an email.
     *
     * @param mixed $value
     * @param mixed $form  Forminator_Form_Model
     * @param mixed $slug  The field's id, with a repeated group row's suffix.
     * @param mixed $data  The submitted data.
     * @return mixed
     */
    public function formatMailValue($value, $form = null, $slug = '', $data = [])
    {
        if (!is_string($value) || $value === '' || !is_object($form) || !method_exists($form, 'get_field') || !is_string($slug)
            || !str_starts_with($slug, 'date-') || !self::inMail() || !self::sentInPersian(is_array($data) ? $data : [])
        ) {
            return $value;
        }

        $field = $form->get_field($slug, true);
        $jalali = is_array($field) ? self::jalaliDate(wp_strip_all_tags($value), $field) : null;

        return $jalali === null ? $value : esc_html($jalali);
    }

    /**
     * Calendar dates on the Submissions screen, in the admin's language.
     *
     * @param mixed $value
     * @param mixed $entry      Forminator_Form_Entry_Model
     * @param mixed $mapper     The column: its type, meta key and field.
     * @param mixed $subMetaKey A field in a group, such as date-1-2.
     * @return mixed
     */
    public function formatEntryValue($value, $entry = null, $mapper = [], $subMetaKey = '')
    {
        if (!is_string($value) || $value === '' || !is_array($mapper) || !ContentLanguage::displaysPersian()) {
            return $value;
        }

        $field = null;
        if (($mapper['type'] ?? '') === 'date') {
            $field = $mapper['field'] ?? $this->entryField($entry, (string) ($mapper['meta_key'] ?? ''));
        } elseif (($mapper['type'] ?? '') === 'group' && is_string($subMetaKey) && str_starts_with($subMetaKey, 'date-')) {
            $field = $this->entryField($entry, $subMetaKey);
        }

        $jalali = is_array($field) ? self::jalaliDate($value, $field) : null;

        return $jalali ?? $value;
    }

    /**
     * The value, a date in the field's format, as a Jalali date in the
     * site's date format; null for other fields and values.
     *
     * @param array<string, mixed> $field
     */
    private static function jalaliDate(string $value, array $field): ?string
    {
        $format = self::isJalali($field) ? self::calendarFormat($field) : null;
        $date = $format === null ? null : self::toGregorian($value, $format);

        return $date === null ? null : JalaliFormatter::fromLocalMysql((string) get_option('date_format'), $date . ' 00:00:00');
    }

    private static function hasJalaliCalendar(object $render): bool
    {
        if (!method_exists($render, 'get_fields')) {
            return method_exists($render, 'has_field_type') && $render->has_field_type('date');
        }

        foreach ((array) $render->get_fields() as $field) {
            if (is_array($field) && self::isJalali($field)) {
                return true;
            }
        }

        return false;
    }

    private static function inMail(): bool
    {
        return class_exists('Forminator_Mail') && \Forminator_Mail::is_email_context();
    }

    /**
     * Forms are sent through admin-ajax or to their page; the page the form
     * was on (page_id) decides the email's language.
     *
     * @param array<mixed> $data
     */
    private static function sentInPersian(array $data): bool
    {
        if (!ContentLanguage::isMultilingual()) {
            return true;
        }

        $pageId = (int) ($data['page_id'] ?? 0);

        return $pageId > 0 ? ContentLanguage::postIsPersian($pageId) : ContentLanguage::displaysPersian();
    }

    /**
     * The form being sent.
     */
    private static function submittedForm(): ?object
    {
        $form = class_exists('Forminator_CForm_Front_Action') ? \Forminator_CForm_Front_Action::$module_object : null;

        return is_object($form) && method_exists($form, 'get_field') ? $form : null;
    }

    /**
     * A field's settings, from the entry's form: for fields in a group, and
     * on releases before 1.54, which leave them out of the Submissions
     * screen's columns.
     *
     * @param mixed $entry
     * @return array<string, mixed>|null
     */
    private function entryField($entry, string $elementId): ?array
    {
        $formId = is_object($entry) ? (int) ($entry->form_id ?? 0) : 0;
        if ($formId <= 0 || $elementId === '' || !class_exists('Forminator_Base_Form_Model')) {
            return null;
        }

        $this->forms[$formId] ??= \Forminator_Base_Form_Model::get_model($formId);
        $form = $this->forms[$formId];
        $field = is_object($form) && method_exists($form, 'get_field') ? $form->get_field($elementId, true) : null;

        return is_array($field) ? $field : null;
    }
}
