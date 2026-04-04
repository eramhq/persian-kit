<?php

namespace PersianKit\Tests\Unit\AdminFont;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\AdminFont\AdminFontModule;
use PersianKit\Container\ServiceContainer;

class AdminFontModuleTest extends TestCase
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

    private function makeModule(array $settings = []): AdminFontModule
    {
        $defaults = AdminFontModule::defaults();
        $merged = array_merge($defaults, $settings);

        $manager = Mockery::mock(SettingsManager::class);
        $manager->shouldReceive('module')
            ->andReturnUsing(function (string $key, ?string $subKey = null, mixed $default = null) use ($merged) {
                if ($subKey === null) {
                    return $merged;
                }
                return $merged[$subKey] ?? $default;
            });

        return new AdminFontModule($manager);
    }

    public function test_key_returns_admin_font(): void
    {
        $this->assertSame('admin_font', AdminFontModule::key());
    }

    public function test_defaults_includes_enabled_and_font(): void
    {
        $defaults = AdminFontModule::defaults();

        $this->assertArrayHasKey('enabled', $defaults);
        $this->assertArrayHasKey('font', $defaults);
        $this->assertTrue($defaults['enabled']);
        $this->assertSame('vazirmatn', $defaults['font']);
    }

    public function test_settings_view_returns_correct_path(): void
    {
        $module = $this->makeModule();

        $this->assertSame('admin/partials/admin-font-settings', $module->settingsView());
    }

    public function test_boot_registers_admin_enqueue_scripts_hook(): void
    {
        $module = $this->makeModule();
        $container = Mockery::mock(ServiceContainer::class);

        $module->boot($container);

        $this->assertTrue(has_action('admin_enqueue_scripts'));
    }

    public function test_enqueue_font_calls_wp_enqueue_style(): void
    {
        if (!defined('PERSIAN_KIT_URL')) {
            define('PERSIAN_KIT_URL', 'https://example.com/wp-content/plugins/persian-kit/');
        }
        if (!defined('PERSIAN_KIT_VERSION')) {
            define('PERSIAN_KIT_VERSION', '1.0.0');
        }

        Functions\expect('wp_enqueue_style')
            ->once()
            ->with(
                'persian-kit-admin-font',
                PERSIAN_KIT_URL . 'public/css/admin-font.css',
                [],
                PERSIAN_KIT_VERSION
            );

        Functions\expect('wp_add_inline_style')
            ->once()
            ->with(
                'persian-kit-admin-font',
                Mockery::type('string')
            );

        $module = $this->makeModule();
        $module->enqueueFont();

        $this->assertTrue(true); // Mockery expectations verified in tearDown
    }

    public function test_enqueue_font_inline_style_contains_css_variable(): void
    {
        $capturedCss = null;

        Functions\expect('wp_enqueue_style')->once();

        Functions\expect('wp_add_inline_style')
            ->once()
            ->with(
                'persian-kit-admin-font',
                Mockery::on(function (string $css) use (&$capturedCss) {
                    $capturedCss = $css;
                    return true;
                })
            );

        $module = $this->makeModule();
        $module->enqueueFont();

        $this->assertStringContainsString('--pk-admin-font', $capturedCss);
        $this->assertStringContainsString('Vazirmatn', $capturedCss);
    }
}
