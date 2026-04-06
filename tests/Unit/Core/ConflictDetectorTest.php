<?php

namespace PersianKit\Tests\Unit\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Core\ConflictDetector;
use PHPUnit\Framework\TestCase;

class ConflictDetectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value) => $value);
        Functions\when('esc_html__')->alias(static fn (string $text) => $text);
        Functions\when('esc_html')->alias(static fn (string $text) => $text);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_detect_returns_active_plugin_conflicts(): void
    {
        Functions\when('is_plugin_active')->alias(static function (string $slug): bool {
            return $slug === 'wp-parsidate/wp-parsidate.php';
        });
        Functions\when('is_multisite')->justReturn(false);

        $detector = new ConflictDetector();
        $conflicts = $detector->detect();

        $this->assertCount(1, $conflicts);
        $this->assertSame('wp-parsidate/wp-parsidate.php', $conflicts[0]['slug']);
        $this->assertSame('WP-Parsidate', $conflicts[0]['name']);
        $this->assertContains('Jalali dates', $conflicts[0]['areas']);
    }

    public function test_detect_includes_network_active_plugins_on_multisite(): void
    {
        Functions\when('is_plugin_active')->justReturn(false);
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('is_plugin_active_for_network')->alias(static function (string $slug): bool {
            return $slug === 'wp-jalali/wp-jalali.php';
        });

        $detector = new ConflictDetector();
        $conflicts = $detector->detect();

        $this->assertCount(1, $conflicts);
        $this->assertSame('WP Jalali', $conflicts[0]['name']);
    }

    public function test_register_notice_only_hooks_in_admin(): void
    {
        $detector = new ConflictDetector();

        Functions\expect('is_admin')
            ->once()
            ->andReturn(true);

        Functions\expect('add_action')
            ->once()
            ->with('admin_notices', [$detector, 'renderNotice']);

        $detector->registerNotice();
        $this->addToAssertionCount(1);
    }

    public function test_render_notice_outputs_conflict_list_for_admin_users(): void
    {
        Functions\when('current_user_can')->alias(static fn (string $capability): bool => $capability === 'activate_plugins');
        Functions\when('is_plugin_active')->alias(static function (string $slug): bool {
            return in_array($slug, [
                'wp-parsidate/wp-parsidate.php',
                'persian-woocommerce/persian-woocommerce.php',
            ], true);
        });
        Functions\when('is_multisite')->justReturn(false);

        $detector = new ConflictDetector();

        ob_start();
        $detector->renderNotice();
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('Persian Kit', $output);
        $this->assertStringContainsString('WP-Parsidate', $output);
        $this->assertStringContainsString('Persian WooCommerce', $output);
        $this->assertStringContainsString('Jalali dates', $output);
        $this->assertStringContainsString('WooCommerce dates', $output);
    }

    public function test_render_notice_skips_users_without_plugin_capability(): void
    {
        Functions\when('current_user_can')->justReturn(false);

        $detector = new ConflictDetector();

        ob_start();
        $detector->renderNotice();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }
}
