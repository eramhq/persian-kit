<?php

namespace PersianKit\Service\Import\Iran;

use PersianKit\Modules\WooCommerce\CityField;
use PersianKit\Modules\WooCommerce\CityNames;

defined('ABSPATH') || exit;

/**
 * Reads an Iranian address however a plugin saved it, as WooCommerce
 * expects it: the province as WooCommerce's code, the city by name.
 *
 * The province may be a code, an old two-letter code, a province name, or
 * a number: the shipping plugin's term id or, in its Tapin mode, Tapin's
 * id. When a number could be either and the two disagree, the city
 * decides; when it can't, the address is left for someone to look at.
 *
 * The city may be a term id (a city, or a district under one), a Tapin id
 * or a name, saved under its listed spelling when there is one and as
 * written otherwise.
 */
final class IranAddressResolver
{
    private const TERM = 'term';
    private const TAPIN = 'tapin';

    private PwsTermMap $terms;
    private TapinCities $tapin;
    private CityNames $cities;
    private bool $tapinMode;

    public function __construct(PwsTermMap $terms, TapinCities $tapin, CityNames $cities, bool $tapinMode = false)
    {
        $this->terms = $terms;
        $this->tapin = $tapin;
        $this->cities = $cities;
        $this->tapinMode = $tapinMode;
    }

    /**
     * The resolver for this site's data, with the snapshot Review took.
     *
     * @param array<string, mixed> $snapshot
     */
    public static function forSite(array $snapshot = []): self
    {
        $tapinMode = array_key_exists('tapin', $snapshot)
            ? (bool) $snapshot['tapin']
            : \PersianKit\Service\Import\Sources\Shipping\PwsSettings::tapinEnabled();

        return new self(
            PwsTermMap::loadOrSnapshot($snapshot),
            TapinCities::load(),
            new CityNames((new CityField(PERSIAN_KIT_DIR . CityField::DATA_FILE))->cities()),
            $tapinMode
        );
    }

    /**
     * @param string               $state    As saved.
     * @param string               $city     As saved.
     * @param array<string, mixed> $ids      The shipping plugin's ids saved next to an order's names: state, city, district.
     * @param string               $district As saved, a term id or a name.
     */
    public function resolve(string $state, string $city, array $ids = [], string $district = ''): AddressResult
    {
        $state = trim($state);
        $city = trim($city);

        // The ids first; the names an order also keeps if a term is gone.
        $stateId = self::numeric($ids['state'] ?? '');
        $cityId = self::numeric($ids['city'] ?? '');
        $resolved = null;
        if ($stateId !== '' || $cityId !== '') {
            $resolved = $this->resolveParts($stateId !== '' ? $stateId : $state, $cityId !== '' ? $cityId : $city);
            if ($resolved->outcome === AddressResult::ATTENTION && ($state !== $stateId || $city !== $cityId)) {
                $fallback = $this->resolveParts($state, $city);
                $resolved = $fallback->outcome === AddressResult::ATTENTION ? $resolved : $fallback;
            }
        }
        $resolved ??= $this->resolveParts($state, $city);

        if ($resolved->outcome === AddressResult::ATTENTION) {
            return $resolved;
        }

        $districtName = $resolved->district !== '' ? $resolved->district : $this->districtName((string) ($ids['district'] ?? ''), $district);
        $changed = $resolved->state !== $state || $resolved->city !== $city;

        return new AddressResult($changed ? AddressResult::CHANGED : AddressResult::UNCHANGED, $resolved->state, $resolved->city, $districtName);
    }

    /**
     * The province code for a saved value, without a city to check against.
     */
    public function stateCode(string $state): ?string
    {
        $result = $this->resolveParts($state, '');

        return $result->outcome === AddressResult::ATTENTION || $result->state === '' ? null : $result->state;
    }

    private function resolveParts(string $state, string $city): AddressResult
    {
        if ($state === '') {
            return new AddressResult(AddressResult::UNCHANGED, '', $city);
        }

        $province = $this->province($state, $city);
        if (is_string($province)) {
            return AddressResult::attention($province);
        }

        [$code, $mode] = $province;

        return $this->city($city, $code, $mode);
    }

