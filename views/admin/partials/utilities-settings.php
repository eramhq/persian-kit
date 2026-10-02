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

$options = [
    'persian_slugs' => [
        'label' => __('Use Persian slugs', 'persian-kit'),
        'help'  => __('Existing slugs are not changed.', 'persian-kit'),
    ],
];
?>

<ul class="persian-kit-options">
    <?php foreach ($options as $settingKey => $option) : ?>
        <?php
        \PersianKit\Components\View::load('admin/partials/checkbox-option', $option + [
            'moduleKey'  => 'utilities',
            'settingKey' => $settingKey,
            'checked'    => !empty($moduleSettings[$settingKey]),
        ]);
        ?>
    <?php endforeach; ?>
</ul>
