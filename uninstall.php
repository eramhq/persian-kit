<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$options = [
    'persian_kit_settings',
    'persian_kit_normalization_state',
    'persian_kit_normalization_cursor',
];

foreach ($options as $option) {
    delete_option($option);
}
