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
        return ['enabled' => true, 'global_conversion' => false, 'jalali_archives' => true, 'jalali_permalinks' => false];
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

        $container->register(JalaliPermalinks::class, function () {
            return new JalaliPermalinks();
        });

        $container->register(JalaliArchiveList::class, function () {
            return new JalaliArchiveList();
        });

        $container->register(JalaliCalendar::class, function () {
            return new JalaliCalendar();
        });

        $container->register(GregorianCalendarMonth::class, function () {
            return new GregorianCalendarMonth();
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
            'jalali_archives'   => !empty($values['jalali_archives']),
            'jalali_permalinks' => !empty($values['jalali_permalinks']),
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

        $permalinks = $container->get(JalaliPermalinks::class);
        $permalinks->register();
        if ($this->usesJalaliPermalinks()) {
            $permalinks->registerJalaliLinks();
        }

        if ($this->showsJalaliArchives()) {
            $container->get(JalaliArchiveList::class)->register();
            $container->get(JalaliCalendar::class)->register();
        } else {
            $container->get(GregorianCalendarMonth::class)->register();
        }

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

    /**
     * Whether the archive list and calendar show Jalali periods. The Jalali
     * archive pages work either way.
     */
    private function showsJalaliArchives(): bool
    {
        return (bool) $this->setting('jalali_archives') && (bool) apply_filters('persian_kit_jalali_archives', true);
    }

    /**
     * Whether post permalinks use Jalali dates. Addresses in either calendar
     * redirect to the current one whatever this returns.
     */
    private function usesJalaliPermalinks(): bool
    {
        return (bool) $this->setting('jalali_permalinks') && (bool) apply_filters('persian_kit_jalali_permalinks', true);
    }
}
