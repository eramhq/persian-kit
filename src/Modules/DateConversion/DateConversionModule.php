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
        return __('Shows post, comment and admin dates as Jalali (Shamsi). Stored dates are not changed, and feeds and structured data keep Gregorian dates.', 'persian-kit');
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return ['enabled' => true, 'global_conversion' => false];
    }

    public function register(ServiceContainer $container): void
    {
        $container->register(DateFilters::class, function () {
            return new DateFilters((bool) $this->setting('global_conversion', false));
        });

        $container->register(PostTypeMonthFilter::class, function () {
            return new PostTypeMonthFilter();
        });

        $container->register(MediaAttachmentDateFormatter::class, function () {
            return new MediaAttachmentDateFormatter();
        });

        $container->register(MediaGridDateFilter::class, function (ServiceContainer $container) {
            return new MediaGridDateFilter($container->get(PostTypeMonthFilter::class));
        });

        $container->register(DateArchiveFilter::class, function () {
            return new DateArchiveFilter();
        });

        $container->register(JalaliDateArchive::class, function () {
            return new JalaliDateArchive();
        });

        $container->register(RestApiExtension::class, function () {
            return new RestApiExtension();
        });

        $container->register(AdminDateScript::class, function () {
            return new AdminDateScript();
        });
    }

    public function settingsView(): ?string
    {
        return 'admin/partials/date-conversion-settings';
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function sanitizeSettings(array $values): array
    {
        return [
            'enabled'           => !empty($values['enabled']),
            'global_conversion' => !empty($values['global_conversion']),
        ];
    }

    public function boot(ServiceContainer $container): void
    {
        $filters = $container->get(DateFilters::class);
        $filters->registerTier1();
        $filters->registerTier2();
        // The admin bar clock also shows on the front end.
        $filters->registerAdminFilters();
        $container->get(DateArchiveFilter::class)->register();
        $container->get(JalaliDateArchive::class)->register();
        $container->get(RestApiExtension::class)->register();

        // Admin screens, admin-ajax (the media grid) and admin-post only.
        // is_admin() is false for REST, cron and WP-CLI.
        if (is_admin()) {
            $container->get(PostTypeMonthFilter::class)->register();
            $container->get(MediaAttachmentDateFormatter::class)->register();
            $container->get(MediaGridDateFilter::class)->register();
            $container->get(AdminDateScript::class)->register();
        }
    }
}
