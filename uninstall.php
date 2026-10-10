<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$persianKitDeleteOptions = static function (): void {
    foreach (['persian_kit_settings', 'persian_kit_db_version', 'persian_kit_show_welcome', 'persian_kit_seen_integrations', 'persian_kit_normalize_job', 'persian_kit_normalize_cursor', 'persian_kit_import_job', 'persian_kit_imports', 'persian_kit_import_lock', 'persian_kit_import_log_version'] as $option) {
        delete_option($option);
    }

    // The switch's log, with its undo data.
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}persian_kit_import_log");
    delete_transient('persian_kit_cf7_field_usage');
    delete_transient('persian_kit_forminator_field_usage');
    delete_transient('persian_kit_gravityforms_field_usage');
    delete_transient('persian_kit_wpforms_field_usage');
    delete_transient('persian_kit_archive_days');
};

if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $persianKitSiteId) {
        switch_to_blog((int) $persianKitSiteId);
        $persianKitDeleteOptions();
        restore_current_blog();
    }
} else {
    $persianKitDeleteOptions();
}
