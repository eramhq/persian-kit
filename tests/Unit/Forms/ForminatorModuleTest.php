<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\Forms\ForminatorDateField;
use PersianKit\Modules\Forms\ForminatorModule;
use PHPUnit\Framework\TestCase;

class ForminatorModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Forminator counts as active when its version is defined.
        if (!defined('FORMINATOR_VERSION')) {
            define('FORMINATOR_VERSION', '1.57.3');
        }
        Functions\when('__')->returnArg();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_it_is_a_form_integration_on_by_default(): void
    {
        $this->assertSame('forminator', ForminatorModule::key());
        $this->assertSame('forms', ForminatorModule::category());
        $this->assertSame(['enabled' => true], ForminatorModule::defaults());
    }

    public function test_boot_registers_the_date_picker(): void
    {
        $this->assertSame([
            ForminatorDateField::class . '::register',
        ], $this->listCalls(fn (ForminatorModule $module, ServiceContainer $container) => $module->boot($container)));
    }

    public function test_it_needs_forminator_1_50_or_newer(): void
    {
        $plugin = $this->makeModule()->requiredPlugins()[0];

        $this->assertSame('Forminator', $plugin['name']);
        $this->assertSame('forminator', $plugin['slug']);
        $this->assertSame('1.50', $plugin['minVersion']);
        $this->assertTrue($this->makeModule()->isAvailable());

        $without = new class (Mockery::mock(SettingsManager::class)) extends ForminatorModule {
            protected function supportsForminator(): bool
            {
                return false;
            }
        };

        $this->assertFalse($without->isAvailable());
        $this->assertSame(AbstractModule::REASON_INACTIVE, $without->unavailableReason()['code'] ?? null);
    }

    public function test_emails_and_submissions_show_jalali_dates_while_date_conversion_is_on(): void
    {
        foreach ([true, false] as $dateConversion) {
            $factories = [];
            $container = Mockery::mock(ServiceContainer::class);
            $container->shouldReceive('register')->andReturnUsing(function (string $id, callable $factory) use (&$factories, &$container) {
                $factories[$id] = $factory;

                return $container;
            });

            $this->makeModule($dateConversion)->register($container);
            $field = $factories[ForminatorDateField::class]($container);

            $this->assertSame($dateConversion, (fn () => $this->jalaliDates)->call($field));
        }
    }

    private function makeModule(bool $dateConversion = true): ForminatorModule
    {
        $manager = Mockery::mock(SettingsManager::class);
        $manager->shouldReceive('module')->andReturnUsing(
            function (string $module, ?string $key = null, mixed $default = null) use ($dateConversion) {
                if ($module === 'date_conversion') {
                    return $key === 'enabled' ? $dateConversion : $default;
                }

                return $key === null ? ForminatorModule::defaults() : (ForminatorModule::defaults()[$key] ?? $default);
            }
        );

        return new ForminatorModule($manager);
    }

    /**
     * @param callable(ForminatorModule, ServiceContainer): void $boot
     * @return list<string> Service::method calls, in order.
     */
    private function listCalls(callable $boot): array
    {
        $calls = [];
        $container = Mockery::mock(ServiceContainer::class);
        $container->shouldReceive('get')->andReturnUsing(function (string $id) use (&$calls) {
            $service = Mockery::mock($id);
            $service->shouldReceive('register')->andReturnUsing(function () use (&$calls, $id) {
                $calls[] = $id . '::register';
            });

            return $service;
        });

        $boot($this->makeModule(), $container);

        return $calls;
    }
}
