<?php

namespace PersianKit\Service\Import;

defined('ABSPATH') || exit;

/**
 * The import log: every field an import changed, with its original value,
 * and everything it could not import, with the reason. Undo and the report
 * read it.
 *
 * The first original value of each object and field is the one kept: a
 * second run logs the value the first run wrote as its "old" value, and
 * undo goes back to the first.
 */
class ImportLog
{
    public const TABLE = 'persian_kit_import_log';

    /** The field was converted. */
    public const CHANGED = 'changed';

    /** Nothing to convert; logged only where the report lists it. */
    public const UNCHANGED = 'unchanged';

    /** Left alone; someone should look at it. */
    public const ATTENTION = 'attention';

    /** A setting or feature with no match in Persian Kit. */
    public const NOT_IMPORTED = 'not_imported';

    /** Undo put the original value back. */
    public const RESTORED = 'restored';

    /** Undo left it, because it changed after the import. */
    public const KEPT = 'kept';

    /** A change a finished undo put back; no longer undone or counted as an original. */
    public const REVERTED = 'reverted';

    public const OUTCOMES = [self::CHANGED, self::UNCHANGED, self::ATTENTION, self::NOT_IMPORTED, self::RESTORED, self::KEPT, self::REVERTED];

    private const SCHEMA_VERSION = '1';
    private const SCHEMA_OPTION = 'persian_kit_import_log_version';

