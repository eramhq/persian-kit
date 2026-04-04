<?php

namespace PersianKit\Components;

defined('ABSPATH') || exit;

class View
{
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
                extract($args, EXTR_SKIP);
            }

            include $__path;
        }

        if ($return) {
            return ob_get_clean();
        }

        return null;
    }
}
