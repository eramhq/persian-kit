<?php

namespace PersianKit\Modules\Multilingual;

use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;

defined('ABSPATH') || exit;

/**
 * The Integrations card for Polylang. The work is done by ContentLanguage,
 * which every module asks, from before the modules boot.
 */
class PolylangModule extends AbstractModule
{
    public static function key(): string
    {
        return 'polylang';
    }

    public static function label(): string
    {
        return __('Polylang', 'persian-kit');
    }

    public static function description(): string
    {
        return __('Dates, digits and Persian writing tools follow each page\'s language.', 'persian-kit');
    }

    public static function category(): ?string
    {
        return 'compat';
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => true,
        ];
    }

    /**
     * @return list<array{name: string, check: callable(): bool, version?: callable(): ?string, minVersion?: string, slug?: string, url?: string}>
     */
    public function requiredPlugins(): array
    {
        return [[
            'name'    => 'Polylang',
            'slug'    => 'polylang',
            'check'   => static fn (): bool => defined('POLYLANG_VERSION'),
            'version' => static fn (): ?string => defined('POLYLANG_VERSION') ? (string) POLYLANG_VERSION : null,
        ]];
    }

    public function register(ServiceContainer $container): void
    {
    }

    public function boot(ServiceContainer $container): void
    {
    }
}
