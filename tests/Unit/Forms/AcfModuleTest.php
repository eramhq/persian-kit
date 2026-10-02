<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\Forms\AcfDateFields;
use PersianKit\Modules\Forms\AcfModule;
use PHPUnit\Framework\TestCase;

class AcfModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('__')->returnArg();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_it_is_a_form_integration_on_by_default(): void
    {
        $this->assertSame('acf', AcfModule::key());
        $this->assertSame('forms', AcfModule::category());
        $this->assertSame(['enabled' => true], AcfModule::defaults());
    }

    public function test_boot_registers_the_date_fields(): void
    {
        $container = Mockery::mock(ServiceContainer::class);
        $container->shouldReceive('get')->once()->with(AcfDateFields::class)->andReturn(
            Mockery::mock(AcfDateFields::class)->shouldReceive('register')->once()->getMock()
        );

        $this->makeModule()->boot($container);
        $this->addToAssertionCount(1);
    }

    public function test_it_is_available_while_acf_is_active(): void
    {
        // ACF counts as active when its field type API exists.
        Functions\when('acf_get_field_type')->justReturn(null);
        $this->assertTrue($this->makeModule()->isAvailable());

        $without = new class (Mockery::mock(SettingsManager::class)) extends AcfModule {
            protected function supportsAcf(): bool
            {
                return false;
            }
        };
        $this->assertFalse($without->isAvailable());
        $this->assertSame(['ACF'], $without->inactivePlugins());
    }

    public function test_templates_show_jalali_dates_while_date_conversion_is_on(): void
    {
        foreach ([true, false] as $dateConversion) {
            $factories = [];
            $container = Mockery::mock(ServiceContainer::class);
            $container->shouldReceive('register')->andReturnUsing(function (string $id, callable $factory) use (&$factories, &$container) {
                $factories[$id] = $factory;

                return $container;
            });

            $this->makeModule($dateConversion)->register($container);
            $fields = $factories[AcfDateFields::class]($container);

            $this->assertSame($dateConversion, (fn () => $this->jalaliValues)->call($fields));
        }
    }

    private function makeModule(bool $dateConversion = true): AcfModule
    {
        $manager = Mockery::mock(SettingsManager::class);
        $manager->shouldReceive('module')->andReturnUsing(
            fn (string $module, ?string $key = null, mixed $default = null) => $module === 'date_conversion' && $key === 'enabled'
                ? $dateConversion
                : $default
        );

        return new AcfModule($manager);
    }
}
