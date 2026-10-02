<?php
/**
 * Forms module settings partial.
 *
 * @var array $moduleSettings Current settings for the forms module.
 */

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$moduleSettings = $args['moduleSettings'] ?? [];

$options = [
    'cf7' => [
        'label' => __('Contact Form 7', 'persian-kit'),
        'help'  => __('Date picker, plus fields such as [mobile_ir] and [national_id].', 'persian-kit'),
    ],
    'acf' => [
        'label' => __('ACF', 'persian-kit'),
        'help'  => __('Date picker for Date and Date Time fields.', 'persian-kit'),
    ],
];
?>

<ul class="persian-kit-options">
    <?php foreach ($options as $settingKey => $option) : ?>
        <?php
        \PersianKit\Components\View::load('admin/partials/checkbox-option', $option + [
            'moduleKey'  => 'forms',
            'settingKey' => $settingKey,
            'checked'    => !empty($moduleSettings[$settingKey]),
        ]);
        ?>
    <?php endforeach; ?>
</ul>
