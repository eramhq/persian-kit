<?php

namespace PersianKit\Service\Import;

use PersianKit\Core\SettingsManager;
use PersianKit\Service\Import\Settings\SettingRow;

defined('ABSPATH') || exit;

/**
 * A plugin a site can switch from to Persian Kit. Everything here reads the
 * data the plugin left in the database, so it works whether the plugin is
 * active, inactive or deleted. Nothing here writes another plugin's data.
 */
interface Source
{
    /** The plugin's WordPress.org slug, such as wp-parsidate. */
    public function key(): string;

    public function name(): string;

    /** The plugin's main file, relative to the plugins folder. */
    public function pluginFile(): string;

    public function isActive(): bool;

    public function isNetworkActive(): bool;

    /** True while the plugin left settings or data behind. */
    public function hasData(): bool;

    /**
     * Null when the import can run: the plugin is inactive, or it only does
     * what Persian Kit does not. Otherwise what to do first.
     */
    public function readyToImport(): ?string;

    /**
     * Its settings next to Persian Kit's, for Review.
     *
     * @return list<SettingRow>
     */
    public function settingRows(SettingsManager $settings): array;

    /**
     * Its data to convert, in the order the import runs them.
     *
     * @return list<Task>
     */
    public function tasks(): array;

    /**
     * What to sort out before deactivating it.
     *
     * @param array<string, mixed> $snapshot What Review saved while it was active.
     * @return list<ChecklistItem>
     */
    public function checklist(array $snapshot): array;

    /**
     * What WordPress or the plugin may lose once it is inactive, saved into
     * the job while it is still active.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array;
}
