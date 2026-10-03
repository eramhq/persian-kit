<?php

namespace PersianKit\Tests\Unit\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Core\ConflictDetector;
use PersianKit\Core\SettingsManager;
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
        // No switch in progress, and Persian WooCommerce's options unsaved.
        Functions\when('get_option')->alias(static fn (string $option, mixed $default = false) => $default);
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
        $this->assertSame('Turn off', $reports[0]['recommendations'][4]['action_label']);
        $this->assertSame('woocommerce', $reports[0]['recommendations'][4]['key']);
        $this->assertSame('Keep on', $reports[0]['recommendations'][5]['action_label']);
    }

    public function test_reports_include_nested_setting_guidance_for_persian_woocommerce(): void
    {
        Functions\when('is_plugin_active')->alias(static fn (string $slug): bool => $slug === 'persian-woocommerce/woocommerce-persian.php');
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

        $woocommerce = array_values(array_filter(
            $reports[0]['recommendations'],
            static fn (array $recommendation): bool => $recommendation['key'] === 'woocommerce'
        ));
        $this->assertSame('turn_off', $woocommerce[0]['action']);
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

    public function test_reports_link_to_the_switch_for_plugins_it_can_import_from(): void
    {
        Functions\when('is_plugin_active')->alias(static fn (string $slug): bool => in_array($slug, ['wp-parsidate/wp-parsidate.php', 'wp-jalali/wp-jalali.php'], true));
        Functions\when('is_multisite')->justReturn(false);

        $reports = (new ConflictDetector())->reports([]);
        $bySlug = array_column($reports, null, 'slug');

        $this->assertSame('wp-parsidate', $bySlug['wp-parsidate/wp-parsidate.php']['import']);
        $this->assertSame('/wp-admin/admin.php?page=persian-kit&tab=tools#persian-kit-switch', $bySlug['wp-parsidate/wp-parsidate.php']['import_url']);
        $this->assertStringContainsString('Old Jalali post links keep working', $bySlug['wp-parsidate/wp-parsidate.php']['note']);
        // WP Jalali is not a source.
        $this->assertSame('', $bySlug['wp-jalali/wp-jalali.php']['import_url']);
    }

    public function test_the_shipping_plugin_keeps_city_suggestions_and_province_list_off(): void
    {
        Functions\when('is_plugin_active')->alias(static fn (string $slug): bool => $slug === 'persian-woocommerce-shipping/woocommerce-shipping.php');
        Functions\when('is_multisite')->justReturn(false);

        $reports = (new ConflictDetector())->reports(['woocommerce' => ['enabled' => true, 'city_select' => true, 'allowed_states' => []]]);

        $this->assertCount(1, $reports);
        $this->assertSame('supplementary', $reports[0]['type']);
        $this->assertSame('persian-woocommerce-shipping', $reports[0]['import']);
        $this->assertSame(['woocommerce.city_select', 'woocommerce.allowed_states'], array_column($reports[0]['recommendations'], 'key'));
        $this->assertSame(['leave_off', 'leave_off'], array_column($reports[0]['recommendations'], 'action'));
        $this->assertSame([true, false], array_column($reports[0]['recommendations'], 'current_value'));
    }

    public function test_persian_woocommerce_kept_only_for_its_gateways_gets_no_advice(): void
    {
        Functions\when('is_plugin_active')->alias(static fn (string $slug): bool => $slug === 'persian-woocommerce/woocommerce-persian.php');
        Functions\when('is_multisite')->justReturn(false);
        $off = [
            'enable_jalali_datepicker'    => 'no',
            'persian_price'               => 'no',
            'enable_iran_cities'          => 'no',
            'allowed_states'              => 'all',
            'fix_postcode_persian_number' => 'no',
            'fix_phone_persian_number'    => 'no',
            'postcode_validation'         => 'no',
            'phone_validation'            => 'no',
            'admin_font_family'           => 'none',
        ];
        Functions\when('get_option')->alias(static fn (string $option, mixed $default = false) => $option === 'PW_Options' ? $off : $default);

        $this->assertSame([], (new ConflictDetector())->reports([]));

        $on = ['persian_price' => 'yes'] + $off;
        Functions\when('get_option')->alias(static fn (string $option, mixed $default = false) => $option === 'PW_Options' ? $on : $default);

        $this->assertCount(1, (new ConflictDetector())->reports([]));
    }

    public function test_render_notice_links_to_the_switch_and_leaves_out_a_waiting_switch(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_current_screen')->justReturn($this->screen('plugins'));
        Functions\when('is_plugin_active')->alias(static fn (string $slug): bool => $slug === 'wp-parsidate/wp-parsidate.php');
        Functions\when('is_multisite')->justReturn(false);

        ob_start();
        (new ConflictDetector())->renderNotice();
        $output = (string) ob_get_clean();
        $this->assertStringContainsString('Switch to Persian Kit', $output);
        $this->assertStringContainsString('tab=tools#persian-kit-switch', $output);

        // The Plugins screen has its own line for a switch waiting there.
        $job = ['run_id' => 'r1', 'source' => 'wp-parsidate', 'step' => 'deactivate'];
        Functions\when('get_option')->alias(static fn (string $option, mixed $default = false) => $option === 'persian_kit_import_job' ? $job : $default);

        ob_start();
        (new ConflictDetector())->renderNotice();
        $this->assertSame('', trim((string) ob_get_clean()));
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
        Functions\when('get_current_screen')->justReturn($this->screen('plugins'));
        Functions\when('is_plugin_active')->alias(static fn (string $slug): bool => $slug === 'wp-parsidate/wp-parsidate.php');
        Functions\when('is_multisite')->justReturn(false);

        $detector = new ConflictDetector();

        ob_start();
        $detector->renderNotice();
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('Another Persian plugin does some of the same things. Use one plugin for each feature.', $output);
        $this->assertStringContainsString('WP-Parsidate is already handling some Persian date and text features.', $output);
        $this->assertStringContainsString('Review recommended settings', $output);
    }

    public function test_render_notice_skips_unrelated_screen(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_current_screen')->justReturn($this->screen('dashboard'));
        Functions\when('is_plugin_active')->alias(static fn (string $slug): bool => $slug === 'wp-parsidate/wp-parsidate.php');
        Functions\when('is_multisite')->justReturn(false);

        $detector = new ConflictDetector();

        ob_start();
        $detector->renderNotice();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function test_render_notice_skips_the_settings_page_which_has_its_own_card(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_current_screen')->justReturn($this->screen('toplevel_page_persian-kit'));
        Functions\when('is_plugin_active')->alias(static fn (string $slug): bool => $slug === 'wp-parsidate/wp-parsidate.php');
        Functions\when('is_multisite')->justReturn(false);

        ob_start();
        (new ConflictDetector())->renderNotice();
        $this->assertSame('', ob_get_clean());
    }

    private function screen(string $id): \WP_Screen
    {
        $screen = new \WP_Screen();
        $screen->id = $id;

        return $screen;
    }

    public function test_notice_disappears_once_overlapping_modules_are_off(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_current_screen')->justReturn($this->screen('plugins'));
        Functions\when('is_plugin_active')->alias(static fn (string $slug): bool => $slug === 'wp-parsidate/wp-parsidate.php');
        Functions\when('is_multisite')->justReturn(false);

        $off = ['enabled' => false];
        $settings = \Mockery::mock(SettingsManager::class);
        $settings->shouldReceive('all')->andReturn([
            'date_conversion' => $off, 'digit_conversion' => $off, 'char_normalization' => $off,
            'admin_font' => $off, 'zwnj_editor' => ['enabled' => true], 'utilities' => ['enabled' => true],
        ]);

        ob_start();
        (new ConflictDetector($settings))->renderNotice();
        $this->assertSame('', ob_get_clean());
    }

    public function test_notice_stays_while_an_overlapping_module_is_on(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_current_screen')->justReturn($this->screen('plugins'));
        Functions\when('is_plugin_active')->alias(static fn (string $slug): bool => $slug === 'wp-parsidate/wp-parsidate.php');
        Functions\when('is_multisite')->justReturn(false);

        $settings = \Mockery::mock(SettingsManager::class);
        $settings->shouldReceive('all')->andReturn([
            'date_conversion' => ['enabled' => true], 'digit_conversion' => ['enabled' => false],
        ]);

        ob_start();
        (new ConflictDetector($settings))->renderNotice();
        $this->assertStringContainsString('WP-Parsidate', (string) ob_get_clean());
    }

    public function test_report_reads_module_enabled_flag(): void
    {
        Functions\when('is_plugin_active')->alias(static fn (string $slug): bool => $slug === 'wp-parsidate/wp-parsidate.php');
        Functions\when('is_multisite')->justReturn(false);

        $reports = (new ConflictDetector())->reports(['date_conversion' => ['enabled' => false, 'global_conversion' => false]]);
        $dates = array_values(array_filter($reports[0]['recommendations'], static fn ($r) => $r['key'] === 'date_conversion'))[0];

        $this->assertFalse($dates['current_value']);
    }
}
