<?php

namespace PersianKit\Core;

defined('ABSPATH') || exit;

class SettingsManager
{
    private const OPTION_KEY = 'persian_kit_settings';

    private ?array $cache = null;

    public function all(): array
    {
        return $this->load();
    }

    public function module(string $moduleKey, ?string $key = null, mixed $default = null): mixed
    {
        $settings = $this->load();
        $moduleSettings = $settings[$moduleKey] ?? [];

        if ($key === null) {
            return $moduleSettings;
        }

        return $moduleSettings[$key] ?? $default;
    }

    public function updateModule(string $moduleKey, array $values): void
    {
        $settings = $this->load();
        $settings[$moduleKey] = $values;
        $this->save($settings);
    }

    public function setDefaults(string $moduleKey, array $defaults): void
    {
        $settings = $this->load();

        if (isset($settings[$moduleKey])) {
            return;
        }

        $settings[$moduleKey] = $defaults;
        $this->save($settings);
    }

    private function load(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $this->cache = get_option(self::OPTION_KEY, []);

        if (!is_array($this->cache)) {
            $this->cache = [];
        }

        return $this->cache;
    }

    private function save(array $settings): void
    {
        update_option(self::OPTION_KEY, $settings, true);
        $this->cache = $settings;
    }
}
