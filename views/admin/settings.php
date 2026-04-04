<?php
/**
 * Main settings page template.
 *
 * @var array                            $modules  Module data array.
 * @var \PersianKit\Core\SettingsManager $settings Settings manager instance.
 */

defined('ABSPATH') || exit;
?>
<div class="wrap" x-data="{ activeTab: null }">
    <h1><?php esc_html_e('Persian Kit', 'persian-kit'); ?></h1>

    <?php if (isset($_GET['updated'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Settings saved.', 'persian-kit'); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="persian_kit_save">
        <?php wp_nonce_field('persian_kit_settings'); ?>

        <div class="persian-kit-modules">
            <?php foreach ($modules as $moduleData) : ?>
                <?php
                \PersianKit\Components\View::load('admin/partials/module-toggle', [
                    'moduleKey'      => $moduleData['key'],
                    'module'         => $moduleData['instance'],
                    'moduleSettings' => $moduleData['settings'],
                ]);
                ?>
            <?php endforeach; ?>
        </div>

        <?php submit_button(__('Save Settings', 'persian-kit')); ?>
    </form>
</div>
