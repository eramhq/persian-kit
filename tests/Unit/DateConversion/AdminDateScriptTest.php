<?php

namespace PersianKit\Tests\Unit\DateConversion;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\DateConversion\AdminDateScript;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AdminDateScriptTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('wp_script_is')->justReturn(false);
        Functions\when('wp_add_inline_script')->justReturn(true);
        Functions\when('wp_json_encode')->alias('json_encode');
        Functions\when('__')->returnArg();
        Functions\when('determine_locale')->justReturn('fa_IR');
        Functions\when('wp_register_style')->justReturn(true);
        Functions\when('wp_enqueue_style')->justReturn(true);

        if (!defined('PERSIAN_KIT_URL')) {
            define('PERSIAN_KIT_URL', 'https://example.com/wp-content/plugins/persian-kit/');
        }

        if (!defined('PERSIAN_KIT_VERSION')) {
            define('PERSIAN_KIT_VERSION', '1.0.0');
        }
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_adds_admin_and_block_editor_hooks(): void
    {
        $script = new AdminDateScript();
        $script->register();

        $this->assertTrue(has_action('admin_enqueue_scripts'));
        $this->assertTrue(has_action('enqueue_block_editor_assets'));
    }

    public function test_enqueue_loads_classic_assets_on_edit_screen(): void
    {
        $registerCalls = [];
        $enqueueCalls = [];

        Functions\when('wp_register_script')->alias(function (...$args) use (&$registerCalls) {
            $registerCalls[] = $args;
        });
        Functions\when('wp_enqueue_script')->alias(function (...$args) use (&$enqueueCalls) {
            $enqueueCalls[] = $args;
        });
        Functions\expect('get_current_screen')->never();

        $script = new AdminDateScript();
        $script->enqueue('edit.php');

        $this->assertSame(['persian-kit-datepicker', 'persian-kit-date-field'], array_column($registerCalls, 0));

        $this->assertSame([
            ['persian-kit-date-field'],
            [
                'persian-kit-classic-date',
                PERSIAN_KIT_URL . 'public/js/classic-date-fields.js',
                ['jquery', 'persian-kit-date-field'],
                PERSIAN_KIT_VERSION,
                true,
            ],
        ], $enqueueCalls);
    }

    public function test_enqueue_skips_unsupported_admin_pages(): void
    {
        Functions\expect('wp_register_script')->never();
        Functions\expect('wp_enqueue_script')->never();
        Functions\expect('get_current_screen')->never();

        $script = new AdminDateScript();
        $script->enqueue('toplevel_page_persian-kit');

        $this->assertTrue(true);
    }

    public function test_enqueue_skips_block_editor_post_screen(): void
    {
        $screen = new class {
            public function is_block_editor(): bool
            {
                return true;
            }
        };

        Functions\expect('get_current_screen')->once()->andReturn($screen);
        Functions\expect('wp_register_script')->never();
        Functions\expect('wp_enqueue_script')->never();

        $script = new AdminDateScript();
        $script->enqueue('post.php');

        $this->assertTrue(true);
    }

    public function test_enqueue_gutenberg_loads_shared_and_editor_assets(): void
    {
        Functions\expect('get_current_screen')->once()->andReturn($this->screen('post', true));

        $registerCalls = [];
        $enqueueScriptCalls = [];
        $enqueueStyleCalls = [];

        Functions\when('wp_register_script')->alias(function (...$args) use (&$registerCalls) {
            $registerCalls[] = $args;
        });
        Functions\when('wp_enqueue_script')->alias(function (...$args) use (&$enqueueScriptCalls) {
            $enqueueScriptCalls[] = $args;
        });
        Functions\when('wp_enqueue_style')->alias(function (...$args) use (&$enqueueStyleCalls) {
            $enqueueStyleCalls[] = $args;
        });

        $script = new AdminDateScript();
        $script->enqueueGutenberg();

        $this->assertSame([[
            'persian-kit-jalali',
            PERSIAN_KIT_URL . 'public/js/jalali.js',
            [],
            PERSIAN_KIT_VERSION,
            true,
        ]], $registerCalls);

        $this->assertSame([
            ['persian-kit-jalali'],
            [
                'persian-kit-gutenberg-jalali',
                PERSIAN_KIT_URL . 'public/js/gutenberg-jalali-date.js',
                [
                    'wp-plugins',
                    'wp-element',
                    'wp-components',
                    'wp-data',
                    'wp-date',
                    'wp-i18n',
                    'wp-editor',
                    'wp-edit-post',
                    'persian-kit-jalali',
                ],
                PERSIAN_KIT_VERSION,
                true,
            ],
        ], $enqueueScriptCalls);

        $this->assertSame([[
            'persian-kit-gutenberg-jalali',
            PERSIAN_KIT_URL . 'public/css/gutenberg-jalali.css',
            ['wp-components'],
            PERSIAN_KIT_VERSION,
        ]], $enqueueStyleCalls);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonPostEditorScreens(): array
    {
        return [
            'site editor'   => ['site-editor'],
            'widget editor' => ['widgets'],
        ];
    }

    #[DataProvider('nonPostEditorScreens')]
    public function test_enqueue_gutenberg_skips_other_block_editors(string $base): void
    {
        Functions\expect('get_current_screen')->once()->andReturn($this->screen($base, true));
        Functions\expect('wp_register_script')->never();
        Functions\expect('wp_enqueue_script')->never();
        Functions\expect('wp_enqueue_style')->never();

        (new AdminDateScript())->enqueueGutenberg();

        $this->assertTrue(true);
    }

    private function screen(string $base, bool $isBlockEditor): object
    {
        return new class ($base, $isBlockEditor) {
            public function __construct(public string $base, private bool $blockEditor)
            {
            }

            public function is_block_editor(): bool
            {
                return $this->blockEditor;
            }
        };
    }
}
