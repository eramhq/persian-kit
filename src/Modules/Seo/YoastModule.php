<?php

namespace PersianKit\Modules\Seo;

use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;

defined('ABSPATH') || exit;

/**
 * Keeps what Yoast SEO gives search engines valid for an Iranian store.
 * Its dates (schema, Open Graph, sitemaps) are made with DateTime, which
 * Persian Kit never changes, so they stay Gregorian.
 */
class YoastModule extends AbstractModule
{
    public static function key(): string
    {
        return 'yoast';
    }

    public static function label(): string
    {
        return __('Yoast SEO', 'persian-kit');
    }

    public static function description(): string
    {
        return __('Prices in rials in the schema search engines read. Dates in schema, Open Graph and sitemaps stay Gregorian.', 'persian-kit');
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
     * @return list<array{name: string, check: callable(): bool, version?: callable(): ?string, minVersion?: string, slug?: string}>
     */
    public function requiredPlugins(): array
    {
        return [[
            'name'    => 'Yoast SEO',
            'slug'    => 'wordpress-seo',
            'check'   => fn (): bool => $this->supportsYoast(),
            'version' => static fn (): ?string => defined('WPSEO_VERSION') ? (string) WPSEO_VERSION : null,
        ]];
    }

    public function register(ServiceContainer $container): void
    {
        $container->register(YoastPrices::class, function () {
            return new YoastPrices();
        });
    }

    /**
     * Without WooCommerce no price is Iranian, so this changes nothing.
     */
    public function boot(ServiceContainer $container): void
    {
        $container->get(YoastPrices::class)->register();
    }

    /**
     * Compatibility has no switch: it works whenever Yoast SEO is active.
     */
    public function bootDisabled(ServiceContainer $container): void
    {
        $this->boot($container);
    }

    protected function supportsYoast(): bool
    {
        return defined('WPSEO_VERSION');
    }
}
