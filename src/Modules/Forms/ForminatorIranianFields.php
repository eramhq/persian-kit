<?php

namespace PersianKit\Modules\Forms;

defined('ABSPATH') || exit;

/**
 * Iranian checks for Forminator fields, turned on with a class in the
 * field's "Additional CSS Classes" box (Forminator's builder can't take new
 * field types):
 *
 *     persian-kit-mobile       mobile number, saved as 09xxxxxxxxx
 *     persian-kit-national-id  national ID (کد ملی), 10 digits
 *     persian-kit-postcode     postcode, 10 digits
 *     persian-kit-card         bank card number, 16 digits
 *     persian-kit-iban         IBAN (شبا), saved as IR and 24 digits
 *
 * The classes count on Text, Phone and Number fields. A value that isn't
 * valid is rejected when the form is sent; a valid one is saved and mailed
 * in its standard form (ForminatorInputNormalizer). Fields hidden by a
 * condition are not checked, and an empty required field gets Forminator's
 * own message.
 *
 * The types themselves are defined in IranianFieldTypes.
 */
class ForminatorIranianFields
{
    /** Class => Iranian field type. */
    public const MARKERS = [
        'persian-kit-mobile'      => 'mobile_ir',
        'persian-kit-national-id' => 'national_id',
        'persian-kit-postcode'    => 'postcode_ir',
        'persian-kit-card'        => 'card_ir',
        'persian-kit-iban'        => 'iban_ir',
    ];

    /** Forminator field types the classes count on. */
    private const FIELD_TYPES = ['text', 'phone', 'number'];

    public function register(): void
    {
        add_filter('forminator_custom_form_submit_errors', [$this, 'validate'], 10, 3);
        add_filter('forminator_field_markup', [$this, 'addInputAttributes'], 10, 2);
    }

    /**
     * The Iranian type a field's classes ask for, or null. With several
     * classes, the first one that is a marker counts.
     *
     * @param array<string, mixed> $field Forminator's field settings.
     */
    public static function markerType(array $field): ?string
    {
        if (!in_array($field['type'] ?? '', self::FIELD_TYPES, true) || !is_string($field['custom-class'] ?? null)) {
            return null;
        }

        foreach (preg_split('/\s+/', trim($field['custom-class'])) ?: [] as $class) {
            if (isset(self::MARKERS[$class])) {
                return self::MARKERS[$class];
            }
        }

        return null;
    }

    /**
     * Forminator lists the fields it checked, without those hidden by a
     * condition or left empty, each repeated group row on its own. An error
     * goes under the id the field was rendered with, so it shows beside it.
     *
     * @param mixed $errors         list<array<string, string>>
     * @param mixed $formId
     * @param mixed $fieldDataArray list<array{name: string, value: mixed, field_array?: array<string, mixed>}>
     * @return mixed
     */
    public function validate($errors, $formId = 0, $fieldDataArray = [])
    {
        if (!is_array($errors) || !is_array($fieldDataArray)) {
            return $errors;
        }

        foreach ($fieldDataArray as $item) {
            $field = is_array($item) ? ($item['field_array'] ?? null) : null;
            $value = $item['value'] ?? null;
            $type = is_array($field) ? self::markerType($field) : null;

            if ($type === null || !is_string($value) || trim($value) === '' || IranianFieldTypes::standardValue($type, $value) !== null) {
                continue;
            }

            $id = (string) ($field['original_id'] ?? $item['name'] ?? '');
            if ($id !== '' && !self::hasError($errors, $id)) {
                $errors[] = [$id => IranianFieldTypes::message($type)];
            }
        }

        return $errors;
    }

    /**
     * Digits and Latin letters read left to right in a Persian form, and
     * phones offer the right keyboard. Attributes the field already has,
     * such as its own character limit, are kept.
     *
     * @param mixed $html
     * @param mixed $field
     * @return mixed
     */
    public function addInputAttributes($html, $field)
    {
        $type = is_array($field) ? self::markerType($field) : null;
        $name = is_array($field) ? (string) ($field['element_id'] ?? '') : '';

        if (!is_string($html) || $type === null || $name === '') {
            return $html;
        }

        $attributes = IranianFieldTypes::inputAttributes($type);
        $processor = new \WP_HTML_Tag_Processor($html);

        while ($processor->next_tag(['tag_name' => 'input'])) {
            if ($processor->get_attribute('name') !== $name) {
                continue;
            }

            $processor->set_attribute('dir', $attributes['dir']);

            foreach (['inputmode', 'autocomplete'] as $attribute) {
                if ($attributes[$attribute] !== null && $processor->get_attribute($attribute) === null) {
                    $processor->set_attribute($attribute, $attributes[$attribute]);
                }
            }

            if (in_array($processor->get_attribute('type'), ['text', 'tel'], true) && $processor->get_attribute('maxlength') === null) {
                $processor->set_attribute('maxlength', (string) $attributes['maxlength']);
            }
        }

        return $processor->get_updated_html();
    }

    /**
     * @param array<mixed> $errors
     */
    private static function hasError(array $errors, string $id): bool
    {
        foreach ($errors as $error) {
            if (is_array($error) && array_key_exists($id, $error)) {
                return true;
            }
        }

        return false;
    }
}
