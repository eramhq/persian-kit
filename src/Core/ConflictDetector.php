<?php

namespace PersianKit\Core;

defined('ABSPATH') || exit;

class ConflictDetector
{
    private const KNOWN_CONFLICTS = [
        'wp-parsidate/wp-parsidate.php' => [
            'name'  => 'WP-Parsidate',
            'areas' => ['Jalali dates', 'digit conversion', 'admin/archive date output'],
        ],
        'wp-jalali/wp-jalali.php' => [
            'name'  => 'WP Jalali',
            'areas' => ['Jalali dates', 'archive/admin date output'],
        ],
        'persian-woocommerce/persian-woocommerce.php' => [
            'name'  => 'Persian WooCommerce',
            'areas' => ['WooCommerce dates', 'Persian product/store formatting'],
        ],
    ];

    public function detect(): array
    {
        $conflicts = [];

        foreach ($this->knownConflicts() as $slug => $conflict) {
            if (!$this->isPluginCurrentlyActive($slug)) {
                continue;
            }

            $conflicts[] = [
                'slug'  => $slug,
                'name'  => $conflict['name'],
                'areas' => $conflict['areas'],
            ];
        }

        return $conflicts;
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

        $conflicts = $this->detect();

        if ($conflicts === []) {
            return;
        }
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php echo esc_html__('Persian Kit', 'persian-kit'); ?>:</strong>
                <?php echo esc_html__('Overlapping Persian localization plugins are active. Keep only one plugin responsible for each area to avoid conflicting output.', 'persian-kit'); ?>
            </p>
            <ul style="list-style: disc; padding-inline-start: 1.25rem;">
                <?php foreach ($conflicts as $conflict) : ?>
                    <li>
                        <strong><?php echo esc_html($conflict['name']); ?></strong>
                        <?php
                        printf(
                            ': %s',
                            esc_html($this->formatAreas($conflict['areas']))
                        );
                        ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    private function knownConflicts(): array
    {
        if (!function_exists('apply_filters')) {
            return self::KNOWN_CONFLICTS;
        }

        $conflicts = apply_filters('persian_kit_known_conflicts', self::KNOWN_CONFLICTS);

        return is_array($conflicts) ? $conflicts : self::KNOWN_CONFLICTS;
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

    private function formatAreas(array $areas): string
    {
        $areas = array_values(array_filter($areas, static fn ($area) => is_string($area) && $area !== ''));

        return implode(', ', $areas);
    }
}
