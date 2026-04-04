<?php

namespace PersianKit\Service\Assets;

defined('ABSPATH') || exit;

class AssetManager
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdmin']);
    }

    public function enqueueAdmin(string $hook): void
    {
        if (!$this->isPluginPage($hook)) {
            return;
        }

        $this->enqueueSettings();
    }

    private function enqueueSettings(): void
    {
        wp_enqueue_style(
            'persian-kit-admin',
            PERSIAN_KIT_URL . 'public/css/admin.css',
            [],
            PERSIAN_KIT_VERSION
        );

        wp_enqueue_script(
            'persian-kit-admin',
            PERSIAN_KIT_URL . 'public/js/admin.min.js',
            [],
            PERSIAN_KIT_VERSION,
            true
        );

        wp_print_inline_script_tag(
            'var persianKitSettings = ' . wp_json_encode($this->getLocalizedData()) . ';',
            ['id' => 'persian-kit-settings-data']
        );
    }

    private function getLocalizedData(): array
    {
        return [
            'restUrl'  => rest_url('persian-kit/v1/'),
            'nonce'    => wp_create_nonce('wp_rest'),
            'version'  => PERSIAN_KIT_VERSION,
            'adminUrl' => admin_url(),
        ];
    }

    private function isPluginPage(string $hook): bool
    {
        return str_contains($hook, 'toplevel_page_persian-kit')
            || str_contains($hook, '_page_persian-kit');
    }
}
