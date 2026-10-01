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
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function sanitizeSettings(array $values): array;

    public function register(ServiceContainer $container): void;

    public function boot(ServiceContainer $container): void;
}
