<?php

namespace PersianKit\Core;

defined('ABSPATH') || exit;

class ConflictDetector
{
    private const DEFAULT_POLICIES = [
        'wp-parsidate/wp-parsidate.php' => [
            'name'    => 'WP-Parsidate',
            'type'    => 'overlap',
            'summary' => 'WP-Parsidate is already handling some Persian date and text features.',
            'handles' => [
                'Jalali dates',
                'Digit conversion in content',
                'Arabic-to-Persian text normalization',
                'Admin and editor font styling',
            ],
            'recommendations' => [
                ['key' => 'date_conversion', 'label' => 'Date Conversion', 'action' => 'turn_off'],
                ['key' => 'digit_conversion', 'label' => 'Digit Conversion', 'action' => 'turn_off'],
                ['key' => 'char_normalization', 'label' => 'Character Normalization', 'action' => 'turn_off'],
                ['key' => 'admin_font', 'label' => 'Admin Font', 'action' => 'turn_off'],
                ['key' => 'zwnj_editor', 'label' => 'ZWNJ Editor Support', 'action' => 'keep_on'],
                ['key' => 'utilities', 'label' => 'Utilities', 'action' => 'keep_on'],
            ],
        ],
        'wp-jalali/wp-jalali.php' => [
            'name'    => 'WP Jalali',
            'type'    => 'overlap',
            'summary' => 'WP Jalali is already handling some Persian date and text features.',
            'handles' => [
                'Jalali dates',
                'Digit conversion in content',
                'Arabic-to-Persian text normalization',
                'Some admin and editor styling',
                'Jalali archive and permalink behavior',
            ],
            'recommendations' => [
                ['key' => 'date_conversion', 'label' => 'Date Conversion', 'action' => 'turn_off'],
                ['key' => 'digit_conversion', 'label' => 'Digit Conversion', 'action' => 'turn_off'],
                ['key' => 'char_normalization', 'label' => 'Character Normalization', 'action' => 'turn_off'],
                ['key' => 'admin_font', 'label' => 'Admin Font', 'action' => 'turn_off'],
                ['key' => 'zwnj_editor', 'label' => 'ZWNJ Editor Support', 'action' => 'keep_on'],
                ['key' => 'utilities', 'label' => 'Utilities', 'action' => 'keep_on'],
            ],
            'note' => 'WP Jalali also changes archive and permalink behavior. Persian Kit does not replace that yet.',
        ],
        'persian-woocommerce/persian-woocommerce.php' => [
            'name'    => 'Persian WooCommerce',
            'type'    => 'supplementary',
            'summary' => 'Persian WooCommerce is already handling WooCommerce-specific Persian date features.',
            'handles' => [
                'WooCommerce Jalali date inputs',
                'Order, product, and coupon date editing',
                'Some WooCommerce date display and email formatting',
            ],
            'recommendations' => [
                ['key' => 'date_conversion', 'label' => 'Date Conversion', 'action' => 'keep_on'],
                ['key' => 'date_conversion.global_conversion', 'label' => 'Global Date Conversion', 'action' => 'leave_off'],
                ['key' => 'digit_conversion', 'label' => 'Digit Conversion', 'action' => 'no_change'],
                ['key' => 'char_normalization', 'label' => 'Character Normalization', 'action' => 'no_change'],
                ['key' => 'admin_font', 'label' => 'Admin Font', 'action' => 'no_change'],
                ['key' => 'zwnj_editor', 'label' => 'ZWNJ Editor Support', 'action' => 'no_change'],
                ['key' => 'utilities', 'label' => 'Utilities', 'action' => 'keep_on'],
            ],
            'note' => 'Let Persian WooCommerce handle Woo-specific dates. Persian Kit can still handle normal WordPress dates.',
        ],
    ];

    public function detect(): array
    {
        return $this->reports();
    }

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

        $reports = $this->reports();

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

    private function policies(): array
    {
        $policies = self::DEFAULT_POLICIES;

        if (function_exists('apply_filters')) {
            $policies = apply_filters('persian_kit_conflict_policies', $policies);
            $policies = apply_filters('persian_kit_known_conflicts', $policies);
        }

        if (!is_array($policies)) {
            $policies = self::DEFAULT_POLICIES;
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
                : sprintf(__('%s is already handling some of the same features.', 'persian-kit'), $name),
            'handles'         => array_values(array_filter($handles, static fn ($handle) => is_string($handle) && $handle !== '')),
            'recommendations' => $normalizedRecommendations,
            'note'            => is_string($policy['note'] ?? null) && $policy['note'] !== '' ? $policy['note'] : '',
        ];
    }

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
            'turn_off' => sprintf(__('Turn off %s in Persian Kit.', 'persian-kit'), $label),
            'keep_on' => sprintf(__('Keep %s enabled in Persian Kit.', 'persian-kit'), $label),
            'leave_off' => sprintf(__('Leave %s off in Persian Kit.', 'persian-kit'), $label),
            default => sprintf(__('No change needed for %s.', 'persian-kit'), $label),
        };
    }

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

        return (bool) $value;
    }

    private function shouldRenderNoticeOnCurrentScreen(): bool
    {
        if (!function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        $screenId = is_object($screen) && isset($screen->id) && is_string($screen->id) ? $screen->id : '';

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
