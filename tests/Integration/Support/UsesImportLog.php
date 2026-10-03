<?php

namespace PersianKit\Tests\Integration\Support;

use PersianKit\Bootstrap;
use PersianKit\Service\Import\ImportLog;

/**
 * The import log as a temporary table: CREATE TABLE would commit the test's
 * transaction. Emptied before each test.
 */
trait UsesImportLog
{
    private function setUpImportLog(): void
    {
        add_filter('query', [self::class, 'temporaryImportLog']);
        Bootstrap::get(ImportLog::class)->install();

        global $wpdb;
        $wpdb->query('DELETE FROM ' . ImportLog::tableName());
    }

    public static function temporaryImportLog(string $query): string
    {
        return preg_replace('/^\s*CREATE TABLE /i', 'CREATE TEMPORARY TABLE ', $query) ?? $query;
    }
}
