<?php
/**
 * Character normalization module settings partial.
 *
 * @var array $moduleSettings Current settings for the char_normalization module.
 */

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$moduleSettings = $args['moduleSettings'] ?? [];

$options = [
    'normalize_on_save' => [
        'label' => __('Fix letters on save', 'persian-kit'),
        'help'  => __('Arabic ي and ك become ی and ک in posts, comments and terms.', 'persian-kit'),
    ],
    'teh_marbuta'       => [
        'label' => __('Also replace ة with ه', 'persian-kit'),
    ],
    'half_space_fix'    => [
        'label' => __('Add half-spaces on save', 'persian-kit'),
        'help'  => __('After می and before ها, تر, ترین. Can join words that should stay apart.', 'persian-kit'),
    ],
];
?>
<ul class="persian-kit-options">
    <?php foreach ($options as $settingKey => $option) : ?>
        <?php
        \PersianKit\Components\View::load('admin/partials/checkbox-option', $option + [
            'moduleKey'  => 'char_normalization',
            'settingKey' => $settingKey,
            'checked'    => !empty($moduleSettings[$settingKey]),
        ]);
        ?>
    <?php endforeach; ?>
</ul>
