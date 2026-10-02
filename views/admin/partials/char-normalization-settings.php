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
        'label' => __('Fix letters when content is saved', 'persian-kit'),
        'help' => __('Replaces Arabic ي and ك with Persian ی and ک, and Arabic-Indic digits with Persian digits, each time something is saved: the title, excerpt and content of public posts and menu items, new comments and their author names, and the names and descriptions of categories, tags and other terms, WooCommerce product categories included. Code blocks and HTML tags are left alone.', 'persian-kit'),
    ],
    'teh_marbuta' => [
        'label' => __('When fixing letters, also replace Arabic Teh Marbuta (ة) with Persian Heh (ه)', 'persian-kit'),
        'help' => __('Warning: This may corrupt Arabic or Quranic text. Only enable if your content is exclusively Persian.', 'persian-kit'),
        'warning' => true,
    ],
    'half_space_fix' => [
        'label' => __('Add half-spaces when posts are saved', 'persian-kit'),
        'help' => __('Replaces the space with a half-space (ZWNJ) after the prefixes می and نمی and before suffixes such as ها, تر and ترین, in the title, excerpt and content of public posts. The rules are simple and can join words that should stay apart, such as می (wine) in poetry. Code blocks and HTML tags are left alone.', 'persian-kit'),
    ],
];
?>
<p class="description">
    <?php esc_html_e('Search always matches both spellings, and Persian and English digits, while this module is on. It does not change any content.', 'persian-kit'); ?>
</p>

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
