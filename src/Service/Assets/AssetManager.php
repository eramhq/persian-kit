<?php

namespace PersianKit\Service\Assets;

defined('ABSPATH') || exit;

class AssetManager
{
    private const SETTINGS_ASSETS = [
        'public/css/admin.css',
        'public/js/admin.min.js',
    ];

    private bool $missingAssetsNoticeRegistered = false;

    public function __construct()
    {
        $this->registerHooks();
    }

    public function enqueueAdmin(string $hook): void
    {
        if (!$this->isPluginPage($hook)) {
            return;
        }

        if (!$this->settingsAssetsExist()) {
            $this->registerMissingAssetsNotice();
            return;
        }

        $this->enqueueSettings();
    }

    public function renderMissingAssetsNotice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php echo esc_html__('Persian Kit', 'persian-kit'); ?>:</strong>
                <?php echo esc_html__('The generated admin assets are missing. Build the plugin before packaging or using it from source.', 'persian-kit'); ?>
            </p>
            <p>
                <code>npm ci && npm run build</code>
            </p>
        </div>
        <?php
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

    protected function settingsAssetsExist(): bool
    {
        foreach (self::SETTINGS_ASSETS as $asset) {
            if (!file_exists(PERSIAN_KIT_DIR . $asset)) {
                return false;
            }
        }

        return true;
    }

    protected function registerHooks(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdmin']);
    }

    private function registerMissingAssetsNotice(): void
    {
        if ($this->missingAssetsNoticeRegistered) {
            return;
        }

        add_action('admin_notices', [$this, 'renderMissingAssetsNotice']);
        $this->missingAssetsNoticeRegistered = true;
    }
}
