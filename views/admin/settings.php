<?php
/**
 * Main settings page template.
 *
 * @var array                            $modules              Module data array.
 * @var array                            $compatibilityReports Compatibility guidance cards.
 * @var \PersianKit\Core\SettingsManager $settings             Settings manager instance.
 */

defined('ABSPATH') || exit;
?>
<div class="wrap persian-kit-wrap">
    <h1><?php esc_html_e('Persian Kit', 'persian-kit'); ?></h1>
    <p class="persian-kit-page-description">
        <?php esc_html_e('Enable or disable modules and configure their settings.', 'persian-kit'); ?>
    </p>

    <?php if (isset($_GET['updated'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Settings saved.', 'persian-kit'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($compatibilityReports)) : ?>
        <?php
        \PersianKit\Components\View::load('admin/partials/compatibility-guidance', [
            'compatibilityReports' => $compatibilityReports,
        ]);
        ?>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="persian_kit_save">
        <?php wp_nonce_field('persian_kit_settings'); ?>

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
