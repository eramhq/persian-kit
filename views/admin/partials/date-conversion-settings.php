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

$options = [
    'jalali_archives' => [
        'label' => __('Show the archive list and calendar in Jalali', 'persian-kit'),
        'help' => __('The Archives and Calendar widgets and blocks list Jalali months and days, and link to Jalali archive pages such as /1405/07/.', 'persian-kit'),
    ],
    'jalali_permalinks' => [
        'label' => __('Use Jalali dates in post permalinks', 'persian-kit'),
        'help' => __('For permalink structures with the date, such as Day and name: /1405/07/09/my-post/ instead of /2026/10/01/my-post/. Old links redirect to the new ones. If Persian Kit is deactivated, the Jalali links stop working.', 'persian-kit'),
    ],
];
?>

<ul class="persian-kit-options">
    <?php foreach ($options as $settingKey => $option) : ?>
        <?php
        \PersianKit\Components\View::load('admin/partials/checkbox-option', $option + [
            'moduleKey'  => 'date_conversion',
            'settingKey' => $settingKey,
            'checked'    => !empty($moduleSettings[$settingKey]),
        ]);
        ?>
    <?php endforeach; ?>
</ul>
<ul class="persian-kit-options">
    <?php
    \PersianKit\Components\View::load('admin/partials/checkbox-option', [
        'moduleKey'  => 'date_conversion',
        'settingKey' => 'global_conversion',
        'label'      => __('Convert all dates (advanced)', 'persian-kit'),
        'help'       => __('By default only post, comment and admin dates are converted. Turn this on only if your theme or a plugin still shows Gregorian dates, because it converts every date WordPress formats with wp_date(). Known side effects:', 'persian-kit'),
        'checked'    => !empty($moduleSettings['global_conversion']),
    ]);
    ?>
</ul>
<ul class="description persian-kit-warning persian-kit-warning-list">
    <li><?php esc_html_e('Search engines may read Jalali dates in structured data (JSON-LD) from SEO plugins.', 'persian-kit'); ?></li>
    <li><?php esc_html_e('Plugins that read back a date they formatted, such as event, booking or form plugins, can save or compare the wrong date.', 'persian-kit'); ?></li>
    <li><?php esc_html_e('Dates in exports and in emails sent by other plugins become Jalali.', 'persian-kit'); ?></li>
</ul>
