<?php

namespace PersianKit\Modules\DateConversion;

use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;

defined('ABSPATH') || exit;

class DateConversionModule extends AbstractModule
{
    public static function key(): string
    {
        return 'date_conversion';
    }

    public static function label(): string
    {
        return __('Date Conversion', 'persian-kit');
    }

    public static function description(): string
    {
        return __('Converts Gregorian dates to Jalali (Shamsi)', 'persian-kit');
    }

    public static function defaults(): array
    {
        return ['enabled' => true, 'global_conversion' => false];
    }

    public function register(ServiceContainer $container): void
    {
        $container->register(DateFilters::class, function () {
            return new DateFilters((bool) $this->setting('global_conversion', false));
        });

        $container->register(RestApiExtension::class, function () {
            return new RestApiExtension();
        });
    }

    public function settingsView(): ?string
    {
        return 'admin/partials/date-conversion-settings';
    }

    public function boot(ServiceContainer $container): void
    {
        $filters = $container->get(DateFilters::class);
        $filters->registerTier1();
        $filters->registerTier2();
        $filters->registerAdminFilters();

        $container->get(RestApiExtension::class)->register();
    }
}
