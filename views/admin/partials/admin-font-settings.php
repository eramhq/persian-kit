<?php
/**
 * Admin Font module settings partial.
 *
 * @var array $moduleSettings Current settings for the admin_font module.
 */

defined('ABSPATH') || exit;

$availableFonts = [
    'vazirmatn' => 'Vazirmatn',
];

$currentFont = $moduleSettings['font'] ?? 'vazirmatn';
?>
<div class="persian-kit-setting-row">
    <label for="persian-kit-admin-font">
        <?php esc_html_e('Admin font', 'persian-kit'); ?>
    </label>
    <select id="persian-kit-admin-font" name="modules[admin_font][font]">
        <?php foreach ($availableFonts as $value => $label) : ?>
            <option value="<?php echo esc_attr($value); ?>" <?php selected($currentFont, $value); ?>>
                <?php echo esc_html($label); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
