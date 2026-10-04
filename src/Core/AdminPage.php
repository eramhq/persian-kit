<?php

namespace PersianKit\Core;

use PersianKit\Abstracts\AbstractModule;
use PersianKit\Components\Icon;
use PersianKit\Components\View;
use PersianKit\Contracts\ModuleInterface;
use PersianKit\Service\Import\ImportJob;
use PersianKit\Service\Import\ImportReview;
use PersianKit\Service\Import\ReportDownload;
use PersianKit\Service\Installation\InstallManager;

defined('ABSPATH') || exit;

class AdminPage
{
    public const MENU_SLUG = 'persian-kit';

    /**
     * The settings page's tabs, in order. The first is the default. The
     * WooCommerce tab is shown only while WooCommerce can be used.
     */
    public const TABS = ['display', 'writing', 'woocommerce', 'integrations', 'tools'];

    /**
     * The tab each core module's card sits on. A module missing here goes on
     * the first tab. Integrations (modules with a category) go on the
     * WooCommerce or Integrations tab instead. The Tools tab holds no modules.
     */
    public const GROUPS = [
        'digit_conversion'   => 'display',
        'date_conversion'    => 'display',
        'admin_font'         => 'display',
        'char_normalization' => 'writing',
        'zwnj_editor'        => 'writing',
        'utilities'          => 'writing',
    ];

    /** Integration categories, in the order the Integrations tab lists them. */
    public const CATEGORIES = ['forms', 'commerce', 'compat'];

    /**
     * Keys of the integrations that have been available on this site and
     * shown on the settings page. One missing here gets a New badge.
     */
    public const SEEN_OPTION = 'persian_kit_seen_integrations';

    /** The icon on each module's card, by name in Icon. */
    public const ICONS = [
        'digit_conversion'   => 'digits',
        'date_conversion'    => 'calendar',
        'admin_font'         => 'font',
        'char_normalization' => 'letters',
        'zwnj_editor'        => 'keyboard',
        'utilities'          => 'link',
        'woocommerce'        => 'cart',
        'cf7'                => 'form',
        'acf'                => 'fields',
        'forminator'         => 'form',
        'gravityforms'       => 'form',
        'wpforms'            => 'form',
        'yoast'              => 'search',
        'rank_math'          => 'search',
        'wpml'               => 'globe',
        'polylang'           => 'globe',
    ];

    public const REPO_URL = 'https://github.com/eramhq/persian-kit';
    public const SUPPORT_URL = 'https://wordpress.org/support/plugin/persian-kit/';

    private SettingsManager $settings;
    private ConflictDetector $conflicts;
    private ?ImportReview $imports;

    /** @var ModuleInterface[] */
    private array $modules;

