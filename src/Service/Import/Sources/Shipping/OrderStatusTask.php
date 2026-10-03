<?php

namespace PersianKit\Service\Import\Sources\Shipping;

use Automattic\WooCommerce\Caches\OrderCache;
use PersianKit\Service\Import\AbstractTask;
use PersianKit\Service\Import\HasReviewOptions;
use PersianKit\Service\Import\ImportContext;
use PersianKit\Service\Import\ImportLog;
use PersianKit\Service\Import\LogRow;
use PersianKit\Service\Import\TaskBatch;
use PersianKit\Service\Import\Woo\OrderCandidates;
use PersianKit\Service\Import\Woo\QuietOrderUpdates;

defined('ABSPATH') || exit;

/**
 * Orders in the shipping plugin's own statuses (wc-pws-*) drop out of the
 * order lists once it is inactive. Each moves to the WooCommerce status
 * chosen in Review, with an order note, sending no email and leaving stock
 * alone.
 */
class OrderStatusTask extends AbstractTask implements HasReviewOptions
{
    public const KEY = 'order_statuses';
    public const DEFAULT_TARGET = 'wc-processing';

    /** Its statuses, with the labels it gives them. */
    public const LABELS = [
        'wc-pws-in-stock'      => 'ارسال شده به انبار',
        'wc-pws-packaged'      => 'بسته بندی شده',
        'wc-pws-courier'       => 'تحویل پیک',
        'wc-pws-post'          => 'تحویل پست',
        'wc-pws-tipax'         => 'تحویل تیپاکس',
        'wc-pws-ready-to-ship' => 'آماده به ارسال',
        'wc-pws-returned'      => 'برگشتی',
        'wc-pws-deleted'       => 'حذف شده',
        'wc-pws-shipping'      => 'در حال ارسال',
        'wc-pws-need-review'   => 'نیازمند بررسی',
    ];

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('Order statuses', 'persian-kit');
    }

    public function available(): ?string
    {
        return function_exists('wc_get_order') ? null : __('Activate WooCommerce to move these orders.', 'persian-kit');
    }

    public function count(ImportContext $context): int
    {
        return array_sum($this->counts());
    }

    public function preview(ImportContext $context, int $limit): array
    {
        $samples = [];
        $targets = self::targets();
        foreach (array_slice($this->counts(), 0, $limit, true) as $slug => $count) {
            $target = $this->target($context, $slug);
            $samples[] = [
                /* translators: %s: number of orders. */
                'label'  => sprintf(_n('%s order', '%s orders', $count, 'persian-kit'), number_format_i18n($count)),
                'before' => self::labelOf($context, $slug),
                'after'  => $targets[$target] ?? $target,
            ];
        }

        return $samples;
    }

    /**
     * Where each status goes, with a dropdown per status.
     */
    public function reviewOptions(ImportContext $context): ?array
    {
        $counts = $this->counts();
        if ($counts === []) {
            return null;
        }

        $statuses = [];
        foreach ($counts as $slug => $count) {
            $statuses[] = ['slug' => $slug, 'label' => self::labelOf($context, $slug), 'count' => $count];
        }

        return ['type' => 'status_map', 'default' => self::DEFAULT_TARGET, 'statuses' => $statuses, 'targets' => self::targets()];
    }

    public function run(ImportContext $context, mixed $cursor, int $limit): TaskBatch
    {
        $ids = $this->ids((int) $cursor, $limit);

        QuietOrderUpdates::run(function () use ($context, $ids): void {
            foreach ($ids as $orderId) {
                $this->guarded($context, 'order', $orderId, function () use ($context, $orderId): void {
                    $order = wc_get_order($orderId);
                    if (!$order instanceof \WC_Order) {
                        return;
                    }

                    $from = 'wc-' . $order->get_status('edit');
                    $to = $this->target($context, $from);
                    $note = sprintf(
                        /* translators: 1: order status, 2: its slug. */
                        __('Status moved from %1$s (%2$s) when switching to Persian Kit.', 'persian-kit'),
                        self::labelOf($context, $from),
                        $from
                    );

                    $order->set_status($to, $note);
                    $order->save();
                    $context->log->changed($context, $this->key(), 'order', $orderId, 'status', $from, $to);
                });
            }
        });

        $last = $ids === [] ? (int) $cursor : (int) end($ids);

        return new TaskBatch(count($ids), $last, count($ids) < $limit);
    }

    /**
     * Puts the order back in the plugin's status. WooCommerce would not
     * accept a status it doesn't know, so it is written directly.
     */
    public function undo(ImportContext $context, LogRow $row): string
    {
        $order = wc_get_order($row->objectId);
        if (!$order instanceof \WC_Order || 'wc-' . $order->get_status('edit') !== $row->newValue || !is_string($row->oldValue)) {
            return ImportLog::KEPT;
        }

        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if (OrderCandidates::usesOrderTables()) {
            $wpdb->update($wpdb->prefix . 'wc_orders', ['status' => $row->oldValue], ['id' => $row->objectId]);
            if (class_exists(OrderCache::class)) {
                wc_get_container()->get(OrderCache::class)->remove($row->objectId);
            }
        }
        if (get_post_type($row->objectId) === 'shop_order') {
            $wpdb->update($wpdb->posts, ['post_status' => $row->oldValue], ['ID' => $row->objectId]);
            clean_post_cache($row->objectId);
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        wc_create_order_note($row->objectId, sprintf(
            /* translators: %s: order status slug. */
            __('Status put back to %s: the switch to Persian Kit was undone.', 'persian-kit'),
            $row->oldValue
        ));

        return ImportLog::RESTORED;
    }

    /**
     * Orders by status, among the plugin's statuses.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        global $wpdb;

        $sql = OrderCandidates::usesOrderTables()
            ? "SELECT status, COUNT(*) AS total FROM {$wpdb->prefix}wc_orders WHERE type = 'shop_order' AND status LIKE 'wc-pws-%' GROUP BY status"
            : "SELECT post_status AS status, COUNT(*) AS total FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status LIKE 'wc-pws-%' GROUP BY post_status";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($sql, ARRAY_A);

        $counts = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * WooCommerce's statuses an order can move to.
     *
     * @return array<string, string>
     */
    public static function targets(): array
    {
        $statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [];

        return array_filter(
            $statuses,
            static fn (string $slug): bool => !str_starts_with($slug, 'wc-pws-') && !in_array($slug, ['wc-checkout-draft', 'wc-refunded'], true),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @return list<int>
     */
    private function ids(int $after, int $limit): array
    {
        global $wpdb;

        $sql = OrderCandidates::usesOrderTables()
            ? "SELECT id FROM {$wpdb->prefix}wc_orders WHERE type = 'shop_order' AND status LIKE 'wc-pws-%' AND id > %d ORDER BY id ASC LIMIT %d"
            : "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status LIKE 'wc-pws-%' AND ID > %d ORDER BY ID ASC LIMIT %d";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return array_map('intval', (array) $wpdb->get_col($wpdb->prepare($sql, $after, $limit)));
    }

    private function target(ImportContext $context, string $from): string
    {
        $map = $context->option('status_map', []);
        $to = is_array($map) && is_string($map[$from] ?? null) ? $map[$from] : self::DEFAULT_TARGET;

        return isset(self::targets()[$to]) ? $to : self::DEFAULT_TARGET;
    }

    /**
     * The label the plugin gave a status: from Review's snapshot while it
     * ran, else the ones it ships with.
     */
    private static function labelOf(ImportContext $context, string $slug): string
    {
        $labels = is_array($context->snapshot['status_labels'] ?? null) ? $context->snapshot['status_labels'] : [];

        return (string) ($labels[$slug] ?? self::LABELS[$slug] ?? $slug);
    }
}
