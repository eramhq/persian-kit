<?php

namespace PersianKit\Components;

defined('ABSPATH') || exit;

class View
{
    /**
     * Renders views/{$view}.php. Templates read their values from $args.
     *
     * @param string|array<int, string> $view Path under views/, without ".php".
     * @param array<string, mixed>      $args
     */
    public static function load(string|array $view, array $args = [], bool $return = false): ?string
    {
        $views = is_array($view) ? $view : [$view];

        if ($return) {
            ob_start();
        }

        foreach ($views as $v) {
            if (!preg_match('#^[a-z0-9_-]+(/[a-z0-9_-]+)*$#', $v)) {
                continue;
            }

            $path = PERSIAN_KIT_DIR . "views/{$v}.php";

            if (file_exists($path)) {
                self::render($path, $args);
            }
        }

        if ($return) {
            return (string) ob_get_clean();
        }

        return null;
    }

    /**
     * Includes the template in its own scope, so it sees only $args.
     *
     * @param array<string, mixed> $args
     */
    private static function render(string $path, array $args): void
    {
        include $path;
    }
}
