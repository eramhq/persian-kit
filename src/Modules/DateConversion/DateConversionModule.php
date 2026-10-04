<?php

namespace PersianKit\Modules\DateConversion;

use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;
use PersianKit\Service\Language\ContentLanguage;

defined('ABSPATH') || exit;

class DateConversionModule extends AbstractModule
{
    public static function key(): string
    {
        return 'date_conversion';
    }

    public static function label(): string
    {
        return __('Jalali dates', 'persian-kit');
    }

    public static function description(): string
    {
        return __('Post, comment and admin dates in the Shamsi calendar. Saved dates stay Gregorian.', 'persian-kit');
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled'             => true,
            'global_conversion'   => false,
            'jalali_archives'     => true,
            'jalali_permalinks'   => false,
            'gregorian_date'      => false,
            'gregorian_style'     => 'numeric',
            'gregorian_order'     => 'jalali_first',
            'gregorian_separator' => 'parentheses',
        ];
    }

    public function register(ServiceContainer $container): void
    {
        $container->register(GregorianCompanion::class, function () {
            return new GregorianCompanion(
                (bool) $this->setting('gregorian_date'),
                (string) $this->setting('gregorian_style'),
                (string) $this->setting('gregorian_order'),
                (string) $this->setting('gregorian_separator')
            );
        });

        $container->register(DateFilters::class, function (ServiceContainer $container) {
            return new DateFilters((bool) $this->setting('global_conversion', false), $container->get(GregorianCompanion::class));
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

        $container->register(LegacyJalaliUrls::class, function (ServiceContainer $container) {
            return new LegacyJalaliUrls($container->get(JalaliDateArchive::class), $container->get(JalaliPermalinks::class));
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
            'enabled'             => !empty($values['enabled']),
            'global_conversion'   => !empty($values['global_conversion']),
            'jalali_archives'     => !empty($values['jalali_archives']),
            'jalali_permalinks'   => !empty($values['jalali_permalinks']),
            'gregorian_date'      => !empty($values['gregorian_date']),
            'gregorian_style'     => self::choice($values['gregorian_style'] ?? null, GregorianCompanion::STYLES),
            'gregorian_order'     => self::choice($values['gregorian_order'] ?? null, GregorianCompanion::ORDERS),
            'gregorian_separator' => self::choice($values['gregorian_separator'] ?? null, GregorianCompanion::SEPARATORS),
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
            // Also used by admin-ajax from the front end, so it checks the
            // language as it runs.
            $container->get(MediaAttachmentDateFormatter::class)->register();

            // In the admin, the language is the admin's own, known already.
            if (ContentLanguage::displaysPersian()) {
                $container->get(PostTypeMonthFilter::class)->register();
                $container->get(MediaGridDateFilter::class)->register();
                $container->get(AdminDateScript::class)->register();
            }
        }
    }

    /**
     * Jalali post links and archive addresses keep working while the module
     * is off: links from Parsi Date, WP Jalali or this module redirect to
     * the Gregorian ones.
     */
    public function bootDisabled(ServiceContainer $container): void
    {
        $container->get(LegacyJalaliUrls::class)->register();
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

    /**
     * $value when it is one of $choices, otherwise the first (the default).
     *
     * @param list<string> $choices
     */
    private static function choice(mixed $value, array $choices): string
    {
        return is_string($value) && in_array($value, $choices, true) ? $value : $choices[0];
    }
}
