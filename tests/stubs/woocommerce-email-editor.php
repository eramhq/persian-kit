<?php

// WooCommerce block email editor signatures for static analysis: only what
// the personalization tag wraps call. Not in the WooCommerce stubs.

namespace Automattic\WooCommerce\EmailEditor\Engine\PersonalizationTags {
    class Personalization_Tag
    {
        /**
         * @param array<string, string> $attributes
         * @param array<string> $post_types
         */
        public function __construct(string $name, string $token, string $category, callable $callback, array $attributes = [], ?string $value_to_insert = null, array $post_types = []) {}
        public function get_name(): string {}
        public function get_token(): string {}
        public function get_category(): string {}
        /** @return array<string, string> */
        public function get_attributes(): array {}
        public function get_value_to_insert(): string {}
        /** @return array<string> */
        public function get_post_types(): array {}
        public function get_callback(): callable {}
        /**
         * @param mixed $context
         * @param mixed $args
         */
        public function execute_callback($context, $args = []): string {}
    }
}
