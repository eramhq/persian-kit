<?php

namespace PersianKit\Service\Import\Tasks;

use PersianKit\Service\Import\HasReviewOptions;
use PersianKit\Service\Import\ImportContext;
use PersianKit\Service\Import\ImportLog;
use PersianKit\Service\Import\Iran\IranProvinces;
use PersianKit\Service\Import\LogRow;
use PersianKit\Service\Import\TaskBatch;

defined('ABSPATH') || exit;

/**
 * Customers' saved addresses, in user meta: the province as a code and the
 * city by name. On a network, only the users of this site; the user table
 * is shared, so other sites that used the plugin see the change too.
 */
class CustomerAddressTask extends AbstractAddressTask implements HasReviewOptions
{
    public const KEY = 'customers';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('Customer addresses', 'persian-kit');
    }

    public function batchSize(): int
    {
        return 200;
    }

    /**
     * Where the district goes, for customers and orders alike.
     */
    public function reviewOptions(ImportContext $context): ?array
    {
        if ($this->legacyOnly) {
            return null;
        }

        return [
            'type'    => 'district_line',
            'default' => true,
            'label'   => __('Put the district in address line 2 when that line is empty, for customers and orders', 'persian-kit'),
            'notice'  => is_multisite()
                ? __('Only this site\'s customers are converted. Users are shared by every site of the network, so their addresses change on the other sites too.', 'persian-kit')
                : '',
        ];
    }

    public function count(ImportContext $context): int
    {
        global $wpdb;

        // Built from constants and an integer only.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return (int) $wpdb->get_var('SELECT COUNT(*) FROM (' . $this->select(0) . ') counted');
    }

    public function preview(ImportContext $context, int $limit): array
    {
        $samples = [];
        foreach ($this->candidates(0, $limit * 2) as $userId) {
            foreach (self::TYPES as $type) {
                $user = get_userdata($userId);
                $sample = $this->sample($context, $user ? $user->display_name : (string) $userId, $this->address($userId, $type));
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
        $ids = $this->candidates((int) $cursor, $limit);

        foreach ($ids as $userId) {
            $this->guarded($context, 'user', $userId, function () use ($context, $userId): void {
                foreach (self::TYPES as $type) {
                    $converted = $this->convert($context, $this->address($userId, $type));
                    foreach ($converted['changes'] as $field => $change) {
                        update_user_meta($userId, "{$type}_{$field}", $change['new']);
                        $context->log->changed($context, $this->key(), 'user', $userId, "{$type}_{$field}", $change['old'], $change['new']);
                    }
                    foreach ($converted['attention'] as $reason) {
                        $context->log->attention($context, $this->key(), 'user', $userId, "{$type}_state", $reason);
                    }
                }
                clean_user_cache($userId);
            });
        }

        $last = $ids === [] ? (int) $cursor : (int) end($ids);

        return new TaskBatch(count($ids), $last, count($ids) < $limit);
    }

    public function undo(ImportContext $context, LogRow $row): string
    {
        if ((string) get_user_meta($row->objectId, $row->field, true) !== (string) $row->newValue) {
            return ImportLog::KEPT;
        }

        update_user_meta($row->objectId, $row->field, (string) $row->oldValue);
        clean_user_cache($row->objectId);

        return ImportLog::RESTORED;
    }

    /**
     * @return array{country: string, state: string, city: string, address_1: string, address_2: string, district: string}
     */
    private function address(int $userId, string $type): array
    {
        $meta = static fn (string $key): string => (string) get_user_meta($userId, "{$type}_{$key}", true);

        return [
            'country'   => $meta('country'),
            'state'     => $meta('state'),
            'city'      => $meta('city'),
            'address_1' => $meta('address_1'),
            'address_2' => $meta('address_2'),
            'district'  => $meta('district'),
        ];
    }

    /**
     * @return list<int>
     */
    private function candidates(int $after, int $limit): array
    {
        global $wpdb;

        // Built from constants and an integer only.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return array_map('intval', (array) $wpdb->get_col($wpdb->prepare($this->select($after) . ' ORDER BY user_id ASC LIMIT %d', $limit)));
    }

    /**
     * Users in Iran with a province that is not a code, or a city saved as
     * a number. Built from constants and an integer only.
     */
    private function select(int $after): string
    {
        global $wpdb;

        $codes = "'" . implode("','", array_keys(IranProvinces::NAMES)) . "'";
        $legacy = "'" . implode("','", array_keys(IranProvinces::LEGACY_TWO_LETTER)) . "'";
        $needs = $this->legacyOnly
            ? "st.meta_value IN ({$legacy})"
            : "((st.meta_value <> '' AND st.meta_value NOT IN ({$codes})) OR ci.meta_value REGEXP '^[0-9]+$')";

        // On a network, only this site's users.
        $site = is_multisite()
            ? " AND EXISTS (SELECT 1 FROM {$wpdb->usermeta} cap WHERE cap.user_id = co.user_id AND cap.meta_key = '" . esc_sql($wpdb->get_blog_prefix() . 'capabilities') . "')"
            : '';

        $parts = [];
        foreach (self::TYPES as $type) {
            $parts[] = "SELECT co.user_id FROM {$wpdb->usermeta} co
                LEFT JOIN {$wpdb->usermeta} st ON st.user_id = co.user_id AND st.meta_key = '{$type}_state'
                LEFT JOIN {$wpdb->usermeta} ci ON ci.user_id = co.user_id AND ci.meta_key = '{$type}_city'
                WHERE co.meta_key = '{$type}_country' AND co.meta_value = 'IR' AND co.user_id > {$after} AND {$needs}{$site}";
        }

        return 'SELECT DISTINCT user_id FROM (' . implode(' UNION ', $parts) . ') candidates';
    }
}
