<?php
/**
 * Reusable module toggle partial.
 *
 * @var string                                $moduleKey         Module key identifier.
 * @var string                                $moduleLabel       Human-readable module name.
 * @var string                                $moduleDescription Module description text.
 * @var string                                $moduleIcon        Icon name in \PersianKit\Components\Icon, or ''.
 * @var \PersianKit\Contracts\ModuleInterface $module            Module instance.
 * @var array                                 $moduleSettings    Current settings for this module.
 * @var bool                                  $ownScope          Whether the card holds its own Alpine "enabled"
 *                                                               state (default), or uses the one around it.
 * @var string|null                           $settingsView      The view under the header, in place of the module's own.
 */

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$moduleKey = $args['moduleKey'];
$moduleLabel = $args['moduleLabel'];
$moduleDescription = $args['moduleDescription'];
$moduleIcon = $args['moduleIcon'] ?? '';
$module = $args['module'];
$moduleSettings = $args['moduleSettings'] ?? [];
$ownScope = $args['ownScope'] ?? true;

$isEnabled    = !empty($moduleSettings['enabled']);
$settingsView = array_key_exists('settingsView', $args) ? $args['settingsView'] : $module->settingsView();
$nameId       = 'persian-kit-module-' . $moduleKey . '-name';
$descId       = 'persian-kit-module-' . $moduleKey . '-description';
?>
<div
    class="persian-kit-module<?php echo $isEnabled ? ' is-on' : ''; ?>"
    :class="{ 'is-on': enabled }"
    <?php if ($ownScope) : ?>
        x-data="{ enabled: <?php echo $isEnabled ? 'true' : 'false'; ?> }"
    <?php endif; ?>
>
    <div class="persian-kit-module__header">
        <?php if ($moduleIcon !== '') : ?>
            <span class="persian-kit-module__icon">
                <?php echo \PersianKit\Components\Icon::render($moduleIcon); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup. ?>
            </span>
        <?php endif; ?>
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
