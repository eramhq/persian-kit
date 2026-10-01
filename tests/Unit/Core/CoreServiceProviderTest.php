<?php

namespace PersianKit\Tests\Unit\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\AdminPage;
use PersianKit\Core\ConflictDetector;
use PersianKit\Core\CoreServiceProvider;
use PersianKit\Core\SettingsRegistrar;
use PersianKit\Service\Assets\AssetManager;
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
            [ConflictDetector::class, SettingsRegistrar::class, AssetManager::class, AdminPage::class],
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

            return match ($id) {
                ConflictDetector::class => Mockery::mock(ConflictDetector::class)->shouldReceive('registerNotice')->once()->getMock(),
                SettingsRegistrar::class => Mockery::mock(SettingsRegistrar::class)->shouldReceive('register')->once()->getMock(),
                AdminPage::class        => Mockery::mock(AdminPage::class)->shouldReceive('register')->once()->getMock(),
                AssetManager::class     => Mockery::mock(AssetManager::class),
            };
        });

        (new CoreServiceProvider())->boot($container);

        return $fetched;
    }
}
