<?php

namespace PersianKit\Service\Import\Sources\Shipping;

use PersianKit\Core\SettingsManager;
use PersianKit\Service\Import\AbstractSource;
use PersianKit\Service\Import\ChecklistItem;
use PersianKit\Service\Import\HasReportTips;
use PersianKit\Service\Import\HasReviewNotes;
use PersianKit\Service\Import\HasUndoWarning;
use PersianKit\Service\Import\Iran\PwsTermMap;
use PersianKit\Service\Import\Sources\ParsiDate\ThemeCalls;
use PersianKit\Service\Import\Sources\ParsiDate\ThemeScanTask;
use PersianKit\Service\Import\Tasks\CustomerAddressTask;
use PersianKit\Service\Import\Tasks\OrderAddressTask;

defined('ABSPATH') || exit;

/**
 * Persian WooCommerce Shipping (حمل و نقل ووکامرس): provinces, cities and
 * districts are terms of its state_city taxonomy, and addresses hold their
 * term IDs, or Tapin's own IDs while its Tapin mode is on (pws_tapin).
 */
class PwsSource extends AbstractSource implements HasReviewNotes, HasReportTips, HasUndoWarning
{
    public const KEY = 'persian-woocommerce-shipping';

    public const TAXONOMY = 'state_city';

    public function key(): string
    {
        return self::KEY;
    }

    public function name(): string
    {
        return __('Persian WooCommerce Shipping', 'persian-kit');
    }

    public function pluginFile(): string
    {
        return 'persian-woocommerce-shipping/woocommerce-shipping.php';
    }

    public function hasData(): bool
    {
        if (get_option('pws_install_cities') || self::arrayOption('pws_tapin') !== []) {
            return true;
        }

        global $wpdb;

        // Read directly: the taxonomy is only registered while it is active.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s LIMIT 1", self::TAXONOMY));
    }

    public function settingRows(SettingsManager $settings): array
    {
        return (new PwsSettings())->rows();
    }

    /**
     * In order of urgency: zones first, so checkout offers shipping again.
     */
    public function tasks(): array
    {
        return [
            new ShippingZoneTask(),
            new DefaultCountryTask(),
            new CustomerAddressTask(),
            new OrderStatusTask(),
            new OrderAddressTask(),
            new ThemeScanTask(['pws'], false),
        ];
    }

    /**
     * Its terms and Tapin mode, in case the terms are deleted with it.
     */
    public function snapshot(): array
    {
        $labels = [];
        $statuses = ['PWS_Status', 'get_statues'];
        if (is_callable($statuses)) {
            foreach ((array) call_user_func($statuses) as $slug => $label) {
                $labels[(string) $slug] = wp_strip_all_tags((string) $label);
            }
        }

        return [
            'term_map'      => PwsTermMap::load()->toSnapshot(),
            'tapin'         => PwsSettings::tapinEnabled(),
            'status_labels' => $labels,
        ];
    }

    /**
     * Keep using it or switch: Persian Kit has no shipping methods, so a
     * store whose rates come from it keeps it by default.
     */
    public function reviewNotes(array $snapshot): array
    {
        $inUse = PwsSettings::methodsByZone() !== [];

        return [[
            'key'    => 'keep_or_switch',
            'title'  => __('Keep using it, or switch?', 'persian-kit'),
            'text'   => $inUse
                ? __('Your shipping zones use its shipping methods, which Persian Kit does not have. Keep it if your rates come from it; Persian Kit then leaves city suggestions and the province list to it.', 'persian-kit')
                : __('None of your shipping zones use its shipping methods, so Persian Kit can take over its province and city fields.', 'persian-kit'),
            'choice' => [
                'keep'    => $inUse,
                'options' => [
                    ['value' => 'keep', 'label' => __('Keep using it', 'persian-kit'), 'description' => __('Nothing is imported. The two plugins work side by side.', 'persian-kit')],
                    ['value' => 'switch', 'label' => __('Switch to Persian Kit', 'persian-kit'), 'description' => __('Addresses, zones and order statuses are converted to WooCommerce\'s own, and its shipping methods go.', 'persian-kit')],
                ],
            ],
        ]];
    }

    public function reportTips(array $job): array
    {
        $tips = [
            __('Orders moved out of its statuses got a note. No email went to customers and stock did not change. Moving an order to Processing or Completed may give customers access to its downloads.', 'persian-kit'),
            __('WooCommerce Analytics catches up with the moved orders in the background, which can take a while on a big store.', 'persian-kit'),
            __('Its shipping methods and per-city prices are gone: check each shipping zone has a WooCommerce method.', 'persian-kit'),
        ];

        return $tips;
    }

    /**
     * Undoing the status moves while it is inactive hides those orders again.
     */
    public function undoWarning(): string
    {
        return $this->isActive()
            ? ''
            : __('Orders moved out of its statuses go back to them, and drop out of the order lists again until it is active.', 'persian-kit');
    }

    public function checklist(array $snapshot): array
    {
        $items = ThemeCalls::items($this->name(), ['pws'], $snapshot, false);
        $zones = PwsSettings::methodsByZone();

        if ($zones !== []) {
            $items[] = new ChecklistItem(
                'shipping_methods',
                __('Shipping methods that will go', 'persian-kit'),
                __('These zones use its courier, Tipax, post or Tapin methods, and its per-city prices. WooCommerce will not offer them once it is inactive.', 'persian-kit'),
                array_map(static fn (array $zone): array => [
                    'label'  => $zone['name'],
                    'detail' => implode('، ', array_unique($zone['methods'])),
                    'url'    => admin_url('admin.php?page=wc-settings&tab=shipping&zone_id=' . $zone['id']),
                ], $zones),
                false,
                true,
                ['acknowledge_label' => __('I know these methods will go', 'persian-kit')]
            );
        }

        $empty = array_values(array_filter($zones, static fn (array $zone): bool => $zone['others'] === 0));
        if ($empty !== []) {
            $items[] = new ChecklistItem(
                'empty_zones',
                __('Zones left with no shipping method', 'persian-kit'),
                __('Add a WooCommerce method, such as flat rate, to each before you deactivate, or customers there can not check out.', 'persian-kit'),
                array_map(static fn (array $zone): array => [
                    'label' => $zone['name'],
                    'url'   => admin_url('admin.php?page=wc-settings&tab=shipping&zone_id=' . $zone['id']),
                ], $empty),
                false,
                true,
                ['acknowledge_label' => __('I have added methods to these zones, or no one orders there', 'persian-kit')]
            );
        }

        return $items;
    }

    protected function isLoaded(): bool
    {
        return defined('PWS_VERSION');
    }
}
