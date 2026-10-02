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
        return __('WooCommerce', 'persian-kit');
    }

    public static function description(): string
    {
        return __('Jalali dates in the shop admin and emails, and checkout fields for Iran.', 'persian-kit');
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled'            => true,
            'checkout_normalize' => true,
            'checkout_validate'  => true,
            'national_id'        => NationalIdField::OFF,
            'city_select'        => false,
        ];
    }

    public function settingsView(): ?string
    {
        return 'admin/partials/woocommerce-settings';
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function sanitizeSettings(array $values): array
    {
        $nationalId = $values['national_id'] ?? NationalIdField::OFF;

        return [
            'enabled'            => !empty($values['enabled']),
            'checkout_normalize' => !empty($values['checkout_normalize']),
            'checkout_validate'  => !empty($values['checkout_validate']),
            'national_id'        => in_array($nationalId, NationalIdField::MODES, true) ? $nationalId : NationalIdField::OFF,
            'city_select'        => !empty($values['city_select']),
        ];
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
        $container->register(CheckoutInputNormalizer::class, function () {
            return new CheckoutInputNormalizer();
        });
        $container->register(CheckoutValidator::class, function () {
            return new CheckoutValidator();
        });
        $container->register(NationalIdField::class, function () {
            return new NationalIdField((string) $this->setting('national_id'));
        });
        $container->register(CityField::class, function () {
            return new CityField(PERSIAN_KIT_DIR . CityField::DATA_FILE);
        });
    }

    public function boot(ServiceContainer $container): void
    {
        if (!$this->supportsWooCommerce()) {
            return;
        }

        $container->get(WooDateDisplayFilter::class)->register();

        // Checkout runs on the front end, in the Store API and through admin-ajax.
        if ($this->setting('checkout_normalize')) {
            $container->get(CheckoutInputNormalizer::class)->register();
        }

        if ($this->setting('checkout_validate')) {
            $container->get(CheckoutValidator::class)->register();
        }

        // Also when the field is off, so national IDs already saved on orders stay visible.
        $container->get(NationalIdField::class)->register();

        if ($this->setting('city_select')) {
            $container->get(CityField::class)->register();
        }

        // Order screens, product and coupon edit screens, and the variations
        // save through admin-ajax.
        if (is_admin()) {
            $container->get(WooOrderMonthFilter::class)->register();
            $container->get(WooAdminDateFields::class)->register();
            $container->get(WooPostedDateNormalizer::class)->register();
        }
    }

    private function supportsWooCommerce(): bool
    {
        return class_exists('WooCommerce') || function_exists('wc_get_orders');
    }
}
