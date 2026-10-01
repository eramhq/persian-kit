<?php
/**
 * Main settings page template.
 *
 * @var array                            $modules              Module data array.
 * @var array                            $compatibilityReports Compatibility guidance cards.
 * @var bool                             $showWelcome          Whether to show the first-run notice.
 * @var string                           $dismissWelcomeUrl    URL that hides the first-run notice.
 */

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$modules = $args['modules'] ?? [];
$compatibilityReports = $args['compatibilityReports'] ?? [];
$showWelcome = !empty($args['showWelcome']);
?>
<div class="wrap persian-kit-wrap">
    <h1><?php esc_html_e('Persian Kit', 'persian-kit'); ?></h1>
    <p class="persian-kit-page-description">
        <?php esc_html_e('Turn each module on or off, then save. Changes take effect on your site right away.', 'persian-kit'); ?>
    </p>

    <?php
    // "Settings saved." from options.php; WordPress shows it by itself only under the Settings menu.
    settings_errors();
    ?>

    <?php if ($showWelcome) : ?>
        <?php
        \PersianKit\Components\View::load('admin/partials/welcome', [
            'modules'           => $modules,
            'dismissWelcomeUrl' => $args['dismissWelcomeUrl'] ?? '',
        ]);
        ?>
    <?php endif; ?>

    <?php if (!empty($compatibilityReports)) : ?>
        <?php
        \PersianKit\Components\View::load('admin/partials/compatibility-guidance', [
            'compatibilityReports' => $compatibilityReports,
        ]);
        ?>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
        <?php settings_fields(\PersianKit\Core\SettingsRegistrar::GROUP); ?>

        <div class="persian-kit-modules">
            <?php foreach ($modules as $moduleData) : ?>
                <?php
                \PersianKit\Components\View::load('admin/partials/module-toggle', [
                    'moduleKey'         => $moduleData['key'],
                    'moduleLabel'       => $moduleData['label'],
                    'moduleDescription' => $moduleData['description'],
                    'module'            => $moduleData['instance'],
                    'moduleSettings'    => $moduleData['settings'],
                ]);
                ?>
            <?php endforeach; ?>
        </div>

        <div class="persian-kit-footer">
            <?php submit_button(__('Save Settings', 'persian-kit')); ?>
        </div>
    </form>
</div>
