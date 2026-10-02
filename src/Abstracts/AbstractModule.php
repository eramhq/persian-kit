<?php

namespace PersianKit\Abstracts;

use PersianKit\Contracts\ModuleInterface;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;

defined('ABSPATH') || exit;

abstract class AbstractModule implements ModuleInterface
{
    /** The plugin is not active. */
    public const REASON_INACTIVE = 'inactive';

    /** The plugin is active but older than the module needs. */
    public const REASON_OUTDATED = 'outdated';

    /** The plugin is active but an add-on it needs, such as its Pro version, is not. */
    public const REASON_MISSING = 'missing';

    protected SettingsManager $settings;

    public function __construct(SettingsManager $settings)
    {
        $this->settings = $settings;
    }

    public static function label(): string
    {
        return static::key();
    }

    public static function description(): string
    {
        return '';
    }

    public function isEnabled(): bool
    {
        return (bool) $this->setting('enabled', false);
    }

    protected function setting(string $key, mixed $default = null): mixed
    {
        $defaults = static::defaults();
        $fallback = array_key_exists($key, $defaults) ? $defaults[$key] : $default;

        return $this->settings->module(static::key(), $key, $fallback);
    }

    public function settingsView(): ?string
    {
        return null;
    }

    public static function category(): ?string
    {
        return null;
    }

    /**
     * @return list<array{name: string, check: callable(): bool, version?: callable(): ?string, minVersion?: string, slug?: string, url?: string}>
     */
    public function requiredPlugins(): array
    {
        return [];
    }

    public function isAvailable(): bool
    {
        return $this->unavailableReason() === null;
    }

    /**
     * The first required plugin missing counts as "not active"; a missing
     * add-on (a later entry) or a version older than minVersion means the
     * plugin is there but cannot be used.
     *
     * @return array{code: string, message: string}|null
     */
    public function unavailableReason(): ?array
    {
        foreach ($this->requiredPlugins() as $index => $plugin) {
            if (!($plugin['check'])()) {
                return $index === 0
                    ? [
                        'code'    => self::REASON_INACTIVE,
                        /* translators: %s: plugin name, such as "Contact Form 7". */
                        'message' => sprintf(__('%s is not active on this site.', 'persian-kit'), $plugin['name']),
                    ]
                    : [
                        'code'    => self::REASON_MISSING,
                        /* translators: %s: plugin name, such as "Elementor Pro". */
                        'message' => sprintf(__('Needs %s.', 'persian-kit'), $plugin['name']),
                    ];
            }

            $installed = isset($plugin['version']) ? ($plugin['version'])() : null;
            if (isset($plugin['minVersion']) && $installed !== null && version_compare($installed, $plugin['minVersion'], '<')) {
                return [
                    'code'    => self::REASON_OUTDATED,
                    'message' => sprintf(
                        /* translators: 1: plugin name, 2: oldest version that works, 3: version on this site. */
                        __('Needs %1$s %2$s or newer (this site has %3$s).', 'persian-kit'),
                        $plugin['name'],
                        $plugin['minVersion'],
                        $installed
                    ),
                ];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function inactivePlugins(): array
    {
        $inactive = [];
        foreach ($this->requiredPlugins() as $plugin) {
            if (!($plugin['check'])()) {
                $inactive[] = $plugin['name'];
            }
        }

        return $inactive;
    }

    /**
     * @return list<array{title: string, url: string}>
     */
    public function formsUsingFields(): array
    {
        return [];
    }

    public function bootDisabled(ServiceContainer $container): void
    {
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function sanitizeSettings(array $values): array
    {
        $defaults = static::defaults();
        $sanitized = array_replace($defaults, array_intersect_key($values, $defaults));

        // Form checkboxes post '0' and '1'; store real booleans.
        foreach ($defaults as $key => $default) {
            if (is_bool($default)) {
                $sanitized[$key] = (bool) $sanitized[$key];
            }
        }

        return $sanitized;
    }
}
