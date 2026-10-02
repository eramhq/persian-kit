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
    'dates'   => [
        'label'       => __('Dates', 'persian-kit'),
        'description' => __('Jalali dates on your site, such as ۱۴ فروردین ۱۴۰۴.', 'persian-kit'),
    ],
    'numbers' => [
        'label'       => __('Counts', 'persian-kit'),
        'description' => __('Numbers WordPress formats for display, such as comment and post counts.', 'persian-kit'),
    ],
    'prices'  => [
        'label'       => __('WooCommerce prices', 'persian-kit'),
        'description' => __('Product, cart and order prices. In the block cart and checkout, a small script converts the prices the blocks draw in the browser.', 'persian-kit'),
    ],
];
?>
<fieldset class="persian-kit-setting-row">
    <legend class="persian-kit-setting-row__title"><?php esc_html_e('Also use Persian digits in', 'persian-kit'); ?></legend>
    <?php foreach ($options as $optionKey => $option) : ?>
        <p>
            <label>
                <input type="hidden" name="persian_kit_settings[digit_conversion][<?php echo esc_attr($optionKey); ?>]" value="0">
                <input
                    type="checkbox"
                    name="persian_kit_settings[digit_conversion][<?php echo esc_attr($optionKey); ?>]"
                    value="1"
                    <?php checked(!empty($moduleSettings[$optionKey])); ?>
                >
                <?php echo esc_html($option['label']); ?>
            </label>
            <span class="description"><?php echo esc_html($option['description']); ?></span>
        </p>
    <?php endforeach; ?>
    <p class="description">
        <?php esc_html_e('Text inside code, <kbd> and <samp> elements keeps its digits.', 'persian-kit'); ?>
    </p>
</fieldset>
