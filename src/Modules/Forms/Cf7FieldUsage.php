<?php

namespace PersianKit\Modules\Forms;

defined('ABSPATH') || exit;

/**
 * Finds the Contact Form 7 forms that use the Iranian field tags, for the
 * warning on the settings page when the integration is turned off. The
 * list is cached and cleared whenever a form is saved, trashed or deleted.
 */
class Cf7FieldUsage
{
    public const TRANSIENT = 'persian_kit_cf7_field_usage';

    private const POST_TYPE = 'wpcf7_contact_form';

    public function register(): void
    {
        add_action('wpcf7_after_save', [$this, 'clear']);
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
            'post_type'        => self::POST_TYPE,
            'post_status'      => 'publish',
            'numberposts'      => -1,
            'fields'           => 'ids',
            'orderby'          => 'title',
            'order'            => 'ASC',
        ]);

        foreach ($ids as $id) {
            $id = (int) $id;
            if (self::usesFields((string) get_post_meta($id, '_form', true))) {
                $forms[] = ['id' => $id, 'title' => (string) get_post_field('post_title', $id)];
            }
        }

        set_transient(self::TRANSIENT, $forms, WEEK_IN_SECONDS);

        return $forms;
    }

    /**
     * True when the form template has one of the tags, such as
     * [national_id your-id] or [mobile_ir* phone].
     */
    public static function usesFields(string $template): bool
    {
        $types = implode('|', array_map('preg_quote', IranianFieldTypes::types()));

        return preg_match('/\[(?:' . $types . ')\*?[\s\]]/', $template) === 1;
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
