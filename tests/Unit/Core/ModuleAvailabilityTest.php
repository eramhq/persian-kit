<?php

namespace PersianKit\Tests\Unit\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PHPUnit\Framework\TestCase;

class ModuleAvailabilityTest extends TestCase
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

    public function test_a_module_that_needs_no_plugin_is_always_available(): void
    {
        $module = $this->module([]);

        $this->assertTrue($module->isAvailable());
        $this->assertNull($module->unavailableReason());
        $this->assertSame([], $module->inactivePlugins());
        $this->assertNull($module::category());
    }

    public function test_an_active_plugin_makes_the_module_available(): void
    {
        $module = $this->module([['name' => 'Gravity Forms', 'check' => fn () => true, 'version' => fn () => '2.9.1', 'minVersion' => '2.7']]);

        $this->assertTrue($module->isAvailable());
        $this->assertNull($module->unavailableReason());
    }

    public function test_a_missing_plugin_is_not_active(): void
    {
        $module = $this->module([['name' => 'Gravity Forms', 'check' => fn () => false]]);

        $this->assertFalse($module->isAvailable());
        $this->assertSame(
            ['code' => AbstractModule::REASON_INACTIVE, 'message' => 'Gravity Forms is not active on this site.'],
            $module->unavailableReason()
        );
        $this->assertSame(['Gravity Forms'], $module->inactivePlugins());
    }

    public function test_an_older_version_than_the_module_needs_is_too_old(): void
    {
        $module = $this->module([['name' => 'Gravity Forms', 'check' => fn () => true, 'version' => fn () => '2.5', 'minVersion' => '2.7']]);

        $this->assertFalse($module->isAvailable());
        $this->assertSame(
            ['code' => AbstractModule::REASON_OUTDATED, 'message' => 'Needs Gravity Forms 2.7 or newer (this site has 2.5).'],
            $module->unavailableReason()
        );
        // It is active, just old.
        $this->assertSame([], $module->inactivePlugins());
    }

    public function test_an_unknown_version_is_not_held_against_the_plugin(): void
    {
        $module = $this->module([['name' => 'Gravity Forms', 'check' => fn () => true, 'version' => fn () => null, 'minVersion' => '2.7']]);

        $this->assertTrue($module->isAvailable());
    }

    public function test_a_missing_add_on_is_named(): void
    {
        $module = $this->module([
            ['name' => 'Elementor', 'check' => fn () => true],
            ['name' => 'Elementor Pro', 'check' => fn () => false],
        ]);

        $this->assertFalse($module->isAvailable());
        $this->assertSame(
            ['code' => AbstractModule::REASON_MISSING, 'message' => 'Needs Elementor Pro.'],
            $module->unavailableReason()
        );
        $this->assertSame(['Elementor Pro'], $module->inactivePlugins());
    }

    public function test_without_the_main_plugin_the_add_on_is_not_mentioned(): void
    {
        $module = $this->module([
            ['name' => 'Elementor', 'check' => fn () => false],
            ['name' => 'Elementor Pro', 'check' => fn () => false],
        ]);

        $this->assertSame(AbstractModule::REASON_INACTIVE, $module->unavailableReason()['code'] ?? null);
    }

    /**
     * @param list<array<string, mixed>> $plugins
     */
    private function module(array $plugins): AbstractModule
    {
        return new class (Mockery::mock(SettingsManager::class), $plugins) extends AbstractModule {
            /**
             * @param list<array<string, mixed>> $plugins
             */
            public function __construct(SettingsManager $settings, private array $plugins)
            {
                parent::__construct($settings);
            }

            public static function key(): string
            {
                return 'fake';
            }

            public static function defaults(): array
            {
                return ['enabled' => true];
            }

            public function requiredPlugins(): array
            {
                return $this->plugins;
            }

            public function register(ServiceContainer $container): void
            {
            }

            public function boot(ServiceContainer $container): void
            {
            }
        };
    }
}
