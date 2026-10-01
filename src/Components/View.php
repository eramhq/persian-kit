<?php

namespace PersianKit\Components;

defined('ABSPATH') || exit;

class View
{
    /**
     * @param string|array<int, string> $view
     * @param array<string, mixed>      $args
     */
    public static function load(string|array $view, array $args = [], bool $return = false): ?string
    {
        $views = is_array($view) ? $view : [$view];

        if ($return) {
            ob_start();
        }

        foreach ($views as $v) {
            $__path = PERSIAN_KIT_DIR . "views/{$v}.php";

            if (!file_exists($__path)) {
                continue;
            }

            if (!empty($args)) {
                // Templates read their documented variables by name.
                extract($args, EXTR_SKIP); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
            }

            include $__path;
        }

        if ($return) {
            return ob_get_clean();
        }

        return null;
    }
}
