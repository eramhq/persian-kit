<?php

namespace PersianKit\Tests\Unit\Service\Assets;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Service\Assets\AssetManager;
use PHPUnit\Framework\TestCase;

final class TestableAssetManager extends AssetManager
{
    public function __construct(private readonly bool $assetsExist)
    {
        parent::__construct();
    }

    protected function registerHooks(): void
    {
    }

    protected function settingsAssetsExist(): bool
    {
        return $this->assetsExist;
    }
}

class AssetManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        if (!defined('PERSIAN_KIT_DIR')) {
            define('PERSIAN_KIT_DIR', dirname(__DIR__, 4) . '/');
        }

        if (!defined('PERSIAN_KIT_URL')) {
            define('PERSIAN_KIT_URL', 'https://example.test/wp-content/plugins/persian-kit/');
        }

        if (!defined('PERSIAN_KIT_VERSION')) {
            define('PERSIAN_KIT_VERSION', '1.0.0');
        }

        Functions\when('rest_url')->alias(static fn (string $path) => 'https://example.test/wp-json/' . ltrim($path, '/'));
        Functions\when('wp_create_nonce')->justReturn('nonce');
        Functions\when('admin_url')->alias(static fn (string $path = '') => 'https://example.test/wp-admin/' . ltrim($path, '/'));
        Functions\when('wp_json_encode')->alias(static fn (mixed $value) => json_encode($value));
        Functions\when('__')->alias(static fn (string $text) => $text);
        Functions\when('esc_html__')->alias(static fn (string $text) => $text);
        Functions\when('esc_html')->alias(static fn (string $text) => $text);
        Functions\when('current_user_can')->justReturn(true);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_enqueue_admin_skips_non_plugin_page(): void
    {
        $manager = new TestableAssetManager(true);

        Functions\expect('wp_enqueue_style')->never();
        Functions\expect('wp_enqueue_script')->never();

        $manager->enqueueAdmin('dashboard');
        $this->addToAssertionCount(1);
    }

    public function test_enqueue_admin_registers_notice_when_generated_assets_are_missing(): void
    {
        $manager = new TestableAssetManager(false);

        Functions\expect('add_action')
            ->once()
            ->with('admin_notices', [$manager, 'renderMissingAssetsNotice']);

        $manager->enqueueAdmin('toplevel_page_persian-kit');

        $this->addToAssertionCount(1);
    }

    public function test_enqueue_admin_loads_assets_when_generated_files_exist(): void
    {
        $manager = new TestableAssetManager(true);

        Functions\expect('wp_enqueue_style')
            ->once()
            ->with(
                'persian-kit-admin',
                PERSIAN_KIT_URL . 'public/css/admin.css',
                [],
                PERSIAN_KIT_VERSION
            );

        Functions\expect('wp_enqueue_script')
            ->once()
            ->with(
                'persian-kit-admin',
                PERSIAN_KIT_URL . 'public/js/admin.min.js',
                [],
                PERSIAN_KIT_VERSION,
                true
            );

        Functions\expect('wp_print_inline_script_tag')
            ->once();

        $manager->enqueueAdmin('toplevel_page_persian-kit');
        $this->addToAssertionCount(1);
    }
}
