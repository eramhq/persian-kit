<?php

namespace PersianKit\Core;

use PersianKit\Components\View;
use PersianKit\Contracts\ModuleInterface;

defined('ABSPATH') || exit;

class AdminPage
{
    public const MENU_SLUG = 'persian-kit';

    private SettingsManager $settings;
    private ConflictDetector $conflicts;

    /** @var ModuleInterface[] */
    private array $modules;

    /**
     * @param SettingsManager   $settings
     * @param ModuleInterface[] $modules
     */
    public function __construct(SettingsManager $settings, array $modules, ConflictDetector $conflicts)
    {
        $this->settings = $settings;
        $this->modules = $modules;
        $this->conflicts = $conflicts;
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
        $settingsByKey = [];

        foreach ($this->modules as $module) {
            $key = $module::key();
            $moduleSettings = $this->settings->module($key);

            $moduleData[] = [
                'key'         => $key,
                'label'       => $module::label(),
                'description' => $module::description(),
                'instance'    => $module,
                'settings'    => $moduleSettings,
            ];
            $settingsByKey[$key] = $moduleSettings;
        }

        View::load('admin/settings', [
            'modules'             => $moduleData,
            'compatibilityReports' => $this->conflicts->reports($settingsByKey),
        ]);
    }

    public function handleSave(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'persian-kit'));
        }

        check_admin_referer('persian_kit_settings');

        // Each module's sanitizeSettings() sanitizes its own values below.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $modules = isset($_POST['modules']) ? wp_unslash($_POST['modules']) : [];

        if (!is_array($modules)) {
            $modules = [];
        }

        $sanitized = [];

        foreach ($this->modules as $module) {
            $key = $module::key();
            $values = $modules[$key] ?? [];

            if (!is_array($values)) {
                $values = [];
            }

            // Toggle: if checkbox not present, module is disabled
            $values['enabled'] = isset($values['enabled']);

            $sanitized[$key] = $module->sanitizeSettings($values);
        }

        $this->settings->updateModules($sanitized);

        wp_safe_redirect(
            add_query_arg(
                ['page' => self::MENU_SLUG, 'updated' => '1'],
                admin_url('admin.php')
            )
        );
        exit;
    }
}
