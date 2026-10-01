<?php

namespace PersianKit\Core;

use PersianKit\Contracts\ModuleInterface;

defined('ABSPATH') || exit;

/**
 * Registers the settings option with the Settings API, so the settings form
 * saves through options.php and every write, from any code, is sanitized.
 */
class SettingsRegistrar
{
    public const GROUP = 'persian_kit';

    /** @var list<ModuleInterface> */
    private array $modules;

    /**
     * @param list<ModuleInterface> $modules
     */
    public function __construct(array $modules)
    {
        $this->modules = $modules;
    }

    public function register(): void
    {
        add_action('init', [$this, 'registerSetting']);
    }

    public function registerSetting(): void
    {
        register_setting(self::GROUP, SettingsManager::OPTION_KEY, [
            'type'              => 'array',
            'default'           => [],
            'show_in_rest'      => false,
            'sanitize_callback' => [$this, 'sanitize'],
        ]);
    }

    /**
     * Each module's values are merged over what is stored (and the module's
     * defaults) and sanitized by the module. A module missing from the input
     * keeps its stored values, and keys that are not modules are dropped.
     *
     * Idempotent: core sanitizes twice when the option is first added.
     *
     * @return array<string, array<string, mixed>>
     */
    public function sanitize(mixed $input): array
    {
        $stored = get_option(SettingsManager::OPTION_KEY, []);
        $stored = is_array($stored) ? array_filter($stored, 'is_array') : [];

        if (!is_array($input)) {
            return $stored;
        }

        $sanitized = [];

        foreach ($this->modules as $module) {
            $key = $module::key();

            if (isset($input[$key]) && is_array($input[$key])) {
                $sanitized[$key] = $module->sanitizeSettings(
                    array_replace($module::defaults(), $stored[$key] ?? [], $input[$key])
                );
            } elseif (isset($stored[$key])) {
                $sanitized[$key] = $stored[$key];
            }
        }

        return $sanitized;
    }
}
