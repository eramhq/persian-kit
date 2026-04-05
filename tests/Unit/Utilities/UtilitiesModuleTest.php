<?php

namespace PersianKit\Tests\Unit\Utilities;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\Utilities\UtilitiesModule;
use PersianKit\Modules\Utilities\Slug;
use PersianKit\Container\ServiceContainer;

class UtilitiesModuleTest extends TestCase
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

    private function makeModule(array $settings = []): UtilitiesModule
    {
        $defaults = UtilitiesModule::defaults();
        $merged = array_merge($defaults, $settings);

        $manager = Mockery::mock(SettingsManager::class);
        $manager->shouldReceive('module')
            ->andReturnUsing(function (string $key, ?string $subKey = null, mixed $default = null) use ($merged) {
                if ($subKey === null) {
                    return $merged;
                }
                return $merged[$subKey] ?? $default;
            });

        return new UtilitiesModule($manager);
    }

    public function test_key_returns_utilities(): void
    {
        $this->assertSame('utilities', UtilitiesModule::key());
    }

    public function test_defaults_has_enabled_true(): void
    {
        $defaults = UtilitiesModule::defaults();
        $this->assertArrayHasKey('enabled', $defaults);
        $this->assertTrue($defaults['enabled']);
    }

    public function test_settings_view_returns_null(): void
    {
        $module = $this->makeModule();
        $this->assertNull($module->settingsView());
    }

    public function test_boot_removes_default_sanitize_title(): void
    {
        Functions\expect('apply_filters')
            ->once()
            ->with('persian_kit_utilities', true, 'sanitize_title')
            ->andReturn(true);

        Functions\expect('remove_filter')
            ->once()
            ->with('sanitize_title', 'sanitize_title_with_dashes', 10);

        Functions\expect('add_filter')
            ->once()
            ->with('sanitize_title', [Slug::class, 'sanitizeTitleFilter'], 10, 3);

        $module = $this->makeModule();
        $container = Mockery::mock(ServiceContainer::class);
        $module->boot($container);

        $this->assertTrue(true); // Mockery verifies expectations in tearDown
    }

    public function test_boot_registers_sanitize_title(): void
    {
        Functions\expect('apply_filters')
            ->once()
            ->with('persian_kit_utilities', true, 'sanitize_title')
            ->andReturn(true);

        Functions\expect('remove_filter')->once();

        Functions\expect('add_filter')
            ->once()
            ->with('sanitize_title', [Slug::class, 'sanitizeTitleFilter'], 10, 3);

        $module = $this->makeModule();
        $container = Mockery::mock(ServiceContainer::class);
        $module->boot($container);

        $this->assertTrue(true); // Mockery verifies expectations in tearDown
    }
}
