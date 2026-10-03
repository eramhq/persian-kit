<?php

namespace PersianKit\Tests\Unit\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Container\ServiceContainer;
use PersianKit\Contracts\ModuleInterface;
use PersianKit\Core\AdminPage;
use PersianKit\Core\ConflictDetector;
use PersianKit\Core\CoreServiceProvider;
use PersianKit\Core\SettingsRegistrar;
use PersianKit\Service\Assets\AssetManager;
use PersianKit\Service\Import\ReportDownload;
use PHPUnit\Framework\TestCase;

class CoreServiceProviderTest extends TestCase
{
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

    public function test_boot_outside_admin_registers_the_setting_but_skips_settings_page_and_assets(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $this->assertSame([ConflictDetector::class, SettingsRegistrar::class], $this->bootAndListFetched());
    }

    public function test_boot_in_admin_loads_settings_page_and_assets(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $this->assertSame(
            [ConflictDetector::class, ReportDownload::class, SettingsRegistrar::class, AssetManager::class, AdminPage::class],
            $this->bootAndListFetched()
        );
    }

    public function test_modules_boot_only_while_their_plugins_are_available(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $calls = [];
        $module = function (string $name, bool $available, bool $enabled) use (&$calls): ModuleInterface {
            $module = Mockery::mock(ModuleInterface::class);
            $module->shouldReceive('isAvailable')->andReturn($available);
            $module->shouldReceive('isEnabled')->andReturn($enabled);
            $module->shouldReceive('boot')->andReturnUsing(function () use (&$calls, $name) {
                $calls[] = "$name:boot";
            });
            $module->shouldReceive('bootDisabled')->andReturnUsing(function () use (&$calls, $name) {
                $calls[] = "$name:bootDisabled";
            });

            return $module;
        };

        $provider = new CoreServiceProvider();
        (fn (array $modules) => $this->modules = $modules)->call($provider, [
            $module('on', true, true),
            $module('off', true, false),
            $module('missing-on', false, true),
            $module('missing-off', false, false),
        ]);

        $container = Mockery::mock(ServiceContainer::class);
        $container->shouldReceive('get')->andReturnUsing(fn (string $id) => match ($id) {
            ConflictDetector::class  => Mockery::mock(ConflictDetector::class)->shouldReceive('registerNotice')->getMock(),
            SettingsRegistrar::class => Mockery::mock(SettingsRegistrar::class)->shouldReceive('register')->getMock(),
        });

        $provider->boot($container);

        $this->assertSame(['on:boot', 'off:bootDisabled'], $calls);
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

            return match ($id) {
                ConflictDetector::class => Mockery::mock(ConflictDetector::class)->shouldReceive('registerNotice')->once()->getMock(),
                SettingsRegistrar::class => Mockery::mock(SettingsRegistrar::class)->shouldReceive('register')->once()->getMock(),
                AdminPage::class        => Mockery::mock(AdminPage::class)->shouldReceive('register')->once()->getMock(),
                AssetManager::class     => Mockery::mock(AssetManager::class),
                ReportDownload::class   => Mockery::mock(ReportDownload::class)->shouldReceive('register')->once()->getMock(),
            };
        });

        (new CoreServiceProvider())->boot($container);

        return $fetched;
    }
}
