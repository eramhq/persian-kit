<?php

namespace PersianKit\Modules\Utilities;

use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;

defined('ABSPATH') || exit;

class UtilitiesModule extends AbstractModule
{
    public static function key(): string
    {
        return 'utilities';
    }

    public static function label(): string
    {
        return __('Utilities', 'persian-kit');
    }

    public static function description(): string
    {
        return __('Controls Persian slugs only. The persian_kit_* helper functions for developers are always available, even when this is off.', 'persian-kit');
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return ['enabled' => true, 'persian_slugs' => true];
    }

    public function register(ServiceContainer $container): void
    {
        $container->register(PersianSlugFilter::class, function () {
            return new PersianSlugFilter();
        });
    }

    public function settingsView(): ?string
    {
        return 'admin/partials/utilities-settings';
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function sanitizeSettings(array $values): array
    {
        return [
            'enabled'       => !empty($values['enabled']),
            'persian_slugs' => !empty($values['persian_slugs']),
        ];
    }

    public function boot(ServiceContainer $container): void
    {
        if ($this->setting('persian_slugs', true) && apply_filters('persian_kit_utilities', true, 'sanitize_title')) {
            $container->get(PersianSlugFilter::class)->register();
        }
    }
}
