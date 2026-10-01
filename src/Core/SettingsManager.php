<?php

namespace PersianKit\Core;

defined('ABSPATH') || exit;

class SettingsManager
{
    public const OPTION_KEY = 'persian_kit_settings';

    /** @var array<string, array<string, mixed>>|null */
    private ?array $cache = null;

    /** @var array<string, array<string, mixed>> */
    private array $defaults = [];

    /**
     * Stored values are read on top of these, so a module or key added in an
     * update reports (and runs with) its default until the user saves.
     *
     * @param array<string, mixed> $defaults
     */
    public function registerDefaults(string $moduleKey, array $defaults): void
    {
        $this->defaults[$moduleKey] = $defaults;
    }

    /**
     * Every module's settings, merged with its registered defaults.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $settings = [];

        foreach (array_keys($this->defaults + $this->load()) as $moduleKey) {
            $settings[$moduleKey] = $this->module($moduleKey);
        }

        return $settings;
    }

    public function module(string $moduleKey, ?string $key = null, mixed $default = null): mixed
    {
        $moduleSettings = array_replace($this->defaults[$moduleKey] ?? [], $this->load()[$moduleKey] ?? []);

        if ($key === null) {
            return $moduleSettings;
        }

        return $moduleSettings[$key] ?? $default;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function updateModule(string $moduleKey, array $values): void
    {
        $this->updateModules([$moduleKey => $values]);
    }

    /**
     * Replace several modules' settings in one write.
     *
     * @param array<string, array<string, mixed>> $valuesByModule
     */
    public function updateModules(array $valuesByModule): void
    {
        $settings = $this->load();

        foreach ($valuesByModule as $moduleKey => $values) {
            $settings[$moduleKey] = $values;
        }

        $this->save($settings);
    }

    /**
     * @param array<string, mixed> $defaults
     */
    public function setDefaults(string $moduleKey, array $defaults): void
    {
        $settings = $this->load();

        if (isset($settings[$moduleKey])) {
            return;
        }

        $settings[$moduleKey] = $defaults;
        $this->save($settings);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function load(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $stored = get_option(self::OPTION_KEY, []);

        $this->cache = is_array($stored) ? array_filter($stored, 'is_array') : [];

        return $this->cache;
    }

    /**
     * @param array<string, array<string, mixed>> $settings
     */
    private function save(array $settings): void
    {
        update_option(self::OPTION_KEY, $settings, true);
        $this->cache = $settings;
    }
}
