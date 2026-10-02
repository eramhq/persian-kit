<?php

namespace PersianKit\Modules\Multilingual;

use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;

defined('ABSPATH') || exit;

/**
 * The Integrations card for WPML. The work is done by ContentLanguage,
 * which every module asks, from before the modules boot.
 */
class WpmlModule extends AbstractModule
{
    public static function key(): string
    {
        return 'wpml';
    }

    public static function label(): string
    {
        return __('WPML', 'persian-kit');
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
            'name'    => 'WPML',
            // WPML is not on WordPress.org.
            'url'     => 'https://wpml.org/',
            'check'   => static fn (): bool => defined('ICL_SITEPRESS_VERSION'),
            'version' => static fn (): ?string => defined('ICL_SITEPRESS_VERSION') ? (string) ICL_SITEPRESS_VERSION : null,
        ]];
    }

    public function register(ServiceContainer $container): void
    {
    }

    public function boot(ServiceContainer $container): void
    {
    }
}
