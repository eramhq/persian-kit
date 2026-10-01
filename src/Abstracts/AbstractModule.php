<?php

namespace PersianKit\Abstracts;

use PersianKit\Contracts\ModuleInterface;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;

defined('ABSPATH') || exit;

abstract class AbstractModule implements ModuleInterface
{
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
