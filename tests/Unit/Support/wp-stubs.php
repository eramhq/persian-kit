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

if (!class_exists('Forminator_Mail')) {
    /**
     * Forminator's mail base; tests say whether an email is being written.
     */
    abstract class Forminator_Mail
    {
        public static bool $writing = false;

        public static function is_email_context(): bool
        {
            return self::$writing;
        }
    }
}

if (!class_exists('Forminator_Front_Action')) {
    /**
     * Forminator's submission state; tests set the form and data being sent.
     */
    abstract class Forminator_Front_Action
    {
        /** @var mixed */
        public static $prepared_data = [];

        /** @var mixed */
        public static $module_object;
    }

    class Forminator_CForm_Front_Action extends Forminator_Front_Action
    {
    }
}

if (!class_exists('Forminator_Base_Form_Model')) {
    /**
     * Forminator's form models; tests register them by id.
     */
    class Forminator_Base_Form_Model
    {
        /** @var array<int, object> */
        public static array $models = [];

        public static function get_model(int $id): object|false
        {
            return self::$models[$id] ?? false;
        }
    }
}

if (!class_exists('GFAPI')) {
    /**
     * Gravity Forms' API; tests register fields by form and field id, and
     * forms.
     */
    class GFAPI
    {
        /** @var array<int, array<string, object>> */
        public static array $fields = [];

        /** @var list<array<string, mixed>> */
        public static array $forms = [];

        public static function get_field(int $formId, mixed $fieldId): object|false
        {
            return self::$fields[$formId][(string) $fieldId] ?? false;
        }

        /** @return list<array<string, mixed>> */
        public static function get_forms(?bool $active = true, bool $trash = false, string $sortColumn = 'id'): array
        {
            return self::$forms;
        }
    }
}

if (!class_exists('GF_Field')) {
    /**
     * Gravity Forms' field base: settings are properties, missing ones null.
     */
    class GF_Field
    {
        /** @var array<string, mixed> */
        private array $settings = [];

        public function __get(string $name): mixed
        {
            return $this->settings[$name] ?? null;
        }

        public function __set(string $name, mixed $value): void
        {
            $this->settings[$name] = $value;
        }

        public function __isset(string $name): bool
        {
            return isset($this->settings[$name]);
        }
    }

    class GF_Field_Text extends GF_Field
    {
        public $type = 'text';

        /** @param array<string, mixed> $form */
        public function get_field_input($form, $value = '', $entry = null)
        {
            return "<div class='ginput_container ginput_container_text'><input name='input_1' id='input_1_1' type='text' value='' class='large'/></div>";
        }
    }

    /**
     * Gravity Forms' field types; tests read what was registered.
     */
    class GF_Fields
    {
        /** @var array<string, GF_Field> */
        public static array $fields = [];

        public static function register(GF_Field $field): void
        {
            if (isset(self::$fields[$field->type])) {
                throw new Exception('Field type already registered: ' . $field->type);
            }
            self::$fields[$field->type] = $field;
        }

        public static function exists(string $type): bool
        {
            return isset(self::$fields[$type]);
        }

        public static function get(string $type): GF_Field|false
        {
            return self::$fields[$type] ?? false;
        }
    }
}

