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
     * Enqueue the Jalali date picker for the post date where the classic UI needs it.
     *
     * Quick Edit lives on edit.php. Classic Editor lives on post.php / post-new.php,
     * but block-editor screens get their own dedicated assets via enqueueGutenberg().
     */
    public function enqueue(string $hookSuffix = ''): void
    {
        if (!$this->shouldEnqueueClassicOverrides($hookSuffix)) {
            return;
        }

        DatePicker::enqueue();

        wp_enqueue_script(
            'persian-kit-classic-date',
            PERSIAN_KIT_URL . 'public/js/classic-date-fields.js',
            ['jquery', DatePicker::FIELD],
            PERSIAN_KIT_VERSION,
            true
        );
    }

    /**
     * Enqueue the Gutenberg Jalali date editor on the block editor post screen.
     *
     * The site and widget editors also fire enqueue_block_editor_assets, but
     * have no publish date.
     */
    public function enqueueGutenberg(): void
    {
        if (!$this->isBlockEditorPostScreen()) {
            return;
        }

        JalaliScript::register();

        wp_enqueue_script('persian-kit-jalali');

        wp_enqueue_style(
            'persian-kit-gutenberg-jalali',
            PERSIAN_KIT_URL . 'public/css/gutenberg-jalali.css',
            ['wp-components'],
            PERSIAN_KIT_VERSION
        );

        wp_enqueue_script(
            'persian-kit-gutenberg-jalali',
            PERSIAN_KIT_URL . 'public/js/gutenberg-jalali-date.js',
            [
                'wp-plugins',
                'wp-element',
                'wp-components',
                'wp-data',
                'wp-date',
                'wp-i18n',
                'wp-editor',
                'wp-edit-post',
                JalaliScript::HANDLE,
            ],
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

        return !$screen->is_block_editor();
    }

    private function isBlockEditorPostScreen(): bool
    {
        if (!function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }

        return $screen->base === 'post' && $screen->is_block_editor();
    }
}
