<?php

namespace PersianKit\Service\Language;

defined('ABSPATH') || exit;

/**
 * For language sources: posts of untranslated types are in every language.
 */
trait UntranslatedPosts
{
    /**
     * A condition on the posts in a language, widened to posts of the
     * untranslated types among those asked for.
     *
     * @param list<string> $untranslated
     */
    private static function orUntranslated(string $condition, array $untranslated): string
    {
        global $wpdb;

        if ($untranslated === []) {
            return $condition;
        }

        $types = $wpdb->prepare(
            "{$wpdb->posts}.post_type IN (" . implode(',', array_fill(0, count($untranslated), '%s')) . ')',
            $untranslated
        );

        return "({$types} OR {$condition})";
    }
}
