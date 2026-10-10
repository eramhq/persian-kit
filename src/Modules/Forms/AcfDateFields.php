<?php

namespace PersianKit\Modules\Forms;

use PersianKit\Modules\DateConversion\DateDisplayGuard;
use PersianKit\Modules\DateConversion\DatePicker;
use PersianKit\Modules\DateConversion\JalaliFormatter;
use PersianKit\Service\Language\ContentLanguage;

defined('ABSPATH') || exit;

/**
 * ACF's Date Picker and Date Time Picker fields with the Jalali date picker,
 * in the admin and in acf_form(). The values are stored as ACF stores them
 * (Ymd and Y-m-d H:i:s), so existing values and theme code keep working.
 *
 * While Date Conversion is on, the field's value in templates (get_field(),
 * the_field()) is a Jalali date in the field's return format. Return
 * formats that code parses, such as Ymd, and REST responses stay Gregorian;
 * the persian_kit_acf_jalali_value filter turns it off for a field.
 */
class AcfDateFields
{
    /** ACF field type => the value format it stores. */
    private const TYPES = [
        'date_picker'      => 'Ymd',
        'date_time_picker' => 'Y-m-d H:i:s',
    ];

    /** Return formats code parses, kept Gregorian besides DateDisplayGuard's. */
    private const MACHINE_FORMATS = ['Ymd', 'Y-m-d', 'Y-m-d H:i', 'Y-m-d H:i:s'];

    /** @var array<string, callable> ACF's own renderers, by field type. */
    private array $acfRenderers = [];

    public function __construct(private bool $jalaliValues)
    {
    }

    public function register(): void
    {
        // ACF creates its field types on init, then fires acf/init.
        if (did_action('acf/init')) {
            $this->replaceRenderers();
        } else {
            add_action('acf/init', [$this, 'replaceRenderers']);
        }

        // render() loads the picker for the fields it prints. Some fields
        // arrive later over AJAX, after the page's scripts are printed.
        add_action('acf/input/admin_enqueue_scripts', [$this, 'enqueueForLaterFields']);

        if ($this->jalaliValues) {
            foreach (array_keys(self::TYPES) as $type) {
                add_filter("acf/format_value/type={$type}", [$this, 'startJalaliValue'], 9, 3);
                add_filter("acf/format_value/type={$type}", [$this, 'stopJalaliValue'], 11);
            }
        }
    }

    /**
     * Loads the picker ahead where ACF adds fields after the page loads:
     * ACF blocks in the block editor, field groups ACF fetches on edit
     * screens when the template, category or format changes, and
     * attachment fields in the media library.
     */
    public function enqueueForLaterFields(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen === null || !ContentLanguage::displaysPersian()) {
            return;
        }

        if ($screen->is_block_editor() || in_array($screen->base, ['post', 'upload'], true)) {
            DatePicker::enqueue();
        }
    }

    public function replaceRenderers(): void
    {
        foreach (array_keys(self::TYPES) as $type) {
            $fieldType = acf_get_field_type($type);
            if (!is_object($fieldType) || !method_exists($fieldType, 'render_field')) {
                continue;
            }

            $renderer = [$fieldType, 'render_field'];
            if (remove_action("acf/render_field/type={$type}", $renderer, 9)) {
                $this->acfRenderers[$type] = $renderer;
                add_action("acf/render_field/type={$type}", [$this, 'render'], 9);
            }
        }
    }

    /**
     * @param array<string, mixed> $field
     */
    public function render(array $field): void
    {
        $type = (string) ($field['type'] ?? '');

        // Fields from ACF before 5.0 store their own save_format; ACF's
        // script converts those. Pages not in Persian, and admins whose
        // language is not Persian, get ACF's own picker.
        if (!isset(self::TYPES[$type]) || !empty($field['save_format']) || !ContentLanguage::displaysPersian()) {
            if (isset($this->acfRenderers[$type])) {
                call_user_func($this->acfRenderers[$type], $field);
            }

            return;
        }

        $format = self::TYPES[$type];
        $value = self::storedValue($field['value'] ?? '', $format);

        if ($value === '' && !empty($field['default_to_current_date'])) {
            $value = current_time($format);
        }

        $attributes = [
            'type'  => 'hidden',
            'id'    => (string) ($field['id'] ?? ''),
            'class' => trim((string) ($field['class'] ?? '')),
            'name'  => (string) ($field['name'] ?? ''),
            'value' => $value,
        ];
        // An empty value prints as a bare attribute, which is value="".
        $attributes = array_filter($attributes, static fn (string $attribute, string $name) => $attribute !== '' || $name === 'value', ARRAY_FILTER_USE_BOTH);

        foreach (['readonly', 'disabled'] as $state) {
            if (!empty($field[$state])) {
                $attributes[$state] = $state;
            }
        }

        $attributes += DatePicker::attributes(['format' => $format]);

        DatePicker::enqueue();

        $html = '<div class="acf-input-wrap persian-kit-acf-date"><input' . DatePicker::attributesHtml($attributes) . '></div>';
        // DatePicker::attributes() adds only data-persian-kit-date* attributes.
        $allowed = [
            'div'   => ['class' => true],
            'input' => [
                'type'     => true,
                'id'       => true,
                'class'    => true,
                'name'     => true,
                'value'    => true,
                'readonly' => true,
                'disabled' => true,
                'data-*'   => true,
            ],
        ];

        echo wp_kses($html, $allowed);
    }

    /**
     * Runs just before ACF formats a date field's value for a template.
     *
     * @param mixed $value
     * @param mixed $postId
     * @param mixed $field
     * @return mixed
     */
    public function startJalaliValue($value, $postId = null, $field = null)
    {
        if (!is_array($field) || wp_is_serving_rest_request() || !ContentLanguage::displaysPersian()) {
            return $value;
        }

        /**
         * Whether an ACF date field's value in templates is a Jalali date.
         *
         * @param bool                 $jalali Default true while Date Conversion is on.
         * @param array<string, mixed> $field  The ACF field.
         */
        if (apply_filters('persian_kit_acf_jalali_value', true, $field)) {
            add_filter('date_i18n', [$this, 'jalaliDate'], 10, 3);
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function stopJalaliValue($value)
    {
        remove_filter('date_i18n', [$this, 'jalaliDate'], 10);

        return $value;
    }

    /**
     * ACF formats the value with date_i18n() and a timestamp of the stored
     * date and time read as UTC.
     */
    public function jalaliDate(string $date, string $format, int $timestamp): string
    {
        if (in_array($format, self::MACHINE_FORMATS, true) || DateDisplayGuard::isMachineFormat($format)) {
            return $date;
        }

        return JalaliFormatter::format($format, $timestamp, new \DateTimeZone('UTC'));
    }

    /**
     * A stored value in the field's own format, read without date_i18n(),
     * which Date Conversion may make Jalali.
     *
     * @param mixed $value
     */
    public static function storedValue($value, string $format): string
    {
        if (!is_string($value) && !is_int($value)) {
            return '';
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if ($format === 'Ymd' && preg_match('/^\d{8}$/', $value)) {
            return $value;
        }

        if ($format === 'Y-m-d H:i:s' && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        // Other values ACF accepts, such as a Unix timestamp.
        $timestamp = ctype_digit($value) && strlen($value) !== 8 ? (int) $value : strtotime($value);

        return $timestamp === false ? '' : gmdate($format, $timestamp);
    }
}
