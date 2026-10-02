<?php

// Contact Form 7's tag generator classes, which load only in its admin.

if (!class_exists('WPCF7_TagGenerator')) {
    class WPCF7_TagGenerator
    {
        /** @var array<string, array{title: string, options: mixed}> */
        public static array $added = [];

        public static function get_instance(): self
        {
            return new self();
        }

        public function add(string $id, string $title, callable $callback, $options = ''): bool
        {
            self::$added[$id] = ['title' => $title, 'options' => $options];

            return true;
        }
    }
}

if (!class_exists('WPCF7_TagGeneratorGenerator')) {
    class WPCF7_TagGeneratorGenerator
    {
        /** @var list<array{string, mixed}> */
        public static array $printed = [];

        public function __construct(string $key)
        {
        }

        public function print(string $part, $options = ''): void
        {
            self::$printed[] = [$part, $options];
        }
    }
}
