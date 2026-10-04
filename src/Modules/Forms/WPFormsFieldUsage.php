<?php

namespace PersianKit\Modules\Forms;

defined('ABSPATH') || exit;

/**
 * Finds the WPForms forms with Iranian or Jalali date fields, for the
 * warning on the settings page when the integration is turned off. The
 * list is cached and cleared whenever a form is saved, created, imported,
 * duplicated, trashed or deleted: each one changes the form's post.
 */
class WPFormsFieldUsage
{
    public const TRANSIENT = 'persian_kit_wpforms_field_usage';

    private const POST_TYPE = 'wpforms';

    /** The Jalali date field's type, besides WPFormsIranianFields::TYPES. */
    private const DATE_TYPE = 'persian-kit-date';

    public function register(): void
    {
        add_action('clean_post_cache', [$this, 'clearForPost'], 10, 2);
    }

    /**
     * Forms not in the trash, by title.
     *
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
            if (self::usesFields(json_decode((string) get_post_field('post_content', $id), true))) {
                $forms[] = ['id' => $id, 'title' => (string) get_post_field('post_title', $id)];
            }
        }

        set_transient(self::TRANSIENT, $forms, WEEK_IN_SECONDS);

        return $forms;
    }

    /**
     * @param mixed $formData The form's settings and fields, as WPForms saves them.
     */
    public static function usesFields($formData): bool
    {
        foreach (is_array($formData) && is_array($formData['fields'] ?? null) ? $formData['fields'] : [] as $field) {
            if (is_array($field) && (WPFormsIranianFields::iranianType($field) !== null || ($field['type'] ?? '') === self::DATE_TYPE)) {
                return true;
            }
        }

        return false;
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
