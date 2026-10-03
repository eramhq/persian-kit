<?php

namespace PersianKit\Service\Language;

defined('ABSPATH') || exit;

/**
 * Languages from WPML, through its filters. WPML works with language codes
 * (fa, en), each mapped to its default locale.
 *
 * WPML caches the locale and does not change it when it switches language
 * during a request (wpml_switch_language), as WooCommerce Multilingual does
 * for customer emails, so determine_locale() is wrong then. Switches away
 * from the request's language are tracked instead.
 *
 * The form fields read here only tell which language a save is for; the
 * forms are checked and saved by WordPress and WPML.
 *
 * phpcs:disable WordPress.Security.NonceVerification
 */
final class WpmlSource implements LanguageSource
{
    use UntranslatedPosts;

    /** @var array<string, string>|null Locales by language code. */
    private ?array $locales = null;

    /** The request's language, once WPML has set it. */
    private ?string $baseline = null;

    private ?string $switched = null;

    public static function detected(): bool
    {
        // Polylang's WPML compatibility answers wpml_* filters too, but defines neither.
        return defined('ICL_SITEPRESS_VERSION') && class_exists('SitePress');
    }

    public function register(): void
    {
        add_action('init', [$this, 'recordBaseline'], PHP_INT_MAX);
        add_action('wpml_language_has_switched', [$this, 'trackSwitch']);
        add_action('switch_blog', [$this, 'forgetLanguages']);
    }

    public function recordBaseline(): void
    {
        $this->baseline = $this->currentCode() ?? '';
        $this->switched = null;
    }

    /**
     * Reads the language WPML switched to rather than the action's
     * arguments: switching back to the request's language passes null.
     */
    public function trackSwitch(): void
    {
        if ($this->baseline === null) {
            return;
        }

        $current = $this->currentCode();
        $this->switched = $current === null || $current === $this->baseline ? null : ($this->locales()[$current] ?? null);
    }

    public function forgetLanguages(): void
    {
        $this->locales = null;
    }

    public function languages(): array
    {
        return array_values($this->locales());
    }

    public function currentLocale(): ?string
    {
        return $this->localeOf($this->currentCode());
    }

    public function defaultLocale(): ?string
    {
        return $this->localeOf(apply_filters('wpml_default_language', null));
    }

    public function postLocale(int $postId): ?string
    {
        $postType = $postId > 0 ? get_post_type($postId) : false;
        if (!is_string($postType)) {
            return null;
        }

        return $this->localeOf(apply_filters('wpml_element_language_code', null, [
            'element_id'   => $postId,
            'element_type' => $postType,
        ]));
    }

    /**
     * WPML keeps term languages by term_taxonomy_id.
     */
    public function termLocale(int $termId): ?string
    {
        $term = $termId > 0 ? get_term($termId) : null;
        if (!$term instanceof \WP_Term) {
            return null;
        }

        return $this->localeOf(apply_filters('wpml_element_language_code', null, [
            'element_id'   => (int) $term->term_taxonomy_id,
            'element_type' => $term->taxonomy,
        ]));
    }

    public function switchedLocale(): ?string
    {
        return $this->switched;
    }

    /**
     * The classic editor's and term forms' language boxes, and a new
     * translation (post-new.php?lang=en).
     */
    public function requestedLocale(string $objectType, int $objectId): ?string
    {
        if ($objectType === 'term') {
            $taxonomy = isset($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : '';
            $field = "icl_tax_{$taxonomy}_language";
            $editedId = isset($_POST['tag_ID']) ? (int) $_POST['tag_ID'] : (isset($_POST['tax_ID']) ? (int) $_POST['tax_ID'] : 0);
            $isEdited = $objectId === 0 || $editedId === 0 || $editedId === $objectId;

            return $taxonomy !== '' && $isEdited && isset($_POST[$field]) ? $this->localeOf(sanitize_key(wp_unslash($_POST[$field]))) : null;
        }

        $editedId = isset($_POST['post_ID']) ? (int) $_POST['post_ID'] : 0;
        if (($objectId === 0 || $editedId === $objectId) && isset($_POST['icl_post_language'])) {
            return $this->localeOf(sanitize_key(wp_unslash($_POST['icl_post_language'])));
        }

        if (($GLOBALS['pagenow'] ?? '') === 'post-new.php' && isset($_GET['lang'])) {
            return $this->localeOf(sanitize_key(wp_unslash($_GET['lang'])));
        }

        return null;
    }

    /**
     * WPML keeps a post's language in its icl_translations table, by
     * element type (post_page, post_attachment).
     */
    public function currentLanguagePosts(array $postTypes): ?string
    {
        global $wpdb;

        $translated = array_values(array_filter($postTypes, static fn (string $type): bool => (bool) apply_filters('wpml_is_translated_post_type', false, $type)));
        $code = $this->currentCode();
        if ($translated === [] || $code === null) {
            return null;
        }

        $condition = $wpdb->prepare(
            "{$wpdb->posts}.ID IN (SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE language_code = %s AND element_type = CONCAT('post_', {$wpdb->posts}.post_type))",
            $code
        );

        return self::orUntranslated($condition, array_values(array_diff($postTypes, $translated)));
    }

    /**
     * Null in the admin while "All languages" is chosen.
     */
    private function currentCode(): ?string
    {
        $code = apply_filters('wpml_current_language', null);

        return is_string($code) && $code !== '' && $code !== 'all' ? $code : null;
    }

    private function localeOf(mixed $code): ?string
    {
        if (!is_string($code) || $code === '') {
            return null;
        }

        return $this->locales()[$code] ?? null;
    }

    /**
     * Not kept while empty: WPML may answer before it has loaded its languages.
     *
     * @return array<string, string>
     */
    private function locales(): array
    {
        if ($this->locales !== null) {
            return $this->locales;
        }

        $languages = apply_filters('wpml_active_languages', null, ['skip_missing' => 0]);
        $locales = [];

        foreach (is_array($languages) ? $languages : [] as $code => $language) {
            $locale = is_array($language) ? ($language['default_locale'] ?? '') : '';
            if (is_string($locale) && $locale !== '') {
                $locales[(string) ($language['code'] ?? $code)] = $locale;
            }
        }

        if ($locales !== []) {
            $this->locales = $locales;
        }

        return $locales;
    }
}
