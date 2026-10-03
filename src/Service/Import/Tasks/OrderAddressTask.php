<?php

namespace PersianKit\Service\Import\Tasks;

use PersianKit\Service\Import\ImportContext;
use PersianKit\Service\Import\ImportLog;
use PersianKit\Service\Import\LogRow;
use PersianKit\Service\Import\TaskBatch;
use PersianKit\Service\Import\Woo\OrderCandidates;

defined('ABSPATH') || exit;

/**
 * Order addresses: the province as a code, the city by name, through
 * WooCommerce's order objects, so both order stores stay in step. The
 * shipping plugin's own *_id meta is kept.
 */
class OrderAddressTask extends AbstractAddressTask
{
    public const KEY = 'order_addresses';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('Order addresses', 'persian-kit');
    }

    public function count(ImportContext $context): int
    {
        return function_exists('wc_get_order') ? OrderCandidates::count($this->legacyOnly) : 0;
    }

    public function preview(ImportContext $context, int $limit): array
    {
        $samples = [];
        foreach (OrderCandidates::ids(0, $limit * 2, $this->legacyOnly) as $orderId) {
            $order = wc_get_order($orderId);
            if (!$order instanceof \WC_Order) {
                continue;
            }
            foreach (self::TYPES as $type) {
                /* translators: %s: order number. */
                $sample = $this->sample($context, sprintf(__('Order #%s', 'persian-kit'), $order->get_order_number()), $this->address($order, $type), $this->ids($order, $type));
                if ($sample !== null) {
                    $samples[] = $sample;
                    break;
                }
            }
            if (count($samples) >= $limit) {
                break;
            }
        }

        return $samples;
    }

    public function run(ImportContext $context, mixed $cursor, int $limit): TaskBatch
    {
        $ids = OrderCandidates::ids((int) $cursor, $limit, $this->legacyOnly);

        foreach ($ids as $orderId) {
            $this->guarded($context, 'order', $orderId, function () use ($context, $orderId): void {
                $order = wc_get_order($orderId);
                if (!$order instanceof \WC_Order) {
                    return;
                }

                $changed = false;
                foreach (self::TYPES as $type) {
                    $converted = $this->convert($context, $this->address($order, $type), $this->ids($order, $type));
                    foreach ($converted['changes'] as $field => $change) {
                        $order->{"set_{$type}_{$field}"}($change['new']);
                        $context->log->changed($context, $this->key(), 'order', $orderId, "{$type}_{$field}", $change['old'], $change['new']);
                        $changed = true;
                    }
                    foreach ($converted['attention'] as $reason) {
                        $context->log->attention($context, $this->key(), 'order', $orderId, "{$type}_state", $reason);
                    }
                }

                if ($changed) {
                    $order->save();
                }
            });
        }

        $last = $ids === [] ? (int) $cursor : (int) end($ids);

        return new TaskBatch(count($ids), $last, count($ids) < $limit);
    }

    public function undo(ImportContext $context, LogRow $row): string
    {
        $order = wc_get_order($row->objectId);
        $getter = 'get_' . $row->field;
        $setter = 'set_' . $row->field;

        if (!$order instanceof \WC_Order || !is_callable([$order, $getter]) || (string) $order->{$getter}('edit') !== (string) $row->newValue) {
            return ImportLog::KEPT;
        }

        $order->{$setter}((string) $row->oldValue);
        $order->save();

        return ImportLog::RESTORED;
    }

    /**
     * @return array{country: string, state: string, city: string, address_1: string, address_2: string, district: string}
     */
    private function address(\WC_Order $order, string $type): array
    {
        $get = static fn (string $field): string => (string) $order->{"get_{$type}_{$field}"}('edit');

        return [
            'country'   => $get('country'),
            'state'     => $get('state'),
            'city'      => $get('city'),
            'address_1' => $get('address_1'),
            'address_2' => $get('address_2'),
            'district'  => (string) $order->get_meta("_{$type}_district"),
        ];
    }

    /**
     * The shipping plugin's ids saved next to the names.
     *
     * @return array<string, string>
     */
    private function ids(\WC_Order $order, string $type): array
    {
        return [
            'state'    => (string) $order->get_meta("_{$type}_state_id"),
            'city'     => (string) $order->get_meta("_{$type}_city_id"),
            'district' => (string) $order->get_meta("_{$type}_district_id"),
        ];
    }
}
