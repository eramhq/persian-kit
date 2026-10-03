<?php

namespace PersianKit\Service\Import\Sources\Shipping;

use PersianKit\Service\Import\AbstractTask;
use PersianKit\Service\Import\ImportContext;
use PersianKit\Service\Import\ImportLog;
use PersianKit\Service\Import\Iran\IranAddressResolver;
use PersianKit\Service\Import\Iran\IranProvinces;
use PersianKit\Service\Import\Iran\PwsTermMap;
use PersianKit\Service\Import\LogRow;
use PersianKit\Service\Import\TaskBatch;

defined('ABSPATH') || exit;

/**
 * Shipping zones that list provinces by the plugin's ids (IR:123) list
 * them by WooCommerce's codes (IR:THR) instead. Zones can't target cities,
 * so city and district locations stay and are reported.
 */
class ShippingZoneTask extends AbstractTask
{
    public const KEY = 'shipping_zones';

    private ?IranAddressResolver $resolver = null;

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('Shipping zones', 'persian-kit');
    }

    public function available(): ?string
    {
        return class_exists('WC_Shipping_Zone') ? null : __('Activate WooCommerce to convert shipping zones.', 'persian-kit');
    }

    public function count(ImportContext $context): int
    {
        return count($this->zonesToConvert());
    }

    public function preview(ImportContext $context, int $limit): array
    {
        $samples = [];
        foreach (array_slice($this->zonesToConvert(), 0, $limit, true) as $zoneId => $codes) {
            $planned = $this->plan($context, $codes);
            $samples[] = [
                'label'  => $this->zoneName($zoneId),
                'before' => implode('، ', $codes),
                'after'  => implode('، ', array_map([IranProvinces::class, 'name'], $planned['codes'])),
            ];
        }

        return $samples;
    }

    public function run(ImportContext $context, mixed $cursor, int $limit): TaskBatch
    {
        $zones = $this->zonesToConvert();

        foreach ($zones as $zoneId => $codes) {
            $this->guarded($context, 'zone', $zoneId, function () use ($context, $zoneId, $codes): void {
                $zone = new \WC_Shipping_Zone($zoneId);
                $old = self::locations($zone);
                $planned = $this->plan($context, $codes);

                $new = [];
                foreach ($old as $location) {
                    $code = $location['type'] === 'state' && str_starts_with($location['code'], 'IR:') ? substr($location['code'], 3) : null;
                    if ($code !== null && isset($planned['map'][$code])) {
                        $location = ['code' => 'IR:' . $planned['map'][$code], 'type' => 'state'];
                    }
                    if (!in_array($location, $new, true)) {
                        $new[] = $location;
                    }
                }

                foreach ($planned['attention'] as $reason) {
                    $context->log->attention($context, $this->key(), 'zone', $zoneId, 'locations', $reason);
                }

                if ($new === $old) {
                    return;
                }

                self::setLocations($zone, $new);
                $context->log->changed($context, $this->key(), 'zone', $zoneId, 'locations', $old, $new);
            });
        }

        return new TaskBatch(count($zones), null, true);
    }

    public function undo(ImportContext $context, LogRow $row): string
    {
        $zone = new \WC_Shipping_Zone($row->objectId);
        if (self::locations($zone) !== $row->newValue || !is_array($row->oldValue)) {
            return ImportLog::KEPT;
        }

        self::setLocations($zone, $row->oldValue);

        return ImportLog::RESTORED;
    }

    /**
     * Zones with Iranian "state" locations that are not WooCommerce codes.
     *
     * @return array<int, list<string>> The location codes after "IR:", by zone id.
     */
    private function zonesToConvert(): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            "SELECT zone_id, location_code FROM {$wpdb->prefix}woocommerce_shipping_zone_locations WHERE location_type = 'state' AND location_code LIKE 'IR:%' ORDER BY zone_id, location_id",
            ARRAY_A
        );

        $zones = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $code = substr((string) $row['location_code'], 3);
            if (!IranProvinces::isCode($code)) {
                $zones[(int) $row['zone_id']][] = $code;
            }
        }

        return $zones;
    }

    /**
     * Which locations become which codes, and which stay.
     *
     * @param list<string> $codes
     * @return array{map: array<string, string>, codes: list<string>, attention: list<string>}
     */
    private function plan(ImportContext $context, array $codes): array
    {
        $this->resolver ??= IranAddressResolver::forSite($context->snapshot);
        $terms = PwsTermMap::loadOrSnapshot($context->snapshot);
        $map = [];
        $attention = [];

        foreach ($codes as $code) {
            if (ctype_digit($code) && $terms->depth((int) $code) >= 1) {
                $attention[] = sprintf(
                    /* translators: %s: city or district name. */
                    __('The zone targets %s, a city or district. WooCommerce zones can not target cities; rebuild it with postcodes.', 'persian-kit'),
                    (string) $terms->name((int) $code)
                );
                continue;
            }

            $resolved = $this->resolver->stateCode($code);
            if ($resolved === null) {
                /* translators: %s: location code. */
                $attention[] = sprintf(__('Unknown province in the zone: %s', 'persian-kit'), $code);
                continue;
            }
            $map[$code] = $resolved;
        }

        return ['map' => $map, 'codes' => array_values(array_unique($map)), 'attention' => $attention];
    }

    private function zoneName(int $zoneId): string
    {
        $zone = new \WC_Shipping_Zone($zoneId);

        return $zone->get_zone_name();
    }

    /**
     * @return list<array{code: string, type: string}>
     */
    private static function locations(\WC_Shipping_Zone $zone): array
    {
        return array_values(array_map(
            static fn (object $location): array => ['code' => (string) $location->code, 'type' => (string) $location->type],
            $zone->get_zone_locations('edit')
        ));
    }

    /**
     * @param array<mixed> $locations
     */
    private static function setLocations(\WC_Shipping_Zone $zone, array $locations): void
    {
        $zone->set_locations(array_values(array_filter($locations, 'is_array')));
        $zone->save();
    }
}