if (!class_exists('WPForms_Field')) {
    /**
     * WPForms' submission handler: errors and formatted fields, by form and
     * field id.
     */
    class WPForms_Process
    {
        /** @var array<int|string, array<int|string, mixed>> */
        public array $errors = [];

        /** @var array<int|string, array<string, mixed>> */
        public array $fields = [];
    }

    /**
     * wpforms(): its objects; tests start each with a new process.
     */
    class WPForms_Registry
    {
        public static ?WPForms_Registry $instance = null;

        public WPForms_Process $process;

        public function __construct()
        {
            $this->process = new WPForms_Process();
        }

        public function obj(string $name): ?object
        {
            return $name === 'process' ? $this->process : null;
        }
    }

    function wpforms(): WPForms_Registry
    {
        return WPForms_Registry::$instance ??= new WPForms_Registry();
    }

    function wpforms_validate_field_id(mixed $id): int|string
    {
        return is_numeric($id) ? (int) $id : (string) $id;
    }

    /**
     * As WPForms' own: empty attributes are left out, empty data attributes
     * are not.
     *
     * @param array<string>        $class
     * @param array<string, mixed> $datas
     * @param array<string, mixed> $atts
     */
    function wpforms_html_attributes(string $id = '', array $class = [], array $datas = [], array $atts = []): string
    {
        $parts = $id === '' ? [] : ['id="' . $id . '"'];
        if ($class !== []) {
            $parts[] = 'class="' . implode(' ', $class) . '"';
        }
        foreach ($datas as $name => $value) {
            $parts[] = 'data-' . $name . '="' . htmlspecialchars((string) $value) . '"';
        }
        foreach ($atts as $name => $value) {
            if ((string) $value === '0' || !empty($value)) {
                $parts[] = $name . '="' . htmlspecialchars((string) $value) . '"';
            }
        }

        return implode(' ', $parts);
    }

    /**
     * WPForms' field base, with the hooks the integration relies on. Field
     * options and previews are recorded rather than printed.
     */
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

        /** @var list<string> The options and preview options asked for, in order. */
        public array $printed = [];

        public function __construct()
        {
            $this->init();

            add_filter("wpforms_fields_get_field_object_{$this->type}", fn () => $this);
            add_filter('wpforms_builder_fields_buttons', [$this, 'field_button'], 15);
            add_action("wpforms_process_validate_{$this->type}", [$this, 'validate'], 10, 3);
            add_action("wpforms_process_format_{$this->type}", [$this, 'format'], 10, 3);
        }

        abstract public function init();

        /** @param mixed $field */
        abstract public function field_options($field);

        /** @param mixed $field */
        abstract public function field_preview($field);

        /**
         * @param mixed $field
         * @param mixed $deprecated
         * @param mixed $form_data
         */
        abstract public function field_display($field, $deprecated, $form_data);

        /**
         * @param array<string, array{group_name: string, fields: list<array<string, mixed>>}> $fields
         * @return array<string, mixed>
         */
        public function field_button($fields)
        {
            $fields[$this->group]['fields'][] = ['order' => $this->order, 'name' => $this->name, 'type' => $this->type, 'icon' => $this->icon, 'keywords' => $this->keywords];

            return $fields;
        }

        /**
         * @param mixed                $field
         * @param array<string, mixed> $args
         */
        public function field_option($option, $field, $args = [], $do_echo = true)
        {
            $this->printed[] = 'option:' . $option . (isset($args['markup']) ? ':' . $args['markup'] : '');

            return '';
        }

        /**
         * @param mixed                $field
         * @param array<string, mixed> $args
         */
        public function field_element($option, $field, $args = [], $do_echo = true)
        {
            $this->printed[] = 'element:' . $option . ':' . ($args['slug'] ?? '');

            return $option === 'select' ? json_encode($args['options'] ?? []) . '=' . ($args['value'] ?? '') : '';
        }

        /**
         * @param mixed                $field
         * @param array<string, mixed> $args
         */
        public function field_preview_option($option, $field, $args = [], $do_echo = true)
        {
            $this->printed[] = 'preview:' . $option;

            return '';
        }

        /**
         * WPForms' Required check.
         *
         * @param mixed $field_id
         * @param mixed $field_submit
         * @param mixed $form_data
         */
        public function validate($field_id, $field_submit, $form_data)
        {
            if (!empty($form_data['fields'][$field_id]['required']) && empty($field_submit) && (string) $field_submit !== '0') {
                wpforms()->obj('process')->errors[$form_data['id']][$field_id] = 'This field is required.';
            }
        }

        /**
         * @param mixed $field_id
         * @param mixed $field_submit
         * @param mixed $form_data
         */
        public function format($field_id, $field_submit, $form_data)
        {
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

            if (str_starts_with($name, 'set_')) {
                $this->props[substr($name, 4)] = $args[0] ?? null;

                return null;
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
