<?php

namespace PersianKit\Modules\WooCommerce;

use PersianKit\Service\Language\ContentLanguage;

defined('ABSPATH') || exit;

/**
 * A font for Persian in WooCommerce emails, from fonts already on the
 * reader's computer or phone, in place of WooCommerce's default font.
 * Emails in other languages, a font the store picked, theme email templates
 * and CSS added by other plugins keep their fonts.
 *
 * Classic emails: WooCommerce prints one font stack on each rule, and the
 * default one is swapped for ours before the styles are inlined. Block
 * emails: the editor's base theme gets our font, and the store's own email
 * styles still override it; on RTL sites the content is right-to-left,
 * which the editor's canvas styles force to left-to-right.
 *
 * Customer emails are rendered in the site's language and admin emails in
 * the request's, so each hook checks the language when it runs.
 */
class PersianEmailFont
{
    public const STACK = "Tahoma, 'Segoe UI', -apple-system, BlinkMacSystemFont, 'Noto Sans Arabic', Arial, sans-serif";

    /** WooCommerce's default for classic emails, when its font list is missing. */
    private const CLASSIC_DEFAULT = "'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif";

    /** The same, as older WooCommerce versions printed it. */
    private const CLASSIC_DEFAULT_OLD = '"Helvetica Neue", Helvetica, Roboto, Arial, sans-serif';

    /** The block email editor's default, in its theme.json: Inter from WooCommerce 11.1, Arial before. */
    private const BLOCK_DEFAULTS = [
        "Inter, 'Helvetica Neue', Arial, sans-serif",
        "Arial, 'Helvetica Neue', Helvetica, sans-serif",
    ];

    private const EMAIL_FONT_CLASS = 'Automattic\\WooCommerce\\Internal\\Email\\EmailFont';

    public function register(): void
    {
        // After WooCommerce's block emails strip fonts from these styles (10).
        add_filter('woocommerce_email_styles', [$this, 'filterClassicStyles'], 20);
        add_filter('woocommerce_email_editor_theme_json', [$this, 'filterBlockTheme'], 20);
        add_filter('woocommerce_email_renderer_styles', [$this, 'filterBlockStyles'], 20);
    }

    /**
     * The font stack for Persian emails.
     */
    public static function stack(): string
    {
        /**
         * The fonts Persian WooCommerce emails use, as a CSS font-family
         * value. Mail apps rarely load web fonts, so list installed ones.
         *
         * @param string $stack Tahoma, then the Persian fonts of Windows, Apple devices and Android.
         */
        $stack = trim((string) apply_filters('persian_kit_email_font_family', self::STACK));

        // It goes into CSS as it is: a stack that could end the rule is ignored.
        return $stack !== '' && !preg_match('/[;{}<>]/', $stack) ? $stack : self::STACK;
    }

    /**
     * Classic emails: WooCommerce's default stack, wherever it is printed.
     */
    public function filterClassicStyles(mixed $css): mixed
    {
        if (!is_string($css) || $css === '' || !ContentLanguage::currentIsPersian()) {
            return $css;
        }

        return str_replace(self::classicDefaults(), self::stack(), $css);
    }

    /**
     * Block emails: the base theme's text and heading fonts, while they are
     * the editor's default.
     */
    public function filterBlockTheme(mixed $theme): mixed
    {
        if (!$theme instanceof \WP_Theme_JSON || !ContentLanguage::currentIsPersian()) {
            return $theme;
        }

        $styles = $theme->get_data()['styles'] ?? [];
        $font = ['typography' => ['fontFamily' => self::stack()]];
        $changes = [];

        if (self::isBlockDefault($styles['typography']['fontFamily'] ?? null)) {
            $changes = $font;
        }
        if (self::isBlockDefault($styles['elements']['heading']['typography']['fontFamily'] ?? null)) {
            $changes['elements']['heading'] = $font;
        }

        if ($changes !== []) {
            $theme->merge(new \WP_Theme_JSON(['version' => \WP_Theme_JSON::LATEST_SCHEMA, 'styles' => $changes], 'default'));
        }

        return $theme;
    }

    /**
     * Block emails on RTL sites: these rules match WooCommerce's
     * left-to-right ones and come after them, so they win when inlined.
     */
    public function filterBlockStyles(mixed $css): mixed
    {
        if (!is_string($css) || !is_rtl() || !ContentLanguage::currentIsPersian()) {
            return $css;
        }

        return $css . "\n.email_content_wrapper { direction: rtl; text-align: right; }\n.email_footer { direction: rtl; }\n";
    }

    /**
     * @return list<string>
     */
    private static function classicDefaults(): array
    {
        $fonts = class_exists(self::EMAIL_FONT_CLASS) ? (self::EMAIL_FONT_CLASS)::$font : [];
        $default = is_array($fonts) && is_string($fonts['Helvetica'] ?? null) ? $fonts['Helvetica'] : self::CLASSIC_DEFAULT;

        return array_values(array_unique([$default, self::CLASSIC_DEFAULT, self::CLASSIC_DEFAULT_OLD]));
    }

    private static function isBlockDefault(mixed $font): bool
    {
        return is_string($font) && in_array(strtolower(trim($font)), array_map('strtolower', self::BLOCK_DEFAULTS), true);
    }
}
