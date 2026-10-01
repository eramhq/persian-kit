<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Core\AdminPage;
use PersianKit\Core\ConflictDetector;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\AdminFont\AdminFontModule;
use PersianKit\Modules\CharNormalization\CharNormalizationModule;
use PersianKit\Modules\DateConversion\DateConversionModule;
use PersianKit\Modules\DigitConversion\DigitConversionModule;
use PersianKit\Modules\Utilities\UtilitiesModule;
use PersianKit\Modules\WooCommerce\WooCommerceModule;
use PersianKit\Modules\ZWNJEditor\ZWNJEditorModule;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

class SettingsDefaultsTest extends WordPressIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        require_once ABSPATH . 'wp-admin/includes/screen.php';
        set_current_screen('toplevel_page_persian-kit');
    }

    public function test_toggles_show_defaults_for_a_module_missing_from_stored_settings(): void
    {
        // Settings saved before the Utilities module existed.
        update_option('persian_kit_settings', [
            'date_conversion' => ['enabled' => true, 'global_conversion' => false],
        ]);

        $output = $this->renderSettingsPage();

        $this->assertMatchesRegularExpression(
            '/name="modules\[utilities\]\[enabled\]"[^>]*checked/s',
            $output
        );
        $this->assertMatchesRegularExpression(
            '/name="modules\[utilities\]\[persian_slugs\]"[^>]*checked/s',
            $output
        );
    }

    public function test_settings_reflect_defaults_for_a_newly_added_key(): void
    {
        // Utilities saved before the persian_slugs key existed.
        update_option('persian_kit_settings', ['utilities' => ['enabled' => true]]);

        $settings = $this->settings();

        $this->assertTrue($settings->module('utilities', 'persian_slugs'));
        $this->assertTrue($settings->all()['utilities']['persian_slugs']);
        $this->assertMatchesRegularExpression(
            '/name="modules\[utilities\]\[persian_slugs\]"[^>]*checked/s',
            $this->renderSettingsPage()
        );
    }

    public function test_save_writes_every_module_in_one_update(): void
    {
        $writes = 0;
        $countWrites = static function () use (&$writes): void {
            $writes++;
        };
        add_action('update_option_persian_kit_settings', $countWrites);
        add_action('add_option_persian_kit_settings', $countWrites);

        $_POST['modules'] = ['utilities' => ['enabled' => '1']];
        $_REQUEST['_wpnonce'] = wp_create_nonce('persian_kit_settings');
        // handleSave() exits after redirecting; stop it at the redirect instead.
        add_filter('wp_redirect', static function (): void {
            throw new \RuntimeException('redirect');
        });

        try {
            $this->adminPage()->handleSave();
        } catch (\RuntimeException $redirect) {
            $this->assertSame('redirect', $redirect->getMessage());
        } finally {
            unset($_POST['modules'], $_REQUEST['_wpnonce']);
        }

        $this->assertSame(1, $writes);
        $this->assertFalse(get_option('persian_kit_settings')['date_conversion']['enabled']);
        $this->assertTrue(get_option('persian_kit_settings')['utilities']['enabled']);
    }

    private function renderSettingsPage(): string
    {
        ob_start();
        $this->adminPage()->render();

        return (string) ob_get_clean();
    }

    private function settings(): SettingsManager
    {
        $settings = new SettingsManager();

        foreach ($this->moduleClasses() as $moduleClass) {
            $settings->registerDefaults($moduleClass::key(), $moduleClass::defaults());
        }

        return $settings;
    }

    private function adminPage(): AdminPage
    {
        $settings = $this->settings();
        $modules = array_map(static fn (string $moduleClass) => new $moduleClass($settings), $this->moduleClasses());

        return new AdminPage($settings, $modules, new ConflictDetector($settings));
    }

    /**
     * @return list<class-string<\PersianKit\Contracts\ModuleInterface>>
     */
    private function moduleClasses(): array
    {
        return [
            DigitConversionModule::class,
            DateConversionModule::class,
            CharNormalizationModule::class,
            AdminFontModule::class,
            ZWNJEditorModule::class,
            WooCommerceModule::class,
            UtilitiesModule::class,
        ];
    }
}
