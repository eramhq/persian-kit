<?php

namespace PersianKit\Tests\Unit\AdminFont;

use PHPUnit\Framework\Attributes\DataProvider;
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
        Functions\when('get_user_locale')->justReturn('fa_IR');
        Functions\when('is_rtl')->justReturn(true);
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

        $this->assertStringContainsString('--persian-kit-admin-font', $capturedCss);
        $this->assertStringContainsString('Vazirmatn', $capturedCss);
    }

    public function test_enqueue_font_skips_non_persian_ltr_admin(): void
    {
        Functions\when('get_user_locale')->justReturn('en_US');
        Functions\when('is_rtl')->justReturn(false);
        Functions\expect('wp_enqueue_style')->never();

        $this->makeModule()->enqueueFont();

        $this->assertTrue(true);
    }

    public function test_enqueue_font_loads_for_rtl_admin_in_other_languages(): void
    {
        Functions\when('get_user_locale')->justReturn('ar');
        Functions\expect('wp_enqueue_style')->once();
        Functions\expect('wp_add_inline_style')->once();

        $this->makeModule()->enqueueFont();

        $this->assertTrue(true);
    }

    public function test_sanitize_settings_falls_back_to_known_font(): void
    {
        $module = $this->makeModule();

        $this->assertSame([
            'enabled' => true,
            'font'    => 'vazirmatn',
        ], $module->sanitizeSettings([
            'enabled' => true,
            'font'    => 'unknown-font',
        ]));
    }

    #[DataProvider('bundledFontProvider')]
    public function test_sanitize_settings_keeps_bundled_font(string $font): void
    {
        $this->assertSame(
            ['enabled' => true, 'font' => $font],
            $this->makeModule()->sanitizeSettings(['enabled' => true, 'font' => $font])
        );
    }

    #[DataProvider('bundledFontProvider')]
    public function test_enqueue_font_inline_style_uses_chosen_font(string $font, string $family): void
    {
        $capturedCss = null;

        Functions\expect('wp_enqueue_style')->once();
        Functions\expect('wp_add_inline_style')
            ->once()
            ->with('persian-kit-admin-font', Mockery::on(function (string $css) use (&$capturedCss) {
                $capturedCss = $css;
                return true;
            }));

        $this->makeModule(['font' => $font])->enqueueFont();

        $this->assertSame(":root { --persian-kit-admin-font: '{$family}'; }", $capturedCss);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function bundledFontProvider(): array
    {
        return [
            'Vazirmatn'            => ['vazirmatn', 'Vazirmatn'],
            'Noto Sans Arabic'     => ['noto-sans-arabic', 'Noto Sans Arabic'],
            'IBM Plex Sans Arabic' => ['ibm-plex-sans-arabic', 'IBM Plex Sans Arabic'],
        ];
    }

    public function test_enqueue_font_falls_back_to_vazirmatn_for_unknown_stored_font(): void
    {
        $capturedCss = null;

        Functions\expect('wp_enqueue_style')->once();
        Functions\expect('wp_add_inline_style')
            ->once()
            ->with('persian-kit-admin-font', Mockery::on(function (string $css) use (&$capturedCss) {
                $capturedCss = $css;
                return true;
            }));

        $this->makeModule(['font' => 'comic-sans'])->enqueueFont();

        $this->assertSame(":root { --persian-kit-admin-font: 'Vazirmatn'; }", $capturedCss);
    }
}
