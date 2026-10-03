<?php

namespace PersianKit\Modules\Forms;

defined('ABSPATH') || exit;

/**
 * Finds the Forminator forms with fields that use the Iranian check classes,
 * for the warning on the settings page when the integration is turned off.
 * The list is cached and cleared whenever a form is saved, cloned,
 * imported, trashed or deleted.
 */
class ForminatorFieldUsage
{
    public const TRANSIENT = 'persian_kit_forminator_field_usage';

    private const POST_TYPE = 'forminator_forms';

    private const META_KEY = 'forminator_form_meta';

    public function register(): void
    {
        foreach ([
            'forminator_custom_form_action_create',
            'forminator_custom_form_action_update',
            'forminator_form_action_delete',
            'forminator_form_action_clone',
            'forminator_form_action_imported',
        ] as $hook) {
            add_action($hook, [$this, 'clear']);
        }

        // Trashing, restoring and deleting a form, and saves from other code.
        add_action('clean_post_cache', [$this, 'clearForPost'], 10, 2);
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    public function formsUsingFields(): array
    {
        $cached = get_transient(self::TRANSIENT);
        if (is_array($cached)) {
            /** @var list<array{id: int, title: string}> $cached */
            return $cached;
        }

        $forms = [];
        $ids = get_posts([
            'post_type'   => self::POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields'      => 'ids',
            'orderby'     => 'title',
            'order'       => 'ASC',
        ]);

        foreach ($ids as $id) {
            $id = (int) $id;
            $meta = get_post_meta($id, self::META_KEY, true);
            if (self::usesFields($meta)) {
                $forms[] = ['id' => $id, 'title' => self::title($id, $meta)];
            }
        }

        set_transient(self::TRANSIENT, $forms, WEEK_IN_SECONDS);

        return $forms;
    }

    /**
     * True when a field of the form has one of the classes, such as
     * persian-kit-national-id.
     *
     * @param mixed $meta The form's forminator_form_meta.
     */
    public static function usesFields($meta): bool
    {
        foreach (is_array($meta) && is_array($meta['fields'] ?? null) ? $meta['fields'] : [] as $field) {
            if (is_array($field) && ForminatorIranianFields::markerType($field) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * The form's name as its builder shows it; the post title is a slug of it.
     *
     * @param mixed $meta
     */
    private static function title(int $id, $meta): string
    {
        $name = is_array($meta) && is_array($meta['settings'] ?? null) ? ($meta['settings']['formName'] ?? '') : '';

        return is_string($name) && $name !== '' ? $name : (string) get_post_field('post_title', $id);
    }

    public function clear(): void
    {
        delete_transient(self::TRANSIENT);
    }

    /**
     * @param int|string $postId
     * @param mixed      $post
     */
    public function clearForPost($postId, $post = null): void
    {
        if ($post instanceof \WP_Post && $post->post_type === self::POST_TYPE) {
            $this->clear();
        }
    }
}
