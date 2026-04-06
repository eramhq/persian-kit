<?php

namespace PersianKit\Modules\DateConversion;

defined('ABSPATH') || exit;

class AdminDateScript
{
    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueGutenberg']);
    }

    /**
     * Enqueue Jalali conversion + admin date override on all admin pages.
     *
     * Quick Edit lives on edit.php, Classic Editor on post.php / post-new.php —
     * we load on all admin pages to keep it simple and cover edge cases.
     */
    public function enqueue(): void
    {
        wp_enqueue_script(
            'persian-kit-jalali',
            PERSIAN_KIT_URL . 'public/js/jalali.js',
            [],
            PERSIAN_KIT_VERSION,
            true
        );

        wp_enqueue_script(
            'persian-kit-admin-date',
            PERSIAN_KIT_URL . 'public/js/admin-date-override.js',
            ['jquery', 'persian-kit-jalali'],
            PERSIAN_KIT_VERSION,
            true
        );
    }

    /**
     * Enqueue Gutenberg Jalali date editor on block editor pages.
     */
    public function enqueueGutenberg(): void
    {
        wp_enqueue_style(
            'persian-kit-gutenberg-jalali',
            PERSIAN_KIT_URL . 'public/css/gutenberg-jalali.css',
            ['wp-components'],
            PERSIAN_KIT_VERSION
        );

        wp_enqueue_script(
            'persian-kit-gutenberg-jalali',
            PERSIAN_KIT_URL . 'public/js/gutenberg-jalali-panel.js',
            ['wp-data', 'wp-components', 'persian-kit-jalali'],
            PERSIAN_KIT_VERSION,
            true
        );
    }
}
