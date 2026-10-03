<?php

namespace PersianKit\Service\Import\Woo;

defined('ABSPATH') || exit;

/**
 * Payment gateways a source plugin brings, and the orders still waiting
 * for payment through them, which can't be paid once it is inactive.
 */
final class PaymentGateways
{
    /**
     * Orders pending or on hold whose payment method is one of these.
     *
     * @param list<string> $ids Gateway ids.
     */
    public static function awaitingPayment(array $ids): int
    {
        if ($ids === [] || !function_exists('wc_get_orders')) {
            return 0;
        }

        $result = wc_get_orders([
            'payment_method' => $ids,
            'status'         => ['pending', 'on-hold'],
            'type'           => 'shop_order',
            'limit'          => 1,
            'paginate'       => true,
            'return'         => 'ids',
        ]);

        return is_object($result) && isset($result->total) ? (int) $result->total : 0;
    }

    /**
     * Whether WooCommerce has the gateway turned on, from its saved settings.
     */
    public static function enabled(string $id): bool
    {
        $settings = get_option('woocommerce_' . $id . '_settings', []);

        return is_array($settings) && ($settings['enabled'] ?? 'no') === 'yes';
    }

    /**
     * The checklist entries: each gateway with its waiting orders.
     *
     * @param array<string, string> $gateways Title by id.
     * @return list<array{label: string, detail: string, url: string}>
     */
    public static function entries(array $gateways): array
    {
        $entries = [];
        foreach ($gateways as $id => $title) {
            $waiting = self::awaitingPayment([$id]);
            $entries[] = [
                'label'  => $title,
                'detail' => $waiting > 0
                    /* translators: %s: number of orders. */
                    ? sprintf(_n('%s order still waiting for payment through it', '%s orders still waiting for payment through it', $waiting, 'persian-kit'), number_format_i18n($waiting))
                    : __('No orders waiting for payment through it', 'persian-kit'),
                'url'    => admin_url('admin.php?page=wc-settings&tab=checkout&section=' . rawurlencode($id)),
            ];
        }

        return $entries;
    }
}
