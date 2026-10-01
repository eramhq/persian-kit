<?php

namespace PersianKit\Modules\WooCommerce;

use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;

defined('ABSPATH') || exit;

class WooCommerceModule extends AbstractModule
{
    public static function key(): string
    {
        return 'woocommerce';
    }

    public static function label(): string
    {
        return __('WooCommerce Support', 'persian-kit');
    }

    public static function description(): string
    {
        return __('Jalali date fields and month filters on WooCommerce order, product and coupon screens, and Jalali dates on order pages and emails customers see. Does nothing unless WooCommerce is active.', 'persian-kit');
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return ['enabled' => true];
    }

    public function register(ServiceContainer $container): void
    {
        $container->register(WooOrderMonthFilter::class, function () {
            return new WooOrderMonthFilter();
        });
        $container->register(WooAdminDateFields::class, function () {
            return new WooAdminDateFields();
        });
        $container->register(WooPostedDateNormalizer::class, function () {
            return new WooPostedDateNormalizer();
        });
        $container->register(WooDateDisplayFilter::class, function () {
            return new WooDateDisplayFilter();
        });
    }

    public function boot(ServiceContainer $container): void
    {
        if (!$this->supportsWooCommerce()) {
            return;
        }

        $container->get(WooOrderMonthFilter::class)->register();
        $container->get(WooAdminDateFields::class)->register();
        $container->get(WooPostedDateNormalizer::class)->register();
        $container->get(WooDateDisplayFilter::class)->register();
    }

    private function supportsWooCommerce(): bool
    {
        return class_exists('WooCommerce') || function_exists('wc_get_orders');
    }
}
