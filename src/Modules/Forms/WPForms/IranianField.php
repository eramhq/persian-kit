<?php

namespace PersianKit\Modules\Forms\WPForms;

use PersianKit\Modules\Forms\IranianFieldTypes;

defined('ABSPATH') || exit;

/**
 * A WPForms field for an Iranian value, in the "Iranian fields" group of
 * the builder's Add Fields panel. It is a single line text input without an
 * input mask or length limit: the value is checked when the form is sent,
 * and saved in its standard form, such as 09121234567
 * (WPFormsInputNormalizer). An empty value is left to the Required check.
 *
 * Created once WPForms has loaded its own fields (WPFormsIranianFields).
 * While the integration is off the fields stay, as plain text inputs
 * without checks, so the forms that use them keep showing them, and the
 * builder no longer offers them.
 */
abstract class IranianField extends \WPForms_Field
{
    /** Whether values are checked: the integration is on. */
    public static bool $checks = false;

    /** The builder's group for the fields. */
    public const GROUP = 'persian_kit';

    /**
     * The IranianFieldTypes type, such as mobile_ir.
     */
    abstract public static function iranianType(): string;

    public function init()
    {
        $this->name = IranianFieldTypes::label(static::iranianType());
        $this->group = self::GROUP;
    }

    /**
     * @param mixed $fields
     * @return mixed
     */
    public function field_button($fields)
    {
        return self::$checks ? parent::field_button($fields) : $fields;
    }

    /**
     * A Single Line Text field's options, without its input mask, length
     * limit and default value.
     *
     * @param mixed $field
     */
    public function field_options($field)
    {
        $this->field_option('basic-options', $field, ['markup' => 'open']);
        $this->field_option('label', $field);
        $this->field_option('description', $field);
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
        $placeholder = is_array($field) && !empty($field['placeholder']) ? (string) $field['placeholder'] : '';

        $this->field_preview_option('label', $field);
        printf('<input type="text" placeholder="%s" class="primary-input" dir="ltr" readonly>', esc_attr($placeholder));
        $this->field_preview_option('description', $field);
    }

    /**
     * The input with the type's attributes: digits read left to right, and
     * phones offer the right keyboard.
     *
     * @param mixed $field
     * @param mixed $deprecated
     * @param mixed $form_data
     */
    public function field_display($field, $deprecated, $form_data)
    {
        $primary = $field['properties']['inputs']['primary'];
        $attributes = IranianFieldTypes::inputAttributes(static::iranianType());

        $primary['attr']['dir'] = $attributes['dir'];
        $primary['attr']['autocomplete'] = $attributes['autocomplete'];
        $primary['attr']['maxlength'] = (string) $attributes['maxlength'];
        if ($attributes['inputmode'] !== null) {
            $primary['attr']['inputmode'] = $attributes['inputmode'];
        }

        printf(
            '<input type="%1$s" %2$s %3$s>',
            esc_attr($attributes['type']),
            wpforms_html_attributes($primary['id'], $primary['class'], $primary['data'], $primary['attr']), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            esc_attr((string) $primary['required'])
        );
    }

    /**
     * @param mixed $field_id
     * @param mixed $field_submit
     * @param mixed $form_data
     */
    public function validate($field_id, $field_submit, $form_data)
    {
        parent::validate($field_id, $field_submit, $form_data);

        if (!self::$checks || !is_string($field_submit) || trim($field_submit) === ''
            || IranianFieldTypes::standardValue(static::iranianType(), $field_submit) !== null
        ) {
            return;
        }

        wpforms()->obj('process')->errors[$form_data['id']][$field_id] = IranianFieldTypes::message(static::iranianType());
    }

    /**
     * The value as WPForms saves text, in its standard form while the
     * integration is on.
     *
     * @param mixed $field_id
     * @param mixed $field_submit
     * @param mixed $form_data
     */
    public function format($field_id, $field_submit, $form_data)
    {
        $value = sanitize_text_field(is_string($field_submit) ? $field_submit : '');
        $label = $form_data['fields'][$field_id]['label'] ?? '';

        wpforms()->obj('process')->fields[$field_id] = [
            'name'  => sanitize_text_field(is_string($label) ? $label : ''),
            'value' => self::$checks ? IranianFieldTypes::normalize(static::iranianType(), $value) : $value,
            'id'    => wpforms_validate_field_id($field_id),
            'type'  => $this->type,
        ];
    }
}
