<?php

namespace PersianKit\Modules\Utilities;

use PersianKit\Dependencies\Eram\Abzar\Text\Slug;

defined('ABSPATH') || exit;

/**
 * Persian-aware replacement for core's sanitize_title_with_dashes().
 *
 * Titles without Arabic-script characters go straight to core, so Latin slugs,
 * Woo attribute taxonomies (pa_color) and percent-encoded input keep their
 * core behaviour. Persian titles are slugged by abzar, which turns ZWNJ into
 * "-". Lookups ('query' context) keep ZWNJ so slugs saved before that change
 * still resolve.
 */
class PersianSlugFilter
{
    private const ZWNJ = "\u{200C}";

    private ?string $redirectUrl = null;

    public function register(): void
    {
        remove_filter('sanitize_title', 'sanitize_title_with_dashes', 10);
        add_filter('sanitize_title', [$this, 'sanitizeTitle'], 10, 3);
        add_filter('pre_handle_404', [$this, 'resolveLegacySlug'], 10, 2);
        // Before core's redirect_canonical() so an exact slug match wins over its 404 guess.
        add_action('template_redirect', [$this, 'redirectLegacySlug'], 9);
    }

    /**
     * @param mixed $title
     * @param mixed $rawTitle
     * @param mixed $context
     */
    public function sanitizeTitle($title, $rawTitle = '', $context = 'display'): string
    {
        $title = (string) $title;
        $decoded = rawurldecode($title);

        if (!self::hasArabicScript($decoded)) {
            return sanitize_title_with_dashes($title, (string) $rawTitle, (string) $context);
        }

        if ($context === 'query') {
            return implode(self::ZWNJ, array_map(
                static fn (string $piece): string => Slug::generate($piece),
                explode(self::ZWNJ, $decoded)
            ));
        }

        return Slug::generate($decoded);
    }

    /**
     * When the main query finds nothing for a Persian post name, look the post up
     * under the other forms its slug may have been saved in: abzar's dash-for-ZWNJ
     * form, and core's percent-encoded form (posts created before activation).
     *
     * A post whose stored name is just an encoding of the requested slug is served
     * in place, since redirecting to its permalink would 404 again. Any other match
     * is redirected to its permalink on template_redirect.
     *
     * @param mixed $preempt
     * @param mixed $query
     * @return mixed
     */
    public function resolveLegacySlug($preempt, $query)
    {
        if ($preempt || !$query instanceof \WP_Query || !empty($query->posts) || $query->is_feed()) {
            return $preempt;
        }

        $requested = $this->requestedSlug($query);
        if ($requested === null) {
            return $preempt;
        }

        $post = $this->findLegacyPost($requested);
        if ($post === null) {
            return $preempt;
        }

        if (rawurldecode($post->post_name) === $requested) {
            $query->query($post->post_type === 'page'
                ? ['page_id' => (int) $post->ID]
                : ['p' => (int) $post->ID, 'post_type' => $post->post_type]);

            return $preempt;
        }

        $url = get_permalink((int) $post->ID);
        $this->redirectUrl = is_string($url) && $url !== '' ? $url : null;

        return $preempt;
    }

    public function redirectLegacySlug(): void
    {
        if ($this->redirectUrl !== null && wp_safe_redirect($this->redirectUrl, 301)) {
            exit;
        }
    }

    public function redirectUrl(): ?string
    {
        return $this->redirectUrl;
    }

    /**
     * @return object{ID: int|string, post_name: string, post_type: string}|null
     */
    public function findLegacyPost(string $slug): ?object
    {
        global $wpdb;

        $candidates = array_values(array_unique(array_filter([
            Slug::generate($slug),
            sanitize_title_with_dashes($slug, '', 'save'),
        ])));

        $postTypes = array_values(get_post_types(['public' => true]));
        if ($candidates === [] || $postTypes === []) {
            return null;
        }

        // Runs only on a 404; matches raw post_name values that WP_Query would re-sanitize.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT ID, post_name, post_type FROM {$wpdb->posts}
             WHERE post_name IN (" . implode(',', array_fill(0, count($candidates), '%s')) . ")
               AND post_type IN (" . implode(',', array_fill(0, count($postTypes), '%s')) . ")
               AND post_status = 'publish'
             ORDER BY ID ASC
             LIMIT 1",
            array_merge($candidates, $postTypes)
        ));

        return is_object($row) ? $row : null;
    }

    /**
     * The decoded last path segment of the requested name/pagename, when it is Persian.
     */
    private function requestedSlug(\WP_Query $query): ?string
    {
        // WP_Query::$query keeps the vars as requested; query_vars hold the sanitized copies.
        $vars = is_array($query->query) ? $query->query : [];
        $requested = $vars['name'] ?? '';
        if (!is_string($requested) || $requested === '') {
            $requested = $vars['pagename'] ?? '';
        }
        if (!is_string($requested) || $requested === '') {
            return null;
        }

        $decoded = rawurldecode(wp_basename($requested));

        return self::hasArabicScript($decoded) ? $decoded : null;
    }

    private static function hasArabicScript(string $text): bool
    {
        return preg_match('/[\x{0600}-\x{06FF}]/u', $text) === 1;
    }
}
