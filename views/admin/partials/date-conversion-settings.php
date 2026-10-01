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
$jalaliArchives = !empty($moduleSettings['jalali_archives']);
$jalaliPermalinks = !empty($moduleSettings['jalali_permalinks']);
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
<div class="persian-kit-setting-row">
    <label>
        <input type="hidden" name="persian_kit_settings[date_conversion][jalali_archives]" value="0">
        <input
            type="checkbox"
            name="persian_kit_settings[date_conversion][jalali_archives]"
            value="1"
            <?php checked($jalaliArchives); ?>
        >
        <?php esc_html_e('Show the archive list and calendar in Jalali', 'persian-kit'); ?>
    </label>
    <p class="description">
        <?php esc_html_e('The Archives and Calendar widgets and blocks list Jalali months and days, and link to Jalali archive pages such as /1405/07/.', 'persian-kit'); ?>
    </p>
</div>
<div class="persian-kit-setting-row">
    <label>
        <input type="hidden" name="persian_kit_settings[date_conversion][jalali_permalinks]" value="0">
        <input
            type="checkbox"
            name="persian_kit_settings[date_conversion][jalali_permalinks]"
            value="1"
            <?php checked($jalaliPermalinks); ?>
        >
        <?php esc_html_e('Use Jalali dates in post permalinks', 'persian-kit'); ?>
    </label>
    <p class="description">
        <?php esc_html_e('For permalink structures with the date, such as Day and name: /1405/07/09/my-post/ instead of /2026/10/01/my-post/. Old links redirect to the new ones. If Persian Kit is deactivated, the Jalali links stop working.', 'persian-kit'); ?>
    </p>
</div>
