<?php

namespace PersianKit\Service\Import\Sources\ParsiDate;

use PersianKit\Service\Import\AbstractTask;
use PersianKit\Service\Import\HasReviewOptions;
use PersianKit\Service\Import\ImportContext;
use PersianKit\Service\Import\ImportLog;
use PersianKit\Service\Import\LogRow;
use PersianKit\Service\Import\TaskBatch;

defined('ABSPATH') || exit;

/**
 * The values of Parsi Date's ACF date fields become what ACF's date picker
 * saves (Gregorian Ymd), wherever ACF keeps them: post, term and user meta
 * and options, in repeaters and groups too. Each value is found through
 * the reference row ACF writes next to it (_name = field_key), so nested
 * rows such as rep_0_date are found the same way. Runs before the fields
 * themselves are converted.
 */
class AcfValueTask extends AbstractTask implements HasReviewOptions
{
    public const KEY = 'acf_values';

    /** Where ACF keeps values: table => [object type, id column]. */
    private const META = [
        'postmeta' => ['post', 'post_id'],
        'termmeta' => ['term', 'term_id'],
        'usermeta' => ['user', 'user_id'],
    ];

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('ACF date values', 'persian-kit');
    }

    public function available(): ?string
    {
        return AcfFields::acfActive() ? null : __('Activate ACF to convert these dates.', 'persian-kit');
    }

    public function batchSize(): int
    {
        return 200;
    }

    public function count(ImportContext $context): int
    {
        return count($this->rows($context));
    }

    public function preview(ImportContext $context, int $limit): array
    {
        $samples = [];
        foreach (array_slice($this->rows($context), 0, $limit) as $row) {
            $converted = AcfDateValue::convert($row['value'], (bool) $context->option('fix_double_dates', false));
            $samples[] = ['label' => $row['key'], 'before' => $row['value'], 'after' => $converted['outcome'] === AcfDateValue::CHANGED ? $converted['value'] : $converted['reason']];
        }

        return $samples;
    }

    /**
     * The choice to fix dates Parsi Date converted twice, when there are any.
     */
    public function reviewOptions(ImportContext $context): ?array
    {
        $twice = array_filter($this->rows($context), static fn (array $row): bool => $row['twice']);

        return $twice === [] ? null : [
            'type'    => 'fix_double_dates',
            'default' => false,
            /* translators: %s: number of values. */
            'label'   => sprintf(_n('Fix %s date saved twice (after the year 2100)', 'Fix %s dates saved twice (after the year 2100)', count($twice), 'persian-kit'), number_format_i18n(count($twice))),
        ];
    }

    public function run(ImportContext $context, mixed $cursor, int $limit): TaskBatch
    {
        $rows = $this->rows($context);
        $offset = (int) $cursor;
        $batch = array_slice($rows, $offset, $limit);
        $fix = (bool) $context->option('fix_double_dates', false);
        $changed = 0;

        foreach ($batch as $row) {
            $this->guarded($context, $row['type'], $row['id'], function () use ($context, $row, $fix, &$changed): void {
                $converted = AcfDateValue::convert($row['value'], $fix);
                if ($converted['outcome'] === AcfDateValue::ATTENTION) {
                    $context->log->attention($context, $this->key(), $row['type'], $row['id'], $row['key'], $converted['reason'], $row['value']);
                } elseif ($converted['outcome'] === AcfDateValue::CHANGED) {
                    self::write($row['type'], $row['id'], $row['key'], $converted['value']);
                    $context->log->changed($context, $this->key(), $row['type'], $row['id'], $row['key'], $row['value'], $converted['value']);
                    $changed++;
                }
            });
        }

        // Converted values leave the list; the cursor counts those left behind.
        $left = $offset + count($batch) - $changed;

        return new TaskBatch(count($batch), $left, $left >= count($rows) - $changed);
    }

    public function undo(ImportContext $context, LogRow $row): string
    {
        $current = $row->objectType === 'option' ? get_option($row->field) : get_metadata($row->objectType, $row->objectId, $row->field, true);
        if ((string) $current !== (string) $row->newValue) {
            return ImportLog::KEPT;
        }

        self::write($row->objectType, $row->objectId, $row->field, (string) $row->oldValue);

        return ImportLog::RESTORED;
    }

    /**
     * Values of Parsi Date's date fields that are not Ymd yet, and date
     * picker values after 2100. The query picks only rows still to convert,
     * so a second run finds what is new.
     *
     * @return list<array{type: string, id: int, key: string, value: string, twice: bool}>
     */
    private function rows(ImportContext $context): array
    {
        global $wpdb;

        // Reference rows start with an underscore.
        $reference = $wpdb->esc_like('_') . '%';
        $jalali = AcfFields::keysOfType(AcfFields::JALALI);
        $pickers = AcfFields::keysOfType('date_picker');
        $rows = [];

        foreach ([[$jalali, "v.%s NOT REGEXP '^[0-9]{8}$'", false], [$pickers, "v.%s REGEXP '^(2[1-9]|[3-9][0-9])[0-9]{6}$'", true]] as [$keys, $condition, $twice]) {
            if ($keys === []) {
                continue;
            }
            $in = implode(',', array_fill(0, count($keys), '%s'));

            foreach (self::META as $table => [$type, $column]) {
                $metaTable = $wpdb->{$table};
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
                $found = $wpdb->get_results($wpdb->prepare(
                    "SELECT v.{$column} AS id, v.meta_key AS meta_key, v.meta_value AS meta_value FROM {$metaTable} r
                    INNER JOIN {$metaTable} v ON v.{$column} = r.{$column} AND v.meta_key = SUBSTRING(r.meta_key, 2)
                    WHERE r.meta_key LIKE %s AND r.meta_value IN ({$in}) AND v.meta_value <> '' AND " . sprintf($condition, 'meta_value') . " ORDER BY v.{$column}, v.meta_key",
                    array_merge([$reference], $keys)
                ), ARRAY_A);
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

                foreach (is_array($found) ? $found : [] as $row) {
                    $rows[] = ['type' => $type, 'id' => (int) $row['id'], 'key' => (string) $row['meta_key'], 'value' => (string) $row['meta_value'], 'twice' => $twice];
                }
            }

            // Options pages: _options_date = field key, beside options_date.
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
            $found = $wpdb->get_results($wpdb->prepare(
                "SELECT v.option_name, v.option_value FROM {$wpdb->options} r
                INNER JOIN {$wpdb->options} v ON v.option_name = SUBSTRING(r.option_name, 2)
                WHERE r.option_name LIKE %s AND r.option_value IN ({$in}) AND v.option_value <> '' AND " . sprintf($condition, 'option_value') . ' ORDER BY v.option_name',
                array_merge([$reference], $keys)
            ), ARRAY_A);
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

            foreach (is_array($found) ? $found : [] as $row) {
                $rows[] = ['type' => 'option', 'id' => 0, 'key' => (string) $row['option_name'], 'value' => (string) $row['option_value'], 'twice' => $twice];
            }
        }

        return $rows;
    }

    private static function write(string $type, int $id, string $key, string $value): void
    {
        if ($type === 'option') {
            update_option($key, $value);

            return;
        }

        update_metadata($type, $id, $key, $value);
    }
}
