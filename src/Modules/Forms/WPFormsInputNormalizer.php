<?php

namespace PersianKit\Modules\Forms;

defined('ABSPATH') || exit;

/**
 * Fixes what people type into WPForms fields before WPForms checks and
 * saves them:
 *
 *     Numbers, Number Sliders and a   Persian and Arabic digits become
 *     price the customer enters,      English
 *     Jalali dates
 *     Iranian fields                  saved in their standard form when
 *                                     valid, such as 09121234567
 *
 * WPForms passes the submission through wpforms_process_before_filter
 * before it checks, formats and saves each field, when a form is sent to
 * its page or over AJAX. Other text keeps its digits. WPForms checks the
 * form's nonce and spam rules itself.
 *
 * wpforms-digits.js fixes the digits as they are typed: the Number field
 * is a number input, which drops Persian digits.
 */
class WPFormsInputNormalizer
{
    public const SCRIPT = 'persian-kit-wpforms-digits';

    /** Field types whose digits become English. */
    private const DIGIT_TYPES = ['number', 'number-slider', 'payment-single', 'persian-kit-date'];

    /** Of those, the ones people type digits into: the slider slides, the date has its picker. */
    private const TYPED_TYPES = ['number', 'payment-single'];

    public function register(): void
    {
        add_filter('wpforms_process_before_filter', [$this, 'normalizeEntry'], 10, 2);
        add_action('wpforms_frontend_js', [$this, 'enqueue']);
    }

    /**
     * @param mixed $entry     The submission: its values under 'fields', by field id.
     * @param mixed $form_data
     * @return mixed
     */
    public function normalizeEntry($entry, $form_data = [])
    {
        if (!is_array($entry) || !is_array($entry['fields'] ?? null) || !is_array($form_data)) {
            return $entry;
        }

        foreach (is_array($form_data['fields'] ?? null) ? $form_data['fields'] : [] as $field) {
            $id = is_array($field) ? ($field['id'] ?? null) : null;
            if (!is_scalar($id) || !isset($entry['fields'][$id]) || !is_string($entry['fields'][$id])) {
                continue;
            }

            $iranianType = WPFormsIranianFields::iranianType($field);
            if ($iranianType !== null) {
                $entry['fields'][$id] = IranianFieldTypes::normalize($iranianType, $entry['fields'][$id]);
            } elseif (in_array($field['type'] ?? '', self::DIGIT_TYPES, true)) {
                $entry['fields'][$id] = persian_kit_to_english_digits($entry['fields'][$id]);
            }
        }

        return $entry;
    }

    /**
     * With WPForms' own scripts, for the forms on the page.
     *
     * @param mixed $forms Form data by form id.
     */
    public function enqueue($forms): void
    {
        foreach (is_array($forms) ? $forms : [] as $form) {
            foreach (is_array($form) && is_array($form['fields'] ?? null) ? $form['fields'] : [] as $field) {
                if (is_array($field) && self::fixesDigits($field)) {
                    FormDigitsScript::enqueue(self::SCRIPT, 'wpforms-digits.js');

                    return;
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $field
     */
    private static function fixesDigits(array $field): bool
    {
        return WPFormsIranianFields::iranianType($field) !== null || in_array($field['type'] ?? '', self::TYPED_TYPES, true);
    }
}
