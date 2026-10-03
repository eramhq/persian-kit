<?php

namespace PersianKit\Tests\Unit\Support;

/**
 * Enough of wpdb to read the SQL a class prepares.
 */
final class PreparingWpdb
{
    public string $prefix = 'wp_';

    public string $posts = 'wp_posts';

    public string $term_relationships = 'wp_term_relationships';

    public function prepare(string $query, mixed ...$args): string
    {
        $args = isset($args[0]) && is_array($args[0]) ? $args[0] : $args;

        return (string) preg_replace_callback('/%[sd]/', static function (array $match) use (&$args): string {
            $value = array_shift($args);

            return $match[0] === '%d' ? (string) (int) $value : "'" . addslashes((string) $value) . "'";
        }, $query);
    }
}
