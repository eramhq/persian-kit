<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$persianKitDeleteOptions = static function (): void {
    foreach (['persian_kit_settings', 'persian_kit_db_version', 'persian_kit_show_welcome', 'persian_kit_normalize_job', 'persian_kit_normalize_cursor'] as $option) {
        delete_option($option);
    }
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
