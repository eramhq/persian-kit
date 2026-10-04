<?php

namespace PersianKit\Modules\Forms\WPForms;

use PersianKit\Modules\DateConversion\DatePicker;
use PersianKit\Modules\Forms\FormDateValues;
use PersianKit\Service\Language\ContentLanguage;

defined('ABSPATH') || exit;

/**
 * A Jalali date field for WPForms, also for WPForms Lite, whose own Date /
 * Time field is a paid one. It is in the builder's "Iranian fields" group.
 *
 * The field shows the Jalali date picker, and submits the Gregorian date in
 * its format (date-field.js). Without JavaScript it is a text input, and a
 * typed Jalali date is read too. Forms on pages in a language that doesn't
 * read Jalali dates show the browser's own date input instead.
 *
 * The entry keeps, as WPForms' Date / Time field does:
 *
 *     value  the date in the field's format, such as 1405/07/12: Jalali,
 *            or Gregorian when the form was sent from a page in another
 *            language. Emails and {all_fields} show it.
 *     date   the Gregorian date, Y-m-d
 *     unix   its timestamp, at midnight UTC
 *
 * While the integration is off the field stays, as a plain text input that
 * saves what was typed, and the builder no longer offers it.
 */
class JalaliDateField extends \WPForms_Field
{
    /** Whether the field has its picker and checks: the integration is on. */
    public static bool $enabled = false;

    /** The formats a field can have: day, month and year in each order. */
    public const FORMATS = ['Y/m/d', 'Y-m-d', 'Y.m.d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'm/d/Y', 'm-d-Y', 'm.d.Y'];

    public const DEFAULT_FORMAT = 'Y/m/d';

    public $type = 'persian-kit-date';

    public $icon = 'fa-calendar';

    public $order = 60;

    public function init()
    {
        $this->name = __('Jalali date', 'persian-kit');
        $this->group = IranianField::GROUP;
        $this->default_settings = ['date_format' => self::DEFAULT_FORMAT];
    }

    /**
     * The field's date format, such as d/m/Y.
     *
     * @param mixed $field
     */
    public static function dateFormat($field): string
    {
        $format = is_array($field) ? ($field['date_format'] ?? '') : '';

        return in_array($format, self::FORMATS, true) ? $format : self::DEFAULT_FORMAT;
    }

    /**
     * @param mixed $fields
     * @return mixed
     */
    public function field_button($fields)
    {
        return self::$enabled ? parent::field_button($fields) : $fields;
    }

    /**
     * @param mixed $field
     */
    public function field_options($field)
    {
        $this->field_option('basic-options', $field, ['markup' => 'open']);
        $this->field_option('label', $field);
        $this->field_option('description', $field);
        $this->formatOption($field);
        $this->field_option('required', $field);
        $this->field_option('basic-options', $field, ['markup' => 'close']);

        $this->field_option('advanced-options', $field, ['markup' => 'open']);
        $this->field_option('size', $field);
        $this->field_option('placeholder', $field);
        $this->field_option('css', $field);
        $this->field_option('label_hide', $field);
        $this->field_option('advanced-options', $field, ['markup' => 'close']);
    }

    /**
     * @param mixed $field
     */
    public function field_preview($field)
    {
        $placeholder = is_array($field) && !empty($field['placeholder']) ? (string) $field['placeholder'] : self::example(self::dateFormat($field), true);

        $this->field_preview_option('label', $field);
        printf('<input type="text" placeholder="%s" class="primary-input" dir="ltr" readonly>', esc_attr($placeholder));
        $this->field_preview_option('description', $field);
    }

    /**
     * @param mixed $field
     * @param mixed $deprecated
     * @param mixed $form_data
     */
    public function field_display($field, $deprecated, $form_data)
    {
        $primary = $field['properties']['inputs']['primary'];
        $format = self::dateFormat($field);
        $type = 'text';
        $picker = '';

        if (self::$enabled && ContentLanguage::displaysPersian()) {
            $picker = DatePicker::attributesHtml(DatePicker::attributes(['format' => $format]));
            DatePicker::enqueue();
        } elseif (self::$enabled) {
            // The browser's date input reads and submits Y-m-d.
            $type = 'date';
            $value = is_string($primary['attr']['value'] ?? null) ? $primary['attr']['value'] : '';
            $primary['attr']['value'] = $value === '' ? '' : (string) FormDateValues::toGregorian($value, $format);
        }

        printf(
            '<input type="%1$s" %2$s%3$s %4$s>',
            esc_attr($type),
            wpforms_html_attributes($primary['id'], $primary['class'], $primary['data'], $primary['attr']), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            $picker, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by attributesHtml().
            esc_attr((string) $primary['required'])
        );
    }

    /**
     * A date that doesn't exist, such as 1405/07/31, fails.
     *
     * @param mixed $field_id
     * @param mixed $field_submit
     * @param mixed $form_data
     */
    public function validate($field_id, $field_submit, $form_data)
    {
        parent::validate($field_id, $field_submit, $form_data);

        $format = self::dateFormat($form_data['fields'][$field_id] ?? []);
        if (!self::$enabled || !is_string($field_submit) || trim($field_submit) === '' || FormDateValues::toGregorian($field_submit, $format) !== null) {
            return;
        }

        wpforms()->obj('process')->errors[$form_data['id']][$field_id] = sprintf(
            /* translators: %s: a date in the field's format, such as 1405/07/25. */
            __('Enter a valid date, such as %s.', 'persian-kit'),
            self::example($format, self::readsJalali())
        );
    }

    /**
     * @param mixed $field_id
     * @param mixed $field_submit
     * @param mixed $form_data
     */
    public function format($field_id, $field_submit, $form_data)
    {
        $field = $form_data['fields'][$field_id] ?? [];
        $label = is_array($field) && is_string($field['label'] ?? null) ? $field['label'] : '';
        $value = sanitize_text_field(is_string($field_submit) ? $field_submit : '');
        $date = self::$enabled && $value !== '' ? FormDateValues::toGregorian($value, self::dateFormat($field)) : null;

        wpforms()->obj('process')->fields[$field_id] = [
            'name'  => sanitize_text_field($label),
            'value' => $date === null ? $value : self::inFormat($date, self::dateFormat($field), self::readsJalali()),
            'date'  => $date ?? '',
            'unix'  => $date === null ? '' : (int) strtotime($date . ' 00:00:00 UTC'),
            'id'    => wpforms_validate_field_id($field_id),
            'type'  => $this->type,
        ];
    }

    /**
     * A Gregorian Y-m-d date in the format, as a Jalali date or Gregorian,
     * with English digits.
     */
    public static function inFormat(string $date, string $format, bool $jalali): string
    {
        if (!$jalali) {
            return FormDateValues::inFormat($date, $format);
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));
        $parts = FormDateValues::jalaliParts($year, $month, $day);

        return $parts === null ? $date : strtr($format, [
            'Y' => sprintf('%04d', $parts['Y']),
            'm' => sprintf('%02d', $parts['m']),
            'd' => sprintf('%02d', $parts['d']),
        ]);
    }

