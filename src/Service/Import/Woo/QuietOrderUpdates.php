<?php

namespace PersianKit\Service\Import\Woo;

defined('ABSPATH') || exit;

/**
 * Runs order changes the switch makes without side effects: no WooCommerce
 * email goes out and stock is not reduced or restored. Everything is back
 * in place afterwards, even when the changes fail.
 */
final class QuietOrderUpdates
{
    /** Stock hooks WooCommerce adds on status changes, by hook. */
    private const STOCK_HOOKS = [
        'woocommerce_payment_complete'         => 'wc_maybe_reduce_stock_levels',
        'woocommerce_order_status_completed'   => 'wc_maybe_reduce_stock_levels',
        'woocommerce_order_status_processing'  => 'wc_maybe_reduce_stock_levels',
        'woocommerce_order_status_on-hold'     => 'wc_maybe_reduce_stock_levels',
        'woocommerce_order_status_cancelled'   => 'wc_maybe_increase_stock_levels',
        'woocommerce_order_status_pending'     => 'wc_maybe_increase_stock_levels',
    ];

    /**
     * @template T
     * @param callable(): T $changes
     * @return T
     */
    public static function run(callable $changes): mixed
    {
        $emails = [];
        if (function_exists('WC')) {
            foreach (WC()->mailer()->get_emails() as $email) {
                $emails[] = 'woocommerce_email_enabled_' . $email->id;
            }
        }
        foreach ($emails as $filter) {
            add_filter($filter, '__return_false', PHP_INT_MAX);
        }

        $removed = [];
        foreach (self::STOCK_HOOKS as $hook => $callback) {
            $priority = has_action($hook, $callback);
            if ($priority !== false) {
                remove_action($hook, $callback, $priority);
                $removed[] = [$hook, $callback, $priority];
            }
        }

        try {
            return $changes();
        } finally {
            foreach ($emails as $filter) {
                remove_filter($filter, '__return_false', PHP_INT_MAX);
            }
            foreach ($removed as [$hook, $callback, $priority]) {
                add_action($hook, $callback, $priority);
            }
        }
    }
}
