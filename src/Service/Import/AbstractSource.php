<?php

namespace PersianKit\Service\Import;

use PersianKit\Core\SettingsManager;

defined('ABSPATH') || exit;

abstract class AbstractSource implements Source
{
    /**
     * True while the plugin runs here: active by its file, or its code is
     * loaded under another folder name.
     */
    public function isActive(): bool
    {
        if ($this->isLoaded()) {
            return true;
        }

        self::loadPluginFunctions();

        return is_plugin_active($this->pluginFile()) || $this->isNetworkActive();
    }

    public function isNetworkActive(): bool
    {
        if (!is_multisite()) {
            return false;
        }

        self::loadPluginFunctions();

        return is_plugin_active_for_network($this->pluginFile());
    }

    public function readyToImport(): ?string
    {
        if (!$this->isActive()) {
            return null;
        }

        /* translators: %s: plugin name, such as Parsi Date. */
        return sprintf(__('Deactivate %s first.', 'persian-kit'), $this->name());
    }

    /**
     * WordPress's own deactivate link for the plugin, or null when this
     * user can't deactivate it. Persian Kit never deactivates a plugin
     * itself.
     */
    public function deactivateUrl(): ?string
    {
        $file = $this->pluginFile();

        if ($this->isNetworkActive()) {
            if (!current_user_can('manage_network_plugins')) {
                return null;
            }

            return self::deactivateLink(network_admin_url('plugins.php'), $file);
        }

        if (!current_user_can('deactivate_plugin', $file)) {
            return null;
        }

        return self::deactivateLink(admin_url('plugins.php'), $file);
    }

    /**
     * The link with its nonce, unescaped: wp_nonce_url() gives &amp;, for HTML.
     */
    private static function deactivateLink(string $base, string $file): string
    {
        return add_query_arg([
            'action'   => 'deactivate',
            'plugin'   => rawurlencode($file),
            '_wpnonce' => wp_create_nonce('deactivate-plugin_' . $file),
        ], $base);
    }

    public function settingRows(SettingsManager $settings): array
    {
        return [];
    }

    public function tasks(): array
    {
        return [];
    }

    public function checklist(array $snapshot): array
    {
        return [];
    }

    public function snapshot(): array
    {
        return [];
    }

    /**
     * How plugins store "on": true, 1, '1', 'on', 'yes' or 'enable'.
     */
    public static function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value == 1;
        }

        return is_string($value) && in_array(strtolower(trim($value)), ['1', 'on', 'yes', 'enable', 'enabled', 'true'], true);
    }

    /**
     * An option as an array; anything else is empty.
     *
     * @return array<mixed>
     */
    protected static function arrayOption(string $name): array
    {
        $value = get_option($name, []);

        return is_array($value) ? $value : [];
    }

    /**
     * The plugin's code is loaded, whatever its folder is called.
     */
    protected function isLoaded(): bool
    {
        return false;
    }

    private static function loadPluginFunctions(): void
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }
}
