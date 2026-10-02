<?php

namespace PersianKit\Core;

use PersianKit\Components\Icon;
use PersianKit\Components\View;
use PersianKit\Contracts\ModuleInterface;
use PersianKit\Service\Installation\InstallManager;

defined('ABSPATH') || exit;

class AdminPage
{
    public const MENU_SLUG = 'persian-kit';

    /** The settings page's tabs, in order. The first is the default. */
    public const TABS = ['display', 'writing', 'integrations', 'tools'];

    /**
     * The tab each module's card sits on. A module missing here goes on the
     * first tab. The Tools tab holds no modules.
     */
    public const GROUPS = [
        'digit_conversion'   => 'display',
        'date_conversion'    => 'display',
        'admin_font'         => 'display',
        'char_normalization' => 'writing',
        'zwnj_editor'        => 'writing',
        'utilities'          => 'writing',
        'woocommerce'        => 'integrations',
        'forms'              => 'integrations',
    ];

    /** The icon on each module's card, by name in Icon. */
    public const ICONS = [
        'digit_conversion'   => 'digits',
        'date_conversion'    => 'calendar',
        'admin_font'         => 'font',
        'char_normalization' => 'letters',
        'zwnj_editor'        => 'keyboard',
        'utilities'          => 'link',
        'woocommerce'        => 'cart',
        'forms'              => 'form',
    ];

    public const REPO_URL = 'https://github.com/eramhq/persian-kit';
    public const SUPPORT_URL = 'https://wordpress.org/support/plugin/persian-kit/';

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
            Icon::menuIcon(),
            80
        );

        add_action('load-' . $hook, [$this, 'hideWordPressFooter']);

        // Remove the auto-created duplicate submenu
        remove_submenu_page(self::MENU_SLUG, self::MENU_SLUG);
    }

    /**
     * The page has its own footer, so WordPress's "Thank you for creating
     * with WordPress" line and version number are left out on it.
     */
    public function hideWordPressFooter(): void
    {
        add_filter('admin_footer_text', '__return_empty_string');
        // After core_update_footer(), which runs at the default priority.
        add_filter('update_footer', '__return_empty_string', 11);
    }

    public function render(): void
    {
        $moduleData = [];
        $settingsByKey = [];
        $groups = array_fill_keys(self::TABS, []);

        foreach ($this->modules as $module) {
            $key = $module::key();
            $moduleSettings = $this->settings->module($key);

            $groups[self::GROUPS[$key] ?? self::TABS[0]][] = $key;
            $moduleData[$key] = [
                'key'         => $key,
                'label'       => $module::label(),
                'icon'        => self::ICONS[$key] ?? '',
                'description' => $module::description(),
                'inactive'    => $module->inactivePlugins(),
                'instance'    => $module,
                'settings'    => $moduleSettings,
            ];
            $settingsByKey[$key] = $moduleSettings;
        }

        View::load('admin/settings', [
            'modules'              => $moduleData,
            'groups'               => $groups,
            'tabs'                 => $this->tabLabels(),
            'activeTab'            => $this->activeTab(),
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

    /**
     * @return array<string, string>
     */
    private function tabLabels(): array
    {
        return [
            'display'      => __('Display', 'persian-kit'),
            'writing'      => __('Writing', 'persian-kit'),
            'integrations' => __('Integrations', 'persian-kit'),
            'tools'        => __('Tools', 'persian-kit'),
        ];
    }

    private function activeTab(): string
    {
        // Only picks which tab is shown; nothing is saved from it.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';

        return in_array($tab, self::TABS, true) ? $tab : self::TABS[0];
    }

    private function pageUrl(): string
    {
        return admin_url('admin.php?page=' . self::MENU_SLUG);
    }
}
