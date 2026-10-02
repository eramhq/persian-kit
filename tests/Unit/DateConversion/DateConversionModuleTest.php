<?php

namespace PersianKit\Tests\Unit\DateConversion;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\DateConversion\AdminDateScript;
use PersianKit\Modules\DateConversion\DateArchiveFilter;
use PersianKit\Modules\DateConversion\DateConversionModule;
use PersianKit\Modules\DateConversion\DateFilters;
use PersianKit\Modules\DateConversion\GregorianCalendarMonth;
use PersianKit\Modules\DateConversion\JalaliArchiveList;
use PersianKit\Modules\DateConversion\JalaliCalendar;
use PersianKit\Modules\DateConversion\JalaliDateArchive;
use PersianKit\Modules\DateConversion\JalaliPermalinks;
use PersianKit\Modules\DateConversion\MediaAttachmentDateFormatter;
use PersianKit\Modules\DateConversion\MediaGridDateFilter;
use PersianKit\Modules\DateConversion\PostTypeMonthFilter;
use PersianKit\Modules\DateConversion\RestApiExtension;
use PersianKit\Service\Language\ContentLanguage;
use PersianKit\Tests\Unit\Support\UsesLanguages;
use PHPUnit\Framework\TestCase;

class DateConversionModuleTest extends TestCase
{
    use UsesLanguages;

    private const FRONT_SERVICES = [
        DateFilters::class,
        DateArchiveFilter::class,
        JalaliDateArchive::class,
        JalaliPermalinks::class,
        JalaliArchiveList::class,
        JalaliCalendar::class,
        RestApiExtension::class,
    ];

    private const ADMIN_SERVICES = [
        MediaAttachmentDateFormatter::class,
        PostTypeMonthFilter::class,
        MediaGridDateFilter::class,
        AdminDateScript::class,
    ];

    private int $jalaliLinksRegistered = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        ContentLanguage::reset();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_boot_outside_admin_skips_admin_services(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $this->assertSame(
            self::FRONT_SERVICES,
            $this->bootAndListFetched()
        );
    }

    public function test_boot_in_admin_registers_admin_services(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $this->assertSame(
            array_merge(self::FRONT_SERVICES, self::ADMIN_SERVICES),
            $this->bootAndListFetched()
        );
    }

    public function test_admins_whose_language_is_not_persian_get_no_jalali_admin_screens(): void
    {
        $this->inLanguage('en_US', true);

        $this->assertSame(
            array_merge(self::FRONT_SERVICES, [MediaAttachmentDateFormatter::class]),
            $this->bootAndListFetched()
        );
    }

    public function test_admins_whose_language_is_persian_get_jalali_admin_screens(): void
    {
        $this->inLanguage('fa_IR', true);

        $this->assertSame(
            array_merge(self::FRONT_SERVICES, self::ADMIN_SERVICES),
            $this->bootAndListFetched()
        );
    }

    public function test_boot_skips_the_jalali_archive_list_when_the_setting_is_off(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $fetched = $this->bootAndListFetched(['jalali_archives' => false]);
        $this->assertNotContains(JalaliArchiveList::class, $fetched);
        $this->assertNotContains(JalaliCalendar::class, $fetched);
        $this->assertContains(JalaliDateArchive::class, $fetched);
        $this->assertContains(GregorianCalendarMonth::class, $fetched);
    }

    public function test_boot_skips_the_jalali_archive_list_when_filtered_off(): void
    {
        Functions\when('is_admin')->justReturn(false);
        Filters\expectApplied('persian_kit_jalali_archives')->once()->with(true)->andReturn(false);

        $fetched = $this->bootAndListFetched();
        $this->assertNotContains(JalaliArchiveList::class, $fetched);
        $this->assertNotContains(JalaliCalendar::class, $fetched);
    }

    public function test_boot_registers_redirects_but_gregorian_links_by_default(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $this->bootAndListFetched();
        $this->assertSame(0, $this->jalaliLinksRegistered);
    }

    public function test_boot_registers_jalali_links_when_the_setting_is_on(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $this->bootAndListFetched(['jalali_permalinks' => true]);
        $this->assertSame(1, $this->jalaliLinksRegistered);
    }

    public function test_boot_skips_jalali_links_when_filtered_off(): void
    {
        Functions\when('is_admin')->justReturn(false);
        Filters\expectApplied('persian_kit_jalali_permalinks')->once()->with(true)->andReturn(false);

        $this->bootAndListFetched(['jalali_permalinks' => true]);
        $this->assertSame(0, $this->jalaliLinksRegistered);
    }

    /**
     * @param array<string, mixed> $settings
     * @return list<string> Service ids fetched from the container, in order.
     */
    private function bootAndListFetched(array $settings = []): array
    {
        $fetched = [];
        $this->jalaliLinksRegistered = 0;
        $container = Mockery::mock(ServiceContainer::class);
        $container->shouldReceive('get')->andReturnUsing(function (string $id) use (&$fetched) {
            $fetched[] = $id;

            if ($id === DateFilters::class) {
                $filters = Mockery::mock(DateFilters::class);
                $filters->shouldReceive('registerTier1', 'registerTier2', 'registerAdminFilters')->once();

                return $filters;
            }

            $service = Mockery::mock($id);
            $service->shouldReceive('register')->once();

            if ($id === JalaliPermalinks::class) {
                $service->shouldReceive('registerJalaliLinks')->andReturnUsing(function () {
                    $this->jalaliLinksRegistered++;
                });
            }

            return $service;
        });

        $this->makeModule($settings)->boot($container);

        return $fetched;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function makeModule(array $settings = []): DateConversionModule
    {
        $manager = Mockery::mock(SettingsManager::class);
        $manager->shouldReceive('module')->andReturnUsing(function (string $key, ?string $subKey = null, mixed $default = null) use ($settings) {
            $defaults = array_replace(DateConversionModule::defaults(), $settings);

            if ($subKey === null) {
                return $defaults;
            }

            return $defaults[$subKey] ?? $default;
        });

        return new DateConversionModule($manager);
    }

    public function test_sanitize_settings_sets_missing_checkbox_to_false(): void
    {
        $module = $this->makeModule();

        $this->assertSame([
            'enabled'           => true,
            'global_conversion' => false,
            'jalali_archives'   => false,
            'jalali_permalinks' => false,
        ], $module->sanitizeSettings([
            'enabled' => true,
        ]));
    }

    public function test_sanitize_settings_accepts_enabled_global_conversion(): void
    {
        $module = $this->makeModule();

        $this->assertSame([
            'enabled'           => true,
            'global_conversion' => true,
            'jalali_archives'   => true,
            'jalali_permalinks' => true,
        ], $module->sanitizeSettings([
            'enabled'           => true,
            'global_conversion' => '1',
            'jalali_archives'   => '1',
            'jalali_permalinks' => '1',
        ]));
    }

    public function test_abstract_sanitize_settings_drops_unknown_keys(): void
    {
        $manager = Mockery::mock(SettingsManager::class);
        $manager->shouldReceive('module')->andReturn([]);

        $module = new class($manager) extends DateConversionModule {
            public function sanitizeSettings(array $values): array
            {
                return parent::sanitizeSettings($values);
            }
        };

        $this->assertSame([
            'enabled'           => true,
            'global_conversion' => false,
            'jalali_archives'   => false,
            'jalali_permalinks' => false,
        ], $module->sanitizeSettings([
            'enabled'           => true,
            'global_conversion' => false,
            'jalali_archives'   => '0',
            'unexpected'        => 'value',
        ]));
    }
}
