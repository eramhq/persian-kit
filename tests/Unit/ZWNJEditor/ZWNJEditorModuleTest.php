<?php

namespace PersianKit\Tests\Unit\ZWNJEditor;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\ZWNJEditor\ZWNJEditorModule;
use PersianKit\Container\ServiceContainer;

class ZWNJEditorModuleTest extends TestCase
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

    private function makeModule(): ZWNJEditorModule
    {
        $manager = Mockery::mock(SettingsManager::class);
        $manager->shouldReceive('module')
            ->andReturnUsing(function (string $key, ?string $subKey = null, mixed $default = null) {
                $defaults = ZWNJEditorModule::defaults();
                if ($subKey === null) {
                    return $defaults;
                }
                return $defaults[$subKey] ?? $default;
            });

        return new ZWNJEditorModule($manager);
    }

    public function test_key_returns_zwnj_editor(): void
    {
        $this->assertSame('zwnj_editor', ZWNJEditorModule::key());
    }

    public function test_defaults_has_only_enabled(): void
    {
        $defaults = ZWNJEditorModule::defaults();

        $this->assertSame(['enabled' => true], $defaults);
    }

    public function test_settings_view_returns_null(): void
    {
        $module = $this->makeModule();

        $this->assertNull($module->settingsView());
    }

    public function test_boot_registers_mce_external_plugins_filter(): void
    {
        $module = $this->makeModule();
        $container = Mockery::mock(ServiceContainer::class);

        $module->boot($container);

        $this->assertTrue(has_filter('mce_external_plugins'));
    }

    public function test_boot_registers_mce_buttons_2_filter(): void
    {
        $module = $this->makeModule();
        $container = Mockery::mock(ServiceContainer::class);

        $module->boot($container);

        $this->assertTrue(has_filter('mce_buttons_2'));
    }

    public function test_boot_registers_admin_enqueue_scripts_action(): void
    {
        $module = $this->makeModule();
        $container = Mockery::mock(ServiceContainer::class);

        $module->boot($container);

        $this->assertTrue(has_action('admin_enqueue_scripts'));
    }

    public function test_boot_registers_block_editor_enqueue_action(): void
    {
        $module = $this->makeModule();
        $container = Mockery::mock(ServiceContainer::class);

        $module->boot($container);

        $this->assertTrue(has_action('enqueue_block_editor_assets'));
    }

    public function test_register_tinymce_plugin_adds_correct_url(): void
    {
        if (!defined('PERSIAN_KIT_URL')) {
            define('PERSIAN_KIT_URL', 'https://example.com/wp-content/plugins/persian-kit/');
        }

        $module = $this->makeModule();
        $existing = ['some_plugin' => 'https://example.com/some-plugin.js'];

        $result = $module->registerTinyMcePlugin($existing);

        $this->assertArrayHasKey('some_plugin', $result);
        $this->assertArrayHasKey('persian_kit_zwnj', $result);
        $this->assertSame(
            PERSIAN_KIT_URL . 'public/js/tinymce-zwnj.js',
            $result['persian_kit_zwnj']
        );
    }

    public function test_register_tinymce_button_appends_button(): void
    {
        $module = $this->makeModule();
        $existing = ['bold', 'italic'];

        $result = $module->registerTinyMceButton($existing);

        $this->assertSame(['bold', 'italic', 'persian_kit_zwnj'], $result);
    }

    public function test_enqueue_text_editor_script_on_post_page(): void
    {
        if (!defined('PERSIAN_KIT_VERSION')) {
            define('PERSIAN_KIT_VERSION', '1.0.0');
        }

        $screen = new class {
            public string $base = 'post';

            public function is_block_editor(): bool
            {
                return false;
            }
        };

        Functions\expect('get_current_screen')->once()->andReturn($screen);

        Functions\expect('wp_enqueue_script')
            ->once()
            ->with(
                'persian-kit-text-editor-zwnj',
                Mockery::type('string'),
                [],
                PERSIAN_KIT_VERSION,
                true
            );

        $module = $this->makeModule();
        $module->enqueueTextEditorScript('post.php');

        $this->assertTrue(true); // Mockery expectations verified in tearDown
    }

    public function test_enqueue_text_editor_script_on_post_new_page(): void
    {
        $screen = new class {
            public string $base = 'post';

            public function is_block_editor(): bool
            {
                return false;
            }
        };

        Functions\expect('get_current_screen')->once()->andReturn($screen);
        Functions\expect('wp_enqueue_script')->once();

        $module = $this->makeModule();
        $module->enqueueTextEditorScript('post-new.php');

        $this->assertTrue(true);
    }

    public function test_enqueue_text_editor_script_skips_non_post_pages(): void
    {
        Functions\expect('wp_enqueue_script')->never();
        Functions\expect('get_current_screen')->never();

        $module = $this->makeModule();
        $module->enqueueTextEditorScript('toplevel_page_persian-kit');

        $this->assertTrue(true);
    }

    public function test_enqueue_text_editor_script_skips_block_editor_post_screen(): void
    {
        $screen = new class {
            public string $base = 'post';

            public function is_block_editor(): bool
            {
                return true;
            }
        };

        Functions\expect('get_current_screen')->once()->andReturn($screen);
        Functions\expect('wp_enqueue_script')->never();

        $module = $this->makeModule();
        $module->enqueueTextEditorScript('post.php');

        $this->assertTrue(true);
    }

    public function test_enqueue_block_editor_script_loads_on_block_editor_post_screen(): void
    {
        $screen = new class {
            public string $base = 'post';

            public function is_block_editor(): bool
            {
                return true;
            }
        };

        Functions\expect('get_current_screen')->once()->andReturn($screen);
        Functions\expect('wp_enqueue_script')
            ->once()
            ->with(
                'persian-kit-gutenberg-zwnj',
                PERSIAN_KIT_URL . 'public/js/gutenberg-zwnj.js',
                [],
                PERSIAN_KIT_VERSION,
                true
            );

        $module = $this->makeModule();
        $module->enqueueBlockEditorScript();

        $this->assertTrue(true);
    }

    public function test_enqueue_block_editor_script_skips_non_post_block_editor_screens(): void
    {
        $screen = new class {
            public string $base = 'widgets';

            public function is_block_editor(): bool
            {
                return true;
            }
        };

        Functions\expect('get_current_screen')->once()->andReturn($screen);
        Functions\expect('wp_enqueue_script')->never();

        $module = $this->makeModule();
        $module->enqueueBlockEditorScript();

        $this->assertTrue(true);
    }
}
