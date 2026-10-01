<?php

// Minimal WordPress class stubs for unit tests that run without WordPress.

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
