<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Core\AdminPage;
use PersianKit\Core\ConflictDetector;
use PersianKit\Core\ModuleRegistry;
use PersianKit\Core\SettingsManager;
use PersianKit\Service\I18n\BundledTranslations;
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
        delete_option('persian_kit_settings');
        update_option('persian_kit_settings', [
            'date_conversion' => ['enabled' => true, 'global_conversion' => false],
        ]);

        $output = $this->renderSettingsPage();

        $this->assertMatchesRegularExpression(
            '/name="persian_kit_settings\[utilities\]\[enabled\]"[^>]*checked/s',
            $output
        );
        $this->assertMatchesRegularExpression(
            '/name="persian_kit_settings\[utilities\]\[persian_slugs\]"[^>]*checked/s',
            $output
        );
    }

    public function test_settings_reflect_defaults_for_a_newly_added_key(): void
    {
        // Utilities saved before the persian_slugs key existed. Raw, as an
        // older version stored it, bypassing the sanitizer that fills in keys.
        delete_option('persian_kit_settings');
        remove_all_filters('sanitize_option_persian_kit_settings');
        update_option('persian_kit_settings', ['utilities' => ['enabled' => true]]);

        $settings = $this->settings();

        $this->assertTrue($settings->module('utilities', 'persian_slugs'));
        $this->assertTrue($settings->all()['utilities']['persian_slugs']);
        $this->assertMatchesRegularExpression(
            '/name="persian_kit_settings\[utilities\]\[persian_slugs\]"[^>]*checked/s',
            $this->renderSettingsPage()
        );
    }

    public function test_an_unticked_toggle_is_saved_as_false(): void
    {
        update_option('persian_kit_settings', $this->formInput([
            'utilities' => ['enabled' => '0', 'persian_slugs' => '1'],
        ]));

        $this->assertSame(['enabled' => false, 'persian_slugs' => true], get_option('persian_kit_settings')['utilities']);
    }

    public function test_jalali_archives_is_on_for_stored_settings_without_it_and_saved_as_a_boolean(): void
    {
        delete_option('persian_kit_settings');
        remove_all_filters('sanitize_option_persian_kit_settings');
        update_option('persian_kit_settings', ['date_conversion' => ['enabled' => true, 'global_conversion' => false]]);

        $this->assertTrue($this->settings()->module('date_conversion', 'jalali_archives'));
        $this->assertMatchesRegularExpression(
            '/<input type="hidden" name="persian_kit_settings\[date_conversion\]\[jalali_archives\]" value="0">\s*<input\s+type="checkbox"\s+name="persian_kit_settings\[date_conversion\]\[jalali_archives\]"[^>]*checked/s',
            $this->renderSettingsPage()
        );
    }

    public function test_an_unticked_jalali_archives_box_is_saved_as_false(): void
    {
        update_option('persian_kit_settings', $this->formInput([
            'date_conversion' => ['enabled' => '1', 'global_conversion' => '0', 'jalali_archives' => '0'],
        ]));

        $this->assertSame(
            ['enabled' => true, 'global_conversion' => false, 'jalali_archives' => false, 'jalali_permalinks' => false],
            get_option('persian_kit_settings')['date_conversion']
        );
    }

    public function test_jalali_permalinks_is_off_by_default_and_saved_as_a_boolean(): void
    {
        delete_option('persian_kit_settings');
        remove_all_filters('sanitize_option_persian_kit_settings');
        update_option('persian_kit_settings', ['date_conversion' => ['enabled' => true, 'global_conversion' => false]]);

        $this->assertFalse($this->settings()->module('date_conversion', 'jalali_permalinks'));
        $this->assertMatchesRegularExpression(
            '/<input type="hidden" name="persian_kit_settings\[date_conversion\]\[jalali_permalinks\]" value="0">\s*<input\s+type="checkbox"\s+name="persian_kit_settings\[date_conversion\]\[jalali_permalinks\]"\s+value="1"(?:\s+aria-describedby="[^"]*")?\s*>/s',
            $this->renderSettingsPage()
        );
    }

    public function test_a_ticked_jalali_permalinks_box_is_saved_as_true(): void
    {
        update_option('persian_kit_settings', $this->formInput([
            'date_conversion' => ['enabled' => '1', 'global_conversion' => '0', 'jalali_archives' => '1', 'jalali_permalinks' => '1'],
        ]));

        $this->assertTrue(get_option('persian_kit_settings')['date_conversion']['jalali_permalinks']);
    }

    public function test_bool_options_of_modules_without_their_own_sanitizer_are_booleans(): void
    {
        update_option('persian_kit_settings', $this->formInput([
            'zwnj_editor' => ['enabled' => '1'],
        ]));

        $stored = get_option('persian_kit_settings');
        $this->assertSame(['enabled' => true], $stored['zwnj_editor']);
    }

    public function test_woocommerce_checkout_options_are_saved_with_their_defaults(): void
    {
        update_option('persian_kit_settings', $this->formInput([
            'woocommerce' => ['enabled' => '1', 'checkout_validate' => '0', 'national_id' => 'required'],
        ]));

        $this->assertSame(
            ['enabled' => true, 'checkout_normalize' => true, 'checkout_validate' => false, 'national_id' => 'required', 'city_select' => false],
            get_option('persian_kit_settings')['woocommerce']
        );
    }

    public function test_a_module_missing_from_the_input_keeps_its_stored_values(): void
    {
        update_option('persian_kit_settings', $this->formInput([
            'date_conversion' => ['enabled' => '1', 'global_conversion' => '1'],
        ]));

        update_option('persian_kit_settings', ['utilities' => ['enabled' => '0', 'persian_slugs' => '0']]);

        $stored = get_option('persian_kit_settings');
        $this->assertSame(
            ['enabled' => true, 'global_conversion' => true, 'jalali_archives' => true, 'jalali_permalinks' => false],
            $stored['date_conversion']
        );
        $this->assertSame(['enabled' => false, 'persian_slugs' => false], $stored['utilities']);
    }

    public function test_a_partial_module_keeps_its_other_stored_values(): void
    {
        update_option('persian_kit_settings', $this->formInput([
            'digit_conversion' => ['enabled' => '1', 'dates' => '0', 'numbers' => '1', 'prices' => '0'],
        ]));

        update_option('persian_kit_settings', ['digit_conversion' => ['prices' => true]] + get_option('persian_kit_settings'));

        $this->assertSame(
            ['enabled' => true, 'dates' => false, 'numbers' => true, 'prices' => true],
            get_option('persian_kit_settings')['digit_conversion']
        );
    }

    public function test_unknown_keys_are_dropped(): void
    {
        update_option('persian_kit_settings', $this->formInput([
            'utilities'       => ['enabled' => '1', 'persian_slugs' => '1', 'unexpected' => 'x'],
            'not_a_module'    => ['enabled' => '1'],
            'also_not_module' => 'value',
        ]));

        $stored = get_option('persian_kit_settings');
        $this->assertArrayNotHasKey('not_a_module', $stored);
        $this->assertArrayNotHasKey('also_not_module', $stored);
        $this->assertSame(['enabled' => true, 'persian_slugs' => true], $stored['utilities']);
    }

    public function test_a_non_array_value_keeps_the_stored_settings(): void
    {
        update_option('persian_kit_settings', $this->formInput([]));
        $before = get_option('persian_kit_settings');

        update_option('persian_kit_settings', 'garbage');

        $this->assertSame($before, get_option('persian_kit_settings'));
    }

    public function test_font_is_limited_to_the_allowed_fonts(): void
    {
        update_option('persian_kit_settings', $this->formInput([
            'admin_font' => ['enabled' => '1', 'font' => 'Comic Sans'],
        ]));

        $this->assertSame('vazirmatn', get_option('persian_kit_settings')['admin_font']['font']);
    }

    public function test_settings_are_sanitized_when_the_option_is_first_added(): void
    {
        delete_option('persian_kit_settings');

        add_option('persian_kit_settings', ['utilities' => ['enabled' => '0'], 'junk' => ['enabled' => '1']]);

        $this->assertSame(
            ['utilities' => ['enabled' => false, 'persian_slugs' => true]],
            get_option('persian_kit_settings')
        );
    }

    public function test_settings_form_posts_to_options_php(): void
    {
        $output = $this->renderSettingsPage();

        $this->assertStringContainsString('options.php', $output);
        $this->assertMatchesRegularExpression('/name=["\']option_page["\'] value=["\']persian_kit["\']/', $output);
        $this->assertMatchesRegularExpression(
            '/<input type="hidden" name="persian_kit_settings\[utilities\]\[enabled\]" value="0">\s*<input\s+type="checkbox"\s+name="persian_kit_settings\[utilities\]\[enabled\]"/',
            $output
        );
        $this->assertStringNotContainsString('name="modules[', $output);
    }

    public function test_settings_page_shows_the_welcome_until_dismissed(): void
    {
        update_option('persian_kit_show_welcome', 1);
        $this->assertStringContainsString('Welcome to Persian Kit', $this->renderSettingsPage());

        delete_option('persian_kit_show_welcome');
        $this->assertStringNotContainsString('Welcome to Persian Kit', $this->renderSettingsPage());
    }

    public function test_settings_page_is_persian_for_a_persian_admin(): void
    {
        BundledTranslations::load('fa_IR');

        try {
            $output = $this->renderSettingsPage();
        } finally {
            unload_textdomain('persian-kit');
        }

        $this->assertStringContainsString('ذخیره تغییرات', $output);
        $this->assertStringContainsString('اعداد فارسی', $output);
        $this->assertStringContainsString('نگارش', $output);
        $this->assertStringNotContainsString('Save changes', $output);
    }

    public function test_plugins_screen_links_to_the_settings(): void
    {
        $this->adminPage()->register();

        $links = apply_filters('plugin_action_links_' . plugin_basename(PERSIAN_KIT_MAIN_FILE), ['deactivate' => 'Deactivate']);

        $this->assertSame(['settings', 'deactivate'], array_keys($links));
        $this->assertStringContainsString('admin.php?page=persian-kit', $links['settings']);
    }

    /**
     * Every module as the settings form posts it, with the given overrides.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function formInput(array $overrides): array
    {
        $input = [];

        foreach ($this->moduleClasses() as $moduleClass) {
            $input[$moduleClass::key()] = array_map(
                static fn (mixed $value): mixed => is_bool($value) ? ($value ? '1' : '0') : $value,
                $moduleClass::defaults()
            );
        }

        return array_replace($input, $overrides);
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
        return ModuleRegistry::MODULES;
    }
}
