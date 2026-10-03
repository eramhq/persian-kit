<?php

namespace PersianKit\Service\Language;

defined('ABSPATH') || exit;

/**
 * Which language Persian Kit is working in, on sites with WPML or Polylang.
 * Elsewhere it changes nothing: everything is Persian, as before.
 *
 * Two questions, answered separately:
 * - Reading (displaysPersian): pages and emails convert dates and digits
 *   when their language is Persian; admin screens follow the admin's own
 *   profile language.
 * - Writing (writesPersian): the save fixes, Persian slugs and the
 *   half-space key apply to Persian content, whoever edits it.
 *
 * The language is only known once the multilingual plugin has read the
 * request (as late as `wp` for Polylang's "language set from content"), so
 * every check runs when its hook fires.
 */
final class ContentLanguage
{
    private static ?LanguageSource $source = null;

    private static bool $sourceResolved = false;

    private static ?bool $multilingual = null;

    /** Whether this admin-ajax request came from wp-admin; kept for the request. */
    private static ?bool $ajaxFromAdmin = null;

    /** @var array{type: 'post'|'term', id: int}|null The post or term the current REST request saves. */
    private static ?array $restObject = null;

    public static function register(): void
    {
        add_action('switch_blog', [self::class, 'forgetSite']);
        add_filter('rest_request_before_callbacks', [self::class, 'captureRestObject'], 10, 3);
        self::source()?->register();
    }

    /**
     * The block editor saves through REST, where filters such as
     * sanitize_title get no post: the route names it.
     */
    public static function captureRestObject(mixed $response, mixed $handler = null, mixed $request = null): mixed
    {
        self::$restObject = null;

        $controller = is_array($handler) && is_array($handler['callback'] ?? null) ? ($handler['callback'][0] ?? null) : null;
        $id = $request instanceof \WP_REST_Request ? (int) $request->get_param('id') : 0;

        if ($id > 0 && $controller instanceof \WP_REST_Posts_Controller) {
            self::$restObject = ['type' => 'post', 'id' => $id];
        } elseif ($id > 0 && $controller instanceof \WP_REST_Terms_Controller) {
            self::$restObject = ['type' => 'term', 'id' => $id];
        }

        return $response;
    }

    public static function forgetSite(): void
    {
        self::$multilingual = null;
    }

    /**
     * True on sites with WPML or Polylang and at least one language.
     */
    public static function isMultilingual(): bool
    {
        if (self::$multilingual !== null) {
            return self::$multilingual;
        }

        $source = self::source();

        /**
         * Whether pages, emails and writing tools follow each page's
         * language. True on sites with WPML or Polylang and a language set
         * up. Return true for other plugins (TranslatePress) to convert only
         * pages whose locale is Persian.
         *
         * @param bool $multilingual
         */
        $multilingual = (bool) apply_filters('persian_kit_multilingual', $source !== null && $source->languages() !== []);

        // WPML may not list its languages before init, so an early answer is not kept.
        if ($source === null || did_action('init')) {
            self::$multilingual = $multilingual;
        }

        return $multilingual;
    }

    /**
     * fa, or a locale of it such as fa_IR or fa_AF.
     */
    public static function isPersianLocale(string $locale): bool
    {
        /**
         * Whether a locale is Persian.
         *
         * @param bool   $persian True for fa and fa_*.
         * @param string $locale  A WordPress locale, such as fa_IR.
         */
        return (bool) apply_filters('persian_kit_is_persian_locale', $locale === 'fa' || str_starts_with($locale, 'fa_'), $locale);
    }

    /**
     * The language people read in this request: the page's, the email's,
     * or in the admin the admin's profile language.
     */
    public static function currentLocale(): string
    {
        /**
         * The language people read in this request.
         *
         * @param string $locale A WordPress locale, such as fa_IR.
         */
        return (string) apply_filters('persian_kit_current_locale', self::readingLocale());
    }

    /**
     * Whether dates and digits are converted in this request.
     */
    public static function displaysPersian(): bool
    {
        return !self::isMultilingual() || self::isPersianLocale(self::currentLocale());
    }

    /**
     * Whether a post is in Persian, for its permalink and the batch letter
     * fix: its own language, else the site's default. A post with neither
     * counts as Persian.
     */
    public static function postIsPersian(\WP_Post|int $post): bool
    {
        if (!self::isMultilingual()) {
            return true;
        }

        $source = self::source();
        $id = $post instanceof \WP_Post ? (int) $post->ID : $post;
        $locale = $source?->postLocale($id) ?? $source?->defaultLocale();

        return $locale === null || self::isPersianLocale($locale);
    }

