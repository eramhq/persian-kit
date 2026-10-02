<?php

namespace PersianKit\Contracts;

use PersianKit\Container\ServiceContainer;

defined('ABSPATH') || exit;

interface ModuleInterface
{
    public static function key(): string;

    public static function label(): string;

    public static function description(): string;

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array;

    public function isEnabled(): bool;

    /**
     * View path (relative to views/) for the module's extra settings, or null.
     */
    public function settingsView(): ?string;

    /**
     * Where an integration's card goes: 'forms', 'commerce' or 'compat'.
     * Null for the modules that need no other plugin.
     */
    public static function category(): ?string;

    /**
     * The plugins this module needs, checked at runtime. The first is the
     * plugin the module integrates with; any others are add-ons it also
     * needs, such as a Pro version.
     *
     * check:      true while the plugin is active.
     * version:    the installed version, or null when it is unknown.
     * minVersion: the oldest version the module works with.
     * slug:       its WordPress.org slug, for the link on its card.
     *
     * @return list<array{name: string, check: callable(): bool, version?: callable(): ?string, minVersion?: string, slug?: string}>
     */
    public function requiredPlugins(): array;

    /**
     * True when every required plugin is active and new enough.
     */
    public function isAvailable(): bool;

    /**
     * Why the module cannot run, or null when it can. The code is one of
     * AbstractModule::REASON_*.
     *
     * @return array{code: string, message: string}|null
     */
    public function unavailableReason(): ?array;

    /**
     * Names of the required plugins that are not active.
     *
     * @return list<string>
     */
    public function inactivePlugins(): array;

    /**
     * Forms that use this integration's fields, for the warning shown while
     * it is off. Empty for modules that add no fields.
     *
     * @return list<array{title: string, url: string}>
     */
    public function formsUsingFields(): array;

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function sanitizeSettings(array $values): array;

    public function register(ServiceContainer $container): void;

    public function boot(ServiceContainer $container): void;

    /**
     * Runs instead of boot() while the module is available but turned off.
     * Integrations use it to keep content made with them working, such as
     * rendering their form fields as plain text inputs.
     */
    public function bootDisabled(ServiceContainer $container): void;
}
