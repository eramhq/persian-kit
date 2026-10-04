<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\Forms\GravityFormsDateField;
use PersianKit\Modules\Forms\GravityFormsInputNormalizer;
use PersianKit\Modules\Forms\GravityFormsModule;
use PHPUnit\Framework\TestCase;

class GravityFormsModuleTest extends TestCase
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
        $this->assertSame('gravityforms', GravityFormsModule::key());
        $this->assertSame('forms', GravityFormsModule::category());
        $this->assertSame(['enabled' => true], GravityFormsModule::defaults());
    }

    public function test_boot_registers_the_dates_and_the_digits(): void
    {
        $this->assertSame([
            GravityFormsDateField::class . '::register',
            GravityFormsInputNormalizer::class . '::register',
        ], $this->listCalls(fn (GravityFormsModule $module, ServiceContainer $container) => $module->boot($container)));
    }

    public function test_it_needs_gravity_forms_2_9_or_newer_from_its_own_website(): void
    {
        $plugin = $this->makeModule()->requiredPlugins()[0];

        $this->assertSame('Gravity Forms', $plugin['name']);
        $this->assertSame('https://www.gravityforms.com/', $plugin['url']);
        $this->assertArrayNotHasKey('slug', $plugin);
        $this->assertSame('2.9', $plugin['minVersion']);

        $without = new class (Mockery::mock(SettingsManager::class)) extends GravityFormsModule {
            protected function supportsGravityForms(): bool
            {
                return false;
            }
        };

        $this->assertFalse($without->isAvailable());
        $this->assertSame(AbstractModule::REASON_INACTIVE, $without->unavailableReason()['code'] ?? null);
    }

    public function test_entries_and_merge_tags_show_jalali_dates_while_date_conversion_is_on(): void
    {
        foreach ([true, false] as $dateConversion) {
            $factories = [];
            $container = Mockery::mock(ServiceContainer::class);
            $container->shouldReceive('register')->andReturnUsing(function (string $id, callable $factory) use (&$factories, &$container) {
                $factories[$id] = $factory;

                return $container;
            });

            $this->makeModule($dateConversion)->register($container);
            $field = $factories[GravityFormsDateField::class]($container);

            $this->assertSame($dateConversion, (fn () => $this->jalaliDates)->call($field));
        }
    }

    protected function makeModule(bool $dateConversion = true): GravityFormsModule
    {
        $manager = Mockery::mock(SettingsManager::class);
        $manager->shouldReceive('module')->andReturnUsing(
            function (string $module, ?string $key = null, mixed $default = null) use ($dateConversion) {
                if ($module === 'date_conversion') {
                    return $key === 'enabled' ? $dateConversion : $default;
                }

                return $key === null ? GravityFormsModule::defaults() : (GravityFormsModule::defaults()[$key] ?? $default);
            }
        );

        return new GravityFormsModule($manager);
    }

    /**
     * @param callable(GravityFormsModule, ServiceContainer): void $boot
     * @return list<string> Service::method calls, in order.
     */
    protected function listCalls(callable $boot): array
    {
        $calls = [];
        $container = Mockery::mock(ServiceContainer::class);
        $container->shouldReceive('get')->andReturnUsing(function (string $id) use (&$calls) {
            $service = Mockery::mock($id);
            foreach (['register', 'registerFallback'] as $method) {
                $service->shouldReceive($method)->andReturnUsing(function () use (&$calls, $id, $method) {
                    $calls[] = $id . '::' . $method;
                });
            }

            return $service;
        });

        $boot($this->makeModule(), $container);

        return $calls;
    }
}
