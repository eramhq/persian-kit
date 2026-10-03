<?php

namespace PersianKit\Service\Import\Settings;

use PersianKit\Core\ModuleRegistry;
use PersianKit\Core\SettingsManager;
use PersianKit\Service\Import\ImportContext;
use PersianKit\Service\Import\LogRow;

defined('ABSPATH') || exit;

/**
 * Turns the ticked setting rows into Persian Kit settings.
 *
 * Merge rules, so importing two sources gives the same result in either
 * order and an import never undoes a choice made in Persian Kit:
 * - a boolean only turns on;
 * - a single value (the admin font) is set only while Persian Kit's is
 *   still the default;
 * - a province list is taken as is while Persian Kit allows every
 *   province, and otherwise added to Persian Kit's.
 *
 * Writes go through SettingsManager, so the settings sanitizer runs, and
 * each changed setting is logged for undo.
 */
class SettingsImporter
{
    public const TASK = 'settings';

    /** Modules that do more than the imported settings ask once turned on. */
    private const MORE_WHEN_ENABLED = ['digit_conversion' => true];

    private SettingsManager $settings;

    public function __construct(SettingsManager $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Review's rows: each with Persian Kit's current value, and "no change"
     * when it is already set.
     *
     * @param list<SettingRow> $rows The source's rows with their module rows (ImportRunner::settingRows()).
     * @return list<array<string, mixed>>
     */
    public function review(array $rows): array
    {
        $current = $this->settings->all();

        return array_map(function (SettingRow $row) use ($current): array {
            $planned = self::mergeAll($current, $this->defaults(), $row->changes);

            return $row->toArray() + [
                'current'   => $this->describe($row->changes, $current),
                'no_change' => $row->imports() && self::diff($current, $planned) === [],
            ];
        }, $rows);
    }

    /**
     * Rows that turn on a module the ticked rows' settings belong to, while
     * it is off; its settings would do nothing otherwise.
     *
     * @param list<SettingRow>                    $rows
     * @param array<string, array<string, mixed>> $current
     * @return list<SettingRow>
     */
    public function moduleRows(array $rows, array $current): array
    {
        $needed = [];
        $controlled = [];
        foreach ($rows as $row) {
            if (!$row->imports()) {
                continue;
            }
            foreach (array_keys($row->changes) as $path) {
                [$module, $key] = self::split($path);
                if ($key === 'enabled') {
                    // The source has its own row for the module itself.
                    $controlled[$module] = true;
                } elseif ($row->ticked && empty($current[$module]['enabled'])) {
                    $needed[$module] = true;
                }
            }
        }

        $labels = self::moduleLabels();
        $moduleRows = [];
        foreach (array_keys(array_diff_key($needed, $controlled)) as $module) {
            $label = $labels[$module] ?? $module;
            $more = self::MORE_WHEN_ENABLED[$module] ?? null;
            $moduleRows[] = new SettingRow(
                'module:' . $module,
                /* translators: %s: Persian Kit module name, such as WooCommerce. */
                sprintf(__('Settings under %s', 'persian-kit'), $label),
                SettingStatus::Automatic,
                $label,
                [$module . '.enabled' => true],
                $more !== null
                    ? __('It is off, so the settings above take effect only once it is on. Turning it on also converts digits in titles and content.', 'persian-kit')
                    : __('It is off, so the settings imported into it would do nothing.', 'persian-kit'),
                $more === null
            );
        }

        return $moduleRows;
    }

    /**
     * Applies the chosen rows and logs each setting it changed.
     *
     * @param list<SettingRow> $rows The rows to import.
     * @return array<string, array{old: mixed, new: mixed}> What changed, by path.
     */
    public function apply(array $rows, ImportContext $context): array
    {
        $current = $this->settings->all();
        $merged = $current;
        foreach ($rows as $row) {
            if ($row->imports()) {
                $merged = self::mergeAll($merged, $this->defaults(), $row->changes);
            }
        }

        $changes = self::diff($current, $merged);
        if ($changes === []) {
            return [];
        }

        if (!$context->dryRun) {
            $modules = [];
            foreach (array_keys($changes) as $path) {
                [$module] = self::split($path);
                $modules[$module] = $merged[$module];
            }
            $this->settings->updateModules($modules);

            // What the sanitizer kept.
            $stored = $this->settings->all();
            foreach ($changes as $path => $change) {
                [$module, $key] = self::split($path);
                $changes[$path]['new'] = $stored[$module][$key] ?? null;
                $context->log->changed($context, self::TASK, 'setting', 0, $path, $change['old'], $changes[$path]['new']);
            }
        }

        return $changes;
    }

    /**
     * Sets one logged setting back while it still holds what the import gave it.
     */
    public function undo(LogRow $row): bool
    {
        [$module, $key] = self::split($row->field);
        $values = $this->settings->module($module);

        if (!is_array($values) || ($values[$key] ?? null) !== $row->newValue) {
            return false;
        }

        $values[$key] = $row->oldValue;
        $this->settings->updateModule($module, $values);

        return true;
    }

    /**
     * The merge rules, for every change of one row.
     *
     * @param array<string, array<string, mixed>> $settings By module.
     * @param array<string, array<string, mixed>> $defaults By module.
     * @param array<string, mixed>                $changes  By path.
     * @return array<string, array<string, mixed>>
     */
    public static function mergeAll(array $settings, array $defaults, array $changes): array
    {
        foreach ($changes as $path => $value) {
            [$module, $key] = self::split($path);
            $settings[$module] ??= $defaults[$module] ?? [];
            $settings[$module][$key] = self::merge($settings[$module][$key] ?? null, $defaults[$module][$key] ?? null, $value);
        }

        return $settings;
    }

    /**
     * One setting's merge rule, decided by its default's type.
     */
    public static function merge(mixed $current, mixed $default, mixed $incoming): mixed
    {
        if (is_bool($default) || is_bool($incoming)) {
            return (bool) $current || (bool) $incoming;
        }

        if (is_array($default) || is_array($incoming)) {
            $current = is_array($current) ? array_values($current) : [];
            $incoming = is_array($incoming) ? array_values($incoming) : [];

            if ($incoming === []) {
                return $current;
            }

            // An empty list allows everything; the source's list is the
            // narrower choice. Otherwise both lists, in one order.
            $merged = $current === [] ? $incoming : array_values(array_unique(array_merge($current, $incoming)));
            sort($merged);

            return $merged;
        }

        return $current === null || $current === $default ? $incoming : $current;
    }

    /**
     * @param array<string, array<string, mixed>> $before
     * @param array<string, array<string, mixed>> $after
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public static function diff(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $module => $values) {
            foreach ($values as $key => $value) {
                $old = $before[$module][$key] ?? null;
                if ($old !== $value) {
                    $changes[$module . '.' . $key] = ['old' => $old, 'new' => $value];
                }
            }
        }

        return $changes;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function split(string $path): array
    {
        $parts = explode('.', $path, 2);

        return [$parts[0], $parts[1] ?? 'enabled'];
    }

    /**
     * Persian Kit's current value of each setting a row changes, for Review.
     *
     * @param array<string, mixed>                $changes
     * @param array<string, array<string, mixed>> $current
     */
    private function describe(array $changes, array $current): string
    {
        $parts = [];
        foreach (array_keys($changes) as $path) {
            [$module, $key] = self::split($path);
            $value = $current[$module][$key] ?? null;

            if (is_bool($value) || $value === null) {
                $parts[] = $value ? __('On', 'persian-kit') : __('Off', 'persian-kit');
            } elseif (is_array($value)) {
                $parts[] = $value === []
                    ? __('All provinces', 'persian-kit')
                    /* translators: %s: number of provinces. */
                    : sprintf(_n('%s province', '%s provinces', count($value), 'persian-kit'), number_format_i18n(count($value)));
            } else {
                $parts[] = (string) $value;
            }
        }

        return implode(' / ', array_unique($parts));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function defaults(): array
    {
        $defaults = [];
        foreach (ModuleRegistry::MODULES as $module) {
            $defaults[$module::key()] = $module::defaults();
        }

        return $defaults;
    }

    /**
     * @return array<string, string>
     */
    private static function moduleLabels(): array
    {
        $labels = [];
        foreach (ModuleRegistry::MODULES as $module) {
            $labels[$module::key()] = $module::label();
        }

        return $labels;
    }
}
