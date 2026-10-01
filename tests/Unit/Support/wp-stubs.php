<?php

// Minimal WordPress class stubs for unit tests that run without WordPress.

if (!defined('WP_LANG_DIR')) {
    // No language packs are installed in unit tests.
    define('WP_LANG_DIR', sys_get_temp_dir() . '/persian-kit-unit-tests/languages');
}

if (!class_exists('WP_Query')) {
    class WP_Query
    {
        /** @var array<string, mixed> Vars as requested. */
        public array $query;

        /** @var array<string, mixed> */
        public array $query_vars;

        /** @var array<int, mixed> */
        public array $posts = [];

        /** @var list<array<string, mixed>> Arguments of each query() call. */
        public array $queried = [];

        public bool $feed = false;

        public function __construct(array $vars = [], private bool $mainQuery = true)
        {
            $this->query = $vars;
            $this->query_vars = $vars;
        }

        public function is_main_query(): bool
        {
            return $this->mainQuery;
        }

        public function is_feed(): bool
        {
            return $this->feed;
        }

        public function get(string $key): mixed
        {
            return $this->query_vars[$key] ?? null;
        }

        public function set(string $key, mixed $value): void
        {
            $this->query_vars[$key] = $value;
        }

        public function query(array $vars): array
        {
            $this->queried[] = $vars;
            $this->query = $vars;
            $this->query_vars = $vars;

            return $this->posts;
        }
    }
}

if (!class_exists('WP_Screen')) {
    class WP_Screen
    {
        public string $id = '';
        public string $base = '';
        public string $post_type = '';
    }
}

if (!class_exists('WP_CLI')) {
    class WP_CLI
    {
        /** @var list<array{0: string, 1: string}> */
        public static array $messages = [];

        public static function log(string $message): void
        {
            self::$messages[] = ['log', $message];
        }

        public static function success(string $message): void
        {
            self::$messages[] = ['success', $message];
        }

        public static function add_command(string $name, $callable): void
        {
        }
    }
}
