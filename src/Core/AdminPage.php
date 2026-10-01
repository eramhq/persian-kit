<?php

namespace PersianKit\Core;

use PersianKit\Components\View;
use PersianKit\Contracts\ModuleInterface;
use PersianKit\Service\Installation\InstallManager;

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
        add_action('admin_post_persian_kit_dismiss_welcome', [$this, 'handleDismissWelcome']);
        add_filter('plugin_action_links_' . plugin_basename(PERSIAN_KIT_MAIN_FILE), [$this, 'addSettingsLink']);
    }

    /**
     * @param array<int|string, string> $links
     * @return array<int|string, string>
     */
    public function addSettingsLink(array $links): array
    {
        if (!current_user_can('manage_options')) {
            return $links;
        }

        return array_merge([
            'settings' => sprintf(
                '<a href="%s">%s</a>',
                esc_url($this->pageUrl()),
                esc_html__('Settings', 'persian-kit')
            ),
        ], $links);
    }

    public function addMenu(): void
    {
        $hook = add_menu_page(
            __('Persian Kit', 'persian-kit'),
            __('Persian Kit', 'persian-kit'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render'],
            'dashicons-translation',
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
            'modules'              => $moduleData,
            'compatibilityReports' => $this->conflicts->reports($settingsByKey),
            'showWelcome'          => (bool) get_option(InstallManager::WELCOME_OPTION, false),
            'dismissWelcomeUrl'    => wp_nonce_url(
                admin_url('admin-post.php?action=persian_kit_dismiss_welcome'),
                'persian_kit_dismiss_welcome'
            ),
        ]);
    }

    public function handleDismissWelcome(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'persian-kit'));
        }

        check_admin_referer('persian_kit_dismiss_welcome');

        delete_option(InstallManager::WELCOME_OPTION);

        wp_safe_redirect($this->pageUrl());
        exit;
    }

    private function pageUrl(): string
    {
        return admin_url('admin.php?page=' . self::MENU_SLUG);
    }
}
