<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\WooCommerce\CheckoutInputNormalizer;
use PersianKit\Modules\WooCommerce\CheckoutValidator;
use PersianKit\Modules\WooCommerce\CityField;
use PersianKit\Modules\WooCommerce\NationalIdField;
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

    public function test_boot_outside_admin_registers_front_end_services_only(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $this->assertSame([
            WooDateDisplayFilter::class,
            CheckoutInputNormalizer::class,
            CheckoutValidator::class,
            NationalIdField::class,
        ], $this->bootAndListFetched());
    }

    public function test_boot_in_admin_registers_admin_services(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $this->assertSame([
            WooDateDisplayFilter::class,
            CheckoutInputNormalizer::class,
            CheckoutValidator::class,
            NationalIdField::class,
            WooOrderMonthFilter::class,
            WooAdminDateFields::class,
            WooPostedDateNormalizer::class,
        ], $this->bootAndListFetched());
    }

    public function test_checkout_options_turn_their_services_on_and_off(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $this->assertSame([
            WooDateDisplayFilter::class,
            NationalIdField::class,
            CityField::class,
        ], $this->bootAndListFetched(['checkout_normalize' => false, 'checkout_validate' => false, 'city_select' => true]));
    }

    public function test_sanitize_settings_keeps_known_national_id_modes_only(): void
    {
        $module = $this->makeModule();

        $this->assertSame([
            'enabled'            => true,
            'checkout_normalize' => false,
            'checkout_validate'  => true,
            'national_id'        => 'required',
            'city_select'        => false,
        ], $module->sanitizeSettings(['enabled' => '1', 'checkout_normalize' => '0', 'checkout_validate' => '1', 'national_id' => 'required']));

        $this->assertSame('off', $module->sanitizeSettings(['national_id' => 'always'])['national_id']);
    }

    public function test_new_checkout_settings_default_to_fixing_and_checking_only(): void
    {
        $this->assertSame([
            'enabled'            => true,
            'checkout_normalize' => true,
            'checkout_validate'  => true,
            'national_id'        => 'off',
            'city_select'        => false,
        ], WooCommerceModule::defaults());
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function makeModule(array $settings = []): WooCommerceModule
    {
        $merged = array_replace(WooCommerceModule::defaults(), $settings);
        $manager = Mockery::mock(SettingsManager::class);
        $manager->shouldReceive('module')->andReturnUsing(
            fn (string $module, ?string $key = null, mixed $default = null) => $key === null ? $merged : ($merged[$key] ?? $default)
        );

        return new WooCommerceModule($manager);
    }

    /**
     * @param array<string, mixed> $settings
     * @return list<string> Service ids fetched from the container, in order.
     */
    private function bootAndListFetched(array $settings = []): array
    {
        $fetched = [];
        $container = Mockery::mock(ServiceContainer::class);
        $container->shouldReceive('get')->andReturnUsing(function (string $id) use (&$fetched) {
            $fetched[] = $id;
            $service = Mockery::mock($id);
            $service->shouldReceive('register')->once();

            return $service;
        });

        $this->makeModule($settings)->boot($container);

        return $fetched;
    }
}
