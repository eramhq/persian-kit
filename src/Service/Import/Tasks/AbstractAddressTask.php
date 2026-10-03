<?php

namespace PersianKit\Service\Import\Tasks;

use PersianKit\Modules\WooCommerce\CityNames;
use PersianKit\Service\Import\AbstractTask;
use PersianKit\Service\Import\ImportContext;
use PersianKit\Service\Import\Iran\AddressResult;
use PersianKit\Service\Import\Iran\IranAddressResolver;

defined('ABSPATH') || exit;

/**
 * Converts the billing and shipping addresses of one kind of object.
 * Shared by Persian WooCommerce (its old two-letter codes only) and the
 * shipping plugin (its ids and names too).
 */
abstract class AbstractAddressTask extends AbstractTask
{
    public const TYPES = ['billing', 'shipping'];

    /** Only Persian WooCommerce's old two-letter codes. */
    protected bool $legacyOnly;

    private ?IranAddressResolver $resolver = null;

    public function __construct(bool $legacyOnly = false, ?IranAddressResolver $resolver = null)
    {
        $this->legacyOnly = $legacyOnly;
        $this->resolver = $resolver;
    }

    public function available(): ?string
    {
        return function_exists('WC') ? null : __('Activate WooCommerce to convert these addresses.', 'persian-kit');
    }

    protected function resolver(ImportContext $context): IranAddressResolver
    {
        return $this->resolver ??= IranAddressResolver::forSite($context->snapshot);
    }

    /**
     * What one address becomes: the fields to change, by name without the
     * billing_ or shipping_ prefix, or the reason to leave it.
     *
     * @param array{country: string, state: string, city: string, address_1: string, address_2: string, district: string} $address
     * @param array<string, mixed>                                                                                           $ids
     * @return array{changes: array<string, array{old: string, new: string}>, attention: list<string>}
     */
    protected function convert(ImportContext $context, array $address, array $ids = []): array
    {
        $none = ['changes' => [], 'attention' => []];
        if ($address['country'] !== 'IR') {
            return $none;
        }
        // Persian WooCommerce's switch leaves the shipping plugin's ids to its own.
        if ($this->legacyOnly && \PersianKit\Service\Import\Iran\IranProvinces::fromLegacy($address['state']) === null) {
            return $none;
        }

        $result = $this->resolver($context)->resolve($address['state'], $address['city'], $ids, $address['district']);
        if ($result->outcome === AddressResult::ATTENTION) {
            return ['changes' => [], 'attention' => [$result->reason]];
        }

        $changes = [];
        if ($result->state !== $address['state']) {
            $changes['state'] = ['old' => $address['state'], 'new' => $result->state];
        }
        if ($result->city !== $address['city']) {
            $changes['city'] = ['old' => $address['city'], 'new' => $result->city];
        }

        $attention = [];
        $district = $result->district;
        if ($district !== '' && !self::mentions($address['address_1'] . ' ' . $address['address_2'], $district)) {
            if ($address['address_2'] === '' && (bool) $context->option('district_line', true)) {
                $changes['address_2'] = ['old' => '', 'new' => $district];
            } else {
                $attention[] = sprintf(
                    /* translators: %s: district name. */
                    __('District %s is not in the address; address line 2 already holds something else.', 'persian-kit'),
                    $district
                );
            }
        }

        return ['changes' => $changes, 'attention' => $attention];
    }

    /**
     * A sample for Review: before and after of one address.
     *
     * @param array{country: string, state: string, city: string, address_1: string, address_2: string, district: string} $address
     * @param array<string, mixed>                                                                                           $ids
     * @return array{label: string, before: string, after: string}|null
     */
    protected function sample(ImportContext $context, string $label, array $address, array $ids = []): ?array
    {
        $converted = $this->convert($context, $address, $ids);
        if ($converted['changes'] === []) {
            return $converted['attention'] === [] ? null : ['label' => $label, 'before' => $address['state'] . ' / ' . $address['city'], 'after' => $converted['attention'][0]];
        }

        $after = $address;
        foreach ($converted['changes'] as $field => $change) {
            $after[$field] = $change['new'];
        }

        return [
            'label'  => $label,
            'before' => trim($address['state'] . ' / ' . $address['city'], ' /'),
            // With the code, which is what changes when the name was saved before.
            'after'  => trim(\PersianKit\Service\Import\Iran\IranProvinces::name($after['state']) . ' (' . $after['state'] . ') / ' . $after['city'] . ($after['address_2'] !== $address['address_2'] ? ' / ' . $after['address_2'] : ''), ' /'),
        ];
    }

    private static function mentions(string $text, string $district): bool
    {
        $needle = CityNames::key($district);

        return $needle !== '' && str_contains(CityNames::key($text), $needle);
    }
}
