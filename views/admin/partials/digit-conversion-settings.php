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
    'dates'   => ['label' => __('Jalali dates', 'persian-kit')],
    'numbers' => ['label' => __('Post and comment counts', 'persian-kit')],
    'prices'  => ['label' => __('Shop prices', 'persian-kit')],
    'emails'  => [
        'label' => __('WooCommerce emails', 'persian-kit'),
        'help'  => __('Order numbers, prices, quantities and dates. Phone numbers, postcodes and links keep English digits.', 'persian-kit'),
    ],
];
?>
<fieldset>
    <legend class="persian-kit-options__legend"><?php esc_html_e('Also in:', 'persian-kit'); ?></legend>
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
</fieldset>
