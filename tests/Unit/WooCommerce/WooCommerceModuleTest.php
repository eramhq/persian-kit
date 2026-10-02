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
use PersianKit\Modules\WooCommerce\IranianCurrencies;
use PersianKit\Modules\WooCommerce\NationalIdField;
use PersianKit\Modules\WooCommerce\OrderNumberInput;
use PersianKit\Modules\WooCommerce\ProvinceLimit;
use PersianKit\Modules\WooCommerce\SchemaPrices;
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
            SchemaPrices::class,
            WooDateDisplayFilter::class,
            CheckoutInputNormalizer::class,
            OrderNumberInput::class,
            CheckoutValidator::class,
            NationalIdField::class,
        ], $this->bootAndListFetched());
    }

    public function test_boot_in_admin_registers_admin_services(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $this->assertSame([
            SchemaPrices::class,
            WooDateDisplayFilter::class,
            CheckoutInputNormalizer::class,
            OrderNumberInput::class,
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
            SchemaPrices::class,
            WooDateDisplayFilter::class,
            NationalIdField::class,
            CityField::class,
        ], $this->bootAndListFetched(['checkout_normalize' => false, 'checkout_validate' => false, 'city_select' => true]));
    }

    public function test_admin_date_fields_have_their_own_option(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $this->assertSame([
            SchemaPrices::class,
            WooDateDisplayFilter::class,
            CheckoutInputNormalizer::class,
            OrderNumberInput::class,
            CheckoutValidator::class,
            NationalIdField::class,
        ], $this->bootAndListFetched(['dates_admin' => false]));
    }

    public function test_schema_prices_stay_while_the_module_is_off(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $this->assertSame([SchemaPrices::class], $this->bootAndListFetched([], 'bootDisabled'));
    }

    public function test_the_currencies_are_added_when_services_register_whether_the_module_is_on_or_off(): void
    {
        $currencies = Mockery::mock(IranianCurrencies::class);
        $currencies->shouldReceive('register')->once();
        $container = Mockery::mock(ServiceContainer::class);
        $container->shouldReceive('register');
        $container->shouldReceive('get')->once()->with(IranianCurrencies::class)->andReturn($currencies);

        $this->makeModule(['enabled' => false])->register($container);
        $this->addToAssertionCount(1);
    }

    public function test_inactive_plugins_names_woocommerce_when_it_is_missing(): void
    {
        Functions\when('__')->returnArg();
        $this->assertSame('commerce', WooCommerceModule::category());
        $this->assertSame([], $this->makeModule()->inactivePlugins());
        $this->assertTrue($this->makeModule()->isAvailable());

        $withoutWooCommerce = new class (Mockery::mock(SettingsManager::class)) extends WooCommerceModule {
            protected function supportsWooCommerce(): bool
            {
                return false;
            }
        };

        $this->assertSame(['WooCommerce'], $withoutWooCommerce->inactivePlugins());
        $this->assertFalse($withoutWooCommerce->isAvailable());
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
            'allowed_states'     => [],
            'dates_admin'        => true,
        ], $module->sanitizeSettings(['enabled' => '1', 'checkout_normalize' => '0', 'checkout_validate' => '1', 'national_id' => 'required', 'dates_admin' => '1']));

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
            'allowed_states'     => [],
            'dates_admin'        => true,
        ], WooCommerceModule::defaults());
    }

    public function test_sanitize_settings_keeps_valid_province_codes_once(): void
    {
        $module = $this->makeModule();

        // The hidden field sends '' with the ticked boxes.
        $this->assertSame(['THR', 'ABZ'], $module->sanitizeSettings(['allowed_states' => ['', 'THR', 'abz', 'THR', 'nope']])['allowed_states']);
        $this->assertSame([], $module->sanitizeSettings(['allowed_states' => ['']])['allowed_states']);
        $this->assertSame([], $module->sanitizeSettings(['allowed_states' => 'THR'])['allowed_states']);
    }

    public function test_chosen_provinces_turn_the_province_limit_on(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $this->assertNotContains(ProvinceLimit::class, $this->bootAndListFetched());
        $this->assertContains(ProvinceLimit::class, $this->bootAndListFetched(['allowed_states' => ['THR']]));
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
    private function bootAndListFetched(array $settings = [], string $method = 'boot'): array
    {
        $fetched = [];
        $container = Mockery::mock(ServiceContainer::class);
        $container->shouldReceive('get')->andReturnUsing(function (string $id) use (&$fetched) {
            $fetched[] = $id;
            $service = Mockery::mock($id);
            $service->shouldReceive('register')->once();

            return $service;
        });

        $this->makeModule($settings)->{$method}($container);

        return $fetched;
    }
}
