<?php
/**
 * Reusable module toggle partial.
 *
 * @var string                                $moduleKey      Module key identifier.
 * @var \PersianKit\Contracts\ModuleInterface $module         Module instance.
 * @var array                                 $moduleSettings Current settings for this module.
 */

defined('ABSPATH') || exit;

$isEnabled = !empty($moduleSettings['enabled']);
?>
<div class="persian-kit-module" x-data="{ enabled: <?php echo $isEnabled ? 'true' : 'false'; ?> }">
    <div class="persian-kit-module__header">
        <label class="persian-kit-module__toggle">
            <input
                type="checkbox"
                name="modules[<?php echo esc_attr($moduleKey); ?>][enabled]"
                value="1"
                x-model="enabled"
                <?php checked($isEnabled); ?>
            >
            <span class="persian-kit-module__name">
                <?php echo esc_html($moduleKey); ?>
            </span>
        </label>
    </div>

    <div class="persian-kit-module__settings" x-show="enabled" x-collapse>
        <?php
        $settingsView = $module->settingsView();
        if ($settingsView !== null) {
            \PersianKit\Components\View::load($settingsView, ['moduleSettings' => $moduleSettings]);
        }
        ?>
    </div>
</div>
