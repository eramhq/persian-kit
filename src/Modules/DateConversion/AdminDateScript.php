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
     * Enqueue Jalali conversion + admin date override only where the classic UI needs it.
     *
     * Quick Edit lives on edit.php. Classic Editor lives on post.php / post-new.php,
     * but block-editor screens get their own dedicated assets via enqueueGutenberg().
     */
    public function enqueue(string $hookSuffix = ''): void
    {
        if (!$this->shouldEnqueueClassicOverrides($hookSuffix)) {
            return;
        }

        $this->registerSharedScripts();

        wp_enqueue_script('persian-kit-jalali');

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
        $this->registerSharedScripts();

        wp_enqueue_script('persian-kit-jalali');

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

    private function registerSharedScripts(): void
    {
        wp_register_script(
            'persian-kit-jalali',
            PERSIAN_KIT_URL . 'public/js/jalali.js',
            [],
            PERSIAN_KIT_VERSION,
            true
        );
    }

    private function shouldEnqueueClassicOverrides(string $hookSuffix): bool
    {
        if ($hookSuffix === 'edit.php') {
            return true;
        }

        if (!in_array($hookSuffix, ['post.php', 'post-new.php'], true)) {
            return false;
        }

        if (!function_exists('get_current_screen')) {
            return true;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return true;
        }

        if (method_exists($screen, 'is_block_editor')) {
            return !$screen->is_block_editor();
        }

        return true;
    }
}
