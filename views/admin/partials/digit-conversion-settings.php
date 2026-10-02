<?php
/**
 * Digit conversion module settings partial.
 *
 * @var array $moduleSettings Current settings for the digit_conversion module.
 */

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$moduleSettings = $args['moduleSettings'] ?? [];

$options = [
    'dates' => [
        'label' => __('Dates', 'persian-kit'),
        'help'  => __('Jalali dates on your site, such as ۱۴ فروردین ۱۴۰۴.', 'persian-kit'),
    ],
    'numbers' => [
        'label' => __('Counts', 'persian-kit'),
        'help'  => __('Numbers WordPress formats for display, such as comment and post counts.', 'persian-kit'),
    ],
    'prices' => [
        'label' => __('WooCommerce prices', 'persian-kit'),
        'help'  => __('Product, cart and order prices. In the block cart and checkout, a small script converts the prices the blocks draw in the browser.', 'persian-kit'),
    ],
];
?>
<fieldset>
    <legend class="persian-kit-options__legend"><?php esc_html_e('Also use Persian digits in', 'persian-kit'); ?></legend>
    <ul class="persian-kit-options">
        <?php foreach ($options as $settingKey => $option) : ?>
            <?php
            \PersianKit\Components\View::load('admin/partials/checkbox-option', $option + [
                'moduleKey'  => 'digit_conversion',
                'settingKey' => $settingKey,
                'checked'    => !empty($moduleSettings[$settingKey]),
            ]);
            ?>
        <?php endforeach; ?>
    </ul>
    <p class="description">
        <?php esc_html_e('Text inside code, <kbd> and <samp> elements keeps its digits.', 'persian-kit'); ?>
    </p>
</fieldset>
