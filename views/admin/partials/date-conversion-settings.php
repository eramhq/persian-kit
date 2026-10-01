<?php
/**
 * Date conversion module settings partial.
 *
 * @var array $moduleSettings Current settings for the date_conversion module.
 */

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$moduleSettings = $args['moduleSettings'] ?? [];

$globalConversion = !empty($moduleSettings['global_conversion']);
?>
<div class="persian-kit-setting-row">
    <label>
        <input type="hidden" name="persian_kit_settings[date_conversion][global_conversion]" value="0">
        <input
            type="checkbox"
            name="persian_kit_settings[date_conversion][global_conversion]"
            value="1"
            <?php checked($globalConversion); ?>
        >
        <?php esc_html_e('Convert all dates (advanced)', 'persian-kit'); ?>
    </label>
    <p class="description">
        <?php esc_html_e('By default only post, comment and admin dates are converted. Turn this on only if your theme or a plugin still shows Gregorian dates, because it converts every date WordPress formats with wp_date(). Known side effects:', 'persian-kit'); ?>
    </p>
    <ul class="description persian-kit-warning persian-kit-warning-list">
        <li><?php esc_html_e('Search engines may read Jalali dates in structured data (JSON-LD) from SEO plugins.', 'persian-kit'); ?></li>
        <li><?php esc_html_e('Plugins that read back a date they formatted, such as event, booking or form plugins, can save or compare the wrong date.', 'persian-kit'); ?></li>
        <li><?php esc_html_e('Dates in exports and in emails sent by other plugins become Jalali.', 'persian-kit'); ?></li>
    </ul>
</div>