    public static function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::TABLE;
    }

    /**
     * Creates the table the first time an import starts.
     */
    public function install(): void
    {
        if ($this->tableExists()) {
            return;
        }

        global $wpdb;

        $table = self::tableName();
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  run_id varchar(32) NOT NULL,
  source varchar(64) NOT NULL,
  task varchar(40) NOT NULL,
  object_type varchar(20) NOT NULL,
  object_id bigint(20) unsigned NOT NULL DEFAULT 0,
  field varchar(191) NOT NULL DEFAULT '',
  outcome varchar(20) NOT NULL,
  reason text NOT NULL,
  old_value longtext NULL,
  new_value longtext NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY run_id (run_id),
  KEY source_outcome (source, outcome),
  KEY object (object_type, object_id)
) {$charset};");

        update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
    }

    /**
     * Whether the table was created, at this schema version. Kept in an
     * option, so reading the log costs no extra query.
     */
    public function tableExists(): bool
    {
        return get_option(self::SCHEMA_OPTION) === self::SCHEMA_VERSION;
    }

    /**
     * Logs a converted field. When an earlier run already changed it, that
     * run's original value is logged as the original again.
     */
    public function changed(ImportContext $context, string $task, string $objectType, int $objectId, string $field, mixed $old, mixed $new, string $reason = ''): void
    {
        $first = $this->firstOriginal($context->source, $objectType, $objectId, $field);

        $this->insert($context, $task, $objectType, $objectId, $field, self::CHANGED, $reason, $first['found'] ? $first['value'] : $old, $new);
    }

    public function attention(ImportContext $context, string $task, string $objectType, int $objectId, string $field, string $reason, mixed $value = null): void
    {
        $this->insert($context, $task, $objectType, $objectId, $field, self::ATTENTION, $reason, $value, null);
    }

    public function notImported(ImportContext $context, string $task, string $field, string $reason): void
    {
        $this->insert($context, $task, 'setting', 0, $field, self::NOT_IMPORTED, $reason, null, null);
    }

    public function unchanged(ImportContext $context, string $task, string $objectType, int $objectId, string $field, string $reason = ''): void
    {
        $this->insert($context, $task, $objectType, $objectId, $field, self::UNCHANGED, $reason, null, null);
    }

    /**
     * Logs what undo did with a changed row.
     */
    public function undone(ImportContext $context, LogRow $row, string $outcome, string $reason = ''): void
    {
        $this->insert($context, $row->task, $row->objectType, $row->objectId, $row->field, $outcome, $reason, $row->newValue, $outcome === self::RESTORED ? $row->oldValue : $row->newValue);
    }

    /**
     * The changed rows of a source, newest first, after $beforeId (0 for
     * the newest), for undo.
     *
     * @return list<LogRow>
     */
    public function changedRows(string $source, int $beforeId, int $limit): array
    {
        global $wpdb;

        $before = $beforeId > 0 ? $wpdb->prepare(' AND id < %d', $beforeId) : '';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM %i WHERE source = %s AND outcome = %s{$before} ORDER BY id DESC LIMIT %d",
            self::tableName(),
            $source,
            self::CHANGED,
            $limit
        ), ARRAY_A);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return array_map([LogRow::class, 'fromDatabase'], is_array($rows) ? $rows : []);
    }

    /**
     * Whether a newer changed row of the same field exists, so undo of this
     * one is already done by the newer one.
     */
    public function hasNewerChange(LogRow $row): bool
    {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM %i WHERE source = %s AND outcome = %s AND object_type = %s AND object_id = %d AND field = %s AND id > %d',
            self::tableName(),
            $row->source,
            self::CHANGED,
            $row->objectType,
            $row->objectId,
            $row->field,
            $row->id
        )) > 0;
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * The report: rows of a source's runs, by outcome, a page at a time.
     *
     * @param list<string> $runIds
     * @return array{rows: list<LogRow>, total: int}
     */
    public function report(array $runIds, ?string $outcome, int $page, int $perPage): array
    {
        if ($runIds === []) {
            return ['rows' => [], 'total' => 0];
        }

        global $wpdb;

        $where = 'run_id IN (' . implode(',', array_fill(0, count($runIds), '%s')) . ')';
        $args = array_merge([self::tableName()], $runIds);
        if ($outcome !== null) {
            $where .= ' AND outcome = %s';
            $args[] = $outcome;
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE {$where}", $args));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM %i WHERE {$where} ORDER BY id ASC LIMIT %d OFFSET %d",
            array_merge($args, [$perPage, max(0, $page - 1) * $perPage])
        ), ARRAY_A);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

        return ['rows' => array_map([LogRow::class, 'fromDatabase'], is_array($rows) ? $rows : []), 'total' => $total];
    }

    /**
     * Counts by outcome for the given runs.
     *
     * @param list<string> $runIds
     * @return array<string, int>
     */
    public function counts(array $runIds): array
    {
        $counts = array_fill_keys(self::OUTCOMES, 0);
        if ($runIds === [] || !$this->tableExists()) {
            return $counts;
        }

        global $wpdb;

        $in = implode(',', array_fill(0, count($runIds), '%s'));

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $rows = $wpdb->get_results($wpdb->prepare("SELECT outcome, COUNT(*) AS total FROM %i WHERE run_id IN ({$in}) GROUP BY outcome", array_merge([self::tableName()], $runIds)), ARRAY_A);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

        foreach (is_array($rows) ? $rows : [] as $row) {
            $counts[(string) $row['outcome']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Whether the source has changes undo could put back.
     */
    public function hasChanges(string $source): bool
    {
        if (!$this->tableExists()) {
            return false;
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (bool) $wpdb->get_var($wpdb->prepare('SELECT 1 FROM %i WHERE source = %s AND outcome = %s LIMIT 1', self::tableName(), $source, self::CHANGED));
    }

    /**
     * After a finished undo: the source's changes are no longer undone
     * again, and the next import starts from the values as they are.
     */
    public function retire(string $source): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(self::tableName(), ['outcome' => self::REVERTED], ['source' => $source, 'outcome' => self::CHANGED], ['%s'], ['%s', '%s']);
    }

    /**
     * Deletes a source's log: its undo data and its report.
     */
    public function forget(string $source): void
    {
        if (!$this->tableExists()) {
            return;
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete(self::tableName(), ['source' => $source], ['%s']);
    }

    public static function encode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $json = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? null : $json;
    }

    public static function decode(mixed $value): mixed
    {
        if (!is_string($value)) {
            return null;
        }

        return json_decode($value, true);
    }

    /**
     * @return array{found: bool, value: mixed}
     */
    private function firstOriginal(string $source, string $objectType, int $objectId, string $field): array
    {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT old_value FROM %i WHERE source = %s AND outcome = %s AND object_type = %s AND object_id = %d AND field = %s ORDER BY id ASC LIMIT 1',
            self::tableName(),
            $source,
            self::CHANGED,
            $objectType,
            $objectId,
            $field
        ), ARRAY_A);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        return is_array($row) ? ['found' => true, 'value' => self::decode($row['old_value'])] : ['found' => false, 'value' => null];
    }

    private function insert(ImportContext $context, string $task, string $objectType, int $objectId, string $field, string $outcome, string $reason, mixed $old, mixed $new): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(self::tableName(), [
            'run_id'      => $context->runId,
            'source'      => $context->source,
            'task'        => $task,
            'object_type' => $objectType,
            'object_id'   => $objectId,
            'field'       => $field,
            'outcome'     => $outcome,
            'reason'      => $reason,
            'old_value'   => self::encode($old),
            'new_value'   => self::encode($new),
            'created_at'  => current_time('mysql', true),
        ], ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']);
    }
}
