<?php

// Minimal WordPress class stubs for unit tests that run without WordPress.

if (!defined('WP_LANG_DIR')) {
    // No language packs are installed in unit tests.
    define('WP_LANG_DIR', sys_get_temp_dir() . '/persian-kit-unit-tests/languages');
}

if (!defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', 604800);
}

if (!class_exists('WP_Post')) {
    class WP_Post
    {
        public int $ID = 0;
        public string $post_type = 'post';
        public string $post_title = '';
    }
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

        /** @var list<array{0: string, 1: mixed}> */
        public static array $commands = [];

        /** @param callable|object|string $callable */
        public static function add_command(string $name, $callable, array $args = []): bool
        {
            self::$commands[] = [$name, $callable];

            return true;
        }
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        /** @var array<string, list<string>> */
        public array $errors = [];

        /** @var array<string, mixed> */
        public array $error_data = [];

        public function __construct(string $code = '', string $message = '', mixed $data = '')
        {
            if ($code !== '') {
                $this->add($code, $message, $data);
            }
        }

        public function add(string $code, string $message, mixed $data = ''): void
        {
            $this->errors[$code][] = $message;
            if ($data !== '') {
                $this->error_data[$code] = $data;
            }
        }

        public function has_errors(): bool
        {
            return $this->errors !== [];
        }

        /** @return list<string> */
        public function get_error_codes(): array
        {
            return array_keys($this->errors);
        }

        public function get_error_message(string $code = ''): string
        {
            $code = $code === '' ? (string) array_key_first($this->errors) : $code;

            return $this->errors[$code][0] ?? '';
        }

        public function get_error_data(string $code = ''): mixed
        {
            return $this->error_data[$code] ?? null;
        }
    }
}

if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server
    {
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        /** @param array<string, mixed> $params */
        public function __construct(private string $method = 'POST', private string $route = '', private array $params = [])
        {
        }

        public function get_route(): string
        {
            return $this->route;
        }

        public function get_param(string $key): mixed
        {
            return $this->params[$key] ?? null;
        }

        public function set_param(string $key, mixed $value): void
        {
            $this->params[$key] = $value;
        }
    }
}

if (!class_exists('WP_REST_Posts_Controller')) {
    class WP_REST_Posts_Controller
    {
    }
}

if (!class_exists('WP_REST_Terms_Controller')) {
    class WP_REST_Terms_Controller
    {
    }
}

if (!class_exists('WP_Term')) {
    class WP_Term
    {
        public int $term_id = 0;
        public int $term_taxonomy_id = 0;
        public string $taxonomy = 'category';
    }
}

if (!class_exists('WPCF7_Submission')) {
    /**
     * Contact Form 7's submission; tests set the one being sent.
     */
    class WPCF7_Submission
    {
        public static ?self $current = null;

        /** @param array<string, mixed> $meta */
        public function __construct(private array $meta = [], private string $formLocale = '')
        {
        }

        public function get_contact_form(): object
        {
            $locale = $this->formLocale;

            return new class ($locale) {
                public function __construct(private string $locale)
                {
                }

                public function locale(): string
                {
                    return $this->locale;
                }
            };
        }

        public static function get_instance(): ?self
        {
            return self::$current;
        }

        public function get_meta(string $name): mixed
        {
            return $this->meta[$name] ?? null;
        }

        public function get_posted_data(string $name = ''): mixed
        {
            return null;
        }
    }
}

if (!class_exists('WC_Data')) {
    /**
     * Meta data and props of WooCommerce objects; get_<prop>() reads $props.
     */
    class WC_Data
    {
        /** @var array<string, mixed> */
        public array $meta = [];

        /** @param array<string, mixed> $props */
        public function __construct(public array $props = [])
        {
        }

        public function get_meta(string $key, bool $single = true): mixed
        {
            return $this->meta[$key] ?? '';
        }

        public function update_meta_data(string $key, mixed $value): void
        {
            $this->meta[$key] = $value;
        }

        public function delete_meta_data(string $key): void
        {
            unset($this->meta[$key]);
        }

        /** @param array<int, mixed> $args */
        public function __call(string $name, array $args): mixed
        {
            if (str_starts_with($name, 'get_')) {
                return $this->props[substr($name, 4)] ?? '';
            }

            throw new BadMethodCallException($name);
        }
    }
}

if (!class_exists('WC_Order')) {
    class WC_Order extends WC_Data
    {
    }
}

if (!class_exists('WC_Customer')) {
    class WC_Customer extends WC_Data
    {
    }
}
