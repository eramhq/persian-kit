<?php

namespace PersianKit\Tests\Unit\DateConversion;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\DateConversion\AdminDateScript;
use PersianKit\Modules\DateConversion\DateArchiveFilter;
use PersianKit\Modules\DateConversion\DateConversionModule;
use PersianKit\Modules\DateConversion\DateFilters;
use PersianKit\Modules\DateConversion\MediaAttachmentDateFormatter;
use PersianKit\Modules\DateConversion\MediaGridDateFilter;
use PersianKit\Modules\DateConversion\PostTypeMonthFilter;
use PersianKit\Modules\DateConversion\RestApiExtension;
use PHPUnit\Framework\TestCase;

class DateConversionModuleTest extends TestCase
{
    private const ADMIN_SERVICES = [
        PostTypeMonthFilter::class,
        MediaAttachmentDateFormatter::class,
        MediaGridDateFilter::class,
        AdminDateScript::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
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
            [DateFilters::class, DateArchiveFilter::class, RestApiExtension::class],
            $this->bootAndListFetched()
        );
    }

    public function test_boot_in_admin_registers_admin_services(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $this->assertSame(
            array_merge([DateFilters::class, DateArchiveFilter::class, RestApiExtension::class], self::ADMIN_SERVICES),
            $this->bootAndListFetched()
        );
    }

    /**
     * @return list<string> Service ids fetched from the container, in order.
     */
    private function bootAndListFetched(): array
    {
        $fetched = [];
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

            return $service;
        });

        $this->makeModule()->boot($container);

        return $fetched;
    }

    private function makeModule(): DateConversionModule
    {
        $manager = Mockery::mock(SettingsManager::class);
        $manager->shouldReceive('module')->andReturnUsing(function (string $key, ?string $subKey = null, mixed $default = null) {
            $defaults = DateConversionModule::defaults();

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
        ], $module->sanitizeSettings([
            'enabled'           => true,
            'global_conversion' => '1',
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
        ], $module->sanitizeSettings([
            'enabled'           => true,
            'global_conversion' => false,
            'unexpected'        => 'value',
        ]));
    }
}