    /**
     * @return array{0: string, 1: ?string}|string The code and how it was read, or why it can't be.
     */
    private function province(string $state, string $city): array|string
    {
        if (!ctype_digit($state)) {
            $code = IranProvinces::resolve($state);

            /* translators: %s: province as saved. */
            return $code !== null ? [$code, null] : sprintf(__('Unknown province: %s', 'persian-kit'), $state);
        }

        $id = (int) $state;
        $byTerm = $this->terms->depth($id) === 0 ? $this->terms->provinceCode($id) : null;
        $byTapin = $this->tapin->provinceCode($id);

        if ($byTerm !== null && ($byTapin === null || $byTapin === $byTerm)) {
            return [$byTerm, self::TERM];
        }
        if ($byTerm === null && $byTapin !== null && ($this->tapinMode || $this->terms->depth($id) === -1)) {
            return [$byTapin, self::TAPIN];
        }
        if ($byTerm === null) {
            return $this->terms->has($id)
                /* translators: %s: term id. */
                ? sprintf(__('Province %s is a city or district, not a province.', 'persian-kit'), $state)
                /* translators: %s: term id. */
                : sprintf(__('Province %s was deleted from the shipping plugin\'s list.', 'persian-kit'), $state);
        }

        // Both lists know the number, as different provinces: the city decides.
        $termFits = $this->cityFits($city, $byTerm, self::TERM, $id);
        $tapinFits = $this->cityFits($city, $byTapin, self::TAPIN, $id);
        if ($termFits !== $tapinFits) {
            return $termFits ? [$byTerm, self::TERM] : [$byTapin, self::TAPIN];
        }

        return sprintf(
            /* translators: 1: province number, 2: province name, 3: another province name. */
            __('Province %1$s could be %2$s or %3$s: saved in the shipping plugin\'s own list or in Tapin\'s.', 'persian-kit'),
            $state,
            IranProvinces::name($byTerm),
            IranProvinces::name($byTapin)
        );
    }

    private function cityFits(string $city, string $code, string $mode, int $provinceId): bool
    {
        if ($city === '') {
            return false;
        }

        if (ctype_digit($city)) {
            return $mode === self::TERM
                ? $this->terms->has((int) $city) && $this->terms->root((int) $city) === $provinceId
                : $this->tapin->hasCity($provinceId, (int) $city);
        }

        return $this->cities->officialName('IR', $code, $city) !== null;
    }

    private function city(string $city, string $code, ?string $mode): AddressResult
    {
        if ($city === '') {
            return new AddressResult(AddressResult::UNCHANGED, $code, '');
        }

        if (!ctype_digit($city)) {
            return new AddressResult(AddressResult::UNCHANGED, $code, $this->cities->officialName('IR', $code, $city) ?? $city);
        }

        $id = (int) $city;
        $tryTerm = $mode !== self::TAPIN;
        $tryTapin = $mode !== self::TERM || $this->terms->depth($id) === -1;

        if ($tryTerm && $this->terms->depth($id) >= 1) {
            if ($this->terms->provinceCode($id) !== $code) {
                /* translators: 1: city name, 2: province name. */
                return AddressResult::attention(sprintf(__('%1$s is not in %2$s.', 'persian-kit'), (string) $this->terms->name($id), IranProvinces::name($code)));
            }

            $cityId = (int) $this->terms->city($id);
            $name = (string) $this->terms->name($cityId);
            $district = $cityId !== $id ? (string) $this->terms->name($id) : '';

            return new AddressResult(AddressResult::UNCHANGED, $code, $this->cities->officialName('IR', $code, $name) ?? $name, $district);
        }

        if ($tryTapin && ($name = $this->tapin->cityName($id)) !== null) {
            $province = $this->tapin->provinceOfCity($id);
            if ($province !== null && IranProvinces::fromTapin($province) !== $code) {
                /* translators: 1: city name, 2: province name. */
                return AddressResult::attention(sprintf(__('%1$s is not in %2$s.', 'persian-kit'), $name, IranProvinces::name($code)));
            }

            return new AddressResult(AddressResult::UNCHANGED, $code, $this->cities->officialName('IR', $code, $name) ?? $name);
        }

        /* translators: %s: city id. */
        return AddressResult::attention(sprintf(__('City %s was deleted from the shipping plugin\'s list.', 'persian-kit'), $city));
    }

    private function districtName(string $id, string $saved): string
    {
        foreach ([trim($id), trim($saved)] as $value) {
            if ($value === '' || $value === '0') {
                continue;
            }
            if (!ctype_digit($value)) {
                return $value;
            }
            if ($this->terms->depth((int) $value) >= 2) {
                return (string) $this->terms->name((int) $value);
            }
        }

        return '';
    }

    private static function numeric(mixed $value): string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return ctype_digit($value) && $value !== '0' ? $value : '';
    }
}
