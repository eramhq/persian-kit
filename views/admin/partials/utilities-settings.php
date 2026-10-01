<?php
/**
 * Utilities module settings partial.
 *
 * @var array $moduleSettings Current settings for the utilities module.
 */

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$moduleSettings = $args['moduleSettings'] ?? [];

$persianSlugs = !empty($moduleSettings['persian_slugs']);
?>
<div class="persian-kit-setting-row">
    <label>
        <input type="hidden" name="persian_kit_settings[utilities][persian_slugs]" value="0">
        <input
            type="checkbox"
            name="persian_kit_settings[utilities][persian_slugs]"
            value="1"
            <?php checked($persianSlugs); ?>
        >
        <?php esc_html_e('Persian slugs', 'persian-kit'); ?>
    </label>
    <p class="description">
        <?php esc_html_e(
            'Keeps Persian letters in post and term slugs instead of percent-encoding them, and turns a half-space into "-". Other slugs are left to WordPress. Existing slugs are not changed.',
            'persian-kit'
        ); ?>
    </p>
</div>
