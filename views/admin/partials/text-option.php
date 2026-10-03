<?php
/**
 * One text field inside a module card: the label, the field under it and
 * a help line.
 *
 * @var string $moduleKey   Module key, such as woocommerce.
 * @var string $settingKey  Setting key inside the module.
 * @var string $label       The field's label.
 * @var string $help        One line under the field, or ''.
 * @var string $value       The saved value.
 * @var string $placeholder Shown while the field is empty, or ''.
 * @var int    $maxlength   The most characters it takes, or 0.
 * @var bool   $ltr         Left to right in any admin language, for phone numbers and addresses.
 * @var string $show        An Alpine expression that shows the row, or ''.
 * @var bool   $hidden      Whether the row starts hidden (until Alpine runs).
 */

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$moduleKey = $args['moduleKey'];
$settingKey = $args['settingKey'];
$help = $args['help'] ?? '';
$placeholder = $args['placeholder'] ?? '';
$maxlength = (int) ($args['maxlength'] ?? 0);
$show = $args['show'] ?? '';
$id = 'persian-kit-' . $moduleKey . '-' . $settingKey;
?>
<li
    class="persian-kit-option persian-kit-option--text"
    <?php if ($show !== '') : ?>
        x-show="<?php echo esc_attr($show); ?>"
    <?php endif; ?>
    <?php echo !empty($args['hidden']) ? 'x-cloak' : ''; ?>
>
    <label class="persian-kit-option__label" for="<?php echo esc_attr($id); ?>"><?php echo esc_html($args['label']); ?></label>
    <input
        type="text"
        class="regular-text"
        id="<?php echo esc_attr($id); ?>"
        name="persian_kit_settings[<?php echo esc_attr($moduleKey); ?>][<?php echo esc_attr($settingKey); ?>]"
        value="<?php echo esc_attr($args['value'] ?? ''); ?>"
        <?php if ($placeholder !== '') : ?>
            placeholder="<?php echo esc_attr($placeholder); ?>"
        <?php endif; ?>
        <?php if ($maxlength > 0) : ?>
            maxlength="<?php echo esc_attr((string) $maxlength); ?>"
        <?php endif; ?>
        <?php if (!empty($args['ltr'])) : ?>
            dir="ltr"
        <?php endif; ?>
        <?php if ($help !== '') : ?>
            aria-describedby="<?php echo esc_attr($id . '-help'); ?>"
        <?php endif; ?>
    >
    <?php if ($help !== '') : ?>
        <span class="persian-kit-option__help" id="<?php echo esc_attr($id . '-help'); ?>"><?php echo esc_html($help); ?></span>
    <?php endif; ?>
</li>
