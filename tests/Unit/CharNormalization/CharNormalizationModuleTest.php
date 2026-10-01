<?php

namespace PersianKit\Tests\Unit\CharNormalization;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Brain\Monkey\Filters;
use Mockery;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\CharNormalization\CharNormalizationModule;
use PersianKit\Container\ServiceContainer;
use PersianKit\Dependencies\Eram\Abzar\Text\CharNormalizer;
use PersianKit\Tests\Unit\Support\FailsPcre;

class CharNormalizationModuleTest extends TestCase
{
    use FailsPcre;

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
            'enabled'     => true,
            'teh_marbuta' => false,
        ], $module->sanitizeSettings([
            'enabled' => true,
        ]));
    }

    /**
     * Register + boot the module with a mocked container.
     */
    private function bootModule(): void
    {
        Functions\expect('apply_filters')
            ->with('persian_kit_char_normalization', true, Mockery::type('string'))
            ->andReturn(true);

        $module = $this->makeModule();
        $container = Mockery::mock(ServiceContainer::class);
        $container->shouldReceive('register')->andReturnSelf();
        $container->shouldReceive('get')->andReturn(Mockery::mock());

        $module->register($container);
        $module->boot($container);
    }

    public function test_boot_registers_wp_insert_post_data_filter(): void
    {
        $this->bootModule();

        $this->assertTrue(has_filter('wp_insert_post_data'));
    }

    public function test_boot_registers_pre_get_posts_action(): void
    {
        $this->bootModule();

        $this->assertTrue(has_action('pre_get_posts'));
    }

    public function test_boot_registers_rest_api_init(): void
    {
        $this->bootModule();

        $this->assertTrue(has_action('rest_api_init'));
    }

    public function test_insert_post_data_saves_unsegmentable_content_unchanged(): void
    {
        $callback = null;
        Filters\expectAdded('wp_insert_post_data')->once()->whenHappen(function ($cb) use (&$callback) {
            $callback = $cb;
        });
        Functions\when('get_post_type_object')->justReturn((object) ['public' => true]);

        $container = Mockery::mock(ServiceContainer::class);
        $container->shouldReceive('register')->andReturnSelf();
        $container->shouldReceive('get')->with(CharNormalizer::class)->andReturn(new CharNormalizer());
        $container->shouldReceive('get')->andReturn(Mockery::mock());

        $this->makeModule()->boot($container);

        $html = self::unsegmentableHtml();
        $data = self::withFailingPcre(fn () => $callback(
            ['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'كتاب', 'post_content' => $html],
            []
        ));

        $this->assertSame('کتاب', $data['post_title']);
        $this->assertSame($html, $data['post_content']);
    }
}
