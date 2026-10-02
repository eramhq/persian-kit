<?php
/**
 * Admin Font module settings partial.
 *
 * @var array $moduleSettings Current settings for the admin_font module.
 */

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$moduleSettings = $args['moduleSettings'] ?? [];

$availableFonts = [
    'vazirmatn' => 'Vazirmatn',
];

$currentFont = $moduleSettings['font'] ?? 'vazirmatn';
?>
<ul class="persian-kit-options">
    <li class="persian-kit-option persian-kit-option--select">
        <label class="persian-kit-option__label" for="persian-kit-admin-font">
            <?php esc_html_e('Font', 'persian-kit'); ?>
        </label>
        <select id="persian-kit-admin-font" name="persian_kit_settings[admin_font][font]">
            <?php foreach ($availableFonts as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($currentFont, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </li>
</ul>