    /**
     * Whether the form was sent from a page that reads Jalali dates: on
     * multilingual sites the page it was on (WPForms sends its id, also
     * over AJAX), else this request's language.
     */
    private static function readsJalali(): bool
    {
        $pageId = isset($_POST['page_id']) ? absint(wp_unslash($_POST['page_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WPForms checks the form's nonce.
        if ($pageId > 0 && ContentLanguage::isMultilingual() && get_post($pageId) !== null) {
            return ContentLanguage::postReadsJalali($pageId);
        }

        return ContentLanguage::displaysPersian();
    }

    /**
     * An example date in the format: 1405/07/25, or 2026/10/17.
     */
    private static function example(string $format, bool $jalali): string
    {
        return strtr($format, $jalali ? ['Y' => '1405', 'm' => '07', 'd' => '25'] : ['Y' => '2026', 'm' => '10', 'd' => '17']);
    }

    /**
     * The format's drop-down, under the label.
     *
     * @param mixed $field
     */
    private function formatOption($field): void
    {
        $options = [];
        foreach (self::FORMATS as $format) {
            $options[$format] = esc_html(self::example($format, true));
        }

        $label = $this->field_element('label', $field, [
            'slug'    => 'date_format',
            'value'   => esc_html__('Date format', 'persian-kit'),
            'tooltip' => esc_html__('How the date is written in the field and in emails.', 'persian-kit'),
        ], false);
        $select = $this->field_element('select', $field, [
            'slug'    => 'date_format',
            'value'   => self::dateFormat($field),
            'options' => $options,
        ], false);

        $this->field_element('row', $field, ['slug' => 'date_format', 'content' => $label . $select]);
    }
}
