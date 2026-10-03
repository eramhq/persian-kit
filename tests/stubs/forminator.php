<?php

// Minimal Forminator signatures for static analysis: only what the
// Forminator integration reads. There is no maintained stubs package for
// Forminator.

namespace {
    define('FORMINATOR_VERSION', '0.0.0');

    abstract class Forminator_Mail
    {
        public static function is_email_context(): bool { return false; }
    }

    abstract class Forminator_Front_Action
    {
        /** @var mixed The submitted data, an array while a form is sent. */
        public static $prepared_data = [];

        /** @var mixed The form being sent, a Forminator_Base_Form_Model. */
        public static $module_object;
    }

    class Forminator_CForm_Front_Action extends Forminator_Front_Action
    {
    }

    abstract class Forminator_Base_Form_Model
    {
        /** @return static|false */
        public static function get_model(int $id) {}

        /** @return array<mixed> */
        public function get_fields() { return []; }

        /** @return array<string, mixed>|object|null */
        public function get_field(string $id, bool $to_array = true) {}
    }
}
