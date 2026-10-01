<?php

namespace PersianKit\Service\I18n;

defined('ABSPATH') || exit;

/**
 * Loads the translations shipped in languages/ until translate.wordpress.org
 * provides a language pack for the locale. Once a pack is installed in
 * wp-content/languages/plugins/, WordPress loads it on its own and the bundled
 * files are ignored.
 */
final class BundledTranslations
{
    private const DOMAIN = 'persian-kit';

    public static function register(): void
    {
        add_action('init', [self::class, 'load'], 0);
        // switch_to_locale() unloads every text domain and reloads only
        // language packs, so the bundled file is loaded again for the new locale.
        add_action('change_locale', [self::class, 'load']);
    }

    /**
     * @param string $locale Locale to load; the current one when empty (init passes '').
     */
    public static function load(string $locale = ''): void
    {
        $locale = $locale !== '' ? $locale : determine_locale();
        $file = self::bundledFile($locale);

        if ($file !== null) {
            load_textdomain(self::DOMAIN, $file, $locale);
        }
    }

    /**
     * Directory for wp_set_script_translations(), or null to let WordPress
     * use the language pack.
     */
    public static function scriptPath(): ?string
    {
        return self::bundledFile(determine_locale()) === null ? null : PERSIAN_KIT_DIR . 'languages';
    }

    private static function bundledFile(string $locale): ?string
    {
        $languagePack = WP_LANG_DIR . '/plugins/' . self::DOMAIN . '-' . $locale;
        if (is_readable($languagePack . '.mo') || is_readable($languagePack . '.l10n.php')) {
            return null;
        }

        $bundled = PERSIAN_KIT_DIR . 'languages/' . self::DOMAIN . '-' . $locale . '.mo';

        return is_readable($bundled) ? $bundled : null;
    }
}
