<?php

namespace PersianKit\Service\Import\Iran;

defined('ABSPATH') || exit;

/**
 * Tapin's provinces and cities, as the shipping plugin saved them while its
 * Tapin mode was on. Bundled from that plugin's data/tapin.json.
 */
final class TapinCities
{
    public const DATA_FILE = 'public/data/pws-tapin.json';

    /** The source the build copies from, for a checkout that hasn't been built. */
    private const SOURCE_FILE = 'resources/data/pws-tapin.json';

    /** @var array<int, array<int, string>> City names by city id, by province id. */
    private array $cities;

    /** @var array<int, int> Province id by city id. */
    private array $provinceOf = [];

    /**
     * @param array<int, array<int, string>> $cities
     */
    public function __construct(array $cities)
    {
        $this->cities = $cities;
        foreach ($cities as $province => $names) {
            foreach (array_keys($names) as $city) {
                $this->provinceOf[$city] ??= $province;
            }
        }
    }

    public static function load(?string $file = null): self
    {
        if ($file === null) {
            $file = PERSIAN_KIT_DIR . self::DATA_FILE;
            if (!is_readable($file)) {
                $file = PERSIAN_KIT_DIR . self::SOURCE_FILE;
            }
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file shipped with the plugin.
        $json = is_readable($file) ? file_get_contents($file) : false;
        $data = is_string($json) ? json_decode($json, true) : null;

        $cities = [];
        foreach (is_array($data) ? $data : [] as $province => $entry) {
            if (!is_numeric($province) || !is_array($entry['cities'] ?? null)) {
                continue;
            }
            foreach ($entry['cities'] as $id => $name) {
                $cities[(int) $province][(int) $id] = (string) $name;
            }
        }

        return new self($cities);
    }

    public function hasProvince(int $id): bool
    {
        return isset($this->cities[$id]);
    }

    public function provinceCode(int $id): ?string
    {
        return $this->hasProvince($id) ? IranProvinces::fromTapin($id) : null;
    }

    public function cityName(int $id): ?string
    {
        $province = $this->provinceOf[$id] ?? null;

        return $province === null ? null : $this->cities[$province][$id];
    }

    public function provinceOfCity(int $id): ?int
    {
        return $this->provinceOf[$id] ?? null;
    }

    public function hasCity(int $province, int $city): bool
    {
        return isset($this->cities[$province][$city]);
    }
}
