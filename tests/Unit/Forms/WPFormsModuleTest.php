<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\Forms\WPFormsIranianFields;
use PersianKit\Modules\Forms\WPFormsModule;
use PHPUnit\Framework\TestCase;

class WPFormsModuleTest extends TestCase
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
        $this->assertSame('wpforms', WPFormsModule::key());
        $this->assertSame('forms', WPFormsModule::category());
        $this->assertSame(['enabled' => true], WPFormsModule::defaults());
    }

    public function test_boot_registers_the_fields(): void
    {
        $this->assertSame([
            WPFormsIranianFields::class . '::register',
        ], $this->listCalls(fn (WPFormsModule $module, ServiceContainer $container) => $module->boot($container)));
    }

    public function test_turned_off_the_fields_stay(): void
    {
        $this->assertSame([
            WPFormsIranianFields::class . '::registerFallback',
        ], $this->listCalls(fn (WPFormsModule $module, ServiceContainer $container) => $module->bootDisabled($container)));
    }

    public function test_it_needs_wpforms_1_9_from_wordpress_org(): void
    {
        $plugin = $this->makeModule()->requiredPlugins()[0];

        $this->assertSame('WPForms', $plugin['name']);
        $this->assertSame('wpforms-lite', $plugin['slug']);
        $this->assertArrayNotHasKey('url', $plugin);
        $this->assertSame('1.9.1', $plugin['minVersion']);

        $without = new class (Mockery::mock(SettingsManager::class)) extends WPFormsModule {
            protected function supportsWPForms(): bool
            {
                return false;
            }
        };

        $this->assertFalse($without->isAvailable());
        $this->assertSame(AbstractModule::REASON_INACTIVE, $without->unavailableReason()['code'] ?? null);
    }

    protected function makeModule(): WPFormsModule
    {
        $manager = Mockery::mock(SettingsManager::class);
        $manager->shouldReceive('module')->andReturnUsing(
            fn (string $module, ?string $key = null, mixed $default = null) => $key === null ? WPFormsModule::defaults() : (WPFormsModule::defaults()[$key] ?? $default)
        );

        // WPForms counts as active.
        return new class ($manager) extends WPFormsModule {
            protected function supportsWPForms(): bool
            {
                return true;
            }
        };
    }

    /**
     * @param callable(WPFormsModule, ServiceContainer): void $boot
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
