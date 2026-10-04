<?php

namespace PersianKit\Modules\Forms\GravityForms;

use PersianKit\Modules\Forms\IranianFieldTypes;

defined('ABSPATH') || exit;

/**
 * A Gravity Forms field for an Iranian value, in the "Iranian fields" group
 * of the form editor's Add Fields panel. It is a Single Line Text field
 * without an input mask or character limit: the value is checked when the
 * form is sent, and saved in its standard form, such as 09121234567
 * (GravityFormsInputNormalizer). An empty value is left to Gravity Forms'
 * "Required" check.
 *
 * Loaded once Gravity Forms has loaded (GravityFormsIranianFields). While
 * the integration is off the fields stay, as plain text inputs without
 * checks, so the forms that use them keep showing them.
 */
abstract class IranianField extends \GF_Field_Text
{
    /** Whether values are checked: the integration is on. */
    public static bool $checks = false;

    /** The form editor's group for the fields. */
    public const GROUP = 'persian_kit_fields';

    /**
     * The IranianFieldTypes type, such as mobile_ir.
     */
    abstract public static function iranianType(): string;

    public function get_form_editor_field_title(): string
    {
        return esc_attr(IranianFieldTypes::label(static::iranianType()));
    }

    /**
     * @return array{group: string, text: string}
     */
    public function get_form_editor_button(): array
    {
        return [
            'group' => self::GROUP,
            'text'  => $this->get_form_editor_field_title(),
        ];
    }

    /**
     * A Single Line Text field's settings, without its input mask, character
     * limit, password and autocomplete settings: the field sets its own.
     *
     * @return list<string>
     */
    public function get_form_editor_field_settings(): array
    {
        return [
            'conditional_logic_field_setting',
            'prepopulate_field_setting',
            'error_message_setting',
            'label_setting',
            'label_placement_setting',
            'admin_label_setting',
            'size_setting',
            'rules_setting',
            'visibility_setting',
            'duplicate_setting',
            'default_value_setting',
            'placeholder_setting',
            'description_setting',
            'css_class_setting',
        ];
    }

    /**
     * @param mixed $value
     * @param mixed $form
     */
    public function validate($value, $form): void
    {
        if (!self::$checks || !is_string($value) || trim($value) === '' || IranianFieldTypes::standardValue(static::iranianType(), $value) !== null) {
            return;
        }

        $this->failed_validation = true;
        $this->validation_message = empty($this->errorMessage) ? IranianFieldTypes::message(static::iranianType()) : $this->errorMessage;
    }

    /**
     * Gravity Forms' text input, in a container themes style as one, with
     * the type's attributes: digits read left to right, phones offer the
     * right keyboard.
     *
     * @param mixed $form
     * @param mixed $value
     * @param mixed $entry
     */
    public function get_field_input($form, $value = '', $entry = null): string
    {
        $processor = new \WP_HTML_Tag_Processor((string) parent::get_field_input($form, $value, $entry));
        $attributes = IranianFieldTypes::inputAttributes(static::iranianType());

        if ($processor->next_tag(['class_name' => 'ginput_container'])) {
            $processor->add_class('ginput_container_persian_kit');
        }

        if ($processor->next_tag(['tag_name' => 'input'])) {
            $processor->set_attribute('type', $attributes['type']);
            $processor->set_attribute('dir', $attributes['dir']);
            $processor->set_attribute('autocomplete', $attributes['autocomplete']);
            $processor->set_attribute('maxlength', (string) $attributes['maxlength']);
            if ($attributes['inputmode'] !== null) {
                $processor->set_attribute('inputmode', $attributes['inputmode']);
            }
        }

        return $processor->get_updated_html();
    }
}
