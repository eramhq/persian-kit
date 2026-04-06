<?php

namespace PersianKit\Tests\Unit\DateConversion;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\DateConversion\AdminDateScript;
use PHPUnit\Framework\TestCase;

class AdminDateScriptTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

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
                'persian-kit-admin-date',
                PERSIAN_KIT_URL . 'public/js/admin-date-override.js',
                ['jquery', 'persian-kit-jalali'],
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
                PERSIAN_KIT_URL . 'public/js/gutenberg-jalali-panel.js',
                ['wp-data', 'wp-components', 'persian-kit-jalali'],
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
}
