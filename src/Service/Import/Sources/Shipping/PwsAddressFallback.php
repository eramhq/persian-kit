<?php

namespace PersianKit\Service\Import\Sources\Shipping;

use PersianKit\Service\Import\Iran\AddressResult;
use PersianKit\Service\Import\Iran\IranAddressResolver;
use PersianKit\Service\Import\Iran\IranProvinces;

defined('ABSPATH') || exit;

/**
 * Addresses the shipping plugin saved as ids show as names while it is
 * inactive: before the switch, while it runs, and for the rows the switch
 * had to leave alone. Nothing is saved.
 */
class PwsAddressFallback
{
    private ?IranAddressResolver $resolver = null;

    /** @var array<string, AddressResult> */
    private array $resolved = [];

    public function register(): void
    {
        // After WooCommerce fills the replacements.
        add_filter('woocommerce_formatted_address_replacements', [$this, 'filterReplacements'], 20, 2);
    }

    /**
     * @param mixed $replace Replacements by tag, as WooCommerce passes them.
     * @param mixed $args    The address.
     * @return mixed
     */
    public function filterReplacements(mixed $replace, mixed $args): mixed
    {
        // While it runs, it shows its own names.
        if (!is_array($replace) || !is_array($args) || defined('PWS_VERSION') || ($args['country'] ?? '') !== 'IR') {
            return $replace;
        }

        $state = trim((string) ($args['state'] ?? ''));
        $city = trim((string) ($args['city'] ?? ''));
        $needsState = $state !== '' && !IranProvinces::isCode($state);
        if (!$needsState && !ctype_digit($city)) {
            return $replace;
        }

        $key = $state . '|' . $city;
        $this->resolved[$key] ??= $this->resolver()->resolve($state, $city);
        $result = $this->resolved[$key];
        if ($result->outcome === AddressResult::ATTENTION) {
            return $replace;
        }

        $stateName = $result->state !== '' ? IranProvinces::name($result->state) : '';
        if ($needsState && $stateName !== '') {
            $replace['{state}'] = $stateName;
            $replace['{state_upper}'] = $stateName;
        }
        if ($result->city !== $city) {
            $replace['{city}'] = $result->city;
            $replace['{city_upper}'] = $result->city;
        }

        return $replace;
    }

    private function resolver(): IranAddressResolver
    {
        return $this->resolver ??= IranAddressResolver::forSite();
    }
}
