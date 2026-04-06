<?php

namespace PersianKit\Core;

use PersianKit\Components\View;
use PersianKit\Contracts\ModuleInterface;

defined('ABSPATH') || exit;

class AdminPage
{
    public const MENU_SLUG = 'persian-kit';

    private SettingsManager $settings;

    /** @var ModuleInterface[] */
    private array $modules;

    /**
     * @param SettingsManager   $settings
     * @param ModuleInterface[] $modules
     */
    public function __construct(SettingsManager $settings, array $modules)
    {
        $this->settings = $settings;
        $this->modules = $modules;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_post_persian_kit_save', [$this, 'handleSave']);
    }

    public function addMenu(): void
    {
        $hook = add_menu_page(
            __('Persian Kit', 'persian-kit'),
            __('Persian Kit', 'persian-kit'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render'],
            'dashicons-admin-generic',
            80
        );

        // Remove the auto-created duplicate submenu
        remove_submenu_page(self::MENU_SLUG, self::MENU_SLUG);
    }

    public function render(): void
    {
        $moduleData = [];

        foreach ($this->modules as $module) {
            $key = $module::key();
            $moduleData[] = [
                'key'         => $key,
                'label'       => $module::label(),
                'description' => $module::description(),
                'instance'    => $module,
                'settings'    => $this->settings->module($key),
            ];
        }

        View::load('admin/settings', [
            'modules'  => $moduleData,
            'settings' => $this->settings,
        ]);
    }

    public function handleSave(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'persian-kit'));
        }

        check_admin_referer('persian_kit_settings');

        $modules = $_POST['modules'] ?? [];

        if (!is_array($modules)) {
            $modules = [];
        }

        foreach ($this->modules as $module) {
            $key = $module::key();
            $values = $modules[$key] ?? [];

            if (!is_array($values)) {
                $values = [];
            }

            // Toggle: if checkbox not present, module is disabled
            $values['enabled'] = isset($values['enabled']);

            $this->settings->updateModule($key, $module->sanitizeSettings($values));
        }

        wp_safe_redirect(
            add_query_arg(
                ['page' => self::MENU_SLUG, 'updated' => '1'],
                admin_url('admin.php')
            )
        );
        exit;
    }
}
