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

$isEnabled    = !empty($moduleSettings['enabled']);
$settingsView = $module->settingsView();
?>
<div class="persian-kit-module" x-data="{ enabled: <?php echo $isEnabled ? 'true' : 'false'; ?> }">
    <div class="persian-kit-module__header">
        <div class="persian-kit-module__info">
            <span class="persian-kit-module__name">
                <?php echo esc_html($moduleLabel); ?>
            </span>
            <?php if ($moduleDescription !== '') : ?>
                <span class="persian-kit-module__description">
                    <?php echo esc_html($moduleDescription); ?>
                </span>
            <?php endif; ?>
        </div>

        <label class="persian-kit-module__toggle">
            <input
                type="checkbox"
                name="modules[<?php echo esc_attr($moduleKey); ?>][enabled]"
                value="1"
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