    /**
     * @param SettingsManager   $settings
     * @param ModuleInterface[] $modules
     */
    public function __construct(SettingsManager $settings, array $modules, ConflictDetector $conflicts, ?ImportReview $imports = null)
    {
        $this->settings = $settings;
        $this->modules = $modules;
        $this->conflicts = $conflicts;
        $this->imports = $imports;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_post_persian_kit_dismiss_welcome', [$this, 'handleDismissWelcome']);
        add_action('wp_ajax_persian_kit_seen_integrations', [$this, 'handleSeenIntegrations']);
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
        $groups = array_fill_keys(self::TABS, []);
        $integrations = [];
        $reports = $this->conflicts->reports($this->settings->all());

        foreach ($this->modules as $module) {
            $key = $module::key();
            $plugin = $module->requiredPlugins()[0] ?? [];
            $slug = $plugin['slug'] ?? '';
            $directoryUrl = $slug !== '' ? 'https://wordpress.org/plugins/' . rawurlencode($slug) . '/' : '';

            $moduleData[$key] = [
                'key'         => $key,
                'label'       => $module::label(),
                'icon'        => self::ICONS[$key] ?? '',
                // The integrated plugin's page on WordPress.org, or its own
                // website when it is not there.
                'pluginUrl'   => $plugin['url'] ?? $directoryUrl,
                'description' => $module::description(),
                'instance'    => $module,
                'settings'    => $this->settings->module($key),
                'advice'      => $this->adviceFor($key, $reports),
            ];

            if ($module::category() === null) {
                $groups[self::GROUPS[$key] ?? self::TABS[0]][] = $key;
            } elseif ($module::category() === 'commerce' && $module->isAvailable()) {
                $groups['woocommerce'][] = $key;
            } else {
                $integrations[] = $module;
            }
        }

        $activeTab = $this->activeTab();
        $seen = $this->seenIntegrations();
        $unseen = $seen !== null && array_diff($this->availableIntegrations(), $seen) !== [];

        // Opening a tab with integrations on it counts as seeing them. On
        // another tab, the page tells the server when one is opened.
        if ($unseen && ($activeTab === 'integrations' || $activeTab === 'woocommerce')) {
            $this->markIntegrationsSeen($activeTab);
            $unseen = array_diff($this->availableIntegrations(), $this->seenIntegrations() ?? []) !== [];
        }

        View::load('admin/settings', [
            'modules'              => $moduleData,
            'groups'               => $groups,
            'integrations'         => $this->integrationCards($integrations, $moduleData, $seen),
            'tabs'                 => $this->tabLabels(),
            'activeTab'            => $activeTab,
            'compatibilityReports' => $reports,
            'seenNonce'            => $unseen ? wp_create_nonce('persian_kit_seen_integrations') : '',
            'tools'                => $this->toolsData(),
            'showWelcome'          => (bool) get_option(InstallManager::WELCOME_OPTION, false),
            'dismissWelcomeUrl'    => wp_nonce_url(
                admin_url('admin-post.php?action=persian_kit_dismiss_welcome'),
                'persian_kit_dismiss_welcome'
            ),
        ]);
    }

    /**
     * The Tools tab's switch card: the plugins this site used, and the
     * switch in progress.
     *
     * @return array<string, mixed>
     */
    private function toolsData(): array
    {
        if ($this->imports === null) {
            return [];
        }

        return [
            'sources' => $this->imports->sources(),
            'job'     => ImportJob::load()?->toPublic(),
            'csvUrl'  => ReportDownload::url(),
        ];
    }

    /**
     * The Integrations tab's cards: those that can be turned on (or cannot
     * run on this site's version), by category, and those whose plugin is
     * not active, for "Also works with".
     *
     * @param list<ModuleInterface>               $modules
     * @param array<string, array<string, mixed>> $moduleData
     * @param list<string>|null                   $seen
     * @return array{groups: array<string, array{label: string, cards: list<array<string, mixed>>}>, also: list<array<string, mixed>>, empty: bool}
     */
    private function integrationCards(array $modules, array $moduleData, ?array $seen): array
    {
        $groups = [];
        $also = [];

        foreach ($modules as $module) {
            $key = $module::key();
            $reason = $module->unavailableReason();

            $card = $moduleData[$key] + [
                'category'  => (string) $module::category(),
                'state'     => $reason === null ? 'available' : ($reason['code'] === AbstractModule::REASON_INACTIVE ? 'inactive' : 'unavailable'),
                'reason'    => $reason['message'] ?? '',
                // Not on the list before: the plugin was activated since.
                'new'       => $reason === null && $seen !== null && !in_array($key, $seen, true),
                'kept'      => $reason !== null && $seen !== null && in_array($key, $seen, true),
                'forms'     => $reason === null ? $module->formsUsingFields() : [],
            ];

            if ($card['state'] === 'inactive') {
                $also[] = $card;
                continue;
            }

            $category = $card['category'];
            $groups[$category] ??= ['label' => $this->categoryLabel($category), 'cards' => []];
            $groups[$category]['cards'][] = $card;
        }

        uksort($groups, static fn (string $a, string $b): int => array_search($a, self::CATEGORIES, true) <=> array_search($b, self::CATEGORIES, true));

        return ['groups' => $groups, 'also' => $also, 'empty' => $this->availableIntegrations() === []];
    }

