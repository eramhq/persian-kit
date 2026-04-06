<?php

namespace PersianKit\Modules\ZWNJEditor;

use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;

defined('ABSPATH') || exit;

class ZWNJEditorModule extends AbstractModule
{
    public static function key(): string
    {
        return 'zwnj_editor';
    }

    public static function label(): string
    {
        return __('ZWNJ Editor Support', 'persian-kit');
    }

    public static function description(): string
    {
        return __('Adds half-space keyboard shortcuts to the Classic Editor and Block Editor', 'persian-kit');
    }

    public static function defaults(): array
    {
        return ['enabled' => true];
    }

    public function register(ServiceContainer $container): void
    {
    }

    public function boot(ServiceContainer $container): void
    {
        add_filter('mce_external_plugins', [$this, 'registerTinyMcePlugin']);
        add_filter('mce_buttons_2', [$this, 'registerTinyMceButton']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueTextEditorScript']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorScript']);
    }

    /**
     * @param array<string, string> $plugins
     * @return array<string, string>
     */
    public function registerTinyMcePlugin(array $plugins): array
    {
        $plugins['persian_kit_zwnj'] = PERSIAN_KIT_URL . 'public/js/tinymce-zwnj.js';

        return $plugins;
    }

    /**
     * @param string[] $buttons
     * @return string[]
     */
    public function registerTinyMceButton(array $buttons): array
    {
        $buttons[] = 'persian_kit_zwnj';

        return $buttons;
    }

    public function enqueueTextEditorScript(string $hook): void
    {
        if (!$this->shouldEnqueueClassicEditorScript($hook)) {
            return;
        }

        wp_enqueue_script(
            'persian-kit-text-editor-zwnj',
            PERSIAN_KIT_URL . 'public/js/text-editor-zwnj.js',
            [],
            PERSIAN_KIT_VERSION,
            true
        );
    }

    public function enqueueBlockEditorScript(): void
    {
        if (!$this->isBlockEditorPostScreen()) {
            return;
        }

        wp_enqueue_script(
            'persian-kit-gutenberg-zwnj',
            PERSIAN_KIT_URL . 'public/js/gutenberg-zwnj.js',
            [],
            PERSIAN_KIT_VERSION,
            true
        );
    }

    private function shouldEnqueueClassicEditorScript(string $hook): bool
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return false;
        }

        return !$this->isBlockEditorPostScreen();
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

        if (property_exists($screen, 'base') && $screen->base !== 'post') {
            return false;
        }

        if (!method_exists($screen, 'is_block_editor')) {
            return false;
        }

        return $screen->is_block_editor();
    }
}
