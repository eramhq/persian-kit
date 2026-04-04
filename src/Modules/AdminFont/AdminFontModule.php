<?php

namespace PersianKit\Modules\AdminFont;

use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;

defined('ABSPATH') || exit;

class AdminFontModule extends AbstractModule
{
    public static function key(): string
    {
        return 'admin_font';
    }

    public static function defaults(): array
    {
        return ['enabled' => true, 'font' => 'vazirmatn'];
    }

    public function register(ServiceContainer $container): void
    {
    }

    public function boot(ServiceContainer $container): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueFont']);
    }

    public function settingsView(): ?string
    {
        return 'admin/partials/admin-font-settings';
    }

    public function enqueueFont(): void
    {
        wp_enqueue_style(
            'persian-kit-admin-font',
            PERSIAN_KIT_URL . 'public/css/admin-font.css',
            [],
            PERSIAN_KIT_VERSION
        );

        wp_add_inline_style(
            'persian-kit-admin-font',
            ":root { --pk-admin-font: 'Vazirmatn'; }"
        );
    }
}
