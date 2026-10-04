<?php

// Minimal Gravity Forms signatures for static analysis: only what the
// Gravity Forms integration reads or extends. Gravity Forms ships no stubs.

namespace {
    class GFForms
    {
        /** @var string */
        public static $version = '0.0.0';
    }

    class GFAPI
    {
        /**
         * @param int|array<string, mixed> $form_or_id
         * @param int|string               $field_id
         * @return GF_Field|false
         */
        public static function get_field($form_or_id, $field_id) {}

        /**
         * @param bool|null $active
         * @return list<array<string, mixed>>
         */
        public static function get_forms($active = true, bool $trash = false, string $sort_column = 'id', string $sort_dir = 'ASC') {}
    }

    /**
     * A field's settings are dynamic properties.
     *
     * @property int|string $id
     * @property string     $type
     * @property string     $errorMessage
     * @property bool       $failed_validation
     * @property string     $validation_message
     */
    class GF_Field
    {
        /** @return string */
        public function get_input_type() {}

        /** @return bool */
        public function is_form_editor() {}

        /**
         * @param string|array<mixed> $value
         * @param array<string, mixed> $form
         * @return void
         */
        public function validate($value, $form) {}

        /**
         * @param array<string, mixed> $form
         * @param string|array<mixed>  $value
         * @param array<string, mixed>|null $entry
         * @return string
         */
        public function get_field_input($form, $value = '', $entry = null) {}

        public function __get(string $name): mixed {}

        public function __set(string $name, mixed $value): void {}

        public function __isset(string $name): bool {}
    }

    class GF_Field_Text extends GF_Field
    {
        /** @var string */
        public $type = 'text';
    }

    class GF_Fields
    {
        /** @param GF_Field $field */
        public static function register($field): void {}

        public static function exists(string $field_type): bool {}
    }
}
