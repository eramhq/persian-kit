<?php

// Minimal WooCommerce block email editor classes, in their real namespace, for
// the tests of the personalization tag wraps. Loaded by those tests only, so
// others run as on WooCommerce versions without the editor.

namespace Automattic\WooCommerce\EmailEditor\Engine\PersonalizationTags;

if (!class_exists(Personalization_Tag::class)) {
    class Personalization_Tag
    {
        /** @var callable */
        private $callback;
        private string $token;
        private string $valueToInsert;

        /**
         * @param array<string, string> $attributes
         * @param array<string> $postTypes
         */
        public function __construct(
            private string $name,
            string $token,
            private string $category,
            callable $callback,
            private array $attributes = [],
            ?string $valueToInsert = null,
            private array $postTypes = []
        ) {
            $this->token = str_starts_with($token, '[') ? $token : "[$token]";
            $this->callback = $callback;
            $this->valueToInsert = $valueToInsert ?: $this->token;
        }

        public function get_name(): string
        {
            return $this->name;
        }

        public function get_token(): string
        {
            return $this->token;
        }

        public function get_category(): string
        {
            return $this->category;
        }

        /** @return array<string, string> */
        public function get_attributes(): array
        {
            return $this->attributes;
        }

        public function get_value_to_insert(): string
        {
            return $this->valueToInsert;
        }

        /** @return array<string> */
        public function get_post_types(): array
        {
            return $this->postTypes;
        }

        public function get_callback(): callable
        {
            return $this->callback;
        }

        /**
         * @param mixed $context
         * @param mixed $args
         */
        public function execute_callback($context, $args = []): string
        {
            return call_user_func($this->callback, $context, $args);
        }
    }
}

if (!class_exists(Personalization_Tags_Registry::class)) {
    class Personalization_Tags_Registry
    {
        /** @var array<string, Personalization_Tag> */
        private array $tags = [];

        public function register(Personalization_Tag $tag): void
        {
            $this->tags[$tag->get_token()] ??= $tag;
        }

        /**
         * @param Personalization_Tag|string $tokenOrTag
         */
        public function unregister($tokenOrTag): ?Personalization_Tag
        {
            $token = $tokenOrTag instanceof Personalization_Tag ? $tokenOrTag->get_token() : $tokenOrTag;
            $tag = $this->tags[$token] ?? null;
            unset($this->tags[$token]);

            return $tag;
        }

        public function get_by_token(string $token): ?Personalization_Tag
        {
            return $this->tags[$token] ?? null;
        }

        /** @return array<string, Personalization_Tag> */
        public function get_all(): array
        {
            return $this->tags;
        }
    }
}
