<?php

namespace PersianKit\Service\Language;

defined('ABSPATH') || exit;

/**
 * Languages from Polylang. Its API loads once it has languages to work
 * with; its language slugs are not unique per locale (fa_IR and fa_AF can
 * both be "fa"), so everything is compared as locales.
 *
 * The form fields read here only tell which language a save is for; the
 * forms are checked and saved by WordPress and Polylang.
 *
 * phpcs:disable WordPress.Security.NonceVerification
 */
final class PolylangSource implements LanguageSource
{
    /** @var array<string, string>|null Locales by language slug. */
    private ?array $locales = null;

    /** @var array{type: string, id: int, lang: string}|null The current REST request's object and lang parameter. */
    private ?array $restRequest = null;

    public static function detected(): bool
    {
        // Brain Monkey leaves stubbed functions defined, so tests rely on the constant.
        return defined('POLYLANG_VERSION') && function_exists('pll_current_language');
    }

    public function register(): void
    {
        add_filter('rest_request_before_callbacks', [$this, 'captureRestLanguage'], 10, 3);
        add_action('switch_blog', [$this, 'forgetLanguages']);
    }

    /**
     * The block editor saves through REST, with the language in "lang".
     *
     * @param mixed $handler
     * @param mixed $request
     */
    public function captureRestLanguage(mixed $response, mixed $handler = null, mixed $request = null): mixed
    {
        $this->restRequest = null;

        if (!$request instanceof \WP_REST_Request) {
            return $response;
        }

        $lang = $request->get_param('lang');
        if (is_string($lang) && $lang !== '') {
            $controller = is_array($handler) && is_array($handler['callback'] ?? null) ? ($handler['callback'][0] ?? null) : null;

            $this->restRequest = [
                'type' => $controller instanceof \WP_REST_Terms_Controller ? 'term' : 'post',
                'id'   => (int) $request->get_param('id'),
                'lang' => sanitize_key($lang),
            ];
        }

        return $response;
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
        return self::locale(pll_current_language('locale'));
    }

    public function defaultLocale(): ?string
    {
        return self::locale(pll_default_language('locale'));
    }

    public function postLocale(int $postId): ?string
    {
        return $postId > 0 ? self::locale(pll_get_post_language($postId, 'locale')) : null;
    }

    public function termLocale(int $termId): ?string
    {
        return $termId > 0 ? self::locale(pll_get_term_language($termId, 'locale')) : null;
    }

    public function switchedLocale(): ?string
    {
        // Polylang for WooCommerce uses switch_to_locale(), which core tracks.
        return null;
    }

    public function requestedLocale(string $objectType, int $objectId): ?string
    {
        $slug = $objectType === 'term' ? $this->requestedTermLanguage($objectId) : $this->requestedPostLanguage($objectId);

        if ($slug === null && $this->restRequest !== null && $this->restRequest['type'] === $objectType
            && ($objectId === 0 || $this->restRequest['id'] === 0 || $this->restRequest['id'] === $objectId)
        ) {
            $slug = $this->restRequest['lang'];
        }

        return $slug === null ? null : ($this->locales()[$slug] ?? null);
    }

    /**
     * The classic editor's language box, Quick Edit and Bulk Edit, and a
     * new translation (post-new.php?new_lang=en).
     */
    private function requestedPostLanguage(int $postId): ?string
    {
        // Other posts saved during the request, such as menu items, have their own language.
        $editedId = isset($_POST['post_ID']) ? (int) $_POST['post_ID'] : 0;
        $isEdited = $postId === 0 || $editedId === $postId;

        if ($isEdited && isset($_POST['post_lang_choice'])) {
            return self::slug(sanitize_key(wp_unslash($_POST['post_lang_choice'])));
        }

        if (isset($_REQUEST['inline_lang_choice'])) {
            $bulkIds = isset($_REQUEST['post']) && is_array($_REQUEST['post']) ? array_map('intval', $_REQUEST['post']) : [];
            $inList = $postId === 0 || ($bulkIds !== [] ? in_array($postId, $bulkIds, true) : $isEdited);

            if ($inList) {
                return self::slug(sanitize_key(wp_unslash($_REQUEST['inline_lang_choice'])));
            }
        }

        if (($GLOBALS['pagenow'] ?? '') === 'post-new.php' && isset($_GET['new_lang'])) {
            return self::slug(sanitize_key(wp_unslash($_GET['new_lang'])));
        }

        return null;
    }

    /**
     * The term forms' language box and Quick Edit.
     */
    private function requestedTermLanguage(int $termId): ?string
    {
        $editedId = isset($_POST['tag_ID']) ? (int) $_POST['tag_ID'] : (isset($_POST['tax_ID']) ? (int) $_POST['tax_ID'] : 0);
        if ($termId !== 0 && $editedId !== 0 && $editedId !== $termId) {
            return null;
        }

        if (isset($_POST['term_lang_choice'])) {
            return self::slug(sanitize_key(wp_unslash($_POST['term_lang_choice'])));
        }

        if (isset($_POST['inline_lang_choice'])) {
            return self::slug(sanitize_key(wp_unslash($_POST['inline_lang_choice'])));
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function locales(): array
    {
        if ($this->locales === null) {
            $slugs = pll_languages_list(['fields' => 'slug']);
            $locales = pll_languages_list(['fields' => 'locale']);

            $this->locales = count($slugs) === count($locales)
                ? array_filter(array_combine(array_map('strval', $slugs), array_map('strval', $locales)), static fn (string $locale): bool => $locale !== '')
                : [];
        }

        return $this->locales;
    }

    /**
     * A language slug from a form field; -1 means "no change" in Bulk Edit.
     */
    private static function slug(string $value): ?string
    {
        return $value === '' || $value === '-1' ? null : $value;
    }

    private static function locale(mixed $locale): ?string
    {
        return is_string($locale) && $locale !== '' ? $locale : null;
    }
}
