<?php

namespace PersianKit\Service\Import\Sources\PersianWooCommerce;

use PersianKit\Core\SettingsManager;
use PersianKit\Service\Import\AbstractSource;
use PersianKit\Service\Import\ChecklistItem;
use PersianKit\Service\Import\HasReportTips;
use PersianKit\Service\Import\ImportJob;
use PersianKit\Service\Import\Woo\PaymentGateways;

defined('ABSPATH') || exit;

/**
 * Persian WooCommerce (ووکامرس فارسی): its settings are 'yes'/'no' values
 * in PW_Options.
 */
class PersianWooCommerceSource extends AbstractSource implements HasReportTips
{
    public const KEY = 'persian-woocommerce';

    /** Its defaults, for options never saved. */
    public const DEFAULTS = [
        'enable_jalali_datepicker'    => 'yes',
        'persian_price'               => 'no',
        'enable_iran_cities'          => 'yes',
        'allowed_states'              => 'all',
        'fix_postcode_persian_number' => 'yes',
        'fix_phone_persian_number'    => 'yes',
        'postcode_validation'         => 'no',
        'phone_validation'            => 'yes',
        'admin_font_family'           => 'iransans',
    ];

    /**
     * Its options that do what Persian Kit does. While any is on, both
     * plugins would convert and check the same things.
     */
    public const OVERLAPPING = [
        'enable_jalali_datepicker',
        'persian_price',
        'enable_iran_cities',
        'fix_postcode_persian_number',
        'fix_phone_persian_number',
        'postcode_validation',
        'phone_validation',
    ];

    public function key(): string
    {
        return self::KEY;
    }

    public function name(): string
    {
        return __('Persian WooCommerce', 'persian-kit');
    }

    public function pluginFile(): string
    {
        return 'persian-woocommerce/woocommerce-persian.php';
    }

    public function hasData(): bool
    {
        return self::arrayOption('PW_Options') !== [];
    }

    /**
     * Null when inactive, or when it is kept only for its payment gateways:
     * every option that overlaps Persian Kit is off.
     */
    public function readyToImport(): ?string
    {
        if (!$this->isActive() || !self::overlapsPersianKit()) {
            return null;
        }

        return __('Deactivate Persian WooCommerce first, or turn off its options that Persian Kit takes over to keep it for its payment gateways.', 'persian-kit');
    }

    public function settingRows(SettingsManager $settings): array
    {
        return (new PersianWooCommerceSettings())->rows();
    }

    public function snapshot(): array
    {
        return ['gateways' => self::liveGateways()];
    }

    public function reportTips(array $job): array
    {
        return [__('Its phrase replacements are not imported. For WooCommerce in Persian, install its language pack under Dashboard > Updates.', 'persian-kit')];
    }

    public function checklist(array $snapshot): array
    {
        $items = [];
        $gateways = self::gateways($snapshot);

        if ($gateways !== []) {
            $items[] = new ChecklistItem(
                'gateways',
                __('Payment gateways that will stop', 'persian-kit'),
                __('These gateways are built on Persian WooCommerce and stop when it is deactivated; Persian Kit has none. Set up another gateway first, or keep Persian WooCommerce active for them with the options below turned off.', 'persian-kit'),
                PaymentGateways::entries($gateways),
                false,
                true,
                ['acknowledge_label' => __('I have another gateway, or I am keeping Persian WooCommerce for these', 'persian-kit')]
            );
        }

        if ($this->isActive()) {
            $labels = self::optionLabels();
            $on = self::overlappingOn();
            $items[] = new ChecklistItem(
                'keep_for_gateways',
                __('Keeping Persian WooCommerce for its gateways?', 'persian-kit'),
                $on === []
                    ? __('Its options that Persian Kit takes over are all off, so it can stay active and the import can run.', 'persian-kit')
                    : __('Then turn off these options in its settings, so the two plugins never do the same thing. The import runs once they are off.', 'persian-kit'),
                array_map(static fn (string $key): array => [
                    'label' => $labels[$key] ?? $key,
                    'url'   => admin_url('admin.php?page=persian-wc-tools'),
                ], $on)
            );
        }

        return $items;
    }

