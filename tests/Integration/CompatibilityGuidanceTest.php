<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Core\AdminPage;
use PersianKit\Core\ConflictDetector;
use PersianKit\Core\SettingsManager;
use PersianKit\Bootstrap;
use PersianKit\Modules\AdminFont\AdminFontModule;
use PersianKit\Modules\CharNormalization\CharNormalizationModule;
use PersianKit\Modules\DateConversion\DateConversionModule;
use PersianKit\Modules\DigitConversion\DigitConversionModule;
use PersianKit\Modules\Utilities\UtilitiesModule;
use PersianKit\Modules\ZWNJEditor\ZWNJEditorModule;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

class CompatibilityGuidanceTest extends WordPressIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once ABSPATH . 'wp-admin/includes/screen.php';

        $adminId = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($adminId);

        delete_option('active_plugins');
        delete_option('persian_kit_settings');

        Bootstrap::activate(false);
    }

    public function test_settings_page_renders_guidance_for_wp_parsidate(): void
    {
        update_option('active_plugins', ['wp-parsidate/wp-parsidate.php']);

        $settingsBefore = get_option('persian_kit_settings');

        set_current_screen('toplevel_page_persian-kit');

        ob_start();
        $this->adminPage()->render();
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('Compatibility', $output);
        $this->assertStringContainsString('WP-Parsidate', $output);
        $this->assertStringContainsString('Turn off Date Conversion in Persian Kit.', $output);
        $this->assertStringContainsString('Keep Utilities enabled in Persian Kit.', $output);
        $this->assertSame($settingsBefore, get_option('persian_kit_settings'));
    }

    public function test_settings_page_renders_supplementary_guidance_for_persian_woocommerce(): void
    {
        update_option('active_plugins', ['persian-woocommerce/persian-woocommerce.php']);

        set_current_screen('toplevel_page_persian-kit');

        ob_start();
        $this->adminPage()->render();
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('Persian WooCommerce', $output);
        $this->assertStringContainsString('Leave Global Date Conversion off in Persian Kit.', $output);
        $this->assertStringContainsString('No change needed for Digit Conversion.', $output);
        $this->assertStringContainsString('Let Persian WooCommerce handle Woo-specific dates.', $output);
    }

    private function adminPage(): AdminPage
    {
        $settings = new SettingsManager();
        $modules = [
            new DigitConversionModule($settings),
            new DateConversionModule($settings),
            new CharNormalizationModule($settings),
            new AdminFontModule($settings),
            new ZWNJEditorModule($settings),
            new UtilitiesModule($settings),
        ];

        return new AdminPage($settings, $modules, new ConflictDetector());
    }
}
