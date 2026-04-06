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

        Functions\when('__')->alias(static fn (string $text) => $text);
        Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value) => $value);
        Functions\when('esc_html__')->alias(static fn (string $text) => $text);
        Functions\when('esc_html')->alias(static fn (string $text) => $text);
        Functions\when('esc_url')->alias(static fn (string $url) => $url);
        Functions\when('admin_url')->alias(static fn (string $path = '') => '/wp-admin/' . ltrim($path, '/'));
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_reports_return_structured_recommendations_for_wp_parsidate(): void
    {
        Functions\when('is_plugin_active')->alias(static fn (string $slug): bool => $slug === 'wp-parsidate/wp-parsidate.php');
        Functions\when('is_multisite')->justReturn(false);

        $detector = new ConflictDetector();
        $reports = $detector->reports([
            'date_conversion' => ['enabled' => true],
            'utilities'       => ['enabled' => true],
        ]);

        $this->assertCount(1, $reports);
        $this->assertSame('WP-Parsidate', $reports[0]['name']);
        $this->assertContains('Jalali dates', $reports[0]['handles']);
        $this->assertSame('Turn off', $reports[0]['recommendations'][0]['action_label']);
        $this->assertSame('Currently on', $reports[0]['recommendations'][0]['current_label']);
        $this->assertSame('Keep on', $reports[0]['recommendations'][4]['action_label']);
    }

    public function test_reports_include_nested_setting_guidance_for_persian_woocommerce(): void
    {
        Functions\when('is_plugin_active')->alias(static fn (string $slug): bool => $slug === 'persian-woocommerce/persian-woocommerce.php');
        Functions\when('is_multisite')->justReturn(false);

        $detector = new ConflictDetector();
        $reports = $detector->reports([
            'date_conversion' => [
                'enabled' => true,
                'global_conversion' => false,
            ],
        ]);

        $this->assertCount(1, $reports);
        $this->assertSame('supplementary', $reports[0]['type']);
        $this->assertSame('Keep on', $reports[0]['recommendations'][0]['action_label']);
        $this->assertSame('Leave off', $reports[0]['recommendations'][1]['action_label']);
        $this->assertSame('Currently off', $reports[0]['recommendations'][1]['current_label']);
    }

    public function test_reports_include_network_active_plugins_on_multisite(): void
    {
        Functions\when('is_plugin_active')->justReturn(false);
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('is_plugin_active_for_network')->alias(static fn (string $slug): bool => $slug === 'wp-jalali/wp-jalali.php');

        $detector = new ConflictDetector();
        $reports = $detector->reports();

        $this->assertCount(1, $reports);
        $this->assertSame('WP Jalali', $reports[0]['name']);
    }

    public function test_reports_support_policy_override_filter(): void
    {
        Functions\when('apply_filters')->alias(static function (string $hook, mixed $value): mixed {
            if ($hook !== 'persian_kit_conflict_policies') {
                return $value;
            }

            return [
                'custom-plugin/custom-plugin.php' => [
                    'name' => 'Custom Plugin',
                    'recommendations' => [
                        ['key' => 'utilities', 'label' => 'Utilities', 'action' => 'turn_off'],
                    ],
                ],
            ];
        });
        Functions\when('is_plugin_active')->alias(static fn (string $slug): bool => $slug === 'custom-plugin/custom-plugin.php');
        Functions\when('is_multisite')->justReturn(false);

        $detector = new ConflictDetector();
        $reports = $detector->reports();

        $this->assertCount(1, $reports);
        $this->assertSame('Custom Plugin', $reports[0]['name']);
        $this->assertSame('Turn off', $reports[0]['recommendations'][0]['action_label']);
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

    public function test_render_notice_outputs_short_guidance_on_supported_screen(): void
    {
        Functions\when('current_user_can')->alias(static fn (string $capability): bool => $capability === 'activate_plugins');
        Functions\when('get_current_screen')->justReturn((object) ['id' => 'plugins']);
        Functions\when('is_plugin_active')->alias(static fn (string $slug): bool => $slug === 'wp-parsidate/wp-parsidate.php');
        Functions\when('is_multisite')->justReturn(false);

        $detector = new ConflictDetector();

        ob_start();
        $detector->renderNotice();
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('Another Persian plugin is already handling some of the same features.', $output);
        $this->assertStringContainsString('WP-Parsidate is already handling some Persian date and text features.', $output);
        $this->assertStringContainsString('Review recommended settings', $output);
    }

    public function test_render_notice_skips_unrelated_screen(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_current_screen')->justReturn((object) ['id' => 'dashboard']);
        Functions\when('is_plugin_active')->alias(static fn (string $slug): bool => $slug === 'wp-parsidate/wp-parsidate.php');
        Functions\when('is_multisite')->justReturn(false);

        $detector = new ConflictDetector();

        ob_start();
        $detector->renderNotice();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }
}