    /**
     * Its gateways that are on: found live while it runs, else from the
     * snapshot Review took, else the ones whose settings say they are on.
     *
     * @param array<string, mixed> $snapshot
     * @return array<string, string> Title by id.
     */
    public static function gateways(array $snapshot = []): array
    {
        $live = self::liveGateways();
        if ($live !== []) {
            return $live;
        }

        if ($snapshot === []) {
            $job = ImportJob::load();
            $snapshot = $job !== null && $job->source === self::KEY ? $job->snapshot : [];
        }
        if (is_array($snapshot['gateways'] ?? null) && $snapshot['gateways'] !== []) {
            return $snapshot['gateways'];
        }

        return PaymentGateways::enabled('wc_zibal') ? ['wc_zibal' => 'Zibal'] : [];
    }

    /**
     * @return list<string>
     */
    public static function gatewayIds(): array
    {
        return array_keys(self::gateways());
    }

    /**
     * Its gateways WooCommerce has on, while it runs.
     *
     * @return array<string, string>
     */
    private static function liveGateways(): array
    {
        if (!function_exists('WC') || !class_exists('Persian_Woocommerce_Gateways') || !did_action('woocommerce_init')) {
            return [];
        }

        $gateways = [];
        foreach (WC()->payment_gateways()->payment_gateways() as $id => $gateway) {
            $ours = $gateway instanceof \Persian_Woocommerce_Gateways || $id === 'wc_zibal';
            if ($ours && ($gateway->enabled ?? 'no') === 'yes') {
                $gateways[(string) $id] = wp_strip_all_tags((string) $gateway->get_method_title());
            }
        }

        return $gateways;
    }

    /**
     * @return array<string, string>
     */
    private static function optionLabels(): array
    {
        return [
            'enable_jalali_datepicker'    => __('Jalali dates and date picker', 'persian-kit'),
            'persian_price'               => __('Persian digits in prices', 'persian-kit'),
            'enable_iran_cities'          => __('Iranian cities', 'persian-kit'),
            'fix_postcode_persian_number' => __('Persian digits in postcode', 'persian-kit'),
            'fix_phone_persian_number'    => __('Persian digits in phone', 'persian-kit'),
            'postcode_validation'         => __('Check postcode', 'persian-kit'),
            'phone_validation'            => __('Check phone', 'persian-kit'),
            'allowed_states'              => __('Provinces it sells to', 'persian-kit'),
            'admin_font_family'           => __('Admin font', 'persian-kit'),
        ];
    }

    /**
     * Whether any of its options that do what Persian Kit does is on.
     */
    public static function overlapsPersianKit(): bool
    {
        return self::overlappingOn() !== [];
    }

    /**
     * Its options that do what Persian Kit does and are on.
     *
     * @return list<string>
     */
    public static function overlappingOn(): array
    {
        $on = [];
        foreach (self::OVERLAPPING as $key) {
            // The shipping plugin turns its city list off while active.
            if ($key === 'enable_iran_cities' && defined('PWS_VERSION')) {
                continue;
            }
            if (self::truthy(self::value($key))) {
                $on[] = $key;
            }
        }

        if (self::value('allowed_states') !== 'all') {
            $on[] = 'allowed_states';
        }
        if (!in_array(self::value('admin_font_family'), ['none', ''], true)) {
            $on[] = 'admin_font_family';
        }

        return $on;
    }

    /**
     * One option as Persian WooCommerce reads it: the saved value, or its default.
     */
    public static function value(string $key): mixed
    {
        $options = self::arrayOption('PW_Options');

        return $options[$key] ?? (self::DEFAULTS[$key] ?? null);
    }

    protected function isLoaded(): bool
    {
        return defined('PW_VERSION') && function_exists('PW');
    }
}
