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
        return __('Persian slug, validation, and formatting tools', 'persian-kit');
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
        if (apply_filters('persian_kit_utilities', true, 'sanitize_title')) {
            remove_filter('sanitize_title', 'sanitize_title_with_dashes', 10);
            add_filter('sanitize_title', [Slug::class, 'sanitizeTitleFilter'], 10, 3);
        }
    }
}
