<?php
/**
 * One checkbox option inside a module card.
 *
 * @var string $moduleKey  Module key, such as date_conversion.
 * @var string $settingKey Setting key inside the module.
 * @var string $label      The option's label.
 * @var string $help       One line under the label, or ''.
 * @var bool   $warning    Shows the help line as a warning.
 * @var bool   $checked    Whether the option is on.
 * @var string $model      An Alpine property the box is bound to, or ''.
 */

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$moduleKey = $args['moduleKey'];
$settingKey = $args['settingKey'];
$help = $args['help'] ?? '';
$model = $args['model'] ?? '';
$name = 'persian_kit_settings[' . $moduleKey . '][' . $settingKey . ']';
$helpId = 'persian-kit-' . $moduleKey . '-' . $settingKey . '-help';
?>
<li class="persian-kit-option">
    <label class="persian-kit-option__label">
        <?php // Sent when the box is unchecked; the checkbox overrides it when checked. ?>
        <input type="hidden" name="<?php echo esc_attr($name); ?>" value="0">
        <input
            type="checkbox"
            name="<?php echo esc_attr($name); ?>"
            value="1"
            <?php if ($help !== '') : ?>
                aria-describedby="<?php echo esc_attr($helpId); ?>"
            <?php endif; ?>
            <?php if ($model !== '') : ?>
                x-model="<?php echo esc_attr($model); ?>"
            <?php endif; ?>
            <?php checked(!empty($args['checked'])); ?>
        >
        <?php echo esc_html($args['label']); ?>
    </label>
    <?php if ($help !== '') : ?>
        <span
            class="persian-kit-option__help<?php echo !empty($args['warning']) ? ' persian-kit-warning' : ''; ?>"
            id="<?php echo esc_attr($helpId); ?>"
        ><?php echo esc_html($help); ?></span>
    <?php endif; ?>
</li>
