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
    'jalali_archives'   => [
        'label' => __('Jalali archives and calendar', 'persian-kit'),
        'help'  => __('Archive lists, the calendar and pages such as /1405/07/.', 'persian-kit'),
    ],
    'jalali_permalinks' => [
        'label' => __('Jalali dates in post links', 'persian-kit'),
        'help'  => __('/1405/07/09/my-post/. Old links redirect.', 'persian-kit'),
    ],
    'global_conversion' => [
        'label'   => __('Convert every date (advanced)', 'persian-kit'),
        'help'    => __('Only if your theme still shows Gregorian dates. It can confuse SEO, booking and form plugins.', 'persian-kit'),
        'warning' => true,
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