    /**
     * Whether text being saved is Persian, for the save fixes, slugs and the
     * half-space key. An ID of 0 means the object this request saves or
     * edits. Unknown counts as Persian.
     *
     * @param 'post'|'term' $objectType
     */
    public static function writesPersian(string $objectType, int $objectId = 0): bool
    {
        /**
         * The language of the content being saved or edited, or null when
         * it is unknown, which counts as Persian.
         *
         * @param string|null   $locale     A WordPress locale, such as fa_IR.
         * @param 'post'|'term' $objectType
         * @param int           $objectId   0 for the object this request saves.
         */
        $locale = apply_filters(
            'persian_kit_content_locale',
            self::isMultilingual() ? self::contentLocale($objectType, $objectId) : null,
            $objectType,
            $objectId
        );

        return !is_string($locale) || $locale === '' || self::isPersianLocale($locale);
    }

    /**
     * An SQL condition on $wpdb->posts that is true for posts in the
     * current content language, for lookups that run their own queries: the
     * page's language, or in the admin the one chosen in the language
     * filter. Already prepared. Null on single-language sites, under "All
     * languages", and when none of the post types is translated.
     *
     * @param list<string> $postTypes
     */
    public static function postsInCurrentLanguage(array $postTypes): ?string
    {
        return self::isMultilingual() ? self::source()?->currentLanguagePosts($postTypes) : null;
    }

    /**
     * Admin screens, and admin-ajax called from them. admin-ajax called from
     * the front end (infinite scroll, load-more) is not.
     */
    public static function isAdminRequest(): bool
    {
        if (!is_admin()) {
            return false;
        }

        if (!wp_doing_ajax()) {
            return true;
        }

        return self::$ajaxFromAdmin ??= self::refererIsAdmin();
    }

    /**
     * @internal For tests.
     */
    public static function useSource(?LanguageSource $source): void
    {
        self::reset();
        self::$source = $source;
        self::$sourceResolved = true;
    }

    /**
     * @internal For tests.
     */
    public static function reset(): void
    {
        self::$source = null;
        self::$sourceResolved = false;
        self::$multilingual = null;
        self::$ajaxFromAdmin = null;
        self::$restObject = null;
    }

    private static function refererIsAdmin(): bool
    {
        $referer = (string) wp_get_raw_referer();

        return $referer === '' || str_starts_with($referer, admin_url());
    }

    private static function readingLocale(): string
    {
        if (!self::isMultilingual()) {
            return determine_locale();
        }

        // WooCommerce, and Polylang for WooCommerce, switch locale for emails.
        if (is_locale_switched()) {
            return determine_locale();
        }

        $source = self::source();
        $switched = $source?->switchedLocale();
        if ($switched !== null) {
            return $switched;
        }

        if (self::isAdminRequest() || self::restAsksForUserLocale()) {
            return get_user_locale();
        }

        return $source?->currentLocale() ?? $source?->defaultLocale() ?? determine_locale();
    }

    /**
     * The language of what is being saved: the language the request gives
     * it, its own (or that of the object the request saves, or of the post
     * open in the editor), the current language, the default one.
     *
     * @param 'post'|'term' $objectType
     */
    private static function contentLocale(string $objectType, int $objectId): ?string
    {
        $source = self::source();
        if ($source === null) {
            return null;
        }

        $locale = $source->requestedLocale($objectType, $objectId);

        if ($locale === null && $objectId > 0) {
            $locale = $objectType === 'term' ? $source->termLocale($objectId) : $source->postLocale($objectId);
        }

        if ($locale === null && $objectId === 0) {
            $edited = self::editedObjectId($objectType);
            $locale = $edited === 0 ? null : ($objectType === 'term' ? $source->termLocale($edited) : $source->postLocale($edited));
        }

        return $locale ?? $source->currentLocale() ?? $source->defaultLocale();
    }

    /**
     * The post or term the REST request saves, or the post open in the
     * editor (post.php and post-new.php).
     *
     * @param 'post'|'term' $objectType
     */
    private static function editedObjectId(string $objectType): int
    {
        if (self::$restObject !== null) {
            return self::$restObject['type'] === $objectType ? self::$restObject['id'] : 0;
        }

        if ($objectType !== 'post' || !in_array($GLOBALS['pagenow'] ?? '', ['post.php', 'post-new.php'], true)) {
            return 0;
        }

        $post = $GLOBALS['post'] ?? null;

        return $post instanceof \WP_Post ? (int) $post->ID : 0;
    }

    /**
     * The block editor's REST requests ask for the user's language.
     */
    private static function restAsksForUserLocale(): bool
    {
        // Only reads which language to answer in.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return isset($_GET['_locale']) && $_GET['_locale'] === 'user' && wp_is_serving_rest_request();
    }

    private static function source(): ?LanguageSource
    {
        if (!self::$sourceResolved) {
            self::$source = match (true) {
                PolylangSource::detected() => new PolylangSource(),
                WpmlSource::detected()     => new WpmlSource(),
                default                    => null,
            };
            self::$sourceResolved = true;
        }

        return self::$source;
    }
}