    /**
     * The compatibility reports that advise turning this module off. The
     * page shows them while the module's toggle is on.
     *
     * @param list<array<string, mixed>> $reports
     * @return list<array<string, mixed>>
     */
    private function adviceFor(string $moduleKey, array $reports): array
    {
        return array_values(array_filter($reports, static function (array $report) use ($moduleKey): bool {
            foreach ($report['recommendations'] as $recommendation) {
                if ($recommendation['key'] === $moduleKey && in_array($recommendation['action'], ['turn_off', 'leave_off'], true)) {
                    return true;
                }
            }

            return false;
        }));
    }

    private function categoryLabel(string $category): string
    {
        return match ($category) {
            'forms'    => __('Forms', 'persian-kit'),
            'commerce' => __('Store', 'persian-kit'),
            'compat'   => __('Compatibility', 'persian-kit'),
            default    => $category,
        };
    }

    /**
     * Null until the page is first shown; then the integrations available
     * at that time count as seen, so a fresh install shows no New badges.
     *
     * @return list<string>|null
     */
    private function seenIntegrations(): ?array
    {
        $seen = get_option(self::SEEN_OPTION, null);

        if (!is_array($seen)) {
            $this->markIntegrationsSeen();

            return null;
        }

        return array_values(array_filter($seen, 'is_string'));
    }

    /**
     * Adds the integrations available now to the seen list: those on the
     * given tab, or all of them. Keys stay on it after their plugin is
     * deactivated, so their card can say the settings are kept.
     */
    private function markIntegrationsSeen(?string $tab = null): void
    {
        // Null while the option does not exist, so an empty list is still written.
        $stored = get_option(self::SEEN_OPTION, null);
        $seen = is_array($stored) ? array_values(array_filter($stored, 'is_string')) : [];
        $seen = array_values(array_unique(array_merge($seen, $this->availableIntegrations($tab))));

        if ($seen !== $stored) {
            update_option(self::SEEN_OPTION, $seen, false);
        }
    }

    /**
     * Keys of the integrations whose plugins can be used now: those on the
     * WooCommerce or Integrations tab, or all of them.
     *
     * @return list<string>
     */
    private function availableIntegrations(?string $tab = null): array
    {
        $keys = [];
        foreach ($this->modules as $module) {
            if ($module::category() === null || !$module->isAvailable()) {
                continue;
            }

            $onTab = $module::category() === 'commerce' ? 'woocommerce' : 'integrations';
            if ($tab === null || $tab === $onTab) {
                $keys[] = $module::key();
            }
        }

        return $keys;
    }

    /**
     * The Integrations or WooCommerce tab was opened in the page, without
     * a reload.
     */
    public function handleSeenIntegrations(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(null, 403);
        }

        check_ajax_referer('persian_kit_seen_integrations');

        $tab = isset($_POST['tab']) ? sanitize_key(wp_unslash($_POST['tab'])) : '';
        $this->markIntegrationsSeen(in_array($tab, ['woocommerce', 'integrations'], true) ? $tab : null);

        wp_send_json_success();
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
     * The tabs shown on this site, by name.
     *
     * @return array<string, string>
     */
    private function tabLabels(): array
    {
        $labels = [
            'display'      => __('Display', 'persian-kit'),
            'writing'      => __('Writing', 'persian-kit'),
            'woocommerce'  => __('WooCommerce', 'persian-kit'),
            'integrations' => __('Integrations', 'persian-kit'),
            'tools'        => __('Tools', 'persian-kit'),
        ];

        if (!$this->hasAvailableCommerce()) {
            unset($labels['woocommerce']);
        }

        return $labels;
    }

    private function hasAvailableCommerce(): bool
    {
        foreach ($this->modules as $module) {
            if ($module::category() === 'commerce' && $module->isAvailable()) {
                return true;
            }
        }

        return false;
    }


    /**
     * The tab from ?tab=, or the first tab when it names none or one this
     * site does not show, such as WooCommerce after it was deactivated.
     */
    private function activeTab(): string
    {
        // Only picks which tab is shown; nothing is saved from it.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';

        return array_key_exists($tab, $this->tabLabels()) ? $tab : self::TABS[0];
    }

    private function pageUrl(): string
    {
        return admin_url('admin.php?page=' . self::MENU_SLUG);
    }
}
