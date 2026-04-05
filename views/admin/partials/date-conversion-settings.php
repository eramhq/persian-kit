<?php
/**
 * Date conversion module settings partial.
 *
 * @var array $moduleSettings Current settings for the date_conversion module.
 */

defined('ABSPATH') || exit;

$globalConversion = !empty($moduleSettings['global_conversion']);
?>
<div class="persian-kit-setting-row">
    <label>
        <input
            type="checkbox"
            name="modules[date_conversion][global_conversion]"
            value="1"
            <?php checked($globalConversion); ?>
        >
        <?php esc_html_e('Global date conversion (wp_date hook)', 'persian-kit'); ?>
    </label>
    <p class="description persian-kit-warning">
        <?php esc_html_e(
            'May cause Jalali dates in structured data (JSON-LD). Only enable if your theme doesn\'t use standard template tags.',
            'persian-kit'
        ); ?>
    </p>
</div>
