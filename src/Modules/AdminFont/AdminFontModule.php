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

    public static function label(): string
    {
        return __('Admin Font', 'persian-kit');
    }

    public static function description(): string
    {
        return __('Uses the Vazirmatn font in the admin when your admin language is Persian or right-to-left. Your site\'s theme is not affected.', 'persian-kit');
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function sanitizeSettings(array $values): array
    {
        $font = is_string($values['font'] ?? null) ? strtolower(trim($values['font'])) : '';
        $allowedFonts = ['vazirmatn'];

        return [
            'enabled' => !empty($values['enabled']),
            'font'    => in_array($font, $allowedFonts, true) ? $font : 'vazirmatn',
        ];
    }

    public function enqueueFont(): void
    {
        if (!$this->isPersianOrRtlAdmin()) {
            return;
        }

        wp_enqueue_style(
            'persian-kit-admin-font',
            PERSIAN_KIT_URL . 'public/css/admin-font.css',
            [],
            PERSIAN_KIT_VERSION
        );

        wp_add_inline_style(
            'persian-kit-admin-font',
            ":root { --persian-kit-admin-font: 'Vazirmatn'; }"
        );
    }

    /**
     * The font only covers Persian/Arabic script, so other admin languages keep
     * WordPress's default font stack.
     */
    private function isPersianOrRtlAdmin(): bool
    {
        $locale = get_user_locale();

        return $locale === 'fa' || str_starts_with($locale, 'fa_') || is_rtl();
    }
}
