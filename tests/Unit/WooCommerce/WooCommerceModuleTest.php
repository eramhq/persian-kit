<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\WooCommerce\WooAdminDateFields;
use PersianKit\Modules\WooCommerce\WooCommerceModule;
use PersianKit\Modules\WooCommerce\WooDateDisplayFilter;
use PersianKit\Modules\WooCommerce\WooOrderMonthFilter;
use PersianKit\Modules\WooCommerce\WooPostedDateNormalizer;
use PHPUnit\Framework\TestCase;

class WooCommerceModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        // WooCommerce counts as active when wc_get_orders() exists.
        Functions\when('wc_get_orders')->justReturn([]);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_boot_outside_admin_registers_front_end_dates_only(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $this->assertSame([WooDateDisplayFilter::class], $this->bootAndListFetched());
    }

    public function test_boot_in_admin_registers_admin_services(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $this->assertSame([
            WooDateDisplayFilter::class,
            WooOrderMonthFilter::class,
            WooAdminDateFields::class,
            WooPostedDateNormalizer::class,
        ], $this->bootAndListFetched());
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
            $service = Mockery::mock($id);
            $service->shouldReceive('register')->once();

            return $service;
        });

        (new WooCommerceModule(Mockery::mock(SettingsManager::class)))->boot($container);

        return $fetched;
    }
}
