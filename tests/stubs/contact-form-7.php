<?php

// Minimal Contact Form 7 signatures for static analysis: only what the Forms
// module calls. There is no maintained stubs package for Contact Form 7.

namespace {
    class WPCF7_FormTag
    {
        /** @var string */
        public $type;
        /** @var string */
        public $basetype;
        /** @var string */
        public $name;
        /** @var list<string> */
        public $options = [];
        /** @var list<string> */
        public $values = [];

        public function is_required(): bool { return false; }
        public function has_option(string $option): bool { return false; }
        /** @return mixed */
        public function get_option(string $option, string $pattern = '', bool $single = false) {}
        /** @return string|false */
        public function get_size_option(string $default_value = '') {}
        /** @return string|false */
        public function get_maxlength_option(string $default_value = '') {}
        /** @param string|array<string> $default_classes */
        public function get_class_option($default_classes = ''): string { return ''; }
        /** @return string|false */
        public function get_id_option() {}
        /** @return string|false */
        public function get_autocomplete_option(string $default_value = '') {}
        public function get_default_option(string $default_value = '', string|array $options = ''): string { return ''; }
    }

    class WPCF7_Validation
    {
        /** @param WPCF7_FormTag|array<string, mixed>|string $context */
        public function invalidate($context, string|\WP_Error $error): void {}
    }

    class WPCF7_Submission
    {
        public static function get_instance(): ?self { return null; }
        /** @return mixed */
        public function get_posted_data(string $name = '') {}
        /** @return mixed */
        public function get_meta(string $name) {}
    }

    class WPCF7_MailTag
    {
        /** @return mixed */
        public function get_option(string $option) {}
    }

    class WPCF7_ContactForm
    {
        /**
         * @param array<string, mixed>|string $cond
         * @return list<WPCF7_FormTag>
         */
        public function scan_form_tags($cond = null): array { return []; }
    }

    class WPCF7_TagGenerator
    {
        public static function get_instance(): self { return new self(); }
        /** @param array<string, mixed>|string $options */
        public function add(string $id, string $title, callable $callback, $options = ''): bool { return true; }
    }

    class WPCF7_TagGeneratorGenerator
    {
        public function __construct(string $key) {}
        /** @param array<string, mixed>|string $options */
        public function print(string $part, $options = ''): void {}
    }

    class WPCF7_SWV_Schema
    {
        public function add_rule(object $rule): self { return $this; }
    }

    /**
     * @param string|list<string> $tag_types
     * @param array<string, mixed> $features
     */
    function wpcf7_add_form_tag($tag_types, callable $callback, array $features = []): void {}

    function wpcf7_get_message(string $status): string { return ''; }

    function wpcf7_get_validation_error(string $name): string { return ''; }

    function wpcf7_get_validation_error_reference(string $name): ?string { return null; }

    /** @param string|array<string> $default_classes */
    function wpcf7_form_controls_class(string $type, $default_classes = ''): string { return ''; }

    /** @param array<string, mixed> $atts */
    function wpcf7_format_atts(array $atts): string { return ''; }

    /**
     * @param mixed $default_value
     * @return mixed
     */
    function wpcf7_get_hangover(string $name, $default_value = null) {}

    /** @param array<string, mixed> $properties */
    function wpcf7_swv_create_rule(string $rule_name, array $properties = []): object { return new \stdClass(); }
}
