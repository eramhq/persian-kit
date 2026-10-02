<?php

namespace PersianKit\Tests\Unit\CharNormalization;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\CharNormalization\CharNormalizationModule;
use PersianKit\Container\ServiceContainer;

class CharNormalizationModuleTest extends TestCase
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

    private function makeModule(array $settings = []): CharNormalizationModule
    {
        $defaults = CharNormalizationModule::defaults();
        $merged = array_merge($defaults, $settings);

        $manager = Mockery::mock(SettingsManager::class);
        $manager->shouldReceive('module')
            ->andReturnUsing(function (string $key, ?string $subKey = null, mixed $default = null) use ($merged) {
                if ($subKey === null) {
                    return $merged;
                }
                return $merged[$subKey] ?? $default;
            });

        return new CharNormalizationModule($manager);
    }

    public function test_key_returns_char_normalization(): void
    {
        $this->assertSame('char_normalization', CharNormalizationModule::key());
    }

    public function test_defaults_includes_enabled_and_teh_marbuta(): void
    {
        $defaults = CharNormalizationModule::defaults();

        $this->assertArrayHasKey('enabled', $defaults);
        $this->assertArrayHasKey('teh_marbuta', $defaults);
        $this->assertTrue($defaults['enabled']);
    }

    public function test_defaults_teh_marbuta_is_false(): void
    {
        $defaults = CharNormalizationModule::defaults();

        $this->assertFalse($defaults['teh_marbuta']);
    }

    public function test_settings_view_returns_correct_path(): void
    {
        $module = $this->makeModule();

        $this->assertSame('admin/partials/char-normalization-settings', $module->settingsView());
    }

    public function test_sanitize_settings_sets_missing_checkbox_to_false(): void
    {
        $module = $this->makeModule();

        $this->assertSame([
            'enabled'           => true,
            'normalize_on_save' => false,
            'teh_marbuta'       => false,
            'half_space_fix'    => false,
        ], $module->sanitizeSettings([
            'enabled' => true,
        ]));
    }

    /**
     * Register + boot the module with a container that builds its services.
     */
    private function bootModule(array $settings = ['normalize_on_save' => true]): void
    {
        Functions\expect('apply_filters')
            ->with('persian_kit_char_normalization', true, Mockery::type('string'))
            ->andReturn(true);

        $module = $this->makeModule($settings);
        $factories = [];
        $container = Mockery::mock(ServiceContainer::class);
        $container->shouldReceive('register')->andReturnUsing(function (string $id, callable $factory) use (&$factories, $container) {
            $factories[$id] = $factory;

            return $container;
        });
        $container->shouldReceive('get')->andReturnUsing(function (string $id) use (&$factories, $container) {
            return $factories[$id]($container);
        });

        $module->register($container);
        $module->boot($container);
    }

    public function test_boot_registers_wp_insert_post_data_filter(): void
    {
        $this->bootModule();

        $this->assertTrue(has_filter('wp_insert_post_data'));
        $this->assertTrue(has_filter('preprocess_comment'));
        $this->assertTrue(has_filter('pre_term_name'));
    }

    public function test_boot_leaves_saves_alone_by_default(): void
    {
        $this->bootModule([]);

        $this->assertFalse(has_filter('wp_insert_post_data'));
        $this->assertFalse(has_filter('preprocess_comment'));
        $this->assertFalse(has_filter('pre_term_name'));
    }

    public function test_boot_with_half_spaces_alone_fixes_posts_only(): void
    {
        $this->bootModule(['half_space_fix' => true]);

        $this->assertTrue(has_filter('wp_insert_post_data'));
        $this->assertFalse(has_filter('preprocess_comment'));
    }

    public function test_boot_registers_search_filter_without_rewriting_the_query(): void
    {
        $this->bootModule([]);

        $this->assertTrue(has_filter('posts_search'));
        $this->assertFalse(has_action('pre_get_posts'));
    }

    public function test_boot_registers_rest_api_init(): void
    {
        $this->bootModule();

        $this->assertTrue(has_action('rest_api_init'));
    }
}
