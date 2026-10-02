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
        return __('Jalali dates in the shop admin and emails, checkout fields for Iran, and thousand toman and thousand rial currencies.', 'persian-kit');
    }

    public static function category(): ?string
    {
        return 'commerce';
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
            'allowed_states'     => [],
            'dates_admin'        => true,
        ];
    }

    /**
     * @return list<array{name: string, check: callable(): bool, version?: callable(): ?string, minVersion?: string, slug?: string}>
     */
    public function requiredPlugins(): array
    {
        return [[
            'name'    => __('WooCommerce', 'persian-kit'),
            'slug'    => 'woocommerce',
            'check'   => fn (): bool => $this->supportsWooCommerce(),
            'version' => static fn (): ?string => defined('WC_VERSION') ? (string) WC_VERSION : null,
        ]];
    }

    /**
     * The section cards on the WooCommerce tab, by anchor. A section shows
     * once it has an option; Emails joins them with its first one.
     *
     * @return array<string, array{title: string, description: string}>
     */
    public static function sections(): array
    {
        return [
            'checkout' => [
                'title'       => __('Checkout and addresses', 'persian-kit'),
                'description' => __('What customers type at checkout, and fields for Iran.', 'persian-kit'),
            ],
            'prices'   => [
                'title'       => __('Prices and currency', 'persian-kit'),
                'description' => '',
            ],
            'dates'    => [
                'title'       => __('Dates', 'persian-kit'),
                'description' => __('Dates on orders and in emails follow Jalali dates on the Display tab.', 'persian-kit'),
            ],
        ];
    }

    /**
     * The section cards; the tab puts the module's own card above them.
     */
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
            'allowed_states'     => ProvinceLimit::sanitizeCodes($values['allowed_states'] ?? [], self::provinces()),
            'dates_admin'        => !empty($values['dates_admin']),
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
        $container->register(OrderNumberInput::class, function () {
            return new OrderNumberInput();
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
        $container->register(ProvinceLimit::class, function () {
            return new ProvinceLimit($this->allowedStates());
        });
        $container->register(IranianCurrencies::class, function () {
            return new IranianCurrencies();
        });
        $container->register(SchemaPrices::class, function () {
            return new SchemaPrices();
        });

        // Now, not at boot: WooCommerce keeps its currency list for the rest
        // of the request the first time it is read, which can be before
        // after_setup_theme. Also while the module is off, so a store priced
        // in thousand toman keeps its currency.
        $container->get(IranianCurrencies::class)->register();
    }

    public function boot(ServiceContainer $container): void
    {
        $container->get(SchemaPrices::class)->register();
        $container->get(WooDateDisplayFilter::class)->register();

        // Checkout runs on the front end, in the Store API and through admin-ajax;
        // order numbers are also typed into the tracking form and order search.
        if ($this->setting('checkout_normalize')) {
            $container->get(CheckoutInputNormalizer::class)->register();
            $container->get(OrderNumberInput::class)->register();
        }

        if ($this->setting('checkout_validate')) {
            $container->get(CheckoutValidator::class)->register();
        }

        // Also when the field is off, so national IDs already saved on orders stay visible.
        $container->get(NationalIdField::class)->register();

        if ($this->setting('city_select')) {
            $container->get(CityField::class)->register();
        }

        if ($this->allowedStates() !== []) {
            $container->get(ProvinceLimit::class)->register();
        }

        // Order screens, product and coupon edit screens, and the variations
        // save through admin-ajax.
        if ($this->setting('dates_admin') && is_admin()) {
            $container->get(WooOrderMonthFilter::class)->register();
            $container->get(WooAdminDateFields::class)->register();
            $container->get(WooPostedDateNormalizer::class)->register();
        }
    }

    /**
     * A store priced in thousand toman keeps valid prices for search engines.
     */
    public function bootDisabled(ServiceContainer $container): void
    {
        $container->get(SchemaPrices::class)->register();
    }

    /**
     * Iran's provinces by WooCommerce code, with WooCommerce's (translated)
     * names: all of them in the admin, the listed ones on the storefront.
     * Empty without WooCommerce.
     *
     * @return array<string, string>
     */
    public static function provinces(): array
    {
        // WooCommerce creates its country list on init.
        if (!function_exists('WC') || !did_action('woocommerce_init')) {
            return [];
        }

        $states = WC()->countries->get_states(ProvinceLimit::COUNTRY);

        return is_array($states) ? array_filter($states, 'is_string') : [];
    }

    /**
     * @return list<string>
     */
    private function allowedStates(): array
    {
        return ProvinceLimit::sanitizeCodes($this->setting('allowed_states'), []);
    }

    protected function supportsWooCommerce(): bool
    {
        return class_exists('WooCommerce') || function_exists('wc_get_orders');
    }
}
