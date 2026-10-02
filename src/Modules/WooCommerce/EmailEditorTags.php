<?php

namespace PersianKit\Modules\WooCommerce;

use Automattic\WooCommerce\EmailEditor\Engine\PersonalizationTags\Personalization_Tag;

defined('ABSPATH') || exit;

/**
 * Personalization tags in WooCommerce's block email editor, such as
 * [woocommerce/order-number], are filled in after the email's template parts
 * have rendered, and their callbacks are private. The registry passes through
 * a filter once per request, so a tag is swapped there for a copy whose
 * callback wraps the original.
 *
 * Does nothing on WooCommerce versions without the editor's package, or when
 * the filter passes something else.
 */
class EmailEditorTags
{
    private const ORDER_TAG_PREFIX = '[woocommerce/order-';

    /**
     * @param callable(string): bool $matches Gets the tag's token.
     * @param callable(callable, mixed, mixed): mixed $wrapper Gets the original
     *        callback, the context and the tag's arguments; returns the value.
     */
    public static function wrap(mixed $registry, callable $matches, callable $wrapper): mixed
    {
        if (
            !class_exists(Personalization_Tag::class)
            || !is_object($registry)
            || !method_exists($registry, 'get_all')
            || !method_exists($registry, 'unregister')
            || !method_exists($registry, 'register')
        ) {
            return $registry;
        }

        $tags = $registry->get_all();
        if (!is_array($tags)) {
            return $registry;
        }

        // Every tag from the first match on is registered again, in order, so
        // the editor lists them as before.
        $replacing = false;
        foreach ($tags as $tag) {
            if (!$tag instanceof Personalization_Tag) {
                continue;
            }

            $match = $matches($tag->get_token());
            if (!$match && !$replacing) {
                continue;
            }

            $replacing = true;
            $registry->unregister($tag);
            $registry->register($match ? self::wrapped($tag, $wrapper) : $tag);
        }

        return $registry;
    }

    public static function isOrderTag(string $token): bool
    {
        return str_starts_with($token, self::ORDER_TAG_PREFIX);
    }

    private static function wrapped(Personalization_Tag $tag, callable $wrapper): Personalization_Tag
    {
        $original = $tag->get_callback();

        return new Personalization_Tag(
            $tag->get_name(),
            $tag->get_token(),
            $tag->get_category(),
            static fn ($context, $args = []) => $wrapper($original, $context, $args),
            $tag->get_attributes(),
            $tag->get_value_to_insert(),
            $tag->get_post_types()
        );
    }
}
