<?php
/**
 * Reusable module toggle partial.
 *
 * @var string                                $moduleKey         Module key identifier.
 * @var string                                $moduleLabel       Human-readable module name.
 * @var string                                $moduleDescription Module description text.
 * @var \PersianKit\Contracts\ModuleInterface $module            Module instance.
 * @var array                                 $moduleSettings    Current settings for this module.
 */

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$moduleKey = $args['moduleKey'];
$moduleLabel = $args['moduleLabel'];
$moduleDescription = $args['moduleDescription'];
$module = $args['module'];
$moduleSettings = $args['moduleSettings'] ?? [];

$isEnabled    = !empty($moduleSettings['enabled']);
$settingsView = $module->settingsView();
$nameId       = 'persian-kit-module-' . $moduleKey . '-name';
$descId       = 'persian-kit-module-' . $moduleKey . '-description';
?>
<div class="persian-kit-module" x-data="{ enabled: <?php echo $isEnabled ? 'true' : 'false'; ?> }">
    <div class="persian-kit-module__header">
        <div class="persian-kit-module__info">
            <span class="persian-kit-module__name" id="<?php echo esc_attr($nameId); ?>">
                <?php echo esc_html($moduleLabel); ?>
            </span>
            <?php if ($moduleDescription !== '') : ?>
                <span class="persian-kit-module__description" id="<?php echo esc_attr($descId); ?>">
                    <?php echo esc_html($moduleDescription); ?>
                </span>
            <?php endif; ?>
        </div>

        <label class="persian-kit-module__toggle">
            <?php // Sent when the toggle is off; the checkbox overrides it when on. ?>
            <input type="hidden" name="persian_kit_settings[<?php echo esc_attr($moduleKey); ?>][enabled]" value="0">
            <input
                type="checkbox"
                name="persian_kit_settings[<?php echo esc_attr($moduleKey); ?>][enabled]"
                value="1"
                aria-labelledby="<?php echo esc_attr($nameId); ?>"
                <?php if ($moduleDescription !== '') : ?>
                    aria-describedby="<?php echo esc_attr($descId); ?>"
                <?php endif; ?>
                x-model="enabled"
                <?php checked($isEnabled); ?>
            >
            <span class="persian-kit-module__toggle-track"></span>
        </label>
    </div>

    <?php if ($settingsView !== null) : ?>
        <div class="persian-kit-module__settings" x-show="enabled">
            <?php
            \PersianKit\Components\View::load($settingsView, ['moduleSettings' => $moduleSettings]);
            ?>
        </div>
    <?php endif; ?>
</div>
