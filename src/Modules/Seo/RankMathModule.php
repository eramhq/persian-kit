<?php

namespace PersianKit\Modules\Seo;

use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;

defined('ABSPATH') || exit;

/**
 * Keeps what Rank Math gives search engines valid for an Iranian store.
 * Its dates are in formats Persian Kit leaves Gregorian (W3C and ATOM),
 * and its sitemaps use DateTime, which Persian Kit never changes.
 */
class RankMathModule extends AbstractModule
{
    public static function key(): string
    {
        return 'rank_math';
    }

    public static function label(): string
    {
        return __('Rank Math SEO', 'persian-kit');
    }

    public static function description(): string
    {
        return __('Prices in rials in the schema and Open Graph tags search engines read. Dates in schema, Open Graph and sitemaps stay Gregorian.', 'persian-kit');
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
            'name'    => 'Rank Math SEO',
            'slug'    => 'seo-by-rank-math',
            'check'   => fn (): bool => $this->supportsRankMath(),
            'version' => static fn (): ?string => defined('RANK_MATH_VERSION') ? (string) RANK_MATH_VERSION : null,
        ]];
    }

    public function register(ServiceContainer $container): void
    {
        $container->register(RankMathPrices::class, function () {
            return new RankMathPrices();
        });
    }

    /**
     * Without WooCommerce no price is Iranian, so this changes nothing.
     */
    public function boot(ServiceContainer $container): void
    {
        $container->get(RankMathPrices::class)->register();
    }

    /**
     * Compatibility has no switch: it works whenever Rank Math is active.
     */
    public function bootDisabled(ServiceContainer $container): void
    {
        $this->boot($container);
    }

    protected function supportsRankMath(): bool
    {
        return defined('RANK_MATH_VERSION');
    }
}
