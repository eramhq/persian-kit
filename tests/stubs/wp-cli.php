<?php

// Minimal WP-CLI signatures for static analysis (php-stubs/wp-cli-stubs does
// not support wordpress-stubs 7.x yet).

namespace {
    class WP_CLI
    {
        public static function log(string $message): void {}
        public static function success(string $message): void {}
        public static function warning(string $message): void {}
        /** @return never */
        public static function error(string $message, bool $exit = true): void { exit(1); }
        /** @param array<string, mixed> $assoc_args */
        public static function confirm(string $question, array $assoc_args = []): void {}
        /** @param callable|object|string $callable */
        public static function add_command(string $name, $callable, array $args = []): bool { return true; }
    }
}

namespace WP_CLI\Utils {
    /**
     * @param array<string, mixed> $assoc_args
     * @param mixed $default
     * @return mixed
     */
    function get_flag_value(array $assoc_args, string $flag, $default = null) {}

    /** @return \cli\progress\Bar|\WP_CLI\NoOp */
    function make_progress_bar(string $message, int $count, int $interval = 100) {}

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int, string>|string $fields
     */
    function format_items(string $format, array $items, $fields): void {}
}

namespace cli\progress {
    class Bar
    {
        public function tick(int $increment = 1, ?string $msg = null): void {}
        public function finish(): void {}
    }
}

namespace WP_CLI {
    class NoOp
    {
        /** @param array<int, mixed> $args */
        public function __call(string $method, array $args): mixed { return null; }
    }
}
