<?php

namespace PersianKit\Modules\Forms;

defined('ABSPATH') || exit;

/**
 * Fixes what people type into Forminator fields before Forminator checks
 * and saves them:
 *
 *     Phone, Number, Currency       Persian and Arabic digits become English
 *     Calendar dates                a typed Jalali date, or one in Persian
 *                                   digits, becomes the Gregorian date in the
 *                                   field's format
 *     fields with a persian-kit-*   saved in their standard form when valid,
 *     class                         such as 09121234567
 *
 * Forminator reads the submission from Forminator_Front_Action::
 * $prepared_data, as do its emails, so the fixed values are written back
 * there. Other text keeps its digits. Forminator checks the form's nonce
 * and spam rules itself.
 *
 * Forminator also checks phone numbers and numbers in the browser, so
 * forminator-digits.js fixes the digits as they are typed.
 */
class ForminatorInputNormalizer
{
    public const SCRIPT = 'persian-kit-forminator-digits';

    /** Field types whose digits become English. */
    private const DIGIT_TYPES = ['phone', 'number', 'currency'];

    public function register(): void
    {
        // Before Forminator reads the fields: sent over AJAX, saved as a
        // draft, or sent to the page.
        add_action('forminator_form_before_save_entry', [$this, 'normalizeSubmission']);
        add_action('forminator_form_draft_before_save_entry', [$this, 'normalizeSubmission']);
        add_action('forminator_form_before_handle_submit', [$this, 'normalizeSubmission']);

        add_action('forminator_custom_forms_enqueue_scripts', [$this, 'enqueue']);
    }

    public function normalizeSubmission(): void
    {
        if (!class_exists('Forminator_CForm_Front_Action')) {
            return;
        }

        $form = \Forminator_CForm_Front_Action::$module_object;
        $data = \Forminator_CForm_Front_Action::$prepared_data;

        if (!is_object($form) || !method_exists($form, 'get_fields') || !is_array($data)) {
            return;
        }

        \Forminator_CForm_Front_Action::$prepared_data = $this->normalizeData($data, self::fieldsById((array) $form->get_fields()));
    }

    /**
     * @param array<mixed>                        $data   The submitted data.
     * @param array<string, array<string, mixed>> $fields Field settings by element id.
     * @return array<mixed>
     */
    public function normalizeData(array $data, array $fields): array
    {
        foreach ($data as $key => $value) {
            if (!is_string($value) || $value === '') {
                continue;
            }

            $field = $fields[$key] ?? null;

            // A repeated group row: date-1-2, text-3-4.
            if ($field === null && preg_match('/^(.+)-\d+$/', (string) $key, $matches) && !empty($fields[$matches[1]]['parent_group'])) {
                $field = $fields[$matches[1]];
            }

            if ($field !== null) {
                $data[$key] = $this->normalize($field, $value);
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $field
     */
    public function normalize(array $field, string $value): string
    {
        $type = ForminatorIranianFields::markerType($field);
        if ($type !== null) {
            return IranianFieldTypes::normalize($type, $value);
        }

        $format = ForminatorDateField::calendarFormat($field);
        if ($format !== null) {
            $date = FormDateValues::toGregorian($value, $format);

            return $date === null ? persian_kit_to_english_digits(trim($value)) : FormDateValues::inFormat($date, $format);
        }

        if (in_array($field['type'] ?? '', self::DIGIT_TYPES, true)) {
            return persian_kit_to_english_digits($value);
        }

        return $value;
    }

    /**
     * @param mixed $render Forminator_CForm_Front
     */
    public function enqueue($render): void
    {
        if (!is_object($render) || !method_exists($render, 'get_fields')) {
            return;
        }

        foreach ((array) $render->get_fields() as $field) {
            if (is_array($field) && (in_array($field['type'] ?? '', self::DIGIT_TYPES, true) || ForminatorIranianFields::markerType($field) !== null)) {
                FormDigitsScript::enqueue(self::SCRIPT, 'forminator-digits.js');

                return;
            }
        }
    }

    /**
     * @param array<mixed> $fields Forminator_Form_Field_Model objects.
     * @return array<string, array<string, mixed>>
     */
    private static function fieldsById(array $fields): array
    {
        $byId = [];

        foreach ($fields as $field) {
            $settings = is_object($field) && method_exists($field, 'to_formatted_array') ? $field->to_formatted_array() : null;
            if (is_array($settings) && !empty($settings['element_id'])) {
                $byId[(string) $settings['element_id']] = $settings;
            }
        }

        return $byId;
    }
}
