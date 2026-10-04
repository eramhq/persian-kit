<?php

// Minimal WPForms signatures for static analysis: only what the WPForms
// integration reads or extends. WPForms ships no stubs.

namespace {
    define('WPFORMS_VERSION', '0.0.0');

    class WPForms_Process
    {
        /** @var array<int|string, array<int|string, mixed>> Errors by form and field id. */
        public $errors = [];

        /** @var array<int|string, array<string, mixed>> The formatted fields, by id. */
        public $fields = [];
    }

    class WPForms_Registry
    {
        /** @return ($name is 'process' ? WPForms_Process : object|null) */
        public function obj(string $name) {}
    }

    /** @return WPForms_Registry */
    function wpforms() {}

    /**
     * @param string                $id
     * @param array<string>|string  $class
     * @param array<string, mixed>  $datas
     * @param array<string, mixed>  $atts
     * @return string
     */
    function wpforms_html_attributes($id = '', $class = [], $datas = [], $atts = [], $echo = false) {}

    /**
     * @param mixed $field_id
     * @return int|string
     */
    function wpforms_validate_field_id($field_id) {}

    /**
     * @param mixed $form_data
     * @return array<string, mixed>|false|null
     */
    function wpforms_decode($form_data) {}

    abstract class WPForms_Field
    {
        /** @var string */
        public $name;

        /** @var string */
        public $type;

        /** @var string|false */
        public $icon = false;

        /** @var string */
        public $keywords = '';

        /** @var int */
        public $order = 1;

        /** @var string */
        public $group = 'standard';

        /** @var mixed */
        public $default_settings;

        /** @param bool $init */
        public function __construct($init = true) {}

        /** @return void */
        abstract public function init();

        /**
         * @param mixed $field
         * @return void
         */
        abstract public function field_options($field);

        /**
         * @param mixed $field
         * @return void
         */
        abstract public function field_preview($field);

        /**
         * @param mixed $field
         * @param mixed $deprecated
         * @param mixed $form_data
         * @return void
         */
        abstract public function field_display($field, $deprecated, $form_data);

        /**
         * @param mixed $fields
         * @return mixed
         */
        public function field_button($fields) {}

        /**
         * @param string               $option
         * @param mixed                $field
         * @param array<string, mixed> $args
         * @param bool                 $do_echo
         * @return string|void
         */
        public function field_option($option, $field, $args = [], $do_echo = true) {}

        /**
         * @param string               $option
         * @param mixed                $field
         * @param array<string, mixed> $args
         * @param bool                 $do_echo
         * @return string|void
         */
        public function field_element($option, $field, $args = [], $do_echo = true) {}

        /**
         * @param string               $option
         * @param mixed                $field
         * @param array<string, mixed> $args
         * @param bool                 $do_echo
         * @return string|void
         */
        public function field_preview_option($option, $field, $args = [], $do_echo = true) {}

        /**
         * @param mixed $field_id
         * @param mixed $field_submit
         * @param mixed $form_data
         * @return void
         */
        public function validate($field_id, $field_submit, $form_data) {}

        /**
         * @param mixed $field_id
         * @param mixed $field_submit
         * @param mixed $form_data
         * @return void
         */
        public function format($field_id, $field_submit, $form_data) {}
    }
}
