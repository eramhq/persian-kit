<?php

namespace PersianKit\Core;

defined('ABSPATH') || exit;

class ConflictDetector
{
    private ?SettingsManager $settings;

    public function __construct(?SettingsManager $settings = null)
    {
        $this->settings = $settings;
    }

    /**
     * Built at runtime so the guidance text can be translated.
     *
     * @return array<string, array<string, mixed>>
     */
    private function defaultPolicies(): array
    {
        return [
            'wp-parsidate/wp-parsidate.php' => [
                'name'    => 'WP-Parsidate',
                'type'    => 'overlap',
                'summary' => __('WP-Parsidate is already handling some Persian date and text features.', 'persian-kit'),
                'handles' => [
                    __('Jalali dates', 'persian-kit'),
                    __('Digit conversion in content', 'persian-kit'),
                    __('Arabic-to-Persian text normalization', 'persian-kit'),
                    __('Admin and editor font styling', 'persian-kit'),
                    __('WooCommerce Jalali dates', 'persian-kit'),
                ],
                'recommendations' => [
                    ['key' => 'date_conversion', 'label' => __('Date Conversion', 'persian-kit'), 'action' => 'turn_off'],
                    ['key' => 'digit_conversion', 'label' => __('Digit Conversion', 'persian-kit'), 'action' => 'turn_off'],
                    ['key' => 'char_normalization', 'label' => __('Character Normalization', 'persian-kit'), 'action' => 'turn_off'],
                    ['key' => 'admin_font', 'label' => __('Admin Font', 'persian-kit'), 'action' => 'turn_off'],
                    ['key' => 'woocommerce', 'label' => __('WooCommerce Support', 'persian-kit'), 'action' => 'turn_off'],
                    ['key' => 'zwnj_editor', 'label' => __('ZWNJ Editor Support', 'persian-kit'), 'action' => 'keep_on'],
                    ['key' => 'utilities', 'label' => __('Utilities', 'persian-kit'), 'action' => 'keep_on'],
                ],
            ],
            'wp-jalali/wp-jalali.php' => [
                'name'    => 'WP Jalali',
                'type'    => 'overlap',
                'summary' => __('WP Jalali is already handling some Persian date and text features.', 'persian-kit'),
                'handles' => [
                    __('Jalali dates', 'persian-kit'),
                    __('Digit conversion in content', 'persian-kit'),
                    __('Arabic-to-Persian text normalization', 'persian-kit'),
                    __('Some admin and editor styling', 'persian-kit'),
                    __('Jalali archive and permalink behavior', 'persian-kit'),
                ],
                'recommendations' => [
                    ['key' => 'date_conversion', 'label' => __('Date Conversion', 'persian-kit'), 'action' => 'turn_off'],
                    ['key' => 'digit_conversion', 'label' => __('Digit Conversion', 'persian-kit'), 'action' => 'turn_off'],
                    ['key' => 'char_normalization', 'label' => __('Character Normalization', 'persian-kit'), 'action' => 'turn_off'],
                    ['key' => 'admin_font', 'label' => __('Admin Font', 'persian-kit'), 'action' => 'turn_off'],
                    ['key' => 'zwnj_editor', 'label' => __('ZWNJ Editor Support', 'persian-kit'), 'action' => 'keep_on'],
                    ['key' => 'utilities', 'label' => __('Utilities', 'persian-kit'), 'action' => 'keep_on'],
                ],
                'note' => __('WP Jalali also changes archive and permalink behavior. Persian Kit does not replace that yet.', 'persian-kit'),
            ],
            'persian-woocommerce/woocommerce-persian.php' => [
                'name'    => 'Persian WooCommerce',
                'type'    => 'supplementary',
                'summary' => __('Persian WooCommerce is already handling WooCommerce-specific Persian date features.', 'persian-kit'),
                'handles' => [
                    __('WooCommerce Jalali date inputs', 'persian-kit'),
                    __('Order, product, and coupon date editing', 'persian-kit'),
                    __('Some WooCommerce date display and email formatting', 'persian-kit'),
                ],
                'recommendations' => [
                    ['key' => 'date_conversion', 'label' => __('Date Conversion', 'persian-kit'), 'action' => 'keep_on'],
                    ['key' => 'date_conversion.global_conversion', 'label' => __('Global Date Conversion', 'persian-kit'), 'action' => 'leave_off'],
                    ['key' => 'digit_conversion', 'label' => __('Digit Conversion', 'persian-kit'), 'action' => 'no_change'],
                    ['key' => 'char_normalization', 'label' => __('Character Normalization', 'persian-kit'), 'action' => 'no_change'],
                    ['key' => 'admin_font', 'label' => __('Admin Font', 'persian-kit'), 'action' => 'no_change'],
                    ['key' => 'zwnj_editor', 'label' => __('ZWNJ Editor Support', 'persian-kit'), 'action' => 'no_change'],
                    ['key' => 'woocommerce', 'label' => __('WooCommerce Support', 'persian-kit'), 'action' => 'turn_off'],
                    ['key' => 'utilities', 'label' => __('Utilities', 'persian-kit'), 'action' => 'keep_on'],
                ],
                'note' => __('Let Persian WooCommerce handle Woo-specific dates. Persian Kit can still handle normal WordPress dates.', 'persian-kit'),
            ],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $currentSettings Settings by module key.
     * @return list<array<string, mixed>>
     */
    public function reports(array $currentSettings = []): array
    {
        $reports = [];

        foreach ($this->policies() as $slug => $policy) {
            if (!$this->isPluginCurrentlyActive($slug)) {
                continue;
            }

            $reports[] = $this->buildReport($slug, $policy, $currentSettings);
        }

        return $reports;
    }

    public function registerNotice(): void
    {
        if (function_exists('is_admin') && !is_admin()) {
            return;
        }

        add_action('admin_notices', [$this, 'renderNotice']);
    }

    public function renderNotice(): void
    {
        if (function_exists('current_user_can') && !current_user_can('activate_plugins')) {
            return;
        }

        if (!$this->shouldRenderNoticeOnCurrentScreen()) {
            return;
        }

        // Once the overlapping modules are switched off, the notice goes away.
        $reports = $this->settings === null
            ? $this->reports()
            : array_values(array_filter(
                $this->reports($this->settings->all()),
                static fn (array $report): bool => self::hasPendingRecommendation($report)
            ));

        if ($reports === []) {
            return;
        }

        $reviewUrl = admin_url('admin.php?page=' . AdminPage::MENU_SLUG . '#persian-kit-compatibility');
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php echo esc_html__('Persian Kit', 'persian-kit'); ?>:</strong>
                <?php echo esc_html__('Another Persian plugin is already handling some of the same features. To avoid mixed results, use only one plugin for each feature area.', 'persian-kit'); ?>
            </p>
            <ul>
                <?php foreach ($reports as $report) : ?>
                    <li>
                        <?php
                        echo esc_html($report['summary']) . ' ';
                        ?>
                        <a href="<?php echo esc_url($reviewUrl); ?>">
                            <?php echo esc_html__('Review recommended settings', 'persian-kit'); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function policies(): array
    {
        $policies = $this->defaultPolicies();

        if (function_exists('apply_filters')) {
            $policies = apply_filters('persian_kit_conflict_policies', $policies);
            $policies = apply_filters('persian_kit_known_conflicts', $policies);
        }

        if (!is_array($policies)) {
            $policies = $this->defaultPolicies();
        }

        $normalizedPolicies = [];

        foreach ($policies as $slug => $policy) {
            if (!is_string($slug) || $slug === '' || !is_array($policy)) {
                continue;
            }

            $normalizedPolicies[$slug] = $this->normalizePolicy($policy);
        }

        return $normalizedPolicies;
    }

    /**
     * @param array<string, mixed> $policy
     * @return array<string, mixed>
     */
    private function normalizePolicy(array $policy): array
    {
        $name = is_string($policy['name'] ?? null) && $policy['name'] !== ''
            ? $policy['name']
            : __('Unknown plugin', 'persian-kit');

        $handles = $policy['handles'] ?? $policy['areas'] ?? [];
        if (!is_array($handles)) {
            $handles = [];
        }

        $recommendations = $policy['recommendations'] ?? [];
        if (!is_array($recommendations)) {
            $recommendations = [];
        }

        $normalizedRecommendations = [];
        foreach ($recommendations as $recommendation) {
            if (!is_array($recommendation)) {
                continue;
            }

            $normalizedRecommendation = $this->normalizeRecommendation($recommendation);
            if ($normalizedRecommendation !== null) {
                $normalizedRecommendations[] = $normalizedRecommendation;
            }
        }

        return [
            'name'            => $name,
            'type'            => in_array($policy['type'] ?? '', ['overlap', 'supplementary'], true) ? $policy['type'] : 'overlap',
            'summary'         => is_string($policy['summary'] ?? null) && $policy['summary'] !== ''
                ? $policy['summary']
                /* translators: %s: name of another plugin. */
                : sprintf(__('%s is already handling some of the same features.', 'persian-kit'), $name),
            'handles'         => array_values(array_filter($handles, static fn ($handle) => is_string($handle) && $handle !== '')),
            'recommendations' => $normalizedRecommendations,
            'note'            => is_string($policy['note'] ?? null) && $policy['note'] !== '' ? $policy['note'] : '',
        ];
    }

    /**
     * @param array<string, mixed> $recommendation
     * @return array<string, mixed>|null
     */
    private function normalizeRecommendation(array $recommendation): ?array
    {
        $key = is_string($recommendation['key'] ?? null) ? trim($recommendation['key']) : '';
        $label = is_string($recommendation['label'] ?? null) ? trim($recommendation['label']) : '';
        $action = is_string($recommendation['action'] ?? null) ? trim($recommendation['action']) : 'no_change';

        if ($key === '' || $label === '') {
            return null;
        }

        if (!in_array($action, ['turn_off', 'keep_on', 'leave_off', 'no_change'], true)) {
            $action = 'no_change';
        }

        return [
            'key'    => $key,
            'label'  => $label,
            'action' => $action,
        ];
    }

    /**
     * @param array<string, mixed>                $policy
     * @param array<string, array<string, mixed>> $currentSettings
     * @return array<string, mixed>
     */
    private function buildReport(string $slug, array $policy, array $currentSettings): array
    {
        $recommendations = [];

        foreach ($policy['recommendations'] as $recommendation) {
            $currentValue = $this->currentSettingValue($recommendation['key'], $currentSettings);
            $recommendations[] = [
                'key'           => $recommendation['key'],
                'label'         => $recommendation['label'],
                'action'        => $recommendation['action'],
                'action_label'  => $this->actionLabel($recommendation['action']),
                'instruction'   => $this->instructionLabel($recommendation['label'], $recommendation['action']),
                'current_value' => $currentValue,
                'current_label' => $currentValue
                    ? __('Currently on', 'persian-kit')
                    : __('Currently off', 'persian-kit'),
            ];
        }

        return [
            'slug'            => $slug,
            'name'            => $policy['name'],
            'type'            => $policy['type'],
            'summary'         => $policy['summary'],
            'handles'         => $policy['handles'],
            'recommendations' => $recommendations,
            'note'            => $policy['note'],
        ];
    }

    /**
     * True while a module the report says to turn (or leave) off is still on.
     *
     * @param array<string, mixed> $report
     */
    private static function hasPendingRecommendation(array $report): bool
    {
        foreach ($report['recommendations'] as $recommendation) {
            if (in_array($recommendation['action'], ['turn_off', 'leave_off'], true) && $recommendation['current_value']) {
                return true;
            }
        }

        return false;
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'turn_off' => __('Turn off', 'persian-kit'),
            'keep_on' => __('Keep on', 'persian-kit'),
            'leave_off' => __('Leave off', 'persian-kit'),
            default => __('No change needed', 'persian-kit'),
        };
    }

    private function instructionLabel(string $label, string $action): string
    {
        return match ($action) {
            /* translators: %s: Persian Kit module name. */
            'turn_off' => sprintf(__('Turn off %s in Persian Kit.', 'persian-kit'), $label),
            /* translators: %s: Persian Kit module name. */
            'keep_on' => sprintf(__('Keep %s enabled in Persian Kit.', 'persian-kit'), $label),
            /* translators: %s: Persian Kit module name. */
            'leave_off' => sprintf(__('Leave %s off in Persian Kit.', 'persian-kit'), $label),
            /* translators: %s: Persian Kit module name. */
            default => sprintf(__('No change needed for %s.', 'persian-kit'), $label),
        };
    }

    /**
     * @param array<string, array<string, mixed>> $currentSettings
     */
    private function currentSettingValue(string $key, array $currentSettings): bool
    {
        $segments = explode('.', $key);
        $value = $currentSettings;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        // A bare module key ("date_conversion") means the module's enabled flag.
        if (is_array($value)) {
            return !empty($value['enabled']);
        }

        return (bool) $value;
    }

    private function shouldRenderNoticeOnCurrentScreen(): bool
    {
        if (!function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        $screenId = $screen instanceof \WP_Screen ? $screen->id : '';

        return in_array($screenId, [
            'plugins',
            'toplevel_page_' . AdminPage::MENU_SLUG,
        ], true);
    }

    private function isPluginCurrentlyActive(string $slug): bool
    {
        if (!$this->canCheckActivePlugins()) {
            return false;
        }

        if (is_plugin_active($slug)) {
            return true;
        }

        return function_exists('is_multisite')
            && is_multisite()
            && function_exists('is_plugin_active_for_network')
            && is_plugin_active_for_network($slug);
    }

    private function canCheckActivePlugins(): bool
    {
        if (function_exists('is_plugin_active')) {
            return true;
        }

        $pluginFunctions = ABSPATH . 'wp-admin/includes/plugin.php';

        if (file_exists($pluginFunctions)) {
            require_once $pluginFunctions;
        }

        return function_exists('is_plugin_active');
    }
}
